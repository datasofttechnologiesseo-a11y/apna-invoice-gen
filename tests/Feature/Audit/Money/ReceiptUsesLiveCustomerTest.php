<?php

namespace Tests\Feature\Audit\Money;

use App\Models\Payment;

/**
 * The invoice PDF and the credit note PDF print the recipient frozen at issue
 * (Invoice::billedCustomer, Rule 46(d)/(e)). The receipt PDF reads the live
 * customer row, so a receipt re-downloaded after the customer record is
 * edited names a different party / GSTIN than the invoice it settles.
 */
class ReceiptUsesLiveCustomerTest extends MoneyAuditBase
{
    public function test_receipt_names_the_party_billed_on_the_invoice(): void
    {
        $inv = $this->issue([
            ['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ], '2026-09-05');
        $this->pay($inv, ['amount' => 1180, 'received_at' => '2026-09-10'])->assertSessionHasNoErrors();
        $p = Payment::where('invoice_id', $inv->id)->sole();

        // Later the customer record is edited (new owner / new registration).
        $this->customer->forceFill(['name' => 'Kulkarni Steel LLP', 'gstin' => '27KLMNO9876P1Z2'])->save();

        $html = view('payments.receipt', ['payment' => $p->fresh()->load('invoice.company.state', 'invoice.customer.state')])->render();

        $this->assertStringContainsString('Patil Traders', $html, 'Receipt no longer names the party the invoice was issued to.');
        $this->assertStringNotContainsString('27KLMNO9876P1Z2', $html, 'Receipt prints a GSTIN that is not on the invoice it settles.');
    }
}
