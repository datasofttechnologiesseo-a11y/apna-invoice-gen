<?php

namespace Tests\Feature\Audit\Money;

use Symfony\Component\Process\Process;

/**
 * The "TDS deducted by customer" block on the invoice page fills the TDS
 * amount as payment amount x section rate. The payment amount defaults to
 * the invoice balance, which includes GST. TDS under Chapter XVII-B is on
 * the value excluding GST when GST is shown separately on the invoice
 * (CBDT Circular 23/2017; 194Q: Circular 13/2021), and GST-TDS u/s 51 is on
 * the value excluding tax by the section's own Explanation. On a
 * 1,00,000 + 18% bill, 194J @10% is 10,000, not 11,800; s.51 @2% is 2,000,
 * not 2,360. The suggested figure goes onto the receipt and Form 26AS
 * matching, and "net to bank" comes out 1,800 short.
 *
 * The page's own Alpine object is evaluated with node to read the figure.
 */
class TdsSuggestionOnGrossTest extends MoneyAuditBase
{
    private function suggested(string $html, string $section, string $amount): ?float
    {
        $start = strpos($html, 'TDS deducted by customer');
        $this->assertNotFalse($start);
        $open = strpos($html, 'x-data="{', $start);
        $close = strpos($html, '}">', $open);
        $obj = substr($html, $open + strlen('x-data="'), $close - $open - strlen('x-data="') + 1);
        $obj = html_entity_decode($obj, ENT_QUOTES | ENT_HTML5);

        $js = 'const document = { querySelector: () => ({ value: ' . json_encode($amount) . ' }) };'
            . 'const o = (' . $obj . ');'
            . 'o.section = ' . json_encode($section) . '; o.applySection();'
            . 'process.stdout.write(String(o.amt));';

        $p = new Process(['node', '-e', $js]);
        $p->run();
        if (! $p->isSuccessful()) {
            $this->markTestSkipped('node not available: ' . $p->getErrorOutput());
        }

        return (float) $p->getOutput();
    }

    public function test_tds_suggested_on_the_value_excluding_gst(): void
    {
        $inv = $this->issue([
            ['description' => 'Consulting', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18],
        ], '2026-09-05');
        $this->assertEqualsWithDelta(118000.0, (float) $inv->balance, 0.001);

        $html = $this->actingAs($this->user)->get(route('invoices.show', $inv))->assertOk()->getContent();

        // The payment box is pre-filled with the balance, 1,18,000.
        $this->assertEqualsWithDelta(10000.0, $this->suggested($html, '194J_prof', '118000'), 0.01, '194J @10% suggested on the GST-inclusive amount');
        $this->assertEqualsWithDelta(2000.0, $this->suggested($html, '51', '118000'), 0.01, 'GST-TDS s.51 @2% suggested on the GST-inclusive amount');
    }
}
