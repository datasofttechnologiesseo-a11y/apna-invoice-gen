<?php

namespace Tests\Feature\Audit\Sales;

use App\Models\Invoice;
use App\Models\Quotation;

/**
 * A GST-registered business must show HSN/SAC on a B2B tax invoice
 * (Rule 46(g), Notification 78/2020-CT), and the invoice form refuses to save
 * a line without one when either party has a GSTIN. Quotations take HSN as
 * optional, conversion copies the lines as they are, and "Issue" never
 * re-checks: the tax invoice goes out with an empty HSN column (and an empty
 * GSTR-1 Table 12 row).
 */
class QuoteConversionSkipsHsnTest extends SalesAuditCase
{
    public function test_quote_converted_invoice_cannot_be_issued_without_hsn(): void
    {
        $this->actingAs($this->user)->post(route('quotations.store'), [
            'customer_id' => $this->local->id,
            'quote_date' => now()->toDateString(),
            'items' => [['description' => 'Steel rods', 'quantity' => 5, 'rate' => 2000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $quote = Quotation::latest('id')->first();

        // The invoice form itself would refuse this line.
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->local->id, 'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Steel rods', 'quantity' => 5, 'rate' => 2000, 'gst_rate' => 18]],
        ])->assertSessionHasErrors('items.0.hsn_sac');

        $this->actingAs($this->user)->post(route('quotations.convert', $quote))->assertRedirect();
        $inv = Invoice::latest('id')->first();
        $this->assertSame('', (string) $inv->items()->first()->hsn_sac, 'precondition: converted line has no HSN');

        $issued = $this->issue($inv);

        $this->assertSame('draft', $issued->status, 'a B2B tax invoice was issued with no HSN/SAC on its line');
    }
}
