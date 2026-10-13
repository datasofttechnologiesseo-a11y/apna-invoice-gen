<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Amounts show without ".00": screens in whole rupees, documents with paise
 * only where the paise are real. Values themselves are never changed.
 */
class MoneyFormatTest extends TestCase
{
    public function test_documents_drop_dot_zero_but_keep_real_paise(): void
    {
        $this->assertSame('1,18,000', inr(118000));
        $this->assertSame('99,153', inr('99153.00'));
        $this->assertSame('4,461.89', inr(4461.89));
        $this->assertSame('1,180.50', inr(1180.5));
        $this->assertSame('-12,345.50', inr(-12345.5));
        $this->assertSame('0', inr(0));
    }

    public function test_screens_show_whole_rupees_rounded_not_cut(): void
    {
        $this->assertSame('1,18,000', rupees(118000.4));
        $this->assertSame('1,181', rupees(1180.5));
        $this->assertSame('1,181', rupees(1180.99));
        $this->assertSame('-12,34,568', rupees(-1234567.89));
        $this->assertSame('0', rupees(-0.004), 'no "-0"');
    }

    public function test_a_float_sum_just_under_a_rupee_is_not_shown_a_rupee_short(): void
    {
        $sum = 33.33 + 33.33 + 33.34; // 100.00000000000001 or 99.99999999999999
        $this->assertSame('100', inr($sum));
        $this->assertSame('100', rupees(99.99999999));
    }

    public function test_input_boxes_get_plain_numbers(): void
    {
        $this->assertSame('58500', plain_amount('58500.00'));
        $this->assertSame('49576.27', plain_amount('49576.27'));
        $this->assertSame('1180.50', plain_amount(1180.5));
        $this->assertSame('', plain_amount(null));
        $this->assertSame('12a', plain_amount('12a'), 'what the user typed comes back as typed');
    }
}
