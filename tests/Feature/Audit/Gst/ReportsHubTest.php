<?php

namespace Tests\Feature\Audit\Gst;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reports hub repros: the period presets and the receivables aging register.
 */
class ReportsHubTest extends TestCase
{
    use RefreshDatabase;
    use BuildsGstBooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooks('2026-09-01 10:00');
    }

    /**
     * "This quarter" on 13 Oct 2026 is Q3 of FY 2026-27: 1 Oct - 31 Dec.
     * ReportController::presets() takes diffInMonths from 1 Apr (at the
     * current time of day) to 1 Oct 00:00, which is 5.98 months; intdiv
     * truncates it to 5 and the preset lands on 1 Jul - 30 Sep.
     */
    public function test_this_quarter_preset_in_the_first_month_of_a_quarter(): void
    {
        $this->at('2026-10-13 10:00');
        $res = $this->actingAs($this->user)->get(route('reports.show', ['report' => 'sales-register']));
        $res->assertOk();
        $q = $res->viewData('presets')['this_quarter'];

        $this->assertSame(['2026-10-01', '2026-12-31'], [$q['from']->toDateString(), $q['to']->toDateString()],
            'This quarter preset on 13-10-2026 is ' . $q['from']->toDateString() . ' to ' . $q['to']->toDateString());
    }

    /**
     * Two different customers who share a name (one business registered in
     * two states, or two "Sharma Traders") each owe money. The receivables
     * aging report must carry both balances; it keys its rows by name, so the
     * second customer overwrites the first and the report total drops.
     */
    public function test_receivables_aging_keeps_two_customers_with_the_same_name(): void
    {
        $this->customer('A', 'MH', '27AAACS1111A1Z1', 'Sharma Traders');
        $this->customer('B', 'KA', '29AAACS2222B1Z2', 'Sharma Traders');
        $this->issue('IA', 'A', '2026-09-05 11:00', [$this->l('Steel', '73269099', 1, 10000, 18)]); // 11,800
        $this->issue('IB', 'B', '2026-09-06 11:00', [$this->l('Steel', '73269099', 1, 5000, 18)]);  // 5,900

        $this->at('2026-09-10 10:00');
        $res = $this->actingAs($this->user)->get(route('reports.show', ['report' => 'receivables-aging']));
        $res->assertOk();
        $totals = $res->viewData('totals');
        $rows = $res->viewData('rows');

        $this->assertEqualsWithDelta(11800.0 + 5900.0, (float) $totals['total'], 0.001,
            'aging report total ' . $totals['total'] . ' vs open balances 17,700 (' . count($rows) . ' row(s))');
        $this->assertCount(2, $rows, 'one row per customer');

        // The CSV export carries the same (wrong) total.
        $csv = $this->actingAs($this->user)->get(route('reports.export', ['report' => 'receivables-aging', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('17700.00', $csv);
    }
}
