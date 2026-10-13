<?php

namespace Tests\Feature\Fintech\Money;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every GST slab the app offers, issued through the real store and finalize
 * routes, intra-state (CGST + SGST, each at half the rate, each rounded to the
 * paisa as the NIC e-invoice schema does) and inter-state (IGST).
 *
 * Taxable value is ₹1,234.56 throughout so every slab exercises paise
 * rounding. Each row was worked out by hand:
 *   intra head = round(1,234.56 x rate/2 %, 2)   inter IGST = round(1,234.56 x rate %, 2)
 *   grand total = round(1,234.56 + tax) to the rupee; round off = grand - raw.
 */
class InvoiceGstSlabTest extends TestCase
{
    use MoneyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootMoney('27'); // Maharashtra supplier
    }

    /**
     * [rate, CGST = SGST, intra grand, intra round off, IGST, inter grand, inter round off]
     */
    public static function slabs(): array
    {
        return [
            // 0%: no tax; 1,234.56 -> 1,235 (+0.44) both ways
            '0%' => [0, 0.00, 1235, 0.44, 0.00, 1235, 0.44],
            // 0.1%: 0.05% = 0.61728 -> 0.62 each, tax 1.24, raw 1,235.80 -> 1,236 (+0.20)
            //       IGST 1.23456 -> 1.23, raw 1,235.79 -> 1,236 (+0.21)
            '0.1%' => [0.1, 0.62, 1236, 0.20, 1.23, 1236, 0.21],
            // 0.25%: 0.125% = 1.5432 -> 1.54 each, tax 3.08, raw 1,237.64 -> 1,238 (+0.36)
            //        IGST 3.0864 -> 3.09, raw 1,237.65 -> 1,238 (+0.35)
            '0.25%' => [0.25, 1.54, 1238, 0.36, 3.09, 1238, 0.35],
            // 1%: 0.5% = 6.1728 -> 6.17 each, tax 12.34, raw 1,246.90 -> 1,247 (+0.10)
            //     IGST 12.3456 -> 12.35, raw 1,246.91 -> 1,247 (+0.09)
            '1%' => [1, 6.17, 1247, 0.10, 12.35, 1247, 0.09],
            // 1.5%: 0.75% = 9.2592 -> 9.26 each, tax 18.52, raw 1,253.08 -> 1,253 (-0.08)
            //       IGST 18.5184 -> 18.52, raw 1,253.08 -> 1,253 (-0.08)
            '1.5%' => [1.5, 9.26, 1253, -0.08, 18.52, 1253, -0.08],
            // 3%: 1.5% = 18.5184 -> 18.52 each, tax 37.04, raw 1,271.60 -> 1,272 (+0.40)
            //     IGST 37.0368 -> 37.04, raw 1,271.60 -> 1,272 (+0.40)
            '3%' => [3, 18.52, 1272, 0.40, 37.04, 1272, 0.40],
            // 5%: 2.5% = 30.864 -> 30.86 each, tax 61.72, raw 1,296.28 -> 1,296 (-0.28)
            //     IGST 61.728 -> 61.73, raw 1,296.29 -> 1,296 (-0.29)
            '5%' => [5, 30.86, 1296, -0.28, 61.73, 1296, -0.29],
            // 7.5%: 3.75% = 46.296 -> 46.30 each, tax 92.60, raw 1,327.16 -> 1,327 (-0.16)
            //       IGST 92.592 -> 92.59, raw 1,327.15 -> 1,327 (-0.15)
            '7.5%' => [7.5, 46.30, 1327, -0.16, 92.59, 1327, -0.15],
            // 12% (legacy): 6% = 74.0736 -> 74.07 each, tax 148.14, raw 1,382.70 -> 1,383 (+0.30)
            //               IGST 148.1472 -> 148.15, raw 1,382.71 -> 1,383 (+0.29)
            '12%' => [12, 74.07, 1383, 0.30, 148.15, 1383, 0.29],
            // 18%: 9% = 111.1104 -> 111.11 each, tax 222.22, raw 1,456.78 -> 1,457 (+0.22)
            //      IGST 222.2208 -> 222.22, raw 1,456.78 -> 1,457 (+0.22)
            '18%' => [18, 111.11, 1457, 0.22, 222.22, 1457, 0.22],
            // 28% (legacy): 14% = 172.8384 -> 172.84 each, tax 345.68, raw 1,580.24 -> 1,580 (-0.24)
            //               IGST 345.6768 -> 345.68, raw 1,580.24 -> 1,580 (-0.24)
            '28%' => [28, 172.84, 1580, -0.24, 345.68, 1580, -0.24],
            // 40% (GST 2.0): 20% = 246.912 -> 246.91 each, tax 493.82, raw 1,728.38 -> 1,728 (-0.38)
            //                IGST 493.824 -> 493.82, raw 1,728.38 -> 1,728 (-0.38)
            '40%' => [40, 246.91, 1728, -0.38, 493.82, 1728, -0.38],
        ];
    }

    #[DataProvider('slabs')]
    public function test_each_slab_splits_cgst_and_sgst_at_half_the_rate_within_a_state(
        float $rate, float $half, float $grand, float $roundOff, float $igst, float $interGrand, float $interRoundOff
    ): void {
        $inv = $this->issue([$this->line(1234.56, $rate)], $this->customerIn('27'));

        $this->assertFalse((bool) $inv->is_interstate);
        $this->assertMoney(1234.56, $inv->subtotal);
        $this->assertMoney($half, $inv->total_cgst, "CGST at {$rate}%");
        $this->assertMoney($half, $inv->total_sgst, "SGST at {$rate}%");
        $this->assertMoney(0.00, $inv->total_igst);
        $this->assertMoney(round(2 * $half, 2), $inv->total_tax);
        $this->assertMoney($roundOff, $inv->round_off, "round off at {$rate}%");
        $this->assertMoney($grand, $inv->grand_total, "grand total at {$rate}%");
        $this->assertMoney($grand, $inv->balance, 'nothing paid: the balance is the grand total');
        $this->assertMoney($rate, $inv->items->first()->gst_rate, 'the rate is kept on the line');
        $this->assertInvoiceAddsUp($inv);
    }

    #[DataProvider('slabs')]
    public function test_each_slab_charges_igst_between_states(
        float $rate, float $half, float $grand, float $roundOff, float $igst, float $interGrand, float $interRoundOff
    ): void {
        $inv = $this->issue([$this->line(1234.56, $rate)], $this->customerIn('29')); // Karnataka

        $this->assertTrue((bool) $inv->is_interstate);
        $this->assertMoney(0.00, $inv->total_cgst);
        $this->assertMoney(0.00, $inv->total_sgst);
        $this->assertMoney($igst, $inv->total_igst, "IGST at {$rate}%");
        $this->assertMoney($interRoundOff, $inv->round_off, "round off at {$rate}%");
        $this->assertMoney($interGrand, $inv->grand_total, "grand total at {$rate}%");
        $this->assertInvoiceAddsUp($inv);
    }

    public function test_a_rate_that_is_not_a_gst_slab_is_refused(): void
    {
        // 10% and 2% are not notified slabs; a typo must not reach a tax invoice.
        foreach ([10, 2, -18, 100] as $bad) {
            $this->actingAs($this->user)->post(route('invoices.store'), [
                'customer_id' => $this->customerIn('27')->id,
                'invoice_date' => now()->toDateString(),
                'items' => [$this->line(1000, $bad)],
            ])->assertSessionHasErrors('items.0.gst_rate');
        }
        $this->assertSame(0, \App\Models\Invoice::count());
    }

    public function test_odd_paise_heads_are_each_rounded_and_the_line_tax_is_their_sum(): void
    {
        // Line 1: 1 x 1,250.55 @ 18%. 9% = 112.5495 -> CGST 112.55, SGST 112.55.
        //         Line tax 225.10 (= round(18% x 1,250.55 = 225.099) as well).
        // Line 2: 1 x 333.33 @ 5%. 2.5% = 8.33325 -> CGST 8.33, SGST 8.33.
        //         Line tax 16.66. Note round(5% x 333.33 = 16.6665) would be 16.67:
        //         heads rounded separately (the e-invoice rule) are one paisa
        //         lower here, and the invoice must use the heads' sum, never
        //         the combined figure, so CGST = SGST and they add to the tax.
        // Subtotal 1,583.88; CGST 120.88; SGST 120.88; tax 241.76;
        // raw 1,825.64 -> 1,826, round off +0.36.
        $inv = $this->issue([$this->line(1250.55, 18), $this->line(333.33, 5)], $this->customerIn('27'));

        [$a, $b] = $inv->items->values();
        $this->assertMoney(112.55, $a->cgst_amount);
        $this->assertMoney(112.55, $a->sgst_amount);
        $this->assertMoney(1475.65, $a->total);
        $this->assertMoney(8.33, $b->cgst_amount);
        $this->assertMoney(8.33, $b->sgst_amount);
        $this->assertMoney(349.99, $b->total);

        $this->assertMoney(1583.88, $inv->subtotal);
        $this->assertMoney(120.88, $inv->total_cgst);
        $this->assertMoney(120.88, $inv->total_sgst);
        $this->assertMoney(241.76, $inv->total_tax);
        $this->assertMoney(0.36, $inv->round_off);
        $this->assertMoney(1826.00, $inv->grand_total);
        $this->assertInvoiceAddsUp($inv);
    }

    public function test_odd_paise_igst_is_rounded_per_line(): void
    {
        // 1,250.55 @ 18% IGST = 225.099 -> 225.10; 333.33 @ 5% = 16.6665 -> 16.67.
        // Subtotal 1,583.88; IGST 241.77; raw 1,825.65 -> 1,826, round off +0.35.
        $inv = $this->issue([$this->line(1250.55, 18), $this->line(333.33, 5)], $this->customerIn('29'));

        [$a, $b] = $inv->items->values();
        $this->assertMoney(225.10, $a->igst_amount);
        $this->assertMoney(16.67, $b->igst_amount);
        $this->assertMoney(241.77, $inv->total_igst);
        $this->assertMoney(0.35, $inv->round_off);
        $this->assertMoney(1826.00, $inv->grand_total);
        $this->assertInvoiceAddsUp($inv);
    }

    /** UTs without a legislature levy UTGST (UTGST Act s.2(8)); amounts are as SGST. */
    public static function unionTerritories(): array
    {
        return [
            'Chandigarh' => ['04'],
            'Dadra & Nagar Haveli and Daman & Diu' => ['26'],
            'Lakshadweep' => ['31'],
            'Andaman & Nicobar' => ['35'],
            'Ladakh' => ['38'],
        ];
    }

    #[DataProvider('unionTerritories')]
    public function test_a_ut_without_a_legislature_prints_utgst_with_the_same_amounts(string $code): void
    {
        $this->company->forceFill(['state_id' => $this->state($code)->id, 'gstin' => $code . 'ABCDE1234F1Z5'])->save();

        // 1 x 1,250.55 @ 18%: CGST 112.55 + UTGST 112.55; raw 1,475.65 -> 1,476 (+0.35).
        $inv = $this->issue([$this->line(1250.55, 18)], $this->customerIn($code));

        $this->assertFalse((bool) $inv->is_interstate);
        $this->assertMoney(112.55, $inv->total_cgst);
        $this->assertMoney(112.55, $inv->total_sgst, 'the UTGST amount is stored in the state-share column');
        $this->assertMoney(1476.00, $inv->grand_total);
        $this->assertSame('UTGST', $inv->stateTaxLabel());

        $pdf = $this->invoicePdfText($inv);
        $this->assertMatchesRegularExpression('/UTGST ₹\s*112\.55/u', $pdf);
        $this->assertStringNotContainsString('SGST', str_replace('UTGST', '', $pdf), 'no "SGST" head on a UT invoice');
    }

    public static function statesWithLegislature(): array
    {
        return ['Delhi' => ['07'], 'Puducherry' => ['34'], 'Jammu and Kashmir' => ['01']];
    }

    #[DataProvider('statesWithLegislature')]
    public function test_delhi_puducherry_and_jk_levy_sgst_not_utgst(string $code): void
    {
        $this->company->forceFill(['state_id' => $this->state($code)->id, 'gstin' => $code . 'ABCDE1234F1Z5'])->save();
        $inv = $this->issue([$this->line(1250.55, 18)], $this->customerIn($code));

        $this->assertSame('SGST', $inv->stateTaxLabel());
        $this->assertMatchesRegularExpression('/SGST ₹\s*112\.55/u', $this->invoicePdfText($inv));
    }

    public function test_an_inter_state_supply_from_a_ut_is_igst_with_no_utgst(): void
    {
        $this->company->forceFill(['state_id' => $this->state('04')->id, 'gstin' => '04ABCDE1234F1Z5'])->save();
        // Chandigarh -> Punjab: 1,250.55 @ 18% IGST 225.10; raw 1,475.65 -> 1,476.
        $inv = $this->issue([$this->line(1250.55, 18)], $this->customerIn('03'));

        $this->assertTrue((bool) $inv->is_interstate);
        $this->assertMoney(225.10, $inv->total_igst);
        $this->assertMoney(0.00, $inv->total_sgst);
        $pdf = $this->invoicePdfText($inv);
        $this->assertMatchesRegularExpression('/IGST ₹\s*225\.10/u', $pdf);
        $this->assertStringNotContainsString('UTGST', $pdf);
    }

    public function test_ship_to_another_state_keeps_the_bill_to_state_as_place_of_supply(): void
    {
        // s.10(1)(b) IGST Act, "bill to - ship to": goods delivered to a third
        // person on the buyer's direction are supplied where the BUYER is.
        // Supplier MH, buyer MH, goods shipped to Karnataka: intra-state,
        // CGST 112.55 + SGST 112.55 on 1,250.55 @ 18%, place of supply MH.
        $inv = $this->issue([$this->line(1250.55, 18, 1, ['hsn_sac' => '84733010'])], $this->customerIn('27'), [
            'ship_to_name' => 'Site office', 'ship_to_city' => 'Bengaluru', 'ship_to_state_id' => $this->state('29')->id,
        ]);

        $this->assertFalse((bool) $inv->is_interstate);
        $this->assertSame($this->state('27')->id, (int) $inv->place_of_supply_state_id);
        $this->assertSame($this->state('29')->id, (int) $inv->ship_to_state_id);
        $this->assertMoney(112.55, $inv->total_cgst);
        $this->assertMoney(0.00, $inv->total_igst);

        // Buyer in Karnataka, goods shipped back into Maharashtra: still the
        // buyer's state, so inter-state IGST 225.10.
        $inv2 = $this->issue([$this->line(1250.55, 18, 1, ['hsn_sac' => '84733010'])], $this->customerIn('29'), [
            'ship_to_name' => 'Pune godown', 'ship_to_city' => 'Pune', 'ship_to_state_id' => $this->state('27')->id,
        ]);
        $this->assertTrue((bool) $inv2->is_interstate);
        $this->assertSame($this->state('29')->id, (int) $inv2->place_of_supply_state_id);
        $this->assertMoney(225.10, $inv2->total_igst);
        $this->assertMoney(0.00, $inv2->total_cgst);
    }
}
