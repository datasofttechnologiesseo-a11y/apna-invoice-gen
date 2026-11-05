<?php

namespace Tests\Feature\Audit\Gst;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unused input credit stays in the electronic credit ledger (s.49(2)) and is
 * used in later returns before any cash. GSTR-3B used to show it as "carried
 * forward, available next month" and then start the next month from nil, so a
 * month with sales and no purchases asked for the whole tax in cash.
 */
class ItcCarryForwardTest extends TestCase
{
    use BuildsGstBooks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooks('2026-10-01 10:00');
        $this->customer('local', 'MH', '27AAACL1234A1Z1', 'Local Buyer Pvt Ltd');
        $this->vendor('supplier', 'MH', '27AAACS9999B1Z2', 'Pune Supplies');
    }

    public function test_octobers_unused_credit_pays_novembers_tax(): void
    {
        // October: ₹1,00,000 bought at 18% (₹9,000 CGST + ₹9,000 SGST credit), nothing sold.
        $this->bill('supplier', '2026-10-10 11:00', 'PS/101', [$this->l('Steel sheets', '72085110', 1, 100000, 18)]);
        // November: ₹1,00,000 sold at 18% (₹9,000 + ₹9,000 tax), nothing bought.
        $this->issue('nov', 'local', '2026-11-12 11:00', [$this->l('Fabricated racks', '94032090', 1, 100000, 18)]);

        $october = $this->gstr3bPage('2026-10');
        $this->assertSame(18000.0, (float) $october['itc_carried']['total']);
        $this->assertSame(0.0, (float) $october['itc_carry_in']['total']);

        $november = $this->gstr3bPage('2026-11');
        $this->assertSame(9000.0, (float) $november['itc_carry_in']['cgst']);
        $this->assertSame(9000.0, (float) $november['itc_carry_in']['sgst']);
        $this->assertSame(0.0, (float) $november['netCash']['total'], 'November tax is paid from October credit');
        $this->assertSame(0.0, (float) $november['itc_carried']['total']);
        // Table 4 is still the period's own credit: nothing in November.
        $this->assertSame(0.0, (float) $november['itc']['total']);
    }

    public function test_credit_carries_across_several_months_and_is_used_in_part(): void
    {
        // ₹18,000 credit in October, ₹9,000 tax in each of Nov, Dec and Jan.
        $this->bill('supplier', '2026-10-10 11:00', 'PS/101', [$this->l('Steel sheets', '72085110', 1, 100000, 18)]);
        $this->issue('nov', 'local', '2026-11-12 11:00', [$this->l('Racks', '94032090', 1, 50000, 18)]);
        $this->issue('dec', 'local', '2026-12-12 11:00', [$this->l('Racks', '94032090', 1, 50000, 18)]);
        $this->issue('jan', 'local', '2027-01-12 11:00', [$this->l('Racks', '94032090', 1, 50000, 18)]);

        $nov = $this->gstr3bPage('2026-11');
        $this->assertSame(0.0, (float) $nov['netCash']['total']);
        $this->assertSame(9000.0, (float) $nov['itc_carried']['total']);

        $dec = $this->gstr3bPage('2026-12');
        $this->assertSame(9000.0, (float) $dec['itc_carry_in']['total']);
        $this->assertSame(0.0, (float) $dec['netCash']['total']);
        $this->assertSame(0.0, (float) $dec['itc_carried']['total']);

        $jan = $this->gstr3bPage('2027-01');
        $this->assertSame(0.0, (float) $jan['itc_carry_in']['total']);
        $this->assertSame(9000.0, (float) $jan['netCash']['total'], 'credit used up: January is paid in cash');
    }

    public function test_igst_credit_brought_forward_still_goes_first(): void
    {
        // October: inter-state purchase, ₹18,000 IGST credit. November: local sale, ₹9,000 + ₹9,000.
        $this->vendor('ka', 'KA', '29AAACK1111C1Z3', 'Bengaluru Metals');
        $this->bill('ka', '2026-10-10 11:00', 'BM/7', [$this->l('Copper wire', '74081190', 1, 100000, 18)]);
        $this->issue('nov', 'local', '2026-11-12 11:00', [$this->l('Panels', '85371000', 1, 100000, 18)]);

        $nov = $this->gstr3bPage('2026-11');
        $this->assertSame(18000.0, (float) $nov['itc_carry_in']['igst']);
        $this->assertSame(0.0, (float) $nov['netCash']['total'], 'IGST credit may pay CGST and SGST (Rule 88A)');
        $this->assertSame(9000.0, (float) $nov['set_off_matrix']['igst']['cgst']);
        $this->assertSame(9000.0, (float) $nov['set_off_matrix']['igst']['sgst']);
    }

    public function test_quarterly_returns_carry_credit_between_quarters(): void
    {
        $this->bill('supplier', '2026-10-10 11:00', 'PS/101', [$this->l('Steel sheets', '72085110', 1, 100000, 18)]);
        $this->issue('jan', 'local', '2027-01-12 11:00', [$this->l('Racks', '94032090', 1, 100000, 18)]);

        $q4 = $this->gstr3bPage('2027-01', true);
        $this->assertSame(18000.0, (float) $q4['itc_carry_in']['total']);
        $this->assertSame(0.0, (float) $q4['netCash']['total']);
    }

    public function test_a_change_in_an_earlier_month_is_picked_up(): void
    {
        $bill = $this->bill('supplier', '2026-10-10 11:00', 'PS/101', [$this->l('Steel sheets', '72085110', 1, 100000, 18)]);
        $this->issue('nov', 'local', '2026-11-12 11:00', [$this->l('Racks', '94032090', 1, 100000, 18)]);
        $this->assertSame(0.0, (float) $this->gstr3bPage('2026-11')['netCash']['total']);

        // The October bill turns out to be blocked credit (Section 17(5)).
        $bill->forceFill(['itc_eligible' => false])->save();

        $this->assertSame(18000.0, (float) $this->gstr3bPage('2026-11')['netCash']['total']);
    }

    public function test_the_page_and_csv_show_the_credit_brought_forward(): void
    {
        $this->bill('supplier', '2026-10-10 11:00', 'PS/101', [$this->l('Steel sheets', '72085110', 1, 100000, 18)]);
        $this->issue('nov', 'local', '2026-11-12 11:00', [$this->l('Racks', '94032090', 1, 100000, 18)]);

        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => '2026-11']))
            ->assertOk()->assertSee('ITC brought forward');
    }
}
