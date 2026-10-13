<?php

namespace Tests\Feature\Audit\Sales;

use App\Models\Invoice;

/**
 * The invoice form's live totals (recompute() in invoices/edit.blade.php)
 * must equal what the server stores and prints. The page's own script is
 * rendered by the real create route and run in node, exactly as a browser
 * would, then the same lines are posted to invoices.store.
 */
class FormTotalMatchesStoredTotalTest extends SalesAuditCase
{
    private function formTotals(array $items, $customer): array
    {
        $node = $this->node();
        if ($node === null) {
            $this->markTestSkipped('node is not available');
        }

        $html = $this->actingAs($this->user)->get(route('invoices.create'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<script>\s*(const UT_STATE_IDS.*?)</script>#s', $html, $m), 'invoiceForm script not found');

        // Browser globals the script touches outside recompute().
        $js = "var window = globalThis;\n" . $m[1] . "\n"
            . 'const f = invoiceForm(' . json_encode($items) . ', ' . json_encode([(string) $customer->id => $customer->state_id])
            . ', ' . $this->company->state_id . ', [], {}, [], true, {});' . "\n"
            . "f.customerId = '" . $customer->id . "';\n"
            . "f.\$refs = { paidInput: { value: '0' } };\n"
            . "f.recompute();\n"
            . 'console.log(JSON.stringify(f.totals));';

        $tmp = tempnam(sys_get_temp_dir(), 'form') . '.js';
        file_put_contents($tmp, $js);
        $out = [];
        $code = 0;
        exec(escapeshellarg($node) . ' ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, implode("\n", $out));

        return json_decode(end($out), true);
    }

    /**
     * One line, Rs 105.50 at 18%, same-state customer.
     * CGST = SGST = 105.50 x 9% = 9.495 -> 9.50 each (half-up, as the server
     * and the NIC schema do). Total 124.50 -> Rs 125.
     * The form rounds 9.495 to 9.49 (Math.round(949.4999999999999)), so it
     * shows CGST 9.49, SGST 9.49 and a grand total of Rs 124.
     */
    public function test_on_screen_grand_total_equals_the_stored_grand_total(): void
    {
        $items = [['description' => 'Service', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 105.50, 'gst_rate' => 18]];
        $screen = $this->formTotals($items, $this->local);

        $inv = $this->draft($items, $this->local, ['invoice_discount' => $screen['discount']]);

        $this->assertEqualsWithDelta(9.50, (float) $inv->total_cgst, 0.001, 'server CGST');
        $this->assertEqualsWithDelta(125.00, (float) $inv->grand_total, 0.001, 'server grand total');

        // What the person saw before pressing Save must be what was saved.
        $this->assertEqualsWithDelta((float) $inv->grand_total, (float) $screen['grandTotal'], 0.001, 'form grand total differs from stored grand total');
        $this->assertEqualsWithDelta((float) $inv->total_cgst, (float) $screen['cgst'], 0.001, 'form CGST differs from stored CGST');
    }

    /** Inter-state: Rs 190.25 at 18% IGST = 34.245 -> 34.25; total 224.50 -> Rs 225. */
    public function test_on_screen_igst_total_equals_the_stored_total(): void
    {
        $items = [['description' => 'Goods', 'hsn_sac' => '8471', 'quantity' => 1, 'rate' => 190.25, 'gst_rate' => 18]];
        $screen = $this->formTotals($items, $this->outside);

        $inv = $this->draft($items, $this->outside, ['invoice_discount' => $screen['discount']]);

        $this->assertEqualsWithDelta(34.25, (float) $inv->total_igst, 0.001);
        $this->assertEqualsWithDelta((float) $inv->grand_total, (float) $screen['grandTotal'], 0.001, 'form grand total differs from stored grand total');
        $this->assertEqualsWithDelta((float) $inv->total_igst, (float) $screen['igst'], 0.001, 'form IGST differs from stored IGST');
    }
}
