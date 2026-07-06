<?php

namespace Tests\Feature;

use App\Models\CashMemo;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accountant-grade reconciliation: drives real workflows through the actual
 * HTTP controllers and verifies the books balance to the paisa at every step —
 * GST split, rounding, payments, credit notes, GST returns and the P&L all tie.
 */
class AccountantReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $homeState;   // Maharashtra (company's state)
    private State $otherState;  // Karnataka (for inter-state)

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->homeState = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->otherState = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'state_id' => $this->homeState->id,
            'gstin' => '27ABCDE1234F1Z5',      // registered → HSN required, tax invoice
            'composition_dealer' => false,
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function customer(?State $state = null): Customer
    {
        return Customer::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'state_id' => ($state ?? $this->homeState)->id,
            'gstin' => '27FGHIJ5678K1Z3',
        ]);
    }

    /** POST a draft invoice through the real store endpoint, return the model. */
    private function createInvoice(array $items, Customer $customer, bool $reverseCharge = false): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'reverse_charge' => $reverseCharge,
            'items' => $items,
        ])->assertRedirect();

        return Invoice::latest('id')->first()->fresh('items');
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_intrastate_invoice_books_are_exact(): void
    {
        // Item 1: 10 × 1500 = 15,000 (no discount). Item 2: 1 × 25,000 − 1,000 = 24,000.
        // Taxable 39,000 · GST 18% = 7,020 → CGST 3,510 + SGST 3,510. Grand 46,020.
        $inv = $this->createInvoice([
            ['description' => 'Consulting', 'hsn_sac' => '998311', 'quantity' => 10, 'rate' => 1500, 'gst_rate' => 18],
            ['description' => 'Design', 'hsn_sac' => '998314', 'quantity' => 1, 'rate' => 25000, 'discount' => 1000, 'gst_rate' => 18],
        ], $this->customer());

        $this->assertEqualsWithDelta(39000.0, (float) $inv->subtotal, 0.001);
        $this->assertEqualsWithDelta(3510.0, (float) $inv->total_cgst, 0.001);
        $this->assertEqualsWithDelta(3510.0, (float) $inv->total_sgst, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_igst, 0.001);
        $this->assertEqualsWithDelta(7020.0, (float) $inv->total_tax, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inv->round_off, 0.001);
        $this->assertEqualsWithDelta(46020.0, (float) $inv->grand_total, 0.001);
        $this->assertEqualsWithDelta(46020.0, (float) $inv->balance, 0.001);

        // Invariant: CGST + SGST + IGST exactly equals total tax (no paisa lost).
        $this->assertEqualsWithDelta((float) $inv->total_tax,
            (float) $inv->total_cgst + (float) $inv->total_sgst + (float) $inv->total_igst, 0.001);
        // Invariant: subtotal + tax + round_off == grand total.
        $this->assertEqualsWithDelta((float) $inv->grand_total,
            (float) $inv->subtotal + (float) $inv->total_tax + (float) $inv->round_off, 0.001);
        // Invariant: line amounts sum to subtotal.
        $this->assertEqualsWithDelta((float) $inv->subtotal, (float) $inv->items->sum('amount'), 0.001);
    }

    public function test_interstate_invoice_charges_igst_only(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Consulting', 'hsn_sac' => '998311', 'quantity' => 10, 'rate' => 1500, 'gst_rate' => 18],
        ], $this->customer($this->otherState));

        $this->assertTrue((bool) $inv->is_interstate);
        $this->assertEqualsWithDelta(2700.0, (float) $inv->total_igst, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_cgst, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_sgst, 0.001);
        $this->assertEqualsWithDelta(17700.0, (float) $inv->grand_total, 0.001);
    }

    public function test_reverse_charge_supplier_collects_no_tax(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Legal services', 'hsn_sac' => '998213', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18],
        ], $this->customer(), reverseCharge: true);

        $this->assertEqualsWithDelta(0.0, (float) $inv->total_cgst + (float) $inv->total_sgst + (float) $inv->total_igst, 0.001);
        $this->assertEqualsWithDelta(10000.0, (float) $inv->grand_total, 0.001, 'RCM: grand total == taxable, no tax collected');
        // The rate is still recorded on the line so the recipient knows what to self-assess.
        $this->assertEqualsWithDelta(18.0, (float) $inv->items->first()->gst_rate, 0.001);
    }

    public function test_rounding_splits_to_paisa_with_no_leak(): void
    {
        // 7 × 142.85 = 999.95 · 18% = 179.991 → 179.99 → CGST/SGST 89.995-ish.
        $inv = $this->createInvoice([
            ['description' => 'Odd-amount item', 'hsn_sac' => '998311', 'quantity' => 7, 'rate' => 142.85, 'gst_rate' => 18],
        ], $this->customer());

        $this->assertEqualsWithDelta(999.95, (float) $inv->subtotal, 0.001);
        $this->assertEqualsWithDelta(179.99, (float) $inv->total_tax, 0.001);
        // No paisa lost in the half-split, and the two halves differ by at most 1 paisa.
        $this->assertEqualsWithDelta((float) $inv->total_tax,
            (float) $inv->total_cgst + (float) $inv->total_sgst, 0.001);
        $this->assertLessThanOrEqual(0.011, abs((float) $inv->total_cgst - (float) $inv->total_sgst));
        // Grand total rounds to the nearest rupee; round_off captures the difference.
        $this->assertEqualsWithDelta(1180.0, (float) $inv->grand_total, 0.001);
        $this->assertEqualsWithDelta(0.06, (float) $inv->round_off, 0.001);
    }

    public function test_payment_lifecycle_reconciles(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Service', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 39000, 'gst_rate' => 18],
        ], $this->customer());
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertRedirect();
        $inv->refresh();
        $this->assertNotNull($inv->invoice_number);
        $this->assertSame('final', $inv->status);
        $grand = (float) $inv->grand_total; // 46,020

        $method = array_key_first(config('payment_methods.methods'));

        // Partial payment
        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => 20000, 'method' => $method, 'received_at' => now()->toDateString(),
        ])->assertRedirect();
        $inv->refresh();
        $this->assertSame('partially_paid', $inv->status);
        $this->assertEqualsWithDelta(20000.0, (float) $inv->paid_amount, 0.001);
        $this->assertEqualsWithDelta($grand - 20000, (float) $inv->balance, 0.001);

        // Settle the rest
        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => $grand - 20000, 'method' => $method, 'received_at' => now()->toDateString(),
        ])->assertRedirect();
        $inv->refresh();
        $this->assertSame('paid', $inv->status);
        $this->assertEqualsWithDelta(0.0, (float) $inv->balance, 0.001);

        // Cannot overpay
        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => 1, 'method' => $method, 'received_at' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        // Reverse the most recent payment → balance + status restore.
        // (Query directly — the payments() relation force-orders by id ASC.)
        $last = Payment::where('invoice_id', $inv->id)->orderByDesc('id')->first();
        $this->actingAs($this->user)->delete(route('payments.destroy', $last))->assertRedirect();
        $inv->refresh();
        $this->assertSame('partially_paid', $inv->status);
        $this->assertEqualsWithDelta($grand - 20000, (float) $inv->balance, 0.001);
    }

    public function test_credit_note_pro_rata_split_and_balance(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Service', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 39000, 'gst_rate' => 18],
        ], $this->customer());
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $inv->refresh();
        $grand = (float) $inv->grand_total; // 46,020

        // Pay in full, then issue a ₹10,000 credit note.
        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => $grand, 'method' => array_key_first(config('payment_methods.methods')), 'received_at' => now()->toDateString(),
        ]);
        $reason = array_key_first(config('credit_note_reasons'));
        $this->actingAs($this->user)->post(route('credit-notes.store', $inv), [
            'credit_note_date' => now()->toDateString(), 'amount' => 10000, 'reason' => $reason,
        ])->assertRedirect();

        $inv->refresh();
        $this->assertEqualsWithDelta(10000.0, (float) $inv->credited_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inv->balance, 0.001, 'paid + credited covers the invoice');
        $this->assertSame('paid', $inv->status);

        // The credit note's taxable value + tax must reconcile back to its gross amount.
        $cn = CreditNote::where('invoice_id', $inv->id)->first();
        $reconstructed = (float) $cn->taxable_value + (float) $cn->total_cgst + (float) $cn->total_sgst + (float) $cn->total_igst;
        $this->assertEqualsWithDelta(10000.0, $reconstructed, 0.05, 'CN taxable + tax ties back to the credited amount');
    }

    public function test_gstr3b_outward_is_net_of_credit_notes(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Service', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 39000, 'gst_rate' => 18],
        ], $this->customer());
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $this->actingAs($this->user)->post(route('credit-notes.store', $inv->fresh()), [
            'credit_note_date' => now()->toDateString(), 'amount' => 10000, 'reason' => array_key_first(config('credit_note_reasons')),
        ]);
        $cn = CreditNote::where('invoice_id', $inv->id)->first();

        // GSTR-3B resolves its period from the `month` (YYYY-MM) param.
        $resp = $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => now()->format('Y-m')]));
        $resp->assertOk();
        $outward = $resp->viewData('outward');

        // Outward tax must be the invoice tax MINUS the credit-note tax (3.1a is net of CNs).
        $this->assertEqualsWithDelta(round(3510 - (float) $cn->total_cgst, 2), (float) $outward['cgst'], 0.011);
        $this->assertEqualsWithDelta(round(3510 - (float) $cn->total_sgst, 2), (float) $outward['sgst'], 0.011);
        $this->assertEqualsWithDelta(round(39000 - (float) $cn->taxable_value, 2), (float) $outward['taxable'], 0.011);
    }

    public function test_cash_memo_creates_matching_linked_expense(): void
    {
        $this->actingAs($this->user)->post(route('finance.cash-memos.store'), [
            'memo_date' => now()->toDateString(),
            'seller_name' => 'Wholesale Traders',
            'seller_state' => 'Maharashtra',
            'payment_mode' => 'cash',
            'expense_category' => 'misc',
            'gst_rate' => 18,
            'items' => [['description' => 'Stock', 'quantity' => 4, 'rate' => 250]],
        ])->assertRedirect();

        $memo = CashMemo::latest('id')->first();
        // 4 × 250 = 1,000 taxable · 18% = 180 (CGST 90 + SGST 90) · grand 1,180.
        $this->assertEqualsWithDelta(1000.0, (float) $memo->taxable_value, 0.001);
        $this->assertEqualsWithDelta(90.0, (float) $memo->total_cgst, 0.001);
        $this->assertEqualsWithDelta(90.0, (float) $memo->total_sgst, 0.001);
        $this->assertEqualsWithDelta(1180.0, (float) $memo->grand_total, 0.001);

        // The linked expense must mirror the memo for the P&L / ITC to tie.
        $expense = Expense::find($memo->expense_id);
        $this->assertNotNull($expense);
        $this->assertEqualsWithDelta((float) $memo->taxable_value, (float) $expense->amount, 0.001);
        $this->assertEqualsWithDelta(
            (float) $memo->total_cgst + (float) $memo->total_sgst + (float) $memo->total_igst,
            (float) $expense->gst_amount, 0.001
        );
    }

    public function test_gstr3b_itc_counts_cash_memo_gst_once(): void
    {
        // A cash memo creates a linked expense carrying the same GST. ITC must
        // count that GST once — not once via the memo and again via its expense.
        $this->actingAs($this->user)->post(route('finance.cash-memos.store'), [
            'memo_date' => now()->toDateString(),
            'seller_name' => 'Wholesale Traders',
            'seller_state' => 'Maharashtra',
            'payment_mode' => 'cash',
            'expense_category' => 'misc',
            'gst_rate' => 18,
            'itc_eligible' => '1',
            'items' => [['description' => 'Stock', 'quantity' => 4, 'rate' => 250]],
        ])->assertRedirect();

        $resp = $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => now()->format('Y-m')]));
        $resp->assertOk();
        $itc = $resp->viewData('itc');

        // 1,000 taxable · 18% = 180 → CGST 90 + SGST 90, counted ONCE (not 360).
        $this->assertEqualsWithDelta(90.0, (float) $itc['cgst'], 0.011);
        $this->assertEqualsWithDelta(90.0, (float) $itc['sgst'], 0.011);
        $this->assertEqualsWithDelta(0.0, (float) $itc['igst'], 0.011);
        $this->assertEqualsWithDelta(180.0, (float) $itc['total'], 0.011);
    }

    public function test_gstr3b_excludes_itc_ineligible_expense(): void
    {
        // Eligible business expense — GST is claimable.
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(),
            'category' => 'rent',
            'description' => 'Office rent',
            'amount' => 1000,
            'gst_amount' => 180,
            'itc_eligible' => '1',
        ])->assertRedirect();

        // Blocked credit under §17(5) — GST must be excluded from ITC. The
        // absent checkbox models an unticked "eligible" box.
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(),
            'category' => 'misc',
            'description' => 'Staff lunch (blocked credit)',
            'amount' => 2000,
            'gst_amount' => 360,
        ])->assertRedirect();

        $resp = $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => now()->format('Y-m')]));
        $itc = $resp->viewData('itc');

        // Only the eligible ₹180 (intra → CGST 90 + SGST 90) counts; the ₹360 is out.
        $this->assertEqualsWithDelta(90.0, (float) $itc['cgst'], 0.011);
        $this->assertEqualsWithDelta(90.0, (float) $itc['sgst'], 0.011);
        $this->assertEqualsWithDelta(180.0, (float) $itc['total'], 0.011);
    }

    public function test_gstr1_export_totals_tie_to_finalized_invoices(): void
    {
        $c = $this->customer();
        foreach ([10000, 20000] as $amt) {
            $inv = $this->createInvoice([
                ['description' => 'Service', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => $amt, 'gst_rate' => 18],
            ], $c);
            $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        }

        // Expected: taxable 30,000 · CGST 2,700 · SGST 2,700 · grand 35,400.
        $resp = $this->actingAs($this->user)->get(route('invoices.gstr1', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]));
        $resp->assertOk();
        $csv = $resp->streamedContent();

        // The TOTAL row carries the period figures.
        $this->assertStringContainsString('30000.00', $csv, 'GSTR-1 total taxable ties to invoices');
        $this->assertStringContainsString('35400.00', $csv, 'GSTR-1 total ties to invoices');
        $this->assertStringContainsString('2700.00', $csv, 'GSTR-1 CGST/SGST ties to invoices');
    }
}
