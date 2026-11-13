<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Duplicate" on an invoice, for the web (InvoiceController::duplicate) and
 * the mobile app (Api\DocumentController::duplicateInvoice).
 *
 * Copies the customer, line items, currency, notes, terms and presentation,
 * but resets the number, dates, status, and all payment/credit history so the
 * copy is a clean new draft. Shipment-specific fields (transporter, e-way
 * bill, ship-to) are intentionally NOT copied.
 */
class InvoiceDuplicator
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function duplicate(Invoice $invoice, User $owner): Invoice
    {
        $invoice->load('items');

        // Preserve the original payment-term gap (due − issue), default 30 days.
        $termDays = ($invoice->invoice_date && $invoice->due_date)
            ? (int) $invoice->invoice_date->diffInDays($invoice->due_date)
            : 30;

        // The copy is taxed as a new invoice would be today: the customer may
        // have moved state since (CGST/SGST vs IGST and the place of supply
        // follow them), or the business joined the composition scheme.
        $company = $invoice->company;
        $customer = $invoice->customer;
        $supplyType = $invoice->supply_type ?: 'regular';
        [$isInterstate, $noTax] = $this->calculator->taxBasis($company->state_id, $customer?->state_id, $supplyType, (bool) $invoice->reverse_charge);
        $noTax = $noTax || (bool) $company->composition_dealer;
        $calc = $this->calculator->recalculate(new Invoice, $invoice->items->map(fn ($item) => $item->only([
            'product_id', 'description', 'hsn_sac', 'quantity', 'unit', 'rate', 'discount', 'gst_rate', 'sort_order',
        ]))->all(), $isInterstate, $noTax);

        $copy = DB::transaction(function () use ($owner, $invoice, $termDays, $customer, $isInterstate, $calc) {
            $new = $owner->invoices()->create([
                'company_id' => $invoice->company_id,
                'customer_id' => $invoice->customer_id,
                'invoice_number' => null,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays($termDays)->toDateString(),
                'place_of_supply_state_id' => $customer?->state_id ?? $invoice->place_of_supply_state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => $invoice->reverse_charge,
                'supply_type' => $invoice->supply_type ?: 'regular',
                'currency' => $invoice->currency,
                'exchange_rate' => $invoice->exchange_rate,
                'status' => 'draft',
                'style' => $invoice->style,
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                ...$calc['totals'],
                'paid_amount' => 0,
                'credited_amount' => 0,
                'balance' => $calc['totals']['grand_total'],
            ]);

            $new->items()->createMany($calc['items']);

            return $new;
        });

        AuditLog::record('invoice.duplicated',
            "Draft #{$copy->id} created by duplicating ".$invoice->displayNumber(),
            $copy
        );

        return $copy;
    }
}
