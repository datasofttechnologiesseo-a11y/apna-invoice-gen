<?php

namespace Tests\Feature\Audit\Sales;

/**
 * s.10(4) CGST Act: a composition taxable person "shall not collect any tax
 * from the recipient". Rule 49 bill of supply carries no tax. The document
 * itself prints "Composition taxable person, not eligible to collect tax on
 * supplies", yet the calculator still charges CGST/SGST at whatever rate the
 * line carries (the form defaults every new row to 18%).
 *
 * s.32(1): a person who is not registered shall not collect any amount by
 * way of tax. Same gap for a business with no GSTIN.
 */
class CompositionDealerCollectsTaxTest extends SalesAuditCase
{
    private function issueOneLine(): \App\Models\Invoice
    {
        $inv = $this->draft([
            ['description' => 'Cement bag', 'hsn_sac' => '2523', 'quantity' => 10, 'rate' => 1000, 'gst_rate' => 18],
        ]);

        return $this->issue($inv);
    }

    public function test_composition_dealer_bill_of_supply_collects_no_tax(): void
    {
        $this->company->forceFill(['composition_dealer' => true])->save();

        $inv = $this->issueOneLine();

        $this->assertNotSame('draft', $inv->status, 'precondition: the bill was issued');
        $this->assertSame('Bill of Supply', $inv->documentTitle());
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_tax, 0.001, 'a composition dealer cannot collect GST from the buyer');
        $this->assertEqualsWithDelta(10000.0, (float) $inv->grand_total, 0.001);
    }

    /**
     * A business with no GSTIN in its settings may still be registered (the
     * number just not entered yet), so tax is not silently dropped: that
     * would under-bill. The form says plainly that only a registered
     * business can charge GST (s.32(1)) and how to fix it.
     */
    public function test_unregistered_seller_is_warned_before_charging_gst(): void
    {
        $this->company->forceFill(['gstin' => null])->save();

        $this->actingAs($this->user)->get(route('invoices.create'))->assertOk()
            ->assertSee('Only a GST-registered business can charge GST.')
            ->assertSee('!companyHasGstin && !compositionNoTax && totals.totalTax > 0', false);
    }
}
