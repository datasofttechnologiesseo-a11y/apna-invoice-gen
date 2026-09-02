<?php

namespace Tests\Feature;

use App\Models\CashMemo;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Financial correctness across the areas the workflow audit did not reach:
 * TDS settlement, the customer ledger, ageing bucket boundaries, cash-memo
 * arithmetic, and the P&L.
 *
 * The standard here is different from "the page loads". Every assertion is a
 * number a business would act on - chase a customer, claim a credit, file a
 * return - so a wrong figure that renders cleanly is the worst outcome, not
 * the acceptable one.
 */
class FinancialCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;
    private State $mh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $this->mh->id]);
    }

    /** An issued invoice for a round taxable amount at 18%. */
    private function issued(float $rate, ?string $date = null, ?string $due = null): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => $date ?? now()->toDateString(),
            'due_date' => $due,
            'items' => [[
                'description' => 'Service', 'quantity' => 1, 'rate' => $rate,
                'gst_rate' => 18, 'hsn_sac' => '998314',
            ]],
        ])->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice))->assertRedirect();

        return $invoice->fresh();
    }

    // ============================================================== TDS

    public function test_a_payment_with_tds_settles_the_invoice_in_full(): void
    {
        // The case that would quietly ruin a receivables ledger: a corporate
        // customer deducts TDS, so less cash arrives, but the invoice IS
        // settled. If only the cash counted, the invoice would sit as
        // partially paid forever and the business would chase money that was
        // never owed.
        //
        // The form asks for the gross amount settled, with TDS recorded as the
        // portion taken at source - the receipt row reads "incl. TDS".
        $invoice = $this->issued(100000);          // 1,18,000 gross
        $total = (float) $invoice->grand_total;

        $this->actingAs($this->user)->post(route('invoices.payments', $invoice), [
            'amount' => $total,
            'tds_amount' => 10000,
            'tds_section' => '194J_prof',
            'tds_rate' => 10,
            'received_at' => now()->toDateString(),
            'method' => 'neft',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status, 'TDS deduction must not leave the invoice unpaid');
        $this->assertEqualsWithDelta(0.0, (float) $invoice->balance, 0.01);

        $payment = $invoice->payments()->first();
        $this->assertEqualsWithDelta(10000.0, (float) $payment->tds_amount, 0.01);
        $this->assertSame('194J_prof', $payment->tds_section);
    }

    public function test_tds_cannot_exceed_the_payment_it_was_deducted_from(): void
    {
        $invoice = $this->issued(1000);

        $this->actingAs($this->user)->post(route('invoices.payments', $invoice), [
            'amount' => 500, 'tds_amount' => 900,
            'received_at' => now()->toDateString(), 'method' => 'neft',
        ])->assertSessionHasErrors('tds_amount');
    }

    public function test_the_receipt_records_the_tds_section_for_the_audit_trail(): void
    {
        // Form 26AS reconciliation depends on the section being on the record.
        $invoice = $this->issued(50000);

        $this->actingAs($this->user)->post(route('invoices.payments', $invoice), [
            'amount' => (float) $invoice->grand_total, 'tds_amount' => 5000,
            'tds_section' => '194C_other', 'tds_rate' => 2,
            'received_at' => now()->toDateString(), 'method' => 'neft',
        ])->assertRedirect();

        $html = $this->actingAs($this->user)->get(route('invoices.show', $invoice))->getContent();
        $this->assertStringContainsString('194C_other', $html);
    }

    // ============================================================== Ledger

    public function test_the_customer_ledger_balances_invoices_against_receipts(): void
    {
        $a = $this->issued(10000);      // 11,800
        $b = $this->issued(20000);      // 23,600

        $this->actingAs($this->user)->post(route('invoices.payments', $a), [
            'amount' => 11800, 'received_at' => now()->toDateString(), 'method' => 'upi',
        ])->assertRedirect();

        $this->actingAs($this->user)->post(route('credit-notes.store', $b), [
            'credit_note_date' => now()->toDateString(), 'amount' => 3600,
            'reason' => 'rate_correction',
        ])->assertRedirect();

        $res = $this->actingAs($this->user)->get(route('customers.ledger', $this->customer))->assertOk();
        $totals = $res->viewData('totals');

        // 35,400 invoiced, 11,800 received, 3,600 credited -> 20,000 outstanding.
        $this->assertEqualsWithDelta(35400.0, $totals['invoiced'], 0.01);
        $this->assertEqualsWithDelta(11800.0, $totals['received'], 0.01);
        $this->assertEqualsWithDelta(3600.0, $totals['credited'], 0.01);
        $this->assertEqualsWithDelta(20000.0, $totals['outstanding'], 0.01,
            'outstanding must be invoiced less receipts less credit notes');
    }

    public function test_the_ledger_outstanding_matches_the_sum_of_invoice_balances(): void
    {
        // Two independent routes to the same number. If they disagree, one of
        // the two screens is lying to the business.
        $this->issued(10000);
        $b = $this->issued(5000);

        $this->actingAs($this->user)->post(route('invoices.payments', $b), [
            'amount' => 2000, 'received_at' => now()->toDateString(), 'method' => 'cash',
        ])->assertRedirect();

        $totals = $this->actingAs($this->user)
            ->get(route('customers.ledger', $this->customer))->viewData('totals');

        $sumOfBalances = (float) Invoice::where('customer_id', $this->customer->id)->sum('balance');

        $this->assertEqualsWithDelta($sumOfBalances, $totals['outstanding'], 0.01);
    }

    public function test_a_cancelled_invoice_does_not_inflate_what_a_customer_owes(): void
    {
        $live = $this->issued(10000);
        $void = $this->issued(99999);

        $this->actingAs($this->user)->post(route('invoices.cancel', $void), [
            'cancellation_reason' => 'Issued in error before despatch.',
        ])->assertRedirect();

        $totals = $this->actingAs($this->user)
            ->get(route('customers.ledger', $this->customer))->viewData('totals');

        $this->assertEqualsWithDelta((float) $live->fresh()->grand_total, $totals['outstanding'], 0.01,
            'a cancelled invoice must not be counted as receivable');
    }

    // ============================================================== Ageing

    public function test_invoices_land_in_the_right_ageing_bucket(): void
    {
        // Bucket boundaries decide who gets chased and how hard, so each one
        // is placed just inside its band rather than in the middle.
        $current = $this->issued(1000, now()->subDays(40)->toDateString(), now()->subDays(10)->toDateString());
        $mid = $this->issued(2000, now()->subDays(80)->toDateString(), now()->subDays(45)->toDateString());
        $old = $this->issued(3000, now()->subDays(120)->toDateString(), now()->subDays(75)->toDateString());
        $ancient = $this->issued(4000, now()->subDays(200)->toDateString(), now()->subDays(120)->toDateString());

        $rows = collect($this->actingAs($this->user)->get(route('finance.aging'))->assertOk()->viewData('rows'));

        $bucketOf = fn (Invoice $i) => $rows->firstWhere('invoice_id', $i->id)['bucket'] ?? null;
        $daysOf = fn (Invoice $i) => $rows->firstWhere('invoice_id', $i->id)['days_overdue'] ?? null;

        $this->assertSame('current', $bucketOf($current), '10 days overdue belongs in current');
        $this->assertSame('30-60', $bucketOf($mid), '45 days overdue belongs in 30-60');
        $this->assertSame('60-90', $bucketOf($old), '75 days overdue belongs in 60-90');
        $this->assertSame('90+', $bucketOf($ancient), '120 days overdue belongs in 90+');

        // The day count itself, not just the label - a bucket built on a wrong
        // number is right by accident.
        $this->assertSame(10, $daysOf($current));
        $this->assertSame(45, $daysOf($mid));
        $this->assertSame(75, $daysOf($old));
        $this->assertSame(120, $daysOf($ancient));
    }

    public function test_a_paid_invoice_leaves_the_ageing_report(): void
    {
        $invoice = $this->issued(5000, now()->subDays(90)->toDateString(), now()->subDays(60)->toDateString());

        $this->actingAs($this->user)->post(route('invoices.payments', $invoice), [
            'amount' => (float) $invoice->grand_total,
            'received_at' => now()->toDateString(), 'method' => 'neft',
        ])->assertRedirect();

        $rows = collect($this->actingAs($this->user)->get(route('finance.aging'))->viewData('rows'));

        $this->assertFalse(
            $rows->contains(fn ($r) => ($r['invoice_id'] ?? null) === $invoice->id),
            'a settled invoice is not a receivable'
        );
    }

    // ============================================================== Cash memo

    public function test_a_cash_memo_computes_gst_and_totals_correctly(): void
    {
        $this->actingAs($this->user)->post(route('finance.cash-memos.store'), [
            'memo_date' => now()->toDateString(),
            'seller_name' => 'Local Wholesaler',
            'seller_state' => 'Maharashtra',
            'payment_mode' => 'cash',
            'gst_rate' => 18,
            'items' => [
                ['description' => 'Packing material', 'quantity' => 5, 'rate' => 200],
                ['description' => 'Courier bags', 'quantity' => 10, 'rate' => 50],
            ],
        ])->assertRedirect();

        $memo = CashMemo::latest('id')->firstOrFail();

        // 1,000 + 500 = 1,500 taxable; 18% = 270, split 135 CGST + 135 SGST.
        $this->assertEqualsWithDelta(1500.0, (float) $memo->subtotal, 0.01);
        $this->assertEqualsWithDelta(135.0, (float) $memo->total_cgst, 0.01);
        $this->assertEqualsWithDelta(135.0, (float) $memo->total_sgst, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $memo->total_igst, 0.01);
        $this->assertEqualsWithDelta(1770.0, (float) $memo->grand_total, 0.01);
    }

    // ============================================================== P&L

    public function test_the_profit_and_loss_nets_expenses_against_revenue(): void
    {
        $this->issued(50000);                       // 50,000 taxable revenue

        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'rent',
            'description' => 'Office rent', 'amount' => 20000,
            'gst_amount' => 3600, 'itc_eligible' => '1',
        ])->assertRedirect();

        $res = $this->actingAs($this->user)->get(route('finance.index'))->assertOk();
        $body = $res->getContent();

        // Taxable figures: revenue excludes GST, expenses exclude recoverable ITC.
        $this->assertStringContainsString('50,000', $body, 'taxable revenue should be on the P&L');
        $this->assertStringContainsString('20,000', $body, 'the expense should be on the P&L');
        $this->assertStringContainsString('30,000', $body, 'net profit should be revenue less expenses');
    }

    public function test_a_draft_invoice_is_not_counted_as_revenue(): void
    {
        // Revenue recognised on an unissued document would overstate the books
        // and the return alike.
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Draft work', 'quantity' => 1, 'rate' => 77777,
                'gst_rate' => 18, 'hsn_sac' => '998314']],
        ])->assertRedirect();

        $body = $this->actingAs($this->user)->get(route('finance.index'))->getContent();

        $this->assertStringNotContainsString('77,777', $body);
    }
}
