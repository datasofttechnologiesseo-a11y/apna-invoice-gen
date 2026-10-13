<?php

namespace Tests\Feature\Fintech\Reconciliation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Identity 2: GSTR-3B as the app computes it agrees with GSTR-1 and the
 * purchase side, month by month and per quarter, and the payment of tax
 * follows s.49(5) / Rule 88A. Hand figures from the SeedsFy2026Books ledger.
 */
class Gstr3bVsGstr1Test extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    /** 3.1(a) [taxable, IGST, CGST, SGST]: non-RCM, non-zero-rated, rate > 0, net of credit notes. */
    private const OUTWARD = [
        '2026-04' => [169800.00, 29070.00, 162.00, 162.00],      // A1+A2+A4+A5(18% part) - cnX0
        '2026-05' => [9999.99, 0.00, 900.00, 900.00],             // M1 only (M2/M3 zero-rated, M5 RCM, M4 cancelled)
        '2026-06' => [224984.81, 22016.19, 450.00, 450.00],
        '2026-07' => [12777.77, 1940.00, -80.00, -80.00],          // L1 + L2 - cnJ4 - cnM1
        '2026-08' => [17500.00, 0.00, 1087.50, 1087.50],
        '2026-09' => [24103.14, 4500.00, -51.57, -51.57],          // S4 - cnG1
        '2026-10' => [5200.00, 0.00, 340.00, 340.00],
        '2026-11' => [30999.95, 5400.00, 90.00, 90.00],            // N1 + N3 (N2 RCM)
        '2026-12' => [10000.00, 500.00, 0.00, 0.00],               // D2 (D1 export)
        '2027-01' => [12000.00, 0.00, 1080.00, 1080.00],
        // F1 4,000 + F2 50,000 + F3 1,00,000 - cnF1's taxable less its nil part (500 x 90.72/4,500 = 10.08):
        // 1,54,010.08 - cnF1 = 1,53,919.36 at 90.72 (see outward())
        '2027-02' => [154010.08, 27000.00, 225.36, 225.36],
        '2027-03' => [11000.00, -1800.00, 1890.00, 1890.00],       // Mr1 + Mr2 - cnF3
    ];

    /** 3.1(b) [taxable, IGST]: exports and SEZ, net of their credit notes. */
    private const ZERO = [
        '2026-05' => [220000.00, 3600.00],   // M2 2,00,000 LUT + M3 20,000 with IGST
        '2026-07' => [50000.00, 9000.00],    // L3
        '2026-09' => [75000.00, 0.00],       // S1 LUT
        '2026-12' => [95000.00, 17100.00],   // D1 1,00,000 / 18,000 - cnL3 5,000 / 900
        '2027-01' => [27000.00, 4860.00],    // Ja2 30,000 / 5,400 - cnJa2 3,000 / 540
    ];

    /** 3.1(c) nil rated, net. */
    private const NIL = ['2026-04' => 1000.00, '2026-09' => 1500.00, '2027-02' => 489.92];  // Feb: 500 - 10.08

    /** 3.1(d) [taxable, IGST, CGST, SGST]: purchases under reverse charge. */
    private const RCM_IN = [
        '2026-04' => [10000.00, 0.00, 250.00, 250.00],    // B3 GTA 5%
        '2026-06' => [25000.00, 0.00, 2250.00, 2250.00],  // B6 legal 18%
        '2026-09' => [8000.00, 0.00, 200.00, 200.00],     // B10
        '2027-01' => [15000.00, 0.00, 1350.00, 1350.00],  // B14
        '2027-02' => [6000.00, 0.00, 150.00, 150.00],     // B20
    ];

    /** ITC 4(A) [IGST, CGST, SGST], blocked credit (B4, B12, B18, E4, CM2) left out. */
    private const ITC = [
        '2026-04' => [3000.00, 8350.00, 8350.00],   // B1 5,400 + B3 250 + E1 2,700 per head; B2 IGST 3,000
        '2026-06' => [1800.00, 5340.00, 5340.00],   // B6 2,250 + B7 3,000 + CM1 90; E3 IGST 1,800 (B18 blocked)
        '2026-07' => [1500.00, 0.00, 0.00],         // B8 (E4 blocked)
        '2026-08' => [0.00, 2700.00, 2700.00],      // B9
        '2026-09' => [0.00, 2900.00, 2900.00],      // B10 200 + E6 2,700
        '2026-10' => [18000.00, 0.00, 0.00],        // B11
        '2026-12' => [0.00, 7200.00, 7200.00],      // B13 5,400 + E7 1,800
        '2027-01' => [0.00, 1350.00, 1350.00],      // B14
        '2027-02' => [750.00, 150.00, 150.00],      // B15, B20
        '2027-03' => [620.00, 540.00, 540.00],      // B19; B16 450 + E8 90
    ];

    /**
     * 4(B)(2) Rule 37 reversals [IGST, CGST, SGST]: credit on the part of a
     * supplier's bill still unpaid 180 days after its date, reversed in the
     * month the 181st day falls in (nothing is paid later within the year).
     */
    private const RULE37 = [
        '2026-10' => [1500.00, 0.00, 0.00],       // B2 08-04, 31,500 of 63,000 unpaid on 06-10: half of IGST 3,000
        '2026-12' => [1500.00, 0.00, 0.00],       // B8 01-07, all 31,500 unpaid on 29-12: IGST 1,500
        '2027-02' => [0.00, 1174.58, 1174.58],    // B9 15-08, 15,400 of 35,400 unpaid on 12-02: 2,700 x 15,400 / 35,400
    ];

    private function row(array $const, string $ym, int $size): array
    {
        return $const[$ym] ?? array_fill(0, $size, 0.0);
    }

    public function test_3_1_a_b_c_equal_gstr1_net_outward_every_month(): void
    {
        $failures = [];
        $cnF1 = $this->cnF1Taxable();
        foreach ($this->months() as $ym) {
            $g = $this->gstr1Page($ym);
            $t = $g['tables'];
            $d = $this->gstr3bPage($ym);

            $cdnrRegular = array_filter($g['cdnr'], fn ($r) => $r['rcm'] === 'N' && $r['inv_typ'] === 'R' && (float) $r['rate'] > 0);
            $cdnrSez = array_filter($g['cdnr'], fn ($r) => in_array($r['inv_typ'], ['SEWP', 'SEWOP'], true));
            $cdnurB2cl = array_filter($g['cdnur'], fn ($r) => $r['ur_type'] === 'B2CL');
            $cdnurExp = array_filter($g['cdnur'], fn ($r) => in_array($r['ur_type'], ['EXPWP', 'EXPWOP'], true));
            $sum = fn (array $rows, string $k) => array_sum(array_map(fn ($r) => (float) $r[$k], $rows));

            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $i => $k) {
                // 3.1(a) = 4A + 5 + 7 - 9B on them
                $fromGstr1 = round($t['b2b'][$k] + $t['b2cl'][$k] + $t['b2cs'][$k] - $sum($cdnrRegular, $k) - $sum($cdnurB2cl, $k), 2);
                $hand = $this->row(self::OUTWARD, $ym, 4)[$i] - ($ym === '2027-02' && $k === 'taxable' ? $cnF1 : 0);
                $this->check($failures, "{$ym} 3.1(a) {$k} (hand)", round($hand, 2), (float) $d['outward'][$k]);
                $this->check($failures, "{$ym} 3.1(a) {$k} vs GSTR-1 tables", (float) $d['outward'][$k], $fromGstr1);
            }
            foreach (['taxable', 'igst'] as $i => $k) {
                // 3.1(b) = 6A + 6B - 9B on them
                $fromGstr1 = round($t['exp'][$k] + $t['sez'][$k] - $sum($cdnrSez, $k) - $sum($cdnurExp, $k), 2);
                $this->check($failures, "{$ym} 3.1(b) {$k} (hand)", $this->row(self::ZERO, $ym, 2)[$i], (float) $d['outward_zero'][$k]);
                $this->check($failures, "{$ym} 3.1(b) {$k} vs GSTR-1 tables", (float) $d['outward_zero'][$k], $fromGstr1);
            }
            // 3.1(c) = Table 8
            $this->check($failures, "{$ym} 3.1(c) (hand)", self::NIL[$ym] ?? 0.0, (float) $d['outward_nil']);
            $this->check($failures, "{$ym} 3.1(c) vs Table 8", (float) $d['outward_nil'], (float) $t['exemp']['taxable']);
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_3_1_d_is_the_reverse_charge_purchases_of_the_month(): void
    {
        $failures = [];
        foreach ($this->months() as $ym) {
            $d = $this->gstr3bPage($ym);
            [$from, $to] = $this->monthRange($ym);
            $rows = array_filter($this->report('purchase-register', ['from' => $from, 'to' => Carbon::parse($to)->addDay()->toDateString()])['rows'],
                fn ($r) => $r['rcm'] === 'Yes' && $r['date']->format('Y-m-d') <= $to);
            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $i => $k) {
                $this->check($failures, "{$ym} 3.1(d) {$k} (hand)", $this->row(self::RCM_IN, $ym, 4)[$i], (float) $d['rcm_inward'][$k]);
                $this->check($failures, "{$ym} 3.1(d) {$k} vs purchase register", round(array_sum(array_column($rows, $k)), 2), (float) $d['rcm_inward'][$k]);
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_input_credit_splits_into_4a3_and_4a5_and_leaves_out_blocked_credit(): void
    {
        $failures = [];
        foreach ($this->months() as $ym) {
            $d = $this->gstr3bPage($ym);
            $itc = $this->row(self::ITC, $ym, 3);
            $r37 = $this->row(self::RULE37, $ym, 3);
            $rcm = $this->row(self::RCM_IN, $ym, 4);   // every RCM bill here is eligible: 4(A)(3) = 3.1(d) tax
            foreach (['igst', 'cgst', 'sgst'] as $i => $k) {
                // 4(C) net credit = 4(A) less the Rule 37 reversal of 4(B)(2).
                $this->check($failures, "{$ym} ITC {$k} (hand)", round($itc[$i] - $r37[$i], 2), (float) $d['itc'][$k]);
                $this->check($failures, "{$ym} 4(B)(2) {$k} (hand)", $r37[$i], (float) $d['itc_reversed'][$k]);
                $this->check($failures, "{$ym} 4(A)(3) {$k} (hand)", $rcm[$i + 1], (float) $d['itc_rcm'][$k]);
                $this->check($failures, "{$ym} 4(A)(5) {$k} (hand)", $itc[$i] - $rcm[$i + 1], (float) $d['itc_other'][$k]);
            }
            // The Expenses screen's "ITC claimable" for the month is the credit
            // booked, 4(A) (bills, cash memos and expenses all post there; blocked
            // ones are flagged). Rule 37 is a later reversal on the return.
            [$from, $to] = $this->monthRange($ym);
            $exp = $this->actingAs($this->user)->get(route('finance.expenses', ['period' => 'custom', 'from' => $from, 'to' => $to]))->viewData('summary');
            $booked = round((float) $d['itc']['total'] + (float) $d['itc_reversed']['total'] - (float) $d['itc_reclaimed']['total'], 2);
            $this->check($failures, "{$ym} ITC 4(A) vs Expenses 'ITC claimable'", $booked, (float) $exp['itc_claimable']);
        }
        $this->assertSame([], $failures, implode("\n", $failures));

        // FY: 25,670 + 28,530 + 28,530 = 82,730 booked; blocked 500 + 3,600 + 9,000 + 150 + 25 = 13,275
        // never counted; Rule 37 reverses 1,500 + 1,500 + 1,174.58 x 2 = 5,349.16, so 4(C) is 77,380.84.
        $fy = array_sum(array_map(fn ($q) => $this->gstr3bPage($q, true)['itc']['total'], $this->quarters()));
        $this->assertEqualsWithDelta(77380.84, $fy, 0.001);
    }

    public function test_a_quarter_is_the_sum_of_its_three_months(): void
    {
        // Quarter figures by hand (sums of the month tables above).
        $hand = [
            'Q1' => ['outward' => [404784.80, 51086.19, 1512.00, 1512.00], 'zero' => [220000.00, 3600.00], 'nil' => 1000.00,
                'rcm' => [35000.00, 0.00, 2500.00, 2500.00], 'itc' => [4800.00, 13690.00, 13690.00]],
            'Q2' => ['outward' => [54380.91, 6440.00, 955.93, 955.93], 'zero' => [125000.00, 9000.00], 'nil' => 1500.00,
                'rcm' => [8000.00, 0.00, 200.00, 200.00], 'itc' => [1500.00, 5600.00, 5600.00]],
            'Q3' => ['outward' => [46199.95, 5900.00, 430.00, 430.00], 'zero' => [95000.00, 17100.00], 'nil' => 0.00,
                'rcm' => [0.00, 0.00, 0.00, 0.00], 'itc' => [15000.00, 7200.00, 7200.00]],   // IGST 18,000 less Rule 37 1,500 + 1,500
            'Q4' => ['outward' => [177010.08, 25200.00, 3195.36, 3195.36], 'zero' => [27000.00, 4860.00], 'nil' => 489.92,
                'rcm' => [21000.00, 0.00, 1500.00, 1500.00], 'itc' => [1370.00, 865.42, 865.42]],   // C/S 2,040 less Rule 37 1,174.58
        ];
        $hand['Q4']['outward'][0] = round($hand['Q4']['outward'][0] - $this->cnF1Taxable(), 2);   // 1,76,919.36 at 90.72
        $failures = [];
        foreach ($this->quarters() as $q => $first) {
            $quarter = $this->gstr3bPage($first, true);
            $start = Carbon::createFromFormat('Y-m-d', $first . '-01');
            $months = [];
            for ($i = 0; $i < 3; $i++) {
                $months[] = $this->gstr3bPage($start->copy()->addMonthsNoOverflow($i)->format('Y-m'));
            }
            $sum = fn (string $block, string $k) => round(array_sum(array_map(fn ($m) => (float) (is_array($m[$block]) ? $m[$block][$k] : $m[$block]), $months)), 2);

            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $i => $k) {
                $this->check($failures, "{$q} 3.1(a) {$k} (hand)", $hand[$q]['outward'][$i], (float) $quarter['outward'][$k]);
                $this->check($failures, "{$q} 3.1(a) {$k} = sum of months", $sum('outward', $k), (float) $quarter['outward'][$k]);
                $this->check($failures, "{$q} 3.1(d) {$k} (hand)", $hand[$q]['rcm'][$i], (float) $quarter['rcm_inward'][$k]);
                $this->check($failures, "{$q} 3.1(d) {$k} = sum of months", $sum('rcm_inward', $k), (float) $quarter['rcm_inward'][$k]);
            }
            foreach (['taxable', 'igst'] as $i => $k) {
                $this->check($failures, "{$q} 3.1(b) {$k} (hand)", $hand[$q]['zero'][$i], (float) $quarter['outward_zero'][$k]);
                $this->check($failures, "{$q} 3.1(b) {$k} = sum of months", $sum('outward_zero', $k), (float) $quarter['outward_zero'][$k]);
            }
            $this->check($failures, "{$q} 3.1(c) (hand)", $hand[$q]['nil'], (float) $quarter['outward_nil']);
            $this->check($failures, "{$q} 3.1(c) = sum of months", $sum('outward_nil', ''), (float) $quarter['outward_nil']);
            foreach (['igst', 'cgst', 'sgst'] as $i => $k) {
                $this->check($failures, "{$q} ITC {$k} (hand)", $hand[$q]['itc'][$i], (float) $quarter['itc'][$k]);
                foreach (['itc', 'itc_rcm', 'itc_other', 'liability'] as $block) {
                    $this->check($failures, "{$q} {$block} {$k} = sum of months", $sum($block, $k), (float) $quarter[$block][$k]);
                }
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_tax_is_paid_in_the_rule_88a_order_and_cash_plus_credit_meets_each_head(): void
    {
        // Set-off by hand for each quarter and for April (liability = 3.1(a) + 3.1(b) tax):
        //  Q1 L I54,686.19 C1,512 S1,512 | ITC I4,800 C13,690 S13,690: I->I 4,800; C->C 1,512, S->S 1,512;
        //     C->I 12,178, S->I 12,178 -> IGST cash 25,530.19; + RCM C/S 2,500 -> 30,530.19; nothing carried.
        //  Q2 L I15,440 C/S 955.93 | ITC I1,500 C/S 5,600: I->I 1,500; C->C, S->S 955.93; C->I and
        //     S->I 4,644.07 each -> IGST cash 4,651.86; + RCM 200/200 -> 5,051.86.
        //  Q3 L I23,000 C/S 430 | ITC I15,000 (18,000 less Rule 37 3,000) C/S 7,200: I->I 15,000;
        //     C->C, S->S 430; C->I 6,770, S->I 1,230 -> no cash; carried C 0, S 5,540.
        //  Q4 L I30,060 C/S 3,195.36 | ITC I1,370 C/S 865.42 (2,040 less Rule 37 1,174.58): I->I 1,370;
        //     C->C, S->S 865.42 -> cash I28,690, C/S 2,329.94; + RCM 1,500/1,500 -> 36,349.88.
        //  Apr L I29,070 C/S 162 | ITC I3,000 C/S 8,350: I->I 3,000; C->C, S->S 162; C->I, S->I
        //     8,188 each -> IGST cash 9,694; + RCM 250/250 -> 10,194.
        $hand = [
            'Q1' => ['cash' => [25530.19, 2500.00, 2500.00, 30530.19], 'used' => [29156.00, 1512.00, 1512.00], 'carried' => [0, 0, 0]],
            'Q2' => ['cash' => [4651.86, 200.00, 200.00, 5051.86], 'used' => [10788.14, 955.93, 955.93], 'carried' => [0, 0, 0]],
            'Q3' => ['cash' => [0, 0, 0, 0], 'used' => [23000.00, 430.00, 430.00], 'carried' => [0, 0, 5540.00]],
            'Q4' => ['cash' => [28690.00, 3829.94, 3829.94, 36349.88], 'used' => [1370.00, 865.42, 865.42], 'carried' => [0, 0, 0]],
            'Apr' => ['cash' => [9694.00, 250.00, 250.00, 10194.00], 'used' => [19376.00, 162.00, 162.00], 'carried' => [0, 0, 0]],
        ];
        $periods = [];
        foreach ($this->quarters() as $q => $first) {
            $periods[$q] = $this->gstr3bPage($first, true);
        }
        $periods['Apr'] = $this->gstr3bPage('2026-04');
        foreach ($this->months() as $ym) {
            $periods[$ym] ??= $this->gstr3bPage($ym);
        }

        $failures = [];
        foreach ($periods as $label => $d) {
            $heads = ['igst', 'cgst', 'sgst'];
            if (isset($hand[$label])) {
                foreach ($heads as $i => $h) {
                    $this->check($failures, "{$label} cash {$h} (hand)", $hand[$label]['cash'][$i], (float) $d['netCash'][$h]);
                    $this->check($failures, "{$label} credit used on {$h} (hand)", $hand[$label]['used'][$i], (float) $d['itc_used'][$h]);
                    $this->check($failures, "{$label} carried {$h} (hand)", $hand[$label]['carried'][$i], (float) $d['itc_carried'][$h]);
                }
                $this->check($failures, "{$label} cash total (hand)", $hand[$label]['cash'][3], (float) $d['netCash']['total']);
            }
            $m = $d['setOffMatrix'];
            foreach ($heads as $h) {
                // Cash on outward supplies + credit used = the liability of that head
                // after any negative liability brought forward (never below nil).
                $cashOutward = (float) $d['netCash'][$h] - (float) $d['rcm_inward'][$h];
                $payable = round((float) $d['liability'][$h] + (float) $d['negative_carry_in'][$h], 2);
                $this->check($failures, "{$label} {$h}: payable = liability + negative carried in", $payable, (float) $d['payable'][$h]);
                // Where Rule 37 takes the net credit below nil, the shortfall is paid too.
                $shortfall = max(0.0, -(float) $d['itc'][$h]);
                $this->check($failures, "{$label} {$h}: cash + credit = payable", round(max(0.0, $payable) + $shortfall, 2), round($cashOutward + (float) $d['itc_used'][$h], 2));
                // Which credit paid which tax adds up both ways.
                $this->check($failures, "{$label} matrix column {$h} = credit used on it", (float) $d['itc_used'][$h], $m['igst'][$h] + $m['cgst'][$h] + $m['sgst'][$h]);
                $this->check($failures, "{$label} matrix row {$h} = credit taken less carried", max(0.0, (float) $d['itc'][$h]) - (float) $d['itc_carried'][$h], array_sum($m[$h]));
            }
            // CGST credit never pays SGST, nor SGST credit CGST.
            $this->check($failures, "{$label} CGST credit used for SGST", 0, $m['cgst']['sgst']);
            $this->check($failures, "{$label} SGST credit used for CGST", 0, $m['sgst']['cgst']);
            // IGST credit is exhausted before any CGST/SGST credit is touched.
            if (array_sum($m['cgst']) + array_sum($m['sgst']) > 0) {
                $this->check($failures, "{$label} IGST credit left while CGST/SGST credit used", 0, (float) $d['itc_carried']['igst']);
            }
            // State/central credit goes to IGST only after its own head is paid.
            foreach (['cgst', 'sgst'] as $h) {
                if ($m[$h]['igst'] > 0) {
                    $this->check($failures, "{$label} {$h} credit used for IGST with own head unpaid", 0, (float) $d['netCash'][$h] - (float) $d['rcm_inward'][$h]);
                }
            }
            // Reverse charge on purchases is paid in cash, never from credit.
            foreach ($heads as $h) {
                if ((float) $d['rcm_inward'][$h] > 0) {
                    $this->assertGreaterThanOrEqual((float) $d['rcm_inward'][$h] - 0.001, (float) $d['netCash'][$h], "{$label} RCM {$h} in cash");
                }
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_twelve_monthly_returns_discharge_the_net_tax_of_the_year(): void
    {
        // Credit notes reduce output tax of the month they are issued in (s.34(2)).
        // Where a month's notes exceed its sales in a head (July CGST/SGST -80,
        // September -51.57 each, March IGST -1,800), the excess has to come off a
        // later month (GSTR-3B negative liability), not vanish. Over the year the
        // twelve monthly 3Bs must therefore discharge the year's net output tax:
        // IGST 1,23,186.19 + CGST 6,093.29 + SGST 6,093.29 = 1,35,372.77 (3.1(a)+(b)
        // tax, FY, from the hand ledger) - except March's IGST excess of 1,800,
        // which has no later month in the year and comes off April 2027 instead.
        $discharged = ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
        foreach ($this->months() as $ym) {
            $d = $this->gstr3bPage($ym);
            foreach ($discharged as $h => $v) {
                // credit used + cash on outward supplies (reverse charge on purchases, and
                // cash paid for a Rule 37 reversal larger than the month's credit, excluded)
                $discharged[$h] += (float) $d['itc_used'][$h] + (float) $d['netCash'][$h] - (float) $d['rcm_inward'][$h]
                    - max(0.0, -(float) $d['itc'][$h]);
            }
        }
        $failures = [];
        $this->check($failures, 'IGST discharged over 12 months', 123186.19 + 1800.00, $discharged['igst']);
        $this->check($failures, 'CGST discharged over 12 months', 6093.29, $discharged['cgst']);
        $this->check($failures, 'SGST discharged over 12 months', 6093.29, $discharged['sgst']);
        $march = $this->gstr3bPage('2027-03');
        $april = $this->gstr3bPage('2027-04');
        $this->check($failures, 'March IGST negative carried out', -1800.00, (float) $march['negative_carry_out']['igst']);
        $this->check($failures, 'April 2027 IGST negative carried in', -1800.00, (float) $april['negative_carry_in']['igst']);
        // July's -80 CGST comes off August; September's -51.57 off October.
        $this->check($failures, 'August CGST negative carried in', -80.00, (float) $this->gstr3bPage('2026-08')['negative_carry_in']['cgst']);
        $this->check($failures, 'October SGST negative carried in', -51.57, (float) $this->gstr3bPage('2026-10')['negative_carry_in']['sgst']);
        // A quarterly (QRMP) return nets its own months first: Q2's CGST is
        // 955.93 overall, so nothing carries into Q3.
        $this->check($failures, 'Q3 CGST negative carried in (quarterly)', 0.00, (float) $this->gstr3bPage('2026-10', true)['negative_carry_in']['cgst']);
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_negative_liability_is_shown_on_the_screen_pdf_csv_and_api(): void
    {
        // July leaves CGST/SGST -80 (shown as carried out); August brings it in.
        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => '2026-07']))
            ->assertOk()->assertSee('Negative liability carried forward');
        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => '2026-08']))
            ->assertOk()->assertSee('Less: negative liability from earlier periods');
        $csv = $this->actingAs($this->user)->get(route('finance.gstr3b.export.csv', ['month' => '2026-08']))->streamedContent();
        $this->assertStringContainsString('Negative liability from earlier periods', $csv);
        $this->actingAs($this->user)->get(route('finance.gstr3b.export.pdf', ['month' => '2026-08']))->assertOk();
        // A month with nothing carried shows neither row.
        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => '2026-05']))
            ->assertOk()
            ->assertDontSee('Less: negative liability from earlier periods')
            ->assertDontSee('Negative liability carried forward');

        // The API adds the same figures alongside its existing keys.
        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $api = $this->getJson('/api/finance/gstr3b?month=2026-08')->assertOk();
        $this->assertEqualsWithDelta(-80.00, (float) $api->json('negative_liability_in.cgst'), 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $api->json('negative_liability_out.cgst'), 0.001);
        $this->assertNotNull($api->json('net_cash.total'));
    }
}
