<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every action that moves money, or changes where money goes, leaves an
 * activity-log entry saying who did it, to which document, and what changed
 * (before -> after for edits). Section 128 Companies Act / Rule 3(1) of the
 * Accounts Rules expect an edit log; the owner reads it at Profile > Activity,
 * which lists the ACTIVE business's entries.
 *
 * Standard invoice: 1 x 1,000 @ 18% = Rs 1,180.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();
    }

    private function lastLog(string $action): AuditLog
    {
        $log = AuditLog::where('action', $action)->latest('id')->first();
        $this->assertNotNull($log, "no '{$action}' entry in the activity log");
        $this->assertSame($this->user->id, $log->user_id, 'who');
        $this->assertSame($this->company->id, $log->company_id, 'which business');

        return $log;
    }

    /** Everything an entry says, lower case, thousands separators removed. */
    private function text(AuditLog $l): string
    {
        return str_replace(',', '', strtolower($l->action . ' ' . $l->summary . ' ' . json_encode($l->changes)));
    }

    /** Entries written since $sinceId. */
    private function newLogs(int $sinceId)
    {
        return AuditLog::where('id', '>', $sinceId)->get();
    }

    public function test_issuing_an_invoice_is_logged_with_who_and_which_invoice(): void
    {
        $inv = $this->issued();

        $log = $this->lastLog('invoice.finalized');
        $this->assertSame(Invoice::class, $log->subject_type);
        $this->assertSame($inv->id, $log->subject_id);
        $this->assertStringContainsString('inv-0001', $this->text($log));
        $this->assertStringContainsString('1180', $this->text($log));
    }

    public function test_cancelling_is_logged_with_the_reason_and_the_amount(): void
    {
        $inv = $this->issued();
        $this->cancel($inv)->assertRedirect();

        $log = $this->lastLog('invoice.cancelled');
        $this->assertSame($inv->id, $log->subject_id);
        $this->assertSame('Billed to the wrong customer', $log->changes['reason'] ?? null);
        $this->assertMoney(1180.00, $log->changes['grand_total'] ?? 0);
    }

    public function test_recording_and_reversing_a_receipt_are_both_logged(): void
    {
        $inv = $this->issued();
        $receipt = $this->pay($inv, 500, ['method' => 'neft', 'reference_number' => 'N61234']);

        $rec = $this->lastLog('payment.recorded');
        $this->assertSame(Payment::class, $rec->subject_type);
        $this->assertSame($receipt->id, $rec->subject_id);
        $this->assertStringContainsString(strtolower($receipt->receipt_number), $this->text($rec));
        $this->assertStringContainsString('500', $this->text($rec));

        $this->actingAs($this->user)->delete(route('payments.destroy', $receipt))->assertRedirect();
        $rev = $this->lastLog('payment.reversed');
        $this->assertSame($inv->id, $rev->subject_id);
        $this->assertSame($receipt->receipt_number, $rev->changes['receipt_number'] ?? null, 'the reversed receipt is kept in the log after the row is gone');
        $this->assertMoney(500.00, $rev->changes['amount'] ?? 0);
        $this->assertSame('N61234', $rev->changes['reference_number'] ?? null);
    }

    public function test_issuing_and_reversing_a_credit_note_are_both_logged(): void
    {
        $inv = $this->issued();
        $cn = $this->credit($inv, 180);

        $made = $this->lastLog('credit_note.created');
        $this->assertSame(CreditNote::class, $made->subject_type);
        $this->assertSame($cn->id, $made->subject_id);
        $this->assertStringContainsString('180', $this->text($made));

        $this->actingAs($this->user)->delete(route('credit-notes.destroy', $cn))->assertRedirect();
        $gone = $this->lastLog('credit_note.deleted');
        $this->assertSame($cn->credit_note_number, $gone->changes['credit_note_number'] ?? null);
        $this->assertMoney(180.00, $gone->changes['amount'] ?? 0);
    }

    public function test_adding_and_deleting_a_purchase_invoice_are_logged(): void
    {
        $bill = $this->bill();
        $added = $this->lastLog('purchase_bill.created');
        $this->assertSame($bill->id, $added->subject_id);
        $this->assertStringContainsString('1180', $this->text($added));

        $this->actingAs($this->user)->delete(route('purchases.destroy', $bill))->assertRedirect();
        $this->assertStringContainsString('mm/77', $this->text($this->lastLog('purchase_bill.deleted')));
    }

    public function test_editing_a_purchase_invoice_logs_the_total_before_and_after(): void
    {
        $bill = $this->bill(); // 1,180
        $since = (int) AuditLog::max('id');

        // Rate 1,000 -> 2,000: 2,000 + 9% + 9% = 2,000 + 180 + 180 = 2,360.
        $this->actingAs($this->user)->put(route('purchases.update', $bill), $this->billPayload(2000))->assertSessionHasNoErrors();
        $this->assertMoney(2360.00, $bill->fresh()->grand_total);

        $entry = $this->newLogs($since)->first(fn ($l) => str_contains($this->text($l), 'mm/77') || $l->subject_id === $bill->id);
        $this->assertNotNull($entry, 'a purchase invoice (and the input tax credit it carries) changed from 1,180 to 2,360 with no activity-log entry');
        $this->assertStringContainsString('1180', $this->text($entry), 'the log shows the amount before');
        $this->assertStringContainsString('2360', $this->text($entry), 'and after');
    }

    public function test_recording_and_removing_a_vendor_payment_are_logged(): void
    {
        $bill = $this->bill();
        $since = (int) AuditLog::max('id');

        $this->actingAs($this->user)->post(route('purchases.payments.store', $bill), [
            'amount' => 680, 'method' => 'neft', 'paid_on' => '2026-10-12', 'reference_number' => 'N700001',
        ])->assertSessionHasNoErrors();
        $paid = $this->newLogs($since)->first(fn ($l) => str_contains($this->text($l), '680'));
        $this->assertNotNull($paid, 'Rs 680 paid to a vendor left no activity-log entry');

        $since = (int) AuditLog::max('id');
        $this->actingAs($this->user)->delete(route('purchases.payments.destroy', [$bill, PurchasePayment::firstOrFail()]))->assertRedirect();
        $removed = $this->newLogs($since)->first(fn ($l) => str_contains($this->text($l), '680'));
        $this->assertNotNull($removed, 'removing a Rs 680 vendor payment left no activity-log entry');
    }

    public function test_expense_edits_and_deletions_are_logged_with_before_and_after(): void
    {
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => '2026-10-05', 'category' => 'rent', 'description' => 'Shop rent', 'amount' => 15000, 'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();
        $e = Expense::firstOrFail();
        $this->assertStringContainsString('15000', $this->text($this->lastLog('expense.created')));

        $this->actingAs($this->user)->patch(route('finance.expenses.update', $e), [
            'entry_date' => '2026-10-05', 'category' => 'rent', 'description' => 'Shop rent', 'amount' => 18000, 'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();
        $upd = $this->lastLog('expense.updated');
        $this->assertMoney(15000.00, $upd->changes['before']['amount'] ?? 0);
        $this->assertMoney(18000.00, $upd->changes['after']['amount'] ?? 0);

        $this->actingAs($this->user)->delete(route('finance.expenses.destroy', $e))->assertRedirect();
        $this->assertStringContainsString('18000', $this->text($this->lastLog('expense.deleted')));
    }

    public function test_changing_the_bank_account_printed_on_invoices_is_logged_with_old_and_new_values(): void
    {
        // Changing the account customers pay into is the classic invoice-fraud
        // move; the log must show it happened, when, by whom, from what to what.
        $this->company->forceFill(['bank_name' => 'HDFC Bank', 'bank_account_number' => '50200012345678', 'bank_ifsc' => 'HDFC0001234'])->save();
        $since = (int) AuditLog::max('id');

        $this->actingAs($this->user)->patch(route('companies.update', $this->company), $this->companyPayload([
            'bank_name' => 'ICICI Bank', 'bank_account_number' => '000401004321', 'bank_ifsc' => 'ICIC0000004',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('000401004321', $this->company->fresh()->bank_account_number);

        $entry = $this->newLogs($since)->first(fn ($l) => str_contains($this->text($l), '4321'));
        $this->assertNotNull($entry, 'the bank account on every invoice changed with no activity-log entry');
        $this->assertStringContainsString('5678', $this->text($entry), 'the old account (at least its last digits) is recorded');
        $this->assertSame($this->user->id, $entry->user_id);
    }

    public function test_changing_the_bank_account_from_the_app_is_logged_too(): void
    {
        $this->company->forceFill(['bank_name' => 'HDFC Bank', 'bank_account_number' => '50200012345678', 'bank_ifsc' => 'HDFC0001234'])->save();
        $since = (int) AuditLog::max('id');

        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $payload = $this->companyPayload(['bank_name' => 'ICICI Bank', 'bank_account_number' => '000401004321', 'bank_ifsc' => 'ICIC0000004']);
        unset($payload['gstin']);
        $this->putJson("/api/companies/{$this->company->id}", $payload)->assertOk();
        $this->assertSame('000401004321', $this->company->fresh()->bank_account_number);

        $entry = $this->newLogs($since)->first(fn ($l) => str_contains($this->text($l), '4321'));
        $this->assertNotNull($entry, 'the bank account was changed from the mobile app with no activity-log entry');
        $this->assertStringContainsString('5678', $this->text($entry));
    }

    public function test_moving_the_books_lock_date_from_the_app_is_logged_too(): void
    {
        $since = (int) AuditLog::max('id');
        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $payload = $this->companyPayload(['books_locked_until' => '2026-03-31']);
        unset($payload['gstin']);
        $this->putJson("/api/companies/{$this->company->id}", $payload)->assertOk();
        $this->assertSame('2026-03-31', $this->company->fresh()->books_locked_until?->toDateString());

        $this->assertNotNull($this->newLogs($since)->first(fn ($l) => str_contains($this->text($l), 'lock')), 'the books were locked from the app with no activity-log entry');
    }

    public function test_moving_the_books_lock_date_is_logged(): void
    {
        $since = (int) AuditLog::max('id');
        $this->actingAs($this->user)->patch(route('companies.update', $this->company), $this->companyPayload(['books_locked_until' => '2026-03-31']))->assertSessionHasNoErrors();
        // ... and unlocking it again, which re-opens a filed year for edits.
        $this->actingAs($this->user)->patch(route('companies.update', $this->company), $this->companyPayload(['books_locked_until' => null]))->assertSessionHasNoErrors();
        $this->assertNull($this->company->fresh()->books_locked_until);

        $lockEntries = $this->newLogs($since)->filter(fn ($l) => str_contains($this->text($l), 'lock'));
        $this->assertGreaterThanOrEqual(2, $lockEntries->count(), 'locking and unlocking a closed year left no trace in the activity log');
    }

    public function test_a_stock_adjustment_records_who_made_it_and_why(): void
    {
        $item = Product::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'OPC Cement 50kg', 'kind' => 'goods',
            'hsn_sac' => '25232910', 'unit' => 'BAG', 'rate' => 400, 'gst_rate' => 18, 'is_active' => true, 'track_stock' => true, 'opening_stock' => 100,
        ]);
        $this->actingAs($this->user)->post(route('products.stock.adjust', $item), [
            'direction' => 'out', 'quantity' => 4, 'moved_on' => '2026-10-11', 'reason' => 'Bags torn in the rain',
        ])->assertSessionHasNoErrors();

        $m = StockMovement::firstOrFail();
        $this->assertSame($this->user->id, $m->user_id);
        $this->assertSame('Bags torn in the rain', $m->note);
        $this->assertEqualsWithDelta(-4.0, (float) $m->quantity, 0.0001);
    }

    public function test_an_action_in_another_of_the_owners_businesses_is_logged_under_that_business(): void
    {
        $draftA = $this->draft();
        $b = Company::factory()->create(['user_id' => $this->user->id, 'name' => 'Sharma Hardware', 'state_id' => $this->mh->id]);
        $this->actingAs($this->user)->post(route('companies.switch', $b))->assertRedirect();

        // Business B is active; the owner issues A's draft from its own page.
        $this->issue($draftA);

        $log = AuditLog::where('action', 'invoice.finalized')->latest('id')->firstOrFail();
        $this->assertSame($this->company->id, $log->company_id, "A's invoice was logged in B's activity log, so A's log misses it");
    }

    // ----------------------------------------------------------- helpers

    private function billPayload(float $rate = 1000): array
    {
        $vendor = $this->company->vendors()->firstOrCreate(['name' => 'Mumbai Metals'], ['user_id' => $this->user->id, 'state_id' => $this->mh->id, 'gstin' => '27PQRST1234U1Z1']);

        return [
            'vendor_id' => $vendor->id, 'bill_number' => 'MM/77', 'bill_date' => '2026-10-10', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Steel sheet', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => $rate, 'gst_rate' => 18]],
        ];
    }

    private function bill(): PurchaseBill
    {
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload())->assertSessionHasNoErrors();

        return PurchaseBill::latest('id')->firstOrFail();
    }

    private function companyPayload(array $over = []): array
    {
        return array_merge([
            'name' => $this->company->name,
            'gstin' => $this->company->gstin,
            'state_id' => $this->company->state_id,
            'address_line1' => '12 MG Road',
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'INV',
            'invoice_number_padding' => 4,
        ], $over);
    }
}
