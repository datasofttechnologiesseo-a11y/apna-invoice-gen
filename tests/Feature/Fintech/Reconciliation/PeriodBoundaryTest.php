<?php

namespace Tests\Feature\Fintech\Reconciliation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Identity 9: documents stay in the period of their date. X0 was issued at
 * 23:59 IST on 31 Mar 2026, A1 at 00:01 IST on 1 Apr 2026, J4 at 23:59 on
 * 30 Jun, L1 at 00:01 on 1 Jul, Mr2 at 23:59 on 31 Mar 2027 and Y1 at 00:01
 * on 1 Apr 2027 (IST is UTC+5:30, so each of these is on the other side of
 * midnight in UTC). Period filters take in both of their end dates.
 */
class PeriodBoundaryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    public function test_an_invoice_issued_at_23_59_on_31_march_belongs_to_march_and_the_old_year(): void
    {
        $x0 = $this->inv['X0'];
        $this->assertSame('SAH/25-26/0001', $x0->invoice_number, 'FY 2025-26 series');
        $this->assertSame('2026-03-31', $x0->invoice_date->toDateString());

        $march = $this->gstr1Page('2026-03');
        $this->assertSame([$x0->invoice_number], array_values(array_unique(array_column($march['b2b'], 'number'))));
        $this->assertEqualsWithDelta(2000.0, $march['tables']['b2b']['taxable'], 0.001);
        $this->assertSame(['nature' => 'Invoices for outward supply', 'from' => 'SAH/25-26/0001', 'to' => 'SAH/25-26/0001', 'total' => 1, 'cancelled' => 0], $march['docs'][0]);
        $this->assertNotContains($x0->invoice_number, array_column($this->gstr1Page('2026-04')['b2b'], 'number'));
        // 3B March 2026: 2,000, CGST = SGST 180; April 2026 does not carry it (1,69,800 by hand).
        $d = $this->gstr3bPage('2026-03');
        $this->assertEqualsWithDelta(2000.0, $d['outward']['taxable'], 0.001);
        $this->assertEqualsWithDelta(180.0, $d['outward']['cgst'], 0.001);
        $this->assertEqualsWithDelta(169800.0, $this->gstr3bPage('2026-04')['outward']['taxable'], 0.001);

        // Mr2, 31 Mar 2027 23:59: FY 2026-27's last number, March 2027 and Q4.
        $mr2 = $this->inv['Mr2'];
        $this->assertSame('SAH/26-27/0036', $mr2->invoice_number);
        $b2cs = collect($this->gstr1Page('2027-03')['b2cs'])->first(fn ($r) => $r['pos'] === '27-Maharashtra' && (float) $r['rate'] === 18.0);
        $this->assertEqualsWithDelta(1000.0, $b2cs['taxable'], 0.001);
        $this->assertSame('SAH/26-27/0036', collect($this->gstr1Page('2027-01', true)['docs'])->firstWhere('nature', 'Invoices for outward supply')['to']);
    }

    public function test_an_invoice_issued_at_00_01_on_1_april_belongs_to_april_and_the_new_year(): void
    {
        $this->assertSame('SAH/26-27/0001', $this->inv['A1']->invoice_number);
        $this->assertSame('2026-04-01', $this->inv['A1']->invoice_date->toDateString());
        $april = $this->gstr1Page('2026-04');
        $this->assertSame('SAH/26-27/0001', $april['docs'][0]['from']);
        // A1 1,000 + A5's 18% line 1,000 at 27-MH 18%.
        $this->assertEqualsWithDelta(2000.0, collect($april['b2cs'])->firstWhere('pos', '27-Maharashtra')['taxable'], 0.001);

        // Y1, 1 Apr 2027 00:01: a new series and a new year; not in March 2027 or Q4.
        $this->assertSame('SAH/27-28/0001', $this->inv['Y1']->invoice_number);
        $this->assertSame('SAH/27-28/0001', $this->gstr1Page('2027-04')['docs'][0]['from']);
        $this->assertSame(2, collect($this->gstr1Page('2027-03')['docs'])->firstWhere('nature', 'Invoices for outward supply')['total']);

        // Receipts at 00:01 are dated the new day: A1's 1,180 in April 2026, Mr2's 1,180 in April 2027.
        $this->assertEqualsWithDelta(0.0, (float) $this->report('receipts', ['from' => '2026-03-01', 'to' => '2026-03-31'])['totals']['amount'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->report('receipts', ['from' => '2027-03-01', 'to' => '2027-03-31'])['totals']['amount'], 0.001);
        // April 2027: Mr2 1,180 (00:01) + Mr1 23,600.
        $this->assertEqualsWithDelta(24780.0, (float) $this->report('receipts', ['from' => '2027-04-01', 'to' => '2027-04-30'])['totals']['amount'], 0.001);
    }

    public function test_the_time_zone_never_moves_a_document_to_another_day(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $x0 = $this->inv['X0']->fresh();
        $this->assertSame('2026-03-31 23:59', $x0->finalized_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'), 'issue time read back unchanged');
        $this->assertSame('2026-04-01 00:01', $this->inv['A1']->fresh()->finalized_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'));

        // The app's JSON gives the dates as dates, not as UTC instants of the day before.
        Sanctum::actingAs($this->user);
        $this->assertSame('2026-03-31', $this->getJson('/api/invoices/' . $x0->id)->assertOk()->json('data.invoice_date') ?? $this->getJson('/api/invoices/' . $x0->id)->json('invoice_date'));
        $this->assertSame('2026-04-01', $this->getJson('/api/invoices/' . $this->inv['A1']->id)->assertOk()->json('data.invoice_date') ?? $this->getJson('/api/invoices/' . $this->inv['A1']->id)->json('invoice_date'));

        // A new invoice opened at 00:01 IST on 1 April is dated 1 April.
        $this->at('2026-04-01 00:01');
        $this->assertSame('2026-04-01', $this->actingAs($this->user)->get(route('invoices.create'))->viewData('invoice')->invoice_date->toDateString());

        // The dashboard's month: half an hour into April, April's issues are A1, A2, A4, A5 (X0 is March's).
        $this->at('2026-04-01 00:30');
        $this->assertSame(4, (int) $this->actingAs($this->user)->get(route('dashboard'))->viewData('stats')['issued_this_month']);
        $this->at('2026-03-31 23:59:30');
        $this->assertSame(1, (int) $this->actingAs($this->user)->get(route('dashboard'))->viewData('stats')['issued_this_month']);
    }

    public function test_quarter_edges_hold_at_midnight(): void
    {
        // J4 (30 Jun 23:59) is June and Q1; its credit note and L1 (1 Jul 00:01) are July and Q2.
        $june = $this->gstr1Page('2026-06');
        $this->assertContains($this->inv['J4']->invoice_number, array_column($june['b2b'], 'number'));
        $july = $this->gstr1Page('2026-07');
        $this->assertNotContains($this->inv['J4']->invoice_number, array_column($july['b2b'], 'number'));
        $this->assertContains($this->inv['J4']->invoice_number, array_column($july['cdnr'], 'invoice'), 'the 1 July note reports in July against a June invoice');
        $this->assertEqualsWithDelta(450.0, $this->gstr3bPage('2026-04', true)['outward']['cgst'] - 162 - 900, 0.001, 'Q1 CGST 1,512 includes J4 450');
        $this->assertEqualsWithDelta(4000.0, collect($july['b2cs'])->firstWhere('pos', '27-Maharashtra')['taxable'], 0.001, 'L1 is July');
    }

    public function test_period_filters_take_in_both_end_dates(): void
    {
        $failures = [];
        // May: M1 05-05 ... M5 31-05 (M4 cancelled shows at nil). Sales register by date filter.
        $may = $this->report('sales-register', ['from' => '2026-05-01', 'to' => '2026-05-31']);
        $this->check($failures, 'sales register May rows (M1-M5, M5 dated 31 May)', 5, count($may['rows']));
        $this->check($failures, 'sales register May taxable (9,999.99 + 2,00,000 + 20,000 + 50,000)', 279999.99, (float) $may['totals']['taxable']);
        $list = $this->actingAs($this->user)->get(route('invoices.index', ['from' => '2026-05-01', 'to' => '2026-05-31']))->viewData('invoices');
        $this->check($failures, 'invoice list May (M1-M5)', 5, $list->total());
        $apr = $this->actingAs($this->user)->get(route('invoices.index', ['from' => '2026-04-01', 'to' => '2026-04-30']))->viewData('invoices');
        $this->check($failures, 'invoice list April (A1 01-04 ... A6 draft 28-04)', 5, $apr->total());

        // April receipts: A1 01-04 1,180 ... A5 30-04 1,000 -> 1,81,540.
        $this->check($failures, 'receipts April', 181540.00, (float) $this->report('receipts', ['from' => '2026-04-01', 'to' => '2026-04-30'])['totals']['amount']);
        // April purchases: B1 02-04 60,000 + B2 08-04 60,000 + B3 30-04 10,000.
        $this->check($failures, 'purchase register April taxable', 130000.00, (float) $this->report('purchase-register', ['from' => '2026-04-01', 'to' => '2026-04-30'])['totals']['taxable']);
        // Paid on 30 April: B1 70,800 + B3 10,000.
        $this->check($failures, 'vendor payments April', 80800.00, (float) $this->report('vendor-payments', ['from' => '2026-04-01', 'to' => '2026-04-30'])['totals']['amount']);
        // July credit notes: cnJ4 01-07 1,180 + cnM1 25-07 1,180.
        $this->check($failures, 'credit notes July', 2360.00, (float) $this->report('credit-notes', ['from' => '2026-07-01', 'to' => '2026-07-31'])['totals']['amount']);
        // April expenses: E1 rent 30-04 30,000 + the bills' mirror rows 60,000 + 60,000 + 10,000.
        $exp = $this->actingAs($this->user)->get(route('finance.expenses', ['period' => 'custom', 'from' => '2026-04-01', 'to' => '2026-04-30']))->viewData('summary');
        $this->check($failures, 'expenses April taxable', 160000.00, (float) $exp['taxable']);
        // P&L April cash spent: E1 35,400 + bill payments on 30-04 (70,800 + 10,000) = 1,16,200.
        $pnl = $this->actingAs($this->user)->get(route('finance.index', ['period' => 'custom', 'from' => '2026-04-01', 'to' => '2026-04-30']))->viewData('expense');
        $this->check($failures, 'P&L April cash spent', 116200.00, (float) $pnl['cash_out']);
        // Stock April: P1 out A1 (01-04) 1 + A2 10 + A5 1 = 12, in B1 100.
        $stock = collect($this->report('stock-summary', ['from' => '2026-04-01', 'to' => '2026-04-30'])['rows'])->firstWhere('name', 'Steel bracket');
        $this->check($failures, 'stock April P1 out', 12, (float) $stock['out']);
        $this->check($failures, 'stock April P1 in', 100, (float) $stock['in']);
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
