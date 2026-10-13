<?php

namespace Tests\Feature\Fintech\Security;

use App\Models\AuditLog;
use App\Models\CashMemo;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchasePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * One login, two businesses (P = Pune, Q = the second firm). Q is the
 * active one. The app deliberately lets the owner open and act on P's
 * documents without switching (InvoiceController::edit says so), so the
 * question is not "is it refused" but "does it stay P's": numbering,
 * company_id, books and the activity trail must all follow the DOCUMENT's
 * business, never whichever one happens to be active.
 *
 * Where the app does scope by the active business (bank lines, the
 * customer ledger), P's records must be refused while Q is active.
 */
class OneOwnerTwoBusinessesTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    private array $p;
    private array $q;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->p = $this->buildBusiness('P', null, ['invoice_prefix' => 'PUN', 'receipt_prefix' => 'PRC', 'credit_note_prefix' => 'PCN']);
        // Same owner, second business; building it leaves Q active.
        $this->q = $this->buildBusiness('Q', $this->p['owner'], ['invoice_prefix' => 'QBL', 'receipt_prefix' => 'QRC', 'credit_note_prefix' => 'QCN']);
        $this->assertSame($this->q['company']->id, (int) $this->owner()->active_company_id, 'fixture: Q is active');
    }

    private function owner()
    {
        return $this->p['owner']->fresh();
    }

    public function test_issuing_the_other_businesss_draft_takes_that_businesss_next_number(): void
    {
        // P issued PUN-0001..0004 in the fixture (open, paid, credited, cancelled); its draft is next.
        $this->actingAs($this->owner())->post(route('invoices.finalize', $this->p['invoiceDraft']))->assertSessionHasNoErrors();

        $draft = $this->p['invoiceDraft']->fresh();
        $this->assertSame('PUN-0005', $draft->invoice_number);
        $this->assertSame($this->p['company']->id, (int) $draft->company_id);
        $this->assertSame(5, (int) $this->p['company']->fresh()->invoice_counter);
        $this->assertSame(4, (int) $this->q['company']->fresh()->invoice_counter, "Q's series is untouched");
    }

    public function test_a_receipt_and_a_credit_note_on_the_other_business_are_numbered_and_filed_there(): void
    {
        $open = $this->p['invoiceOpen']; // 2,360 owed
        $this->actingAs($this->owner())->post(route('invoices.payments', $open), [
            'amount' => 1000, 'method' => 'upi', 'received_at' => '2026-09-29',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->owner())->post(route('credit-notes.store', $open), [
            'credit_note_date' => '2026-09-29', 'amount' => 236, 'reason' => 'post_sale_discount',
        ])->assertSessionHasNoErrors();

        $receipt = Payment::where('invoice_id', $open->id)->sole();
        $this->assertSame('PRC-0002', $receipt->receipt_number, "P's second receipt");
        $this->assertSame($this->p['company']->id, (int) $receipt->company_id);
        $note = CreditNote::where('invoice_id', $open->id)->sole();
        $this->assertSame('PCN-0002', $note->credit_note_number, "P's second credit note");
        $this->assertSame($this->p['company']->id, (int) $note->company_id);

        $this->assertSame(1, (int) $this->q['company']->fresh()->receipt_counter, "Q's receipt series untouched");
        $this->assertSame(1, (int) $this->q['company']->fresh()->credit_note_counter, "Q's credit note series untouched");
        // 2,360 - 1,000 paid - 236 credited = 1,124 still owed on P's invoice.
        $this->assertEqualsWithDelta(1124.00, (float) $open->fresh()->balance, 0.001);
    }

    public function test_a_vendor_payment_on_the_other_businesss_purchase_stays_with_that_business(): void
    {
        // P's purchase invoice PB-P-1 (590) was paid in full in the fixture; remove and re-record it.
        $bill = $this->p['purchaseBill'];
        $this->actingAs($this->owner())->delete(route('purchases.payments.destroy', [$bill, $this->p['purchasePayment']]))->assertSessionHasNoErrors();
        $this->actingAs($this->owner())->post(route('purchases.payments.store', $bill), [
            'amount' => 590, 'method' => 'neft', 'paid_on' => '2026-09-29',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->p['company']->id, (int) PurchasePayment::where('purchase_bill_id', $bill->id)->sole()->company_id);
    }

    /**
     * The activity log is per business. An action on P's document while Q
     * is active is P's history; filing it under Q shows P's invoices and
     * amounts to anyone who can read Q's log - including a team member who
     * was invited to Q only.
     */
    public function test_the_activity_trail_files_an_action_under_the_documents_business_not_the_active_one(): void
    {
        $this->actingAs($this->owner())->post(route('invoices.finalize', $this->p['invoiceDraft']))->assertSessionHasNoErrors();
        $this->actingAs($this->owner())->post(route('invoices.payments', $this->p['invoiceOpen']), [
            'amount' => 500, 'method' => 'cash', 'received_at' => '2026-09-29',
        ])->assertSessionHasNoErrors();

        $finalized = AuditLog::where('action', 'invoice.finalized')->where('subject_id', $this->p['invoiceDraft']->id)->sole();
        $receipt = AuditLog::where('action', 'payment.recorded')->latest('id')->firstOrFail();
        $this->assertSame($this->p['company']->id, (int) $finalized->company_id, 'invoice.finalized filed under the wrong business');
        $this->assertSame($this->p['company']->id, (int) $receipt->company_id, 'payment.recorded filed under the wrong business');
    }

    public function test_a_member_invited_only_to_the_second_business_never_sees_the_first_businesss_activity(): void
    {
        // Q's accountant (fixture) works in Q only.
        $qMember = $this->q['memberUser'];
        $qMember->forceFill(['active_company_id' => $this->q['company']->id])->save();

        $this->actingAs($this->owner())->post(route('invoices.finalize', $this->p['invoiceDraft']))->assertSessionHasNoErrors();
        $this->actingAs($this->owner())->post(route('invoices.payments', $this->p['invoiceOpen']), [
            'amount' => 777, 'method' => 'cash', 'received_at' => '2026-09-29',
        ])->assertSessionHasNoErrors();

        // The audit summaries read "Invoice PUN-0005 finalized ..." and
        // "... Receipt PRC-0002 · Invoice PUN-0001". (The notification bell is
        // a separate leak, covered in TeamRolesTest.)
        // The member signs in on their own device: a fresh session, so the
        // owner's own "Payment recorded. Receipt PRC-0002 issued." flash (one
        // shared test session) is not mistaken for activity the member can read.
        $this->flushSession();
        $html = $this->actingAs($qMember->fresh())->get(route('profile.activity'))->assertOk()->getContent();
        foreach (['Invoice PUN-0005 finalized', 'Receipt PRC-0002'] as $p) {
            $this->assertFalse(str_contains($html, $p), "Q's member reads P's activity: {$p}");
        }
    }

    /**
     * Cash memo numbers are unique per business (unique index company_id +
     * memo_number). Editing P's memo while Q is active must check P's
     * numbers: today validated() checks the ACTIVE business, so a clash
     * inside P slips past validation and hits the database index.
     */
    public function test_editing_the_other_businesss_cash_memo_to_a_number_it_already_used_is_a_validation_error(): void
    {
        $memo = $this->p['cashMemo'];
        CashMemo::create([
            'user_id' => $this->owner()->id, 'company_id' => $this->p['company']->id, 'memo_number' => 'P-TAKEN-2',
            'memo_date' => '2026-09-21', 'seller_name' => 'Other seller', 'subtotal' => 100, 'taxable_value' => 100, 'grand_total' => 100,
        ]);

        $res = $this->actingAs($this->owner())->put(route('finance.cash-memos.update', $memo), [
            'memo_date' => '2026-09-15', 'memo_number' => 'P-TAKEN-2', 'seller_name' => 'SECRET-SELLER-P', 'payment_mode' => 'cash',
            'items' => [['description' => 'Printer paper', 'quantity' => 2, 'rate' => 100]],
        ]);

        $this->assertNotSame(500, $res->getStatusCode(), 'a duplicate memo number crashed with a server error');
        $res->assertSessionHasErrors('memo_number');
        $this->assertNotSame('P-TAKEN-2', $memo->fresh()->memo_number);
    }

    public function test_a_number_used_only_by_the_active_business_is_free_for_the_other_businesss_cash_memo(): void
    {
        $qNumber = $this->q['cashMemo']->memo_number;
        $memo = $this->p['cashMemo'];

        $this->actingAs($this->owner())->put(route('finance.cash-memos.update', $memo), [
            'memo_date' => '2026-09-15', 'memo_number' => $qNumber . '-X', 'seller_name' => 'SECRET-SELLER-P', 'payment_mode' => 'cash',
            'items' => [['description' => 'Printer paper', 'quantity' => 2, 'rate' => 100]],
        ])->assertSessionHasNoErrors();
        CashMemo::where('id', $this->q['cashMemo']->id)->update(['memo_number' => 'Q-ONLY-7']);

        // Q owns Q-ONLY-7; P has no such memo, so P may use it.
        $this->actingAs($this->owner())->put(route('finance.cash-memos.update', $memo), [
            'memo_date' => '2026-09-15', 'memo_number' => 'Q-ONLY-7', 'seller_name' => 'SECRET-SELLER-P', 'payment_mode' => 'cash',
            'items' => [['description' => 'Printer paper', 'quantity' => 2, 'rate' => 100]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Q-ONLY-7', $memo->fresh()->memo_number);
        $this->assertSame($this->p['company']->id, (int) $memo->fresh()->company_id);
    }

    public function test_screens_scoped_to_the_active_business_refuse_the_other_businesss_records(): void
    {
        $before = $this->snapshot();

        $this->actingAs($this->owner())->post(route('bank.lines.ignore', $this->p['bankLine']))->assertForbidden();
        $this->actingAs($this->owner())->post(route('bank.lines.match', $this->p['bankLine']), [
            'candidate' => 'new-expense', 'category' => 'bank_charges', 'description' => 'SMS charges',
        ])->assertForbidden();
        $this->actingAs($this->owner())->delete(route('bank.statements.destroy', $this->p['statement']))->assertForbidden();
        $this->actingAs($this->owner())->get(route('customers.ledger', $this->p['customer']))->assertForbidden();

        $this->assertSame([], $this->changedTables($before, $this->snapshot()));
    }

    public function test_the_lists_of_the_active_business_show_none_of_the_other_businesss_documents(): void
    {
        foreach (['invoices.index', 'customers.index', 'products.index', 'quotations.index', 'purchases.index', 'vendors.index',
            'finance.expenses', 'finance.cash-memos.index', 'bank.index', 'finance.aging'] as $name) {
            $page = $this->actingAs($this->owner())->get(route($name))->assertOk();
            // The notification bell in the layout lists the owner's invoices
            // across BOTH businesses (by user, not business). For the owner
            // that is their own data; for a team member it is a leak, see
            // TeamRolesTest. So the markers here are ones the bell never
            // prints: a customer with no invoices, items, vendors, sellers,
            // expenses and bank accounts.
            foreach (['SECRET-PROSPECT-P', 'SECRET-ITEM-P', 'SECRET-VENDOR-P', 'SECRET-SELLER-P', 'SECRET-RENT-P', 'SECRET-BANK-P'] as $marker) {
                $this->assertFalse(str_contains($page->getContent(), $marker), "{$name} shows {$marker} from the inactive business");
            }
        }
    }
}
