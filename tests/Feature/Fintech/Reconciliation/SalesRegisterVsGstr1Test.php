<?php

namespace Tests\Feature\Fintech\Reconciliation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identity 1: what the Sales Register says was sold, less the Credit Note
 * register, is exactly what GSTR-1 reports: Tables 4A + 4B + 5 + 6A + 6B + 7
 * + 8 less 9B, month by month, per quarter and for the year; the JSON the
 * portal takes, Table 12 and Table 13 say the same thing again.
 */
class SalesRegisterVsGstr1Test extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    /**
     * Sales register less credit notes, by hand from the SeedsFy2026Books ledger:
     * [taxable, IGST, CGST, SGST].
     */
    private const NET_BY_MONTH = [
        // A1 1,000 + A2 18,000 + A4 1,50,000 + A5 2,000 - cnX0 200; tax 29,070 / 180-18 / 180-18
        '2026-04' => [170800.00, 29070.00, 162.00, 162.00],
        // M1 9,999.99 + M2 2,00,000 + M3 20,000 + M5 50,000 (M4 cancelled)
        '2026-05' => [279999.99, 3600.00, 900.00, 900.00],
        // J1 40,000 + J2 84,745.76 + J3 95,239.05 + J4 5,000; IGST 2,000 + 15,254.24 + 4,761.95
        '2026-06' => [224984.81, 22016.19, 450.00, 450.00],
        // L1 4,000 + L2 10,777.77 + L3 50,000 - cnJ4 1,000 - cnM1 1,000; CGST 100 - 90 - 90
        '2026-07' => [62777.77, 10940.00, -80.00, -80.00],
        '2026-08' => [17500.00, 0.00, 1087.50, 1087.50],
        // S1 75,000 + S2 1,500 + S4 25,000 - cnG1 896.86
        '2026-09' => [100603.14, 4500.00, -51.57, -51.57],
        // O1 6,000 + O2 1,200 - cnO1 2,000 (O3 cancelled)
        '2026-10' => [5200.00, 0.00, 340.00, 340.00],
        '2026-11' => [70999.95, 5400.00, 90.00, 90.00],
        // D1 1,00,000 + D2 10,000 - cnL3 5,000; IGST 18,000 + 500 - 900
        '2026-12' => [105000.00, 17600.00, 0.00, 0.00],
        '2027-01' => [39000.00, 4860.00, 1080.00, 1080.00],
        // F1 4,500 + F2 50,000 + F3 1,00,000, less cnF1's taxable (90.72; see netByMonth); CGST 230 - 4.64
        '2027-02' => [154500.00, 27000.00, 225.36, 225.36],
        // Mr1 20,000 + Mr2 1,000 - cnF3 10,000; IGST -1,800
        '2027-03' => [11000.00, -1800.00, 1890.00, 1890.00],
    ];

    /** NET_BY_MONTH with February less cnF1's taxable value as recorded (90.72 when it adds up). */
    private function netByMonth(): array
    {
        $net = self::NET_BY_MONTH;
        $net['2027-02'][0] = round($net['2027-02'][0] - $this->cnF1Taxable(), 2);

        return $net;
    }

    /**
     * GSTR-1 tables, net: 4A + 4B + 5 + 6A + 6B + 7 + 8 - 9B (cdnr + cdnur),
     * on the registers' basis. The registers show tax charged; on a
     * reverse-charge supply GSTR-1 reports the tax the RECIPIENT pays (4B, and
     * its notes in 9B), which was never charged. So for tax the 4B figures and
     * the reverse-charge notes are left out; taxable value counts in full.
     */
    private function gstr1Net(array $page): array
    {
        $tables = $page['tables'];
        $rcmNotes = collect($page['cdnr'] ?? [])->where('rcm', 'Y');
        $out = [];
        foreach (['taxable', 'igst', 'cgst', 'sgst'] as $k) {
            $rcm = $k === 'taxable' ? 0.0 : $tables['b2b_rcm'][$k] - (float) $rcmNotes->sum($k);
            $out[$k] = round($tables['b2b'][$k] + $tables['b2b_rcm'][$k] + $tables['b2cl'][$k] + $tables['exp'][$k]
                + $tables['sez'][$k] + $tables['b2cs'][$k] + $tables['exemp'][$k] - $tables['cdnr'][$k] - $tables['cdnur'][$k] - $rcm, 2);
        }

        return $out;
    }

    /** Sales register less credit-note register for a date range. */
    private function registersNet(string $from, string $to): array
    {
        $sr = $this->registerTotals('sales-register', $from, $to);
        $cn = $this->registerTotals('credit-notes', $from, $to);
        $out = [];
        foreach (['taxable', 'igst', 'cgst', 'sgst'] as $k) {
            $out[$k] = round(($sr[$k] ?? 0) - ($cn[$k] ?? 0), 2);
        }

        return $out;
    }

    public function test_sales_register_less_credit_notes_equals_gstr1_tables_every_month(): void
    {
        $failures = [];
        $hand = $this->netByMonth();
        foreach ($this->months() as $ym) {
            [$from, $to] = $this->monthRange($ym);
            $registers = $this->registersNet($from, $to);
            $gstr1 = $this->gstr1Net($this->gstr1Page($ym));
            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $i => $k) {
                $this->check($failures, "{$ym} registers {$k} (hand)", $hand[$ym][$i], $registers[$k]);
                $this->check($failures, "{$ym} GSTR-1 tables {$k} vs registers", $registers[$k], $gstr1[$k]);
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_sales_register_less_credit_notes_equals_gstr1_for_each_quarter_and_the_year(): void
    {
        $failures = [];
        $year = ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
        foreach ($this->quarters() as $q => $first) {
            $last = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $first . '-01')->addMonthsNoOverflow(2)->endOfMonth()->toDateString();
            $registers = $this->registersNet($first . '-01', $last);
            $gstr1 = $this->gstr1Net($this->gstr1Page($first, true));
            // The quarter by hand is the sum of its three months above.
            $hand = [0, 0, 0, 0];
            foreach ($this->netByMonth() as $ym => $v) {
                if ($ym >= $first && $ym <= substr($last, 0, 7)) {
                    foreach ($v as $i => $x) {
                        $hand[$i] += $x;
                    }
                }
            }
            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $i => $k) {
                $this->check($failures, "{$q} registers {$k} (hand)", $hand[$i], $registers[$k]);
                $this->check($failures, "{$q} GSTR-1 tables {$k} vs registers", $registers[$k], $gstr1[$k]);
                $year[$k] += $gstr1[$k];
            }
        }
        // FY 2026-27: invoices 12,65,462.52 less credit notes 23,096.86 + cnF1 = 12,42,365.66 - cnF1
        // = 12,42,274.94 with cnF1 at 90.72 (3.1(a) 6,82,285.02 + 3.1(b) 4,67,000 + 3.1(c) 2,989.92
        // + reverse-charge sales 90,000); tax IGST 1,23,186.19, CGST = SGST 6,093.29.
        $fy = $this->registersNet('2026-04-01', '2027-03-31');
        foreach (['taxable' => round(1242365.66 - $this->cnF1Taxable(), 2), 'igst' => 123186.19, 'cgst' => 6093.29, 'sgst' => 6093.29] as $k => $v) {
            $this->check($failures, "FY registers {$k} (hand)", $v, $fy[$k]);
            $this->check($failures, "FY GSTR-1 (four quarters) {$k} vs registers", $fy[$k], $year[$k]);
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_cancelled_and_draft_invoices_are_left_out_of_every_figure(): void
    {
        $m4 = $this->inv['M4']->invoice_number;   // cancelled, May, B2CS intra 5,000 @18
        $o3 = $this->inv['O3']->invoice_number;   // cancelled, Oct, B2C inter 3,000 @18

        $may = $this->gstr1Page('2026-05');
        $oct = $this->gstr1Page('2026-10');
        // May's only B2CS sale was M4: no B2CS row, and its steel brackets are
        // not in the B2C tab of Table 12 (the B2C tab holds only the M2 export).
        $this->assertSame([], $may['b2cs']);
        $this->assertSame(['998314'], array_column(array_values(array_filter($may['hsn'], fn ($r) => $r['tab'] === 'B2C')), 'hsn'));
        // October B2CS is O1 alone, net of its sales return: 27-MH 5% 6,000 - 2,000 = 4,000.
        $this->assertCount(1, $oct['b2cs']);
        $this->assertSame('27-Maharashtra', $oct['b2cs'][0]['pos']);
        $this->assertEqualsWithDelta(4000.00, $oct['b2cs'][0]['taxable'], 0.001);
        $this->assertNull(collect($oct['hsn'])->firstWhere('hsn', '94052090'), 'cancelled O3 is not in Table 12');
        foreach ([$may, $oct] as $g) {
            foreach (['b2b', 'b2cl', 'cdnr', 'cdnur'] as $t) {
                $this->assertNotContains($m4, array_column($g[$t], 'number'));
                $this->assertNotContains($o3, array_column($g[$t], 'number'));
            }
        }

        // GSTR-3B counts issued, live invoices only: May M1, M2, M3, M5; Oct O1, O2.
        $this->assertSame(4, $this->gstr3bPage('2026-05')['invoiceCount']);
        $this->assertSame(2, $this->gstr3bPage('2026-10')['invoiceCount']);

        // Sales register keeps the cancelled rows (no gap in the series) at zero; drafts never appear.
        $rows = collect($this->report('sales-register', ['from' => '2026-04-01', 'to' => '2027-04-01'])['rows'])
            ->filter(fn ($r) => $r['date']->format('Y-m-d') <= '2027-03-31');
        $this->assertCount(36, $rows, '36 numbers issued in FY 2026-27, drafts A6 and G3 not among them');
        foreach ([$m4, $o3] as $no) {
            $row = $rows->firstWhere('number', $no);
            $this->assertSame('Cancelled', $row['status']);
            foreach (['taxable', 'cgst', 'sgst', 'igst', 'total', 'balance'] as $k) {
                $this->assertEqualsWithDelta(0.0, $row[$k], 0.001, "{$no} {$k}");
            }
        }
        $this->assertSame(0, $rows->filter(fn ($r) => str_starts_with((string) $r['number'], 'Draft'))->count());

        // Receivables and the customer ledgers never see them.
        $aging = $this->actingAs($this->user)->get(route('finance.aging'))->viewData('rows');
        $this->assertNotContains($m4, $aging->pluck('invoice_number')->all());
        $this->assertNotContains($o3, $aging->pluck('invoice_number')->all());
        $ledger = $this->actingAs($this->user)->get(route('customers.ledger', $this->cust['BLR']))->viewData('entries');
        $this->assertNotContains($o3, $ledger->pluck('ref')->all());

        // The dashboard's "invoiced this month", read in May: M1 11,800 + M2 2,00,000 + M3 23,600 + M5 50,000.
        $this->at('2026-05-31 18:00');
        $stats = $this->actingAs($this->user)->get(route('dashboard'))->viewData('stats');
        $this->assertEqualsWithDelta(285400.00, (float) $stats['invoiced_this_month'], 0.001);
    }

    public function test_the_portal_json_totals_equal_the_gstr1_page_every_month(): void
    {
        $failures = [];
        foreach ($this->months() as $ym) {
            $page = $this->gstr1Page($ym);
            $t = $page['tables'];
            $res = $this->actingAs($this->user)->get(route('finance.gstr1.export.json', ['month' => $ym]));
            $res->assertOk();
            $j = json_decode($res->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

            $sum = function (array $dets) {
                $s = ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
                foreach ($dets as $d) {
                    $s['taxable'] += (float) ($d['txval'] ?? 0);
                    $s['igst'] += (float) ($d['iamt'] ?? 0);
                    $s['cgst'] += (float) ($d['camt'] ?? 0);
                    $s['sgst'] += (float) ($d['samt'] ?? 0);
                }

                return array_map(fn ($v) => round($v, 2), $s);
            };
            $sections = [
                // 4A + 4B + 6B are one b2b block in the JSON
                'b2b' => [$sum(collect($j['b2b'] ?? [])->flatMap(fn ($c) => $c['inv'])->flatMap(fn ($i) => $i['itms'])->pluck('itm_det')->all()),
                    ['b2b', 'b2b_rcm', 'sez']],
                'b2cl' => [$sum(collect($j['b2cl'] ?? [])->flatMap(fn ($p) => $p['inv'])->flatMap(fn ($i) => $i['itms'])->pluck('itm_det')->all()), ['b2cl']],
                'b2cs' => [$sum($j['b2cs'] ?? []), ['b2cs']],
                'cdnr' => [$sum(collect($j['cdnr'] ?? [])->flatMap(fn ($c) => $c['nt'])->flatMap(fn ($n) => $n['itms'])->pluck('itm_det')->all()), ['cdnr']],
                'cdnur' => [$sum(collect($j['cdnur'] ?? [])->flatMap(fn ($n) => $n['itms'])->pluck('itm_det')->all()), ['cdnur']],
                'exp' => [$sum(collect($j['exp'] ?? [])->flatMap(fn ($e) => $e['inv'])->flatMap(fn ($i) => $i['itms'])->all()), ['exp']],
                'hsn_b2b' => [$sum($j['hsn']['hsn_b2b'] ?? []), ['hsn_b2b']],
                'hsn_b2c' => [$sum($j['hsn']['hsn_b2c'] ?? []), ['hsn_b2c']],
            ];
            foreach ($sections as $name => [$json, $keys]) {
                foreach (['taxable', 'igst', 'cgst', 'sgst'] as $k) {
                    $screen = round(array_sum(array_map(fn ($key) => $t[$key][$k], $keys)), 2);
                    $this->check($failures, "{$ym} JSON {$name} {$k}", $screen, $json[$k]);
                }
            }
            $nil = round(array_sum(array_column($j['nil']['inv'] ?? [], 'nil_amt')), 2);
            $this->check($failures, "{$ym} JSON nil", $t['exemp']['taxable'], $nil);
            $docs = collect($j['doc_issue']['doc_det'] ?? [])->flatMap(fn ($d) => $d['docs']);
            $this->check($failures, "{$ym} JSON docs total", array_sum(array_column($page['docs'], 'total')), $docs->sum('totnum'));
            $this->check($failures, "{$ym} JSON docs cancelled", array_sum(array_column($page['docs'], 'cancelled')), $docs->sum('cancel'));
            $this->assertSame(substr($ym, 5, 2) . substr($ym, 0, 4), $j['fp']);
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_hsn_summary_equals_tables_4_to_8_net_of_credit_notes_split_b2b_and_b2c(): void
    {
        $failures = [];
        foreach ($this->months() as $ym) {
            $g = $this->gstr1Page($ym);
            $t = $g['tables'];
            $nilReg = $nilUnreg = 0.0;
            foreach ($g['exemp'] as $r) {
                str_contains($r['description'], 'unregistered') ? $nilUnreg += $r['nil'] : $nilReg += $r['nil'];
            }
            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $k) {
                // B2B tab: 4A + 4B + 6B + nil to registered persons - credit notes to them.
                $b2b = $t['b2b'][$k] + $t['b2b_rcm'][$k] + $t['sez'][$k] + ($k === 'taxable' ? $nilReg : 0) - $t['cdnr'][$k];
                // B2C tab: 5 + 6A + 7 (already net) + nil to unregistered persons - B2CL/export notes.
                $b2c = $t['b2cl'][$k] + $t['exp'][$k] + $t['b2cs'][$k] + ($k === 'taxable' ? $nilUnreg : 0) - $t['cdnur'][$k];
                $this->check($failures, "{$ym} Table 12 B2B {$k}", round($b2b, 2), $t['hsn_b2b'][$k]);
                $this->check($failures, "{$ym} Table 12 B2C {$k}", round($b2c, 2), $t['hsn_b2c'][$k]);
            }
            // The HSN Summary report is the same Table 12.
            [$from, $to] = $this->monthRange($ym);
            $report = $this->report('hsn-summary', ['from' => $from, 'to' => $to])['totals'];
            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $k) {
                $this->check($failures, "{$ym} HSN report {$k}", round($t['hsn_b2b'][$k] + $t['hsn_b2c'][$k], 2), $report[$k]);
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));

        // October by hand. B2B: O2 drinks 22021010 24 NOS @40% 1,200, C/S 240.
        // B2C: O1 tea 15 kg 6,000 @5% less the 5 kg sales return 2,000 -> 10 kg, 4,000, C/S 100.
        $hsn = collect($this->gstr1Page('2026-10')['hsn']);
        $drinks = $hsn->first(fn ($r) => $r['tab'] === 'B2B' && $r['hsn'] === '22021010');
        $this->assertEqualsWithDelta(24.0, $drinks['qty'], 0.001);
        $this->assertEqualsWithDelta(1200.0, $drinks['taxable'], 0.001);
        $this->assertEqualsWithDelta(240.0, $drinks['cgst'], 0.001);
        $tea = $hsn->first(fn ($r) => $r['tab'] === 'B2C' && $r['hsn'] === '09023020');
        $this->assertEqualsWithDelta(10.0, $tea['qty'], 0.001, 'a sales return takes the quantity off');
        $this->assertEqualsWithDelta(4000.0, $tea['taxable'], 0.001);
        $this->assertEqualsWithDelta(100.0, $tea['sgst'], 0.001);
        // SEZ goes to the B2B tab, exports to B2C (May: M3 P1 20 to SEZ; M2 software export).
        $may = collect($this->gstr1Page('2026-05')['hsn']);
        $this->assertSame('B2B', $may->firstWhere('hsn', '73269099')['tab']);
        $this->assertSame('B2C', $may->firstWhere('hsn', '998314')['tab']);
        $this->assertEqualsWithDelta(200000.0, $may->firstWhere('hsn', '998314')['taxable'], 0.001);
    }

    public function test_table_13_counts_each_months_serial_range_with_cancelled_ones(): void
    {
        // [first, last, total, cancelled] for invoices; credit notes [first, last, total].
        $expected = [
            '2026-04' => [['0001', '0004', 4, 0], ['CRN-0001', 'CRN-0001', 1]],
            '2026-05' => [['0005', '0009', 5, 1], null],     // M4 0008 cancelled
            '2026-06' => [['0010', '0013', 4, 0], null],
            '2026-07' => [['0014', '0016', 3, 0], ['CRN-0002', 'CRN-0003', 2]],
            '2026-08' => [['0017', '0018', 2, 0], null],     // draft G3 has no number
            '2026-09' => [['0019', '0021', 3, 0], ['CRN-0004', 'CRN-0004', 1]],
            '2026-10' => [['0022', '0024', 3, 1], ['CRN-0005', 'CRN-0005', 1]],   // O3 0024 cancelled
            '2026-11' => [['0025', '0027', 3, 0], null],
            '2026-12' => [['0028', '0029', 2, 0], ['CRN-0006', 'CRN-0006', 1]],
            '2027-01' => [['0030', '0031', 2, 0], ['CRN-0007', 'CRN-0007', 1]],
            '2027-02' => [['0032', '0034', 3, 0], ['CRN-0008', 'CRN-0008', 1]],
            '2027-03' => [['0035', '0036', 2, 0], ['CRN-0009', 'CRN-0009', 1]],
        ];
        $failures = [];
        foreach ($expected as $ym => [$inv, $cn]) {
            $docs = collect($this->gstr1Page($ym)['docs'])->keyBy('nature');
            $i = $docs['Invoices for outward supply'] ?? null;
            $want = ['SAH/26-27/' . $inv[0], 'SAH/26-27/' . $inv[1], $inv[2], $inv[3]];
            $got = $i ? [$i['from'], $i['to'], $i['total'], $i['cancelled']] : null;
            if ($got !== $want) {
                $failures[] = "{$ym} invoices: " . json_encode($got) . ' expected ' . json_encode($want);
            }
            $c = $docs['Credit Note'] ?? null;
            $gotCn = $c ? [$c['from'], $c['to'], $c['total']] : null;
            if ($gotCn !== $cn) {
                $failures[] = "{$ym} credit notes: " . json_encode($gotCn) . ' expected ' . json_encode($cn);
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));

        // The year's 36 numbers, 2 cancelled, reach the portal file as 36 / 2 / 34 net for the quarter sums.
        $q = array_sum(array_map(fn ($first) => collect($this->gstr1Page($first, true)['docs'])->firstWhere('nature', 'Invoices for outward supply')['total'], $this->quarters()));
        $this->assertSame(36, $q);
    }

    public function test_reverse_charge_sales_report_in_table_4b_the_tax_the_recipient_pays(): void
    {
        // M5 (May): security services 50,000 @18% to Desai Pharma, Maharashtra,
        // reverse charge. GSTR-1 Table 4B carries the tax the recipient must pay
        // (form GSTR-1 Table 4 has tax columns for 4B; the offline tool works it
        // out as rate x taxable from the b2b sheet): CGST = SGST = 50,000 x 9% = 4,500.
        // N2 (Nov): 40,000 @18% -> 3,600 each. The supplier's own 3B is unaffected.
        $failures = [];
        foreach (['2026-05' => ['M5', 4500.00], '2026-11' => ['N2', 3600.00]] as $ym => [$key, $head]) {
            $page = $this->gstr1Page($ym);
            $this->check($failures, "{$ym} Table 4B CGST on screen", $head, $page['tables']['b2b_rcm']['cgst']);
            $this->check($failures, "{$ym} Table 4B SGST on screen", $head, $page['tables']['b2b_rcm']['sgst']);

            $j = json_decode($this->actingAs($this->user)->get(route('finance.gstr1.export.json', ['month' => $ym]))->streamedContent(), true);
            $inv = collect(collect($j['b2b'])->firstWhere('ctin', '27AAACD3456E1Z1')['inv'])->firstWhere('inum', $this->inv[$key]->invoice_number);
            $this->assertSame('Y', $inv['rchrg']);
            $det = $inv['itms'][0]['itm_det'];
            $this->check($failures, "{$ym} JSON {$key} camt", $head, (float) $det['camt']);
            $this->check($failures, "{$ym} JSON {$key} samt", $head, (float) $det['samt']);
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
