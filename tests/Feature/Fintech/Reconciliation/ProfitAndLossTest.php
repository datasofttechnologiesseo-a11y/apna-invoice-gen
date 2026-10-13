<?php

namespace Tests\Feature\Fintech\Reconciliation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identity 6: the Finance (P&L) screen for FY 2026-27 against the registers
 * and returns it summarises. Read on 5 Apr 2027 with a custom period
 * 01-04-2026 to 31-03-2027.
 */
class ProfitAndLossTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    private array $pnl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
        $res = $this->actingAs($this->user)->get(route('finance.index', ['period' => 'custom', 'from' => '2026-04-01', 'to' => '2027-03-31']));
        $res->assertOk();
        $this->pnl = $res->original->getData();
    }

    public function test_revenue_is_sales_net_of_credit_notes_and_of_tax(): void
    {
        // Issued FY invoices: taxable 12,65,462.52, tax 1,39,221.19, grand 14,04,684.
        // Credit notes dated in the FY: taxable 23,096.86 + cnF1 (90.72), tax 3,848.42, amount 27,036.
        // Revenue 12,42,365.66 - cnF1 = 12,42,274.94 (= 3.1(a) 6,82,285.02 + 3.1(b) 4,67,000 + 3.1(c) 2,989.92
        // + reverse-charge sales 90,000); GST collected 1,35,372.77; gross 13,77,648.
        $r = $this->pnl['revenue'];
        $this->assertEqualsWithDelta(round(1242365.66 - $this->cnF1Taxable(), 2), (float) $r['taxable'], 0.001);
        $this->assertEqualsWithDelta(135372.77, (float) $r['gst_collected'], 0.001);
        $this->assertEqualsWithDelta(1377648.00, (float) $r['grand_total'], 0.001);

        // Same as the registers: sales register less credit-note register.
        $sr = $this->registerTotals('sales-register', '2026-04-01', '2027-03-31');
        $cn = $this->registerTotals('credit-notes', '2026-04-01', '2027-03-31');
        $this->assertEqualsWithDelta(round($sr['taxable'] - $cn['taxable'], 2), (float) $r['taxable'], 0.001);
        $this->assertEqualsWithDelta(round($sr['cgst'] + $sr['sgst'] + $sr['igst'] - $cn['cgst'] - $cn['sgst'] - $cn['igst'], 2), (float) $r['gst_collected'], 0.001);
    }

    public function test_expenses_are_net_of_claimable_credit_and_blocked_credit_is_a_cost(): void
    {
        // Amounts before GST: bills 5,53,233.33 + expenses 1,48,000 + cash memos 1,499.99 = 7,02,733.32.
        // Blocked GST (cost): B4 500 + B12 3,600 + B18 9,000 + E4 150 + CM2 25 = 13,275.
        // Expenses 7,16,008.32; claimable GST 82,730 (= GSTR-3B ITC for the year).
        // Net profit (12,42,365.66 - cnF1) - 7,16,008.32 = 5,26,266.62 at 90.72; margin 42.36%.
        $e = $this->pnl['expense'];
        $this->assertEqualsWithDelta(716008.32, (float) $e['taxable'], 0.001);
        $this->assertEqualsWithDelta(82730.00, (float) $e['gst_itc'], 0.001);
        $this->assertEqualsWithDelta(round(1242365.66 - $this->cnF1Taxable() - 716008.32, 2), (float) $this->pnl['netProfit'], 0.001);
        $this->assertEqualsWithDelta(42.36, (float) $this->pnl['margin'], 0.001);

        // The Expenses screen for the year: before-GST total + GST not claimable = the P&L cost.
        $s = $this->actingAs($this->user)->get(route('finance.expenses', ['period' => 'custom', 'from' => '2026-04-01', 'to' => '2027-03-31']))->viewData('summary');
        $this->assertEqualsWithDelta(702733.32, (float) $s['taxable'], 0.001);
        $this->assertEqualsWithDelta(96005.00, (float) $s['gst'], 0.001);
        $this->assertEqualsWithDelta(82730.00, (float) $s['itc_claimable'], 0.001);
        $this->assertEqualsWithDelta((float) $e['taxable'], round((float) $s['taxable'] + (float) $s['gst'] - (float) $s['itc_claimable'], 2), 0.001);

        // Purchases inside it: register taxable 5,53,233.33, claimable 66,170, blocked 13,100.
        $pr = $this->registerTotals('purchase-register', '2026-04-01', '2027-03-31');
        $this->assertEqualsWithDelta(553233.33, $pr['taxable'], 0.001);
        $this->assertEqualsWithDelta(66170.00, $pr['itc'], 0.001);
        $this->assertEqualsWithDelta(13100.00, round($pr['cgst'] + $pr['sgst'] + $pr['igst'] - $pr['itc'], 2), 0.001);
    }

    public function test_the_category_breakdown_adds_up_to_the_expense_total(): void
    {
        // Each category with its blocked GST: purchases 3,00,733.33; misc 1,50,000 + 9,000;
        // professional 60,000; rent 60,000; salaries 50,000; courier 24,000; marketing 20,000 + 3,600;
        // food 13,500 + 500 + 150 + 25; software 10,000; repairs 5,999.99; utilities 4,000;
        // office supplies 3,500; bank charges 1,000.  Sum 7,16,008.32.
        $hand = ['purchases' => 300733.33, 'misc' => 159000.00, 'professional' => 60000.00, 'rent' => 60000.00,
            'salaries' => 50000.00, 'courier' => 24000.00, 'marketing' => 23600.00, 'food' => 14175.00, 'software' => 10000.00,
            'repairs' => 5999.99, 'utilities' => 4000.00, 'office_supplies' => 3500.00, 'bank_charges' => 1000.00];
        $by = collect($this->pnl['byCategory'])->keyBy('category');
        $failures = [];
        foreach ($hand as $cat => $v) {
            $this->check($failures, "category {$cat}", $v, (float) ($by[$cat]['total'] ?? 0));
        }
        $this->check($failures, 'categories add up to the expense total', (float) $this->pnl['expense']['taxable'], round($by->sum('total'), 2));
        $this->check($failures, 'shares add up to 100%', 100.0, round($by->sum('share'), 6), 0.0001);
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_gst_payable_is_what_gstr3b_works_out_for_the_year(): void
    {
        // Output tax 1,35,372.77 + reverse charge on purchases 8,400 - ITC 82,730 = 61,042.77,
        // the year's 3B (four quarters): liability less credit plus reverse charge in cash.
        $this->assertEqualsWithDelta(61042.77, (float) $this->pnl['gstPayable'], 0.001);
        $y = ['liability' => 0.0, 'rcm' => 0.0, 'itc' => 0.0];
        foreach ($this->quarters() as $first) {
            $d = $this->gstr3bPage($first, true);
            $y['liability'] += $d['liability']['igst'] + $d['liability']['cgst'] + $d['liability']['sgst'];
            $y['rcm'] += $d['rcm_inward']['igst'] + $d['rcm_inward']['cgst'] + $d['rcm_inward']['sgst'];
            $y['itc'] += $d['itc']['total'];
        }
        $this->assertEqualsWithDelta(round($y['liability'] + $y['rcm'] - $y['itc'], 2), (float) $this->pnl['gstPayable'], 0.001);
    }

    public function test_cash_received_is_the_money_received_in_the_period(): void
    {
        // Cash flow is by the date money moved. Receipts dated 01-04-2026..31-03-2027
        // (receipts register) = 9,55,019. Not the same thing as "what the year's
        // invoices have been paid so far": that includes Mr1 23,600 and Mr2 1,180
        // received on 1 Apr 2027 and leaves out X0's 2,360 received on 3 Apr 2026.
        $register = (float) $this->report('receipts', ['from' => '2026-04-01', 'to' => '2027-03-31'])['totals']['amount'];
        $this->assertEqualsWithDelta(955019.00, $register, 0.001);
        $this->assertEqualsWithDelta($register, (float) $this->pnl['revenue']['received'], 0.001,
            'Cash received on the P&L / Cash flow screen should equal the receipts register for the period');
    }

    public function test_cash_spent_is_what_was_paid_out_in_the_period(): void
    {
        // Vendor payments 2,87,983 + expenses paid with GST 1,64,530 (E1 35,400, E2 4,000,
        // E3 11,800, E4 3,150, E5 50,000, E6 35,400, E7 23,600, E8 1,180) + cash memos as paid
        // CM1 1,180 (grand total, round-off 0.01 included) + CM2 525 = 4,54,218.00.
        $vendorPayments = $this->registerTotals('vendor-payments', '2026-04-01', '2027-03-31')['amount'];
        $this->assertEqualsWithDelta(287983.00, $vendorPayments, 0.001);
        $this->assertEqualsWithDelta(454218.00, (float) $this->pnl['expense']['cash_out'], 0.001,
            'Cash memo CM1 was paid 1,180.00 (its grand total) but cash spent counts 1,179.99');
    }

    public function test_cash_in_hand_counts_only_money_that_reached_the_bank(): void
    {
        // Received 9,55,019 less TDS 585.56 kept by customers (a tax credit, not cash)
        // = 9,54,433.44 in; out 4,54,218.00  ->  cash in hand 5,00,215.44.
        $this->assertEqualsWithDelta(500215.44, (float) $this->pnl['cashInHand'], 0.001);
    }
}
