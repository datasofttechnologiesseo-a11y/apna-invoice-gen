<?php

namespace Tests\Unit;

use App\Services\GstSetOff;
use PHPUnit\Framework\TestCase;

class GstSetOffTest extends TestCase
{
    private function settle(array $liability, array $credit): array
    {
        return (new GstSetOff())->settle($liability, $credit);
    }

    public function test_igst_credit_pays_cgst_and_sgst_when_there_is_no_igst_liability(): void
    {
        // The case the old per-head max(0, …) got wrong: ₹18,000 payable shown
        // while ₹18,000 of IGST credit sat unused.
        $r = $this->settle(['igst' => 0, 'cgst' => 9000, 'sgst' => 9000], ['igst' => 18000, 'cgst' => 0, 'sgst' => 0]);

        $this->assertSame(0.0, $r['cash']['total']);
        $this->assertSame(0.0, $r['carried']['total']);
    }

    public function test_igst_credit_goes_to_igst_first(): void
    {
        $r = $this->settle(['igst' => 5000, 'cgst' => 2000, 'sgst' => 2000], ['igst' => 6000, 'cgst' => 0, 'sgst' => 0]);

        $this->assertSame(0.0, $r['cash']['igst']);
        $this->assertSame(1000.0, $r['cash']['cgst']); // 1,000 of IGST credit left after IGST, used on CGST
        $this->assertSame(2000.0, $r['cash']['sgst']);
        $this->assertSame(3000.0, $r['cash']['total']);
    }

    public function test_leftover_igst_credit_is_split_so_no_cash_is_paid_while_credit_waits(): void
    {
        // CGST 9k + SGST 9k due; credit IGST 9k + CGST 4.5k + SGST 4.5k. All of
        // the IGST on CGST would leave 4,500 SGST in cash and 4,500 CGST credit
        // idle. Split by shortfall, nothing is payable.
        $r = $this->settle(['igst' => 0, 'cgst' => 9000, 'sgst' => 9000], ['igst' => 9000, 'cgst' => 4500, 'sgst' => 4500]);

        $this->assertSame(0.0, $r['cash']['total']);
        $this->assertSame(0.0, $r['carried']['total']);
        $this->assertSame(['igst' => 0.0, 'cgst' => 9000.0, 'sgst' => 9000.0], $r['used']);
    }

    public function test_cgst_credit_never_pays_sgst(): void
    {
        $r = $this->settle(['igst' => 0, 'cgst' => 0, 'sgst' => 5000], ['igst' => 0, 'cgst' => 5000, 'sgst' => 0]);

        $this->assertSame(5000.0, $r['cash']['sgst']);
        $this->assertSame(5000.0, $r['carried']['cgst']);
    }

    public function test_cgst_and_sgst_credit_can_pay_igst(): void
    {
        $r = $this->settle(['igst' => 4000, 'cgst' => 0, 'sgst' => 0], ['igst' => 0, 'cgst' => 1500, 'sgst' => 1500]);

        $this->assertSame(1000.0, $r['cash']['igst']);
        $this->assertSame(0.0, $r['carried']['total']);
    }

    public function test_own_head_is_used_before_igst_for_state_and_central_credit(): void
    {
        $r = $this->settle(['igst' => 1000, 'cgst' => 1000, 'sgst' => 1000], ['igst' => 0, 'cgst' => 1500, 'sgst' => 500]);

        $this->assertSame(0.0, $r['cash']['cgst']);    // 1,000 of CGST credit on CGST
        $this->assertSame(500.0, $r['cash']['sgst']);  // SGST credit short by 500
        $this->assertSame(500.0, $r['cash']['igst']);  // remaining 500 CGST credit on IGST
        $this->assertSame(0.0, $r['carried']['total']);
    }

    public function test_paise_are_handled_without_drift(): void
    {
        $r = $this->settle(['igst' => 0.03, 'cgst' => 0.02, 'sgst' => 0.02], ['igst' => 0.05, 'cgst' => 0, 'sgst' => 0]);

        $this->assertSame(0.02, $r['cash']['total']);
        $this->assertSame(0.0, $r['carried']['total']);
    }
}
