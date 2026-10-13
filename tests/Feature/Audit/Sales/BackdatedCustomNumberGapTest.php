<?php

namespace Tests\Feature\Audit\Sales;

use Illuminate\Support\Carbon;

/**
 * Rule 46(b): a consecutive series, unique for the financial year. With an
 * FY-wise series ("INV/{FY}/{N}"), a late bill for last March carrying last
 * year's number (INV/2025-26/0151) must not move THIS year's counter.
 * Company::learnSeriesFrom() takes the trailing digits of any custom number
 * and stamps them on the current FY's counter, so the next ordinary invoice
 * jumps from 0010 to 0152: 141 missing numbers in the 2026-27 series that
 * GSTR-1 Table 13 cannot explain.
 */
class BackdatedCustomNumberGapTest extends SalesAuditCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 11:00:00');
        $this->company->forceFill([
            'invoice_number_format' => 'INV/{FY}/{N}', 'invoice_number_padding' => 4,
            'invoice_counter' => 10, 'invoice_counter_fy' => 2026,   // INV/2026-27/0001..0010 issued
        ])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_last_years_custom_number_does_not_move_this_years_counter(): void
    {
        $line = [['description' => 'Item', 'hsn_sac' => '8471', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]];

        // Late bill for 31 March 2026 (FY 2025-26), numbered in that year's series.
        $late = $this->issue($this->draft($line, null, ['invoice_date' => '2026-03-31', 'invoice_number' => 'INV/2025-26/0151']));
        $this->assertSame('INV/2025-26/0151', $late->invoice_number);
        $this->assertNotSame('draft', $late->status);

        // Next ordinary invoice today, FY 2026-27.
        $next = $this->issue($this->draft($line));

        $this->assertSame('INV/2026-27/0011', $next->invoice_number, 'the 2026-27 series must continue at 0011');
    }
}
