<?php

namespace Tests\Feature\Fintech\Reconciliation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Identity 8: what is downloaded is what is on screen. Every report's CSV and
 * Excel total row equals the screen total, and the GST/aging/expense exports
 * carry the page's figures. Files are read back the way an accountant opens
 * them: CSV parsed, XLSX through OpenSpout.
 */
class ExportTotalsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    public function test_every_report_csv_and_excel_total_row_equals_the_screen_total(): void
    {
        $failures = [];
        $checked = 0;
        foreach (config('reports.registry') as $key => $class) {
            $columns = (new $class($this->company, Request::create('/')))->columns();
            $totalCols = array_filter($columns, fn ($c) => ! empty($c['total']));
            if (! $totalCols) {
                continue;
            }
            $instance = new $class($this->company, Request::create('/'));
            $query = $instance->usesDates() ? ['from' => '2026-04-01', 'to' => '2027-03-31'] : [];
            $screen = $this->report($key, $query)['totals'];
            $labels = array_column($columns, 'label');

            $csv = $this->csvRows($this->actingAs($this->user)->get(route('reports.export', ['report' => $key, 'format' => 'csv'] + $query))->streamedContent());
            $head = array_search($labels, $csv, true);
            $total = collect($csv)->last(fn ($r) => ($r[0] ?? null) === 'Total');
            $dataRows = array_slice($csv, $head + 1, max(0, count($csv) - $head - 2));

            $xlsx = $this->xlsxSheets($this->actingAs($this->user)->get(route('reports.export', ['report' => $key, 'format' => 'xlsx'] + $query))->streamedContent());
            $sheet = array_values($xlsx)[0];
            $xTotal = collect($sheet)->last(fn ($r) => ($r[0] ?? null) === 'Total');

            if ($head === false || $total === null || $xTotal === null) {
                $failures[] = "{$key}: no heading or Total row in the export";
                continue;
            }
            foreach ($columns as $i => $col) {
                if (empty($col['total'])) {
                    continue;
                }
                $this->check($failures, "{$key} CSV total {$col['key']}", (float) $screen[$col['key']], (float) $total[$i]);
                $this->check($failures, "{$key} Excel total {$col['key']}", (float) $screen[$col['key']], (float) $xTotal[$i]);
                $this->check($failures, "{$key} CSV rows add up to the total {$col['key']}", (float) $total[$i], round(array_sum(array_map(fn ($r) => (float) ($r[$i] ?? 0), $dataRows)), 2));
            }
            $checked++;
        }
        $this->assertGreaterThanOrEqual(11, $checked, 'every register and list with totals was checked');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_the_aging_csv_total_equals_the_aging_page(): void
    {
        $page = $this->actingAs($this->user)->get(route('finance.aging'))->viewData('summary');
        $csv = $this->csvRows($this->actingAs($this->user)->get(route('finance.aging.export.csv'))->streamedContent());
        $total = collect($csv)->first(fn ($r) => in_array('TOTAL', $r, true));
        $this->assertEqualsWithDelta(401035.00, (float) end($total), 0.001);
        $this->assertEqualsWithDelta((float) $page['total'], (float) end($total), 0.001);
        // Each row adds up: grand total - paid - credit notes = balance.
        $head = collect($csv)->search(fn ($r) => ($r[0] ?? null) === 'S.No.');
        foreach (array_slice($csv, $head + 1) as $r) {
            if (! is_numeric($r[0] ?? null)) {
                break;
            }
            $this->assertEqualsWithDelta((float) $r[11], round((float) $r[8] - (float) $r[9] - (float) $r[10], 2), 0.001, "aging CSV row {$r[3]}");
        }
    }

    public function test_the_gstr3b_csv_carries_the_page_figures_for_every_month_and_quarter(): void
    {
        $periods = [];
        foreach ($this->months() as $ym) {
            $periods[$ym] = ['month' => $ym];
        }
        foreach ($this->quarters() as $q => $first) {
            $periods[$q] = ['month' => $first, 'frequency' => 'quarterly'];
        }
        $failures = [];
        foreach ($periods as $label => $query) {
            $res = $this->actingAs($this->user)->get(route('finance.gstr3b', $query));
            $d = $res->original->getData();
            $csv = collect($this->csvRows($this->actingAs($this->user)->get(route('finance.gstr3b.export.csv', $query))->streamedContent()));
            $row = fn (string $start) => $csv->first(fn ($r) => str_starts_with((string) ($r[0] ?? ''), $start));
            $pairs = [
                ['(a) Outward taxable', [1 => $d['outward']['taxable'], 2 => $d['outward']['igst'], 3 => $d['outward']['cgst'], 4 => $d['outward']['sgst']]],
                ['(b) Outward taxable supplies (zero', [1 => $d['outward_zero']['taxable'], 2 => $d['outward_zero']['igst']]],
                ['(c) Other outward', [1 => $d['outward_nil']]],
                ['(d) Inward supplies', [1 => $d['rcm_inward']['taxable'], 2 => $d['rcm_inward']['igst'], 3 => $d['rcm_inward']['cgst'], 4 => $d['rcm_inward']['sgst']]],
                ['(A)(3)', [1 => $d['itc_rcm']['igst'], 2 => $d['itc_rcm']['cgst'], 3 => $d['itc_rcm']['sgst']]],
                ['(A)(5)', [1 => $d['itc_other']['igst'], 2 => $d['itc_other']['cgst'], 3 => $d['itc_other']['sgst']]],
                ['(C) Net ITC', [1 => $d['itc']['igst'], 2 => $d['itc']['cgst'], 3 => $d['itc']['sgst']]],
                ['Tax on outward supplies', [1 => $d['liability']['igst'], 2 => $d['liability']['cgst'], 3 => $d['liability']['sgst']]],
                ['Paid through ITC', [1 => $d['itc_used']['igst'], 2 => $d['itc_used']['cgst'], 3 => $d['itc_used']['sgst']]],
                ['Net cash payable', [1 => $d['netCash']['igst'], 2 => $d['netCash']['cgst'], 3 => $d['netCash']['sgst']]],
                ['Total cash to deposit', [1 => $d['netCash']['total']]],
                ['ITC carried forward', [1 => $d['itc_carried']['igst'], 2 => $d['itc_carried']['cgst'], 3 => $d['itc_carried']['sgst']]],
            ];
            foreach ($pairs as [$start, $cells]) {
                $r = $row($start);
                if ($r === null) {
                    $failures[] = "{$label}: no '{$start}' row in the CSV";
                    continue;
                }
                foreach ($cells as $i => $v) {
                    $this->check($failures, "{$label} CSV '{$start}' col {$i}", (float) $v, (float) $r[$i]);
                }
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_the_expense_csv_total_equals_the_expenses_page_for_the_year(): void
    {
        $q = ['period' => 'custom', 'from' => '2026-04-01', 'to' => '2027-03-31'];
        $page = $this->actingAs($this->user)->get(route('finance.expenses', $q))->viewData('summary');
        $csv = $this->csvRows($this->actingAs($this->user)->get(route('finance.expenses.export.csv', $q))->streamedContent());
        $total = collect($csv)->first(fn ($r) => ($r[4] ?? null) === 'TOTAL');
        // Before GST 7,02,733.32; GST 96,005 (82,730 claimable + 13,275 blocked); paid 7,98,738.32.
        $this->assertEqualsWithDelta(702733.32, (float) $total[5], 0.001);
        $this->assertEqualsWithDelta(96005.00, (float) $total[6], 0.001);
        $this->assertEqualsWithDelta(798738.32, (float) $total[7], 0.001);
        $this->assertEqualsWithDelta((float) $page['taxable'], (float) $total[5], 0.001);
        $this->assertEqualsWithDelta((float) $page['gst'], (float) $total[6], 0.001);
        $this->assertEqualsWithDelta((float) $page['cash_out'], (float) $total[7], 0.001);
    }

    public function test_the_gstr1_csv_and_offline_workbook_carry_the_page_figures(): void
    {
        $failures = [];
        // The year's GSTR-1 CSV. Invoice register TOTAL: the 36 FY numbers less the 2 cancelled:
        // taxable 12,65,462.52, CGST = SGST 6,397.50, IGST 1,26,426.19, value 14,04,684.
        $csv = collect($this->csvRows($this->actingAs($this->user)->get(route('invoices.gstr1', ['from' => '2026-04-01', 'to' => '2027-03-31']))->streamedContent()));
        $total = $csv->first(fn ($r) => ($r[5] ?? null) === 'TOTAL');
        foreach ([9 => 1265462.52, 10 => 6397.50, 11 => 6397.50, 12 => 126426.19, 15 => 1404684.00] as $i => $v) {
            $this->check($failures, "FY GSTR-1 CSV invoice TOTAL col {$i}", $v, (float) $total[$i]);
        }
        // Its 3.1(a) line = the four quarterly 3Bs: 6,82,285.02 / IGST 88,626.19 / CGST = SGST 6,093.29.
        $a = $csv->first(fn ($r) => str_starts_with((string) ($r[0] ?? ''), 'GSTR-3B 3.1(a)'));
        $quarters = array_map(fn ($first) => $this->gstr3bPage($first, true)['outward'], $this->quarters());
        foreach ([3 => 'taxable', 4 => 'igst', 5 => 'cgst', 6 => 'sgst'] as $i => $k) {
            $this->check($failures, "FY CSV 3.1(a) {$k} vs quarters", round(array_sum(array_column($quarters, $k)), 2), (float) $a[$i]);
        }
        // 6,82,375.74 less cnF1's taxable = 6,82,285.02 at 90.72.
        $this->check($failures, 'FY CSV 3.1(a) taxable (hand)', round(682375.74 - $this->cnF1Taxable(), 2), (float) $a[3]);

        // Offline-tool workbook, every month: the Read me table totals = the page; each data
        // sheet's summary row = the sum of its rows.
        $sheetKeys = ['b2b' => 11, 'b2cl' => 6, 'b2cs' => 4, 'cdnr' => 11, 'cdnur' => 8, 'exp' => 8, 'hsn(b2b)' => 5, 'hsn(b2c)' => 5];
        foreach ($this->months() as $ym) {
            $page = $this->gstr1Page($ym);
            $book = $this->xlsxSheets($this->actingAs($this->user)->get(route('finance.gstr1.export.xlsx', ['month' => $ym]))->streamedContent());
            $readMe = collect($book['Read me']);
            foreach ($page['tables'] as $key => $t) {
                $r = $readMe->first(fn ($row) => ($row[1] ?? null) === $t['label']);
                if ($r === null) {
                    $failures[] = "{$ym} Read me has no row '{$t['label']}'";
                    continue;
                }
                foreach ([3 => 'taxable', 4 => 'igst', 5 => 'cgst', 6 => 'sgst'] as $i => $k) {
                    $this->check($failures, "{$ym} Read me {$key} {$k}", (float) $t[$k], (float) $r[$i]);
                }
            }
            foreach ($sheetKeys as $sheet => $col) {
                $rows = array_slice($book[$sheet] ?? [], 4);
                $this->check($failures, "{$ym} sheet {$sheet} summary = rows", (float) ($book[$sheet][2][$col] ?? 0), round(array_sum(array_map(fn ($r) => (float) ($r[$col] ?? 0), $rows)), 2));
            }
            // b2b sheet = 4A + 4B + 6B; HSN sheets = Table 12 on the page.
            $this->check($failures, "{$ym} b2b sheet taxable", round($page['tables']['b2b']['taxable'] + $page['tables']['b2b_rcm']['taxable'] + $page['tables']['sez']['taxable'], 2), (float) ($book['b2b'][2][11] ?? 0));
            $this->check($failures, "{$ym} hsn(b2b) sheet taxable", (float) $page['tables']['hsn_b2b']['taxable'], (float) ($book['hsn(b2b)'][2][5] ?? 0));
            $this->check($failures, "{$ym} hsn(b2c) sheet taxable", (float) $page['tables']['hsn_b2c']['taxable'], (float) ($book['hsn(b2c)'][2][5] ?? 0));
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
