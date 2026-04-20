<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Services\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    private InvoiceCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new InvoiceCalculator();
    }

    private function recalc(array $items, bool $isInterstate = false): array
    {
        return $this->calc->recalculate(new Invoice(), $items, $isInterstate);
    }

    public function test_single_line_intrastate_splits_into_cgst_and_sgst(): void
    {
        $result = $this->recalc([
            ['quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ]);

        $this->assertSame(1000.0, (float) $result['items'][0]['amount']);
        $this->assertSame(90.0, (float) $result['items'][0]['cgst_amount']);
        $this->assertSame(90.0, (float) $result['items'][0]['sgst_amount']);
        $this->assertSame(0.0, (float) $result['items'][0]['igst_amount']);
        $this->assertSame(1180.0, (float) $result['items'][0]['total']);

        $this->assertSame(1000.0, (float) $result['totals']['subtotal']);
        $this->assertSame(180.0, (float) $result['totals']['total_tax']);
        $this->assertSame(1180.0, (float) $result['totals']['grand_total']);
    }

    public function test_single_line_interstate_produces_igst_only(): void
    {
        $result = $this->recalc([
            ['quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ], isInterstate: true);

        $this->assertSame(0.0, (float) $result['items'][0]['cgst_amount']);
        $this->assertSame(0.0, (float) $result['items'][0]['sgst_amount']);
        $this->assertSame(180.0, (float) $result['items'][0]['igst_amount']);
        $this->assertSame(180.0, (float) $result['totals']['total_igst']);
    }

    public function test_zero_gst_rate_produces_no_tax(): void
    {
        $result = $this->recalc([
            ['quantity' => 5, 'rate' => 200, 'gst_rate' => 0],
        ]);

        $this->assertSame(0.0, (float) $result['totals']['total_tax']);
        $this->assertSame(1000.0, (float) $result['totals']['grand_total']);
    }

    public function test_cgst_plus_sgst_always_equals_total_tax_on_odd_rates(): void
    {
        // 0.25% on odd amount — half-rate rounding would drift by 1 paisa under
        // the old implementation. The fix splits from total tax, never from halves.
        $result = $this->recalc([
            ['quantity' => 3, 'rate' => 101.07, 'gst_rate' => 0.25],
        ]);

        $cgst = (float) $result['totals']['total_cgst'];
        $sgst = (float) $result['totals']['total_sgst'];
        $tax = (float) $result['totals']['total_tax'];

        $this->assertEqualsWithDelta($tax, $cgst + $sgst, 0.001,
            "CGST ({$cgst}) + SGST ({$sgst}) must equal total tax ({$tax})");
    }

    public function test_intrastate_and_interstate_produce_identical_total_tax_for_same_items(): void
    {
        $items = [
            ['quantity' => 1, 'rate' => 75000.55, 'gst_rate' => 18],
            ['quantity' => 3, 'rate' => 101.07,   'gst_rate' => 0.25],
            ['quantity' => 1, 'rate' => 499.99,   'gst_rate' => 40],
        ];

        $intra = $this->recalc($items, isInterstate: false);
        $inter = $this->recalc($items, isInterstate: true);

        $this->assertEqualsWithDelta(
            (float) $intra['totals']['total_tax'],
            (float) $inter['totals']['total_igst'],
            0.001,
            'Same sale must produce identical tax whether billed as CGST+SGST or IGST'
        );
    }

    public function test_mixed_rate_invoice_sums_each_line_independently(): void
    {
        $result = $this->recalc([
            ['quantity' => 10, 'rate' => 50,       'gst_rate' => 5],   // Rice: 500 + 25 tax
            ['quantity' => 1,  'rate' => 15000,    'gst_rate' => 3],   // Jewellery: 15000 + 450 tax
            ['quantity' => 2,  'rate' => 22000,    'gst_rate' => 12],  // Mobiles: 44000 + 5280 tax
            ['quantity' => 1,  'rate' => 499.99,   'gst_rate' => 40],  // Cigarettes: 499.99 + 200.00 tax
        ]);

        $this->assertSame(59999.99, round((float) $result['totals']['subtotal'], 2));
        $this->assertEqualsWithDelta(5955.00, (float) $result['totals']['total_tax'], 0.02);
    }

    public function test_quantity_and_rate_compute_amount_correctly(): void
    {
        $result = $this->recalc([
            ['quantity' => 2.5, 'rate' => 400, 'gst_rate' => 18],
        ]);

        $this->assertSame(1000.0, (float) $result['items'][0]['amount']);
    }

    public function test_grand_total_rounds_to_nearest_rupee(): void
    {
        // 18% on 100.33 = 18.0594 → CGST+SGST = 18.06 (rounded)
        // Subtotal 100.33 + 18.06 = 118.39 → round-off to 118
        $result = $this->recalc([
            ['quantity' => 1, 'rate' => 100.33, 'gst_rate' => 18],
        ]);

        $grand = (float) $result['totals']['grand_total'];
        $this->assertSame(118.0, $grand);
        $this->assertLessThan(0.5, abs((float) $result['totals']['round_off']));
    }

    public function test_is_interstate_compares_state_ids(): void
    {
        $this->assertTrue($this->calc->isInterstate(1, 2));
        $this->assertFalse($this->calc->isInterstate(1, 1));
        $this->assertFalse($this->calc->isInterstate(null, 1));
        $this->assertFalse($this->calc->isInterstate(1, null));
    }

    public function test_empty_items_produce_zero_totals(): void
    {
        $result = $this->recalc([]);

        $this->assertSame(0.0, (float) $result['totals']['subtotal']);
        $this->assertSame(0.0, (float) $result['totals']['total_tax']);
        $this->assertSame(0.0, (float) $result['totals']['grand_total']);
        $this->assertSame([], $result['items']);
    }
}
