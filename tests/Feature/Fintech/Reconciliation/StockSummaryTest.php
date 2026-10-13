<?php

namespace Tests\Feature\Fintech\Reconciliation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Identity 7: the Stock Summary. For any range, opening + in - out = closing;
 * the closing of one day is the opening of the next; value = quantity x
 * purchase price (the sale rate when no purchase price is known).
 *
 * Movements (from the SeedsFy2026Books ledger):
 *  P1 Steel bracket, opening 100, cost 600:
 *    31-03-26 -2 X0 | 01-04 -1 A1 | 02-04 +100 B1 | 10-04 -10 A2 | 25-04 -1 A5 | 18-05 -20 M3
 *    22-05 -5 M4 | 23-05 +5 M4 cancelled | 30-06 -5 J4 | 10-07 -3 L2 | 20-07 -50 L3 | 05-08 -8 G1
 *    15-08 +50 B9 | 15-09 -3 damaged | 10-11 -30 N1 | 20-12 +100 B13 | 10-01-27 -12 Ja1
 *    05-02 -2 F1 | 28-02 -100 F3
 *  P2 Packaged tea, opening 50, no cost until B2 (300/kg):
 *    08-04 +200 B2 | 10-04 -25 A2 | 05-06 -100 J1 | 01-07 -10 L1, +100 B8 | 05-08 -20 G1
 *    05-10 -15 O1 | 12-10 +5 sales return | 05-02-27 -5 F1 | 10-02 +50 B15 | 05-03 +40 B19
 *    31-03-27 +2 count
 *  P3 Generator, opening 3, never bought (valued at its sale rate 1,50,000): 20-04 -1 A4
 */
class StockSummaryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    private function stock(?string $from, ?string $to): array
    {
        return collect($this->report('stock-summary', array_filter(['from' => $from, 'to' => $to]))['rows'])->keyBy('name')->all();
    }

    public function test_opening_plus_in_less_out_is_closing_for_the_year_its_quarters_and_odd_ranges(): void
    {
        // [opening, in, out, closing] by hand.
        $ranges = [
            'FY' => ['2026-04-01', '2027-03-31', ['Steel bracket' => [98, 255, 250, 103], 'Packaged tea' => [50, 397, 175, 272], 'Generator' => [3, 0, 1, 2]]],
            'Q1' => ['2026-04-01', '2026-06-30', ['Steel bracket' => [98, 105, 42, 161], 'Packaged tea' => [50, 200, 125, 125], 'Generator' => [3, 0, 1, 2]]],
            'Q2' => ['2026-07-01', '2026-09-30', ['Steel bracket' => [161, 50, 64, 147], 'Packaged tea' => [125, 100, 30, 195], 'Generator' => [2, 0, 0, 2]]],
            'Q3' => ['2026-10-01', '2026-12-31', ['Steel bracket' => [147, 100, 30, 217], 'Packaged tea' => [195, 5, 15, 185], 'Generator' => [2, 0, 0, 2]]],
            'Q4' => ['2027-01-01', '2027-03-31', ['Steel bracket' => [217, 0, 114, 103], 'Packaged tea' => [185, 92, 5, 272], 'Generator' => [2, 0, 0, 2]]],
            // May: the cancelled M4 goes out and comes back.
            'May' => ['2026-05-01', '2026-05-31', ['Steel bracket' => [186, 5, 25, 166], 'Packaged tea' => [225, 0, 0, 225]]],
            // October: 15 kg sold, 5 kg returned on a credit note.
            'Oct' => ['2026-10-01', '2026-10-31', ['Packaged tea' => [195, 5, 15, 185]]],
            '31-03-2026' => ['2026-03-31', '2026-03-31', ['Steel bracket' => [100, 0, 2, 98], 'Packaged tea' => [50, 0, 0, 50]]],
            '30-06-2026' => ['2026-06-30', '2026-06-30', ['Steel bracket' => [166, 0, 5, 161], 'Packaged tea' => [125, 0, 0, 125]]],
            '01-07-2026' => ['2026-07-01', '2026-07-01', ['Steel bracket' => [161, 0, 0, 161], 'Packaged tea' => [125, 100, 10, 215]]],
        ];
        $failures = [];
        foreach ($ranges as $label => [$from, $to, $items]) {
            $rows = $this->stock($from, $to);
            foreach ($rows as $name => $r) {
                $this->check($failures, "{$label} {$name}: opening + in - out = closing", round($r['opening'] + $r['in'] - $r['out'], 3), (float) $r['closing'], 0.0005);
            }
            foreach ($items as $name => [$o, $i, $out, $c]) {
                foreach (['opening' => $o, 'in' => $i, 'out' => $out, 'closing' => $c] as $k => $v) {
                    $this->check($failures, "{$label} {$name} {$k} (hand)", $v, (float) $rows[$name][$k], 0.0005);
                }
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_the_closing_of_one_day_is_the_opening_of_the_next(): void
    {
        $failures = [];
        // Every month end of the year, plus the year's own edges.
        $days = ['2026-03-31'];
        for ($m = Carbon::create(2026, 4, 1); $m->lt(Carbon::create(2027, 4, 1)); $m->addMonth()) {
            $days[] = $m->copy()->endOfMonth()->toDateString();
        }
        foreach ($days as $day) {
            $next = Carbon::parse($day)->addDay()->toDateString();
            $closing = $this->stock(Carbon::parse($day)->startOfMonth()->toDateString(), $day);
            $opening = $this->stock($next, $next);
            foreach ($closing as $name => $r) {
                $this->check($failures, "{$name}: closing {$day} = opening {$next}", (float) $r['closing'], (float) $opening[$name]['opening'], 0.0005);
            }
        }
        // Stock on hand today (no dates) = the year's closing; nothing has moved since.
        $now = $this->stock(null, null);
        foreach (['Steel bracket' => 103, 'Packaged tea' => 272, 'Generator' => 2] as $name => $qty) {
            $this->check($failures, "{$name} on hand today", $qty, (float) $now[$name]['closing'], 0.0005);
            $page = $this->actingAs($this->user)->get(route('products.stock', $this->prod[['Steel bracket' => 'P1', 'Packaged tea' => 'P2', 'Generator' => 'P3'][$name]]))->viewData('onHand');
            $this->check($failures, "{$name} item stock page", $qty, (float) $page, 0.0005);
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_stock_is_valued_at_purchase_price_or_at_the_rate_when_none_is_known(): void
    {
        // 31-03-2027: brackets 103 x 600 = 61,800; tea 272 x 300 (cost from its first
        // bill B2 - the later 310/kg bill does not change it) = 81,600; generator never
        // bought, 2 x 1,50,000 sale rate = 3,00,000. Total 4,43,400.
        $rows = $this->stock('2026-04-01', '2027-03-31');
        $this->assertEqualsWithDelta(600.0, $rows['Steel bracket']['price'], 0.001);
        $this->assertEqualsWithDelta(61800.0, $rows['Steel bracket']['value'], 0.001);
        $this->assertEqualsWithDelta(300.0, $rows['Packaged tea']['price'], 0.001);
        $this->assertEqualsWithDelta(81600.0, $rows['Packaged tea']['value'], 0.001);
        $this->assertEqualsWithDelta(150000.0, $rows['Generator']['price'], 0.001);
        $this->assertEqualsWithDelta(300000.0, $rows['Generator']['value'], 0.001);
        $this->assertEqualsWithDelta(443400.0, $this->report('stock-summary', ['from' => '2026-04-01', 'to' => '2027-03-31'])['totals']['value'], 0.001);
        foreach ($rows as $name => $r) {
            $this->assertEqualsWithDelta(round(max(0, $r['closing']) * $r['price'], 2), $r['value'], 0.001, "{$name}: value = qty x price");
        }
    }
}
