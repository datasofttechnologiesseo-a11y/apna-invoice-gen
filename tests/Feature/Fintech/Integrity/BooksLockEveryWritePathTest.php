<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\CashMemo;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Books locked up to 31 Mar 2026 (FY 2025-26 closed, returns filed). Every
 * write dated on or before that day must be refused with a clear message and
 * leave the data exactly as it was - on the web and in the app API.
 * BooksLockedTest already covers web issue/delete/cancel/payment-reversal and
 * the expense screens; this goes through every other door.
 *
 * Fixtures (all created BEFORE the lock was set, as real history would be):
 *   locked invoice   INV-0001, 15 Mar 2026, Rs 1,180; receipt Rs 500 on 20 Mar;
 *                    credit note Rs 100 on 25 Mar -> balance 1,180-500-100 = 580
 *   unpaid invoice   INV-0002, 16 Mar 2026, Rs 1,180
 *   draft            dated 17 Mar 2026
 *   purchase MM/1    10 Mar 2026, Rs 1,180, paid Rs 500 on 12 Mar
 *   purchase MM/2    01 Oct 2026 (open period), Rs 1,180
 *   expense          05 Mar 2026, Rs 2,000
 *   cash memo        08 Mar 2026
 *   bank lines       20 Mar credit Rs 500 (unmatched); 12 Mar debit Rs 500 (matched to the MM/1 payment)
 */
class BooksLockEveryWritePathTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    private const MSG = 'Books are locked up to 31 Mar 2026';

    private Invoice $locked;
    private Invoice $lockedUnpaid;
    private Invoice $lockedDraft;
    private Payment $receipt;
    private CreditNote $note;
    private Vendor $vendor;
    private PurchaseBill $oldBill;
    private PurchaseBill $newBill;
    private Expense $expense;
    private CashMemo $memo;
    private Product $cement;
    private BankStatementLine $creditLine;
    private BankStatementLine $matchedDebit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();

        $this->locked = $this->issued(['invoice_date' => '2026-03-15']);
        $this->lockedUnpaid = $this->issued(['invoice_date' => '2026-03-16']);
        $this->lockedDraft = $this->draft(['invoice_date' => '2026-03-17']);
        $this->receipt = $this->pay($this->locked, 500, ['received_at' => '2026-03-20']);
        $this->note = $this->credit($this->locked, 100, ['credit_note_date' => '2026-03-25']);

        $this->vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'Mumbai Metals', 'state_id' => $this->mh->id, 'gstin' => '27PQRST1234U1Z1']);
        $this->oldBill = $this->bill('MM/1', '2026-03-10', ['paid_now' => 500, 'paid_on' => '2026-03-12', 'payment_method' => 'neft']);
        $this->newBill = $this->bill('MM/2', '2026-10-01');

        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => '2026-03-05', 'category' => 'rent', 'description' => 'Shop rent March', 'amount' => 2000, 'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();
        $this->expense = Expense::where('description', 'Shop rent March')->firstOrFail();

        $this->actingAs($this->user)->post(route('finance.cash-memos.store'), $this->memoPayload('2026-03-08'))->assertSessionHasNoErrors();
        $this->memo = CashMemo::firstOrFail();

        $this->cement = Product::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'OPC Cement 50kg', 'kind' => 'goods',
            'hsn_sac' => '25232910', 'unit' => 'BAG', 'rate' => 400, 'gst_rate' => 18, 'is_active' => true,
            'track_stock' => true, 'opening_stock' => 100,
        ]);

        $statement = BankStatement::create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'account_name' => 'HDFC Current', 'file_name' => 'march.csv', 'lines_count' => 2]);
        $this->creditLine = BankStatementLine::create([
            'company_id' => $this->company->id, 'bank_statement_id' => $statement->id, 'txn_date' => '2026-03-20',
            'description' => 'UPI-PATIL STORES', 'reference' => 'UPI1', 'debit' => 0, 'credit' => 500, 'status' => 'unmatched', 'line_hash' => 'h1',
        ]);
        $this->matchedDebit = BankStatementLine::create([
            'company_id' => $this->company->id, 'bank_statement_id' => $statement->id, 'txn_date' => '2026-03-12',
            'description' => 'NEFT DR-MUMBAI METALS', 'reference' => 'N1', 'debit' => 500, 'credit' => 0, 'status' => 'matched',
            'matched_type' => (new PurchasePayment)->getMorphClass(), 'matched_id' => PurchasePayment::firstOrFail()->id, 'matched_at' => now(), 'line_hash' => 'h2',
        ]);

        $this->company->forceFill(['books_locked_until' => '2026-03-31'])->save();
        $this->company->refresh();
    }

    // ----------------------------------------------------------- invoices

    public function test_a_new_invoice_dated_inside_the_lock_is_refused(): void
    {
        $count = Invoice::count();
        $body = ['customer_id' => $this->customer->id, 'invoice_date' => '2026-03-30', 'due_date' => '2026-03-30', 'items' => [$this->line()]];

        $this->assertRefused($this->actingAs($this->user)->post(route('invoices.store'), $body));
        Sanctum::actingAs($this->user);
        $this->assertRefused($this->postJson('/api/invoices', $body));

        $this->assertSame($count, Invoice::count(), 'a document dated in the closed year was created');
    }

    public function test_an_issued_invoice_dated_inside_the_lock_cannot_be_edited(): void
    {
        $this->assertRefused($this->actingAs($this->user)->put(route('invoices.update', $this->lockedUnpaid), [
            'due_date' => '2026-04-30', 'notes' => 'changed after the year was closed',
        ]));
        Sanctum::actingAs($this->user);
        $this->assertRefused($this->patchJson("/api/invoices/{$this->lockedUnpaid->id}", ['due_date' => '2026-04-30']));

        $this->assertSame('2026-03-16', $this->lockedUnpaid->fresh()->due_date->toDateString());
        $this->assertNull($this->lockedUnpaid->fresh()->notes);
    }

    public function test_a_draft_dated_inside_the_lock_can_be_moved_out_of_it_and_issued(): void
    {
        // The way out for a draft left in a closed year: re-date it, then issue.
        $this->actingAs($this->user)->put(route('invoices.update', $this->lockedDraft), [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-12', 'due_date' => '2026-10-12', 'items' => [$this->line()],
        ])->assertSessionHasNoErrors();

        $this->assertSame('INV-0003', $this->issue($this->lockedDraft)->invoice_number);
    }

    public function test_issuing_cancelling_and_deleting_inside_the_lock_are_refused_on_the_app_api(): void
    {
        Sanctum::actingAs($this->user);
        $this->assertRefused($this->postJson("/api/invoices/{$this->lockedDraft->id}/finalize"));
        $this->assertRefused($this->postJson("/api/invoices/{$this->lockedUnpaid->id}/cancel", ['cancellation_reason' => 'Wrong customer billed']));
        $this->assertRefused($this->deleteJson("/api/invoices/{$this->lockedDraft->id}"));

        $this->assertSame('draft', $this->lockedDraft->fresh()->status);
        $this->assertNull($this->lockedDraft->fresh()->invoice_number);
        $this->assertSame('final', $this->lockedUnpaid->fresh()->status);
        $this->assertSame(2, (int) $this->company->fresh()->invoice_counter);
    }

    // ----------------------------------------------------------- receipts and credit notes

    public function test_a_receipt_dated_inside_the_lock_cannot_be_recorded_on_the_web_or_the_app(): void
    {
        $pay = ['amount' => 1180, 'method' => 'neft', 'received_at' => '2026-03-28'];
        $this->assertRefused($this->actingAs($this->user)->post(route('invoices.payments', $this->lockedUnpaid), $pay));
        Sanctum::actingAs($this->user);
        $this->assertRefused($this->postJson("/api/invoices/{$this->lockedUnpaid->id}/payments", $pay));

        $this->assertSame(1, Payment::count());
        $this->assertMoney(1180.00, $this->lockedUnpaid->fresh()->balance);
        $this->assertSame(1, (int) $this->company->fresh()->receipt_counter, 'no receipt number burnt');
    }

    public function test_a_receipt_dated_inside_the_lock_cannot_be_reversed_on_the_app(): void
    {
        Sanctum::actingAs($this->user);
        $this->assertRefused($this->deleteJson("/api/payments/{$this->receipt->id}"));

        $this->assertNotNull(Payment::find($this->receipt->id));
        $this->assertMoney(580.00, $this->locked->fresh()->balance);
    }

    public function test_credit_notes_inside_the_lock_cannot_be_issued_or_reversed(): void
    {
        $cn = ['credit_note_date' => '2026-03-30', 'amount' => 50, 'reason' => 'rate_correction'];
        $this->assertRefused($this->actingAs($this->user)->post(route('credit-notes.store', $this->locked), $cn));
        $this->assertRefused($this->actingAs($this->user)->delete(route('credit-notes.destroy', $this->note)));
        Sanctum::actingAs($this->user);
        $this->assertRefused($this->postJson("/api/invoices/{$this->locked->id}/credit-notes", $cn));
        $this->assertRefused($this->deleteJson("/api/credit-notes/{$this->note->id}"));

        $this->assertSame(1, CreditNote::count());
        $this->assertNotNull(CreditNote::find($this->note->id));
        $this->assertMoney(100.00, $this->locked->fresh()->credited_amount);
        $this->assertMoney(580.00, $this->locked->fresh()->balance);
    }

    // ----------------------------------------------------------- purchases

    public function test_purchase_invoices_inside_the_lock_cannot_be_edited_or_deleted(): void
    {
        $this->assertRefused($this->actingAs($this->user)->put(route('purchases.update', $this->oldBill), $this->billPayload('MM/1', '2026-03-10', 2000)));
        $this->assertRefused($this->actingAs($this->user)->delete(route('purchases.destroy', $this->oldBill)));

        $this->assertSame(2, PurchaseBill::count());
        $bill = $this->oldBill->fresh();
        $this->assertMoney(1180.00, $bill->grand_total);
        $this->assertMoney(680.00, $bill->balance);
        $this->assertMoney(1000.00, Expense::where('purchase_bill_id', $bill->id)->value('amount'), 'the mirrored expense row is untouched');
    }

    public function test_a_late_supplier_invoice_dated_inside_the_lock_is_booked_in_the_open_month(): void
    {
        // A March bill that reached the office after the year was closed keeps
        // its own date but is booked today, so the closed year does not change
        // (its input credit is claimed in a later return, s.16(4)).
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload('MM/3', '2026-03-20'))->assertSessionHasNoErrors();

        $late = PurchaseBill::where('bill_number', 'MM/3')->firstOrFail();
        $this->assertSame('2026-03-20', $late->bill_date->toDateString());
        $this->assertNotNull($late->booked_on);
        $this->assertFalse($this->company->fresh()->isBooksLockedOn($late->booked_on), 'booked in an open period');
        $this->assertTrue(Expense::where('purchase_bill_id', $late->id)->whereDate('entry_date', $late->booked_on)->exists());
    }

    public function test_a_purchase_invoice_cannot_be_moved_into_the_lock(): void
    {
        $this->assertRefused($this->actingAs($this->user)->put(route('purchases.update', $this->newBill), $this->billPayload('MM/2', '2026-03-20')));

        $this->assertSame('2026-10-01', $this->newBill->fresh()->bill_date->toDateString());
    }

    public function test_vendor_payments_inside_the_lock_cannot_be_recorded_or_removed(): void
    {
        $vp = PurchasePayment::firstOrFail();
        $this->assertRefused($this->actingAs($this->user)->post(route('purchases.payments.store', $this->oldBill), [
            'amount' => 680, 'method' => 'neft', 'paid_on' => '2026-03-28',
        ]));
        $this->assertRefused($this->actingAs($this->user)->delete(route('purchases.payments.destroy', [$this->oldBill, $vp])));

        $this->assertSame(1, PurchasePayment::count());
        $this->assertMoney(680.00, $this->oldBill->fresh()->balance);
    }

    // ----------------------------------------------------------- expenses, cash memos, stock, bank

    public function test_expenses_inside_the_lock_are_refused_on_the_app_api(): void
    {
        Sanctum::actingAs($this->user);
        $body = ['entry_date' => '2026-03-10', 'category' => 'rent', 'description' => 'Back-dated rent', 'amount' => 999];
        $this->assertRefused($this->postJson('/api/expenses', $body));
        $this->assertRefused($this->patchJson("/api/expenses/{$this->expense->id}", ['entry_date' => '2026-03-05', 'category' => 'rent', 'description' => 'Shop rent March', 'amount' => 1]));
        $this->assertRefused($this->deleteJson("/api/expenses/{$this->expense->id}"));

        $this->assertNull(Expense::where('description', 'Back-dated rent')->first());
        $this->assertMoney(2000.00, $this->expense->fresh()->amount);
    }

    public function test_cash_memos_inside_the_lock_cannot_be_created_edited_or_deleted(): void
    {
        $this->assertRefused($this->actingAs($this->user)->post(route('finance.cash-memos.store'), $this->memoPayload('2026-03-09')));
        $this->assertRefused($this->actingAs($this->user)->put(route('finance.cash-memos.update', $this->memo), $this->memoPayload('2026-03-08', 999)));
        $this->assertRefused($this->actingAs($this->user)->delete(route('finance.cash-memos.destroy', $this->memo)));
        Sanctum::actingAs($this->user);
        $this->assertRefused($this->postJson('/api/cash-memos', $this->memoPayload('2026-03-09')));
        $this->assertRefused($this->deleteJson("/api/cash-memos/{$this->memo->id}"));

        $this->assertSame(1, CashMemo::count());
        $this->assertMoney(300.00, $this->memo->fresh()->grand_total, '3 x 100 = 300, untouched');
    }

    public function test_a_stock_adjustment_dated_inside_the_lock_is_refused(): void
    {
        $this->assertRefused($this->actingAs($this->user)->post(route('products.stock.adjust', $this->cement), [
            'direction' => 'out', 'quantity' => 5, 'moved_on' => '2026-03-15', 'reason' => 'Damaged in the monsoon',
        ]));

        $this->assertSame(0, StockMovement::count());
        $this->assertEqualsWithDelta(100.0, $this->cement->fresh()->stockOnHand(), 0.001);
    }

    public function test_bank_lines_dated_inside_the_lock_cannot_be_matched_or_unmatched(): void
    {
        $this->assertRefused($this->actingAs($this->user)->post(route('bank.lines.match', $this->creditLine), [
            'candidate' => $this->receipt->getMorphClass() . '#' . $this->receipt->id,
        ]));
        $this->assertRefused($this->actingAs($this->user)->post(route('bank.lines.unmatch', $this->matchedDebit)));

        $this->assertSame('unmatched', $this->creditLine->fresh()->status, 'March reconciliation changed after the year was closed');
        $this->assertSame('matched', $this->matchedDebit->fresh()->status);
    }

    // ----------------------------------------------------------- helpers

    private function assertRefused(TestResponse $r): void
    {
        $this->assertNotSame(500, $r->status(), 'server error instead of a refusal');
        $ct = (string) $r->headers->get('content-type');
        if (str_contains($ct, 'json')) {
            $this->assertSame(422, $r->status(), 'the app API accepted a write inside the lock: ' . $r->getContent());
            $this->assertStringContainsString(self::MSG, (string) $r->getContent());

            return;
        }
        $session = app('session.store');
        $messages = collect([(string) $session->get('error')]);
        if ($errors = $session->get('errors')) {
            foreach ($errors->getBags() as $bag) {
                $messages = $messages->merge($bag->all());
            }
        }
        $this->assertTrue(
            $messages->contains(fn ($m) => str_contains((string) $m, self::MSG)),
            'no "' . self::MSG . '" message (HTTP ' . $r->status() . '): ' . $messages->filter()->implode(' | ')
        );
    }

    private function billPayload(string $number, string $date, float $rate = 1000): array
    {
        return [
            'vendor_id' => $this->vendor->id, 'bill_number' => $number, 'bill_date' => $date, 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Steel sheet', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => $rate, 'gst_rate' => 18]],
        ];
    }

    private function bill(string $number, string $date, array $extra = []): PurchaseBill
    {
        $this->actingAs($this->user)->post(route('purchases.store'), array_merge($this->billPayload($number, $date), $extra))->assertSessionHasNoErrors();

        return PurchaseBill::where('bill_number', $number)->firstOrFail();
    }

    private function memoPayload(string $date, float $rate = 100): array
    {
        return [
            'memo_date' => $date, 'seller_name' => 'Ganesh Kirana', 'payment_mode' => 'cash', 'gst_rate' => 0, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Packing tape', 'quantity' => 3, 'rate' => $rate]],
        ];
    }
}
