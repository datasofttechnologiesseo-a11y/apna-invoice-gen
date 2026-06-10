<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full invoice payload (detail view). Use InvoiceListResource for the index
 * list to avoid shipping every line item over the wire.
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'display_number' => $this->displayNumber(),
            'document_title' => $this->documentTitle(),
            'status' => $this->status,
            'invoice_date' => optional($this->invoice_date)->toDateString(),
            'due_date' => optional($this->due_date)->toDateString(),
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'place_of_supply_state_id' => $this->place_of_supply_state_id,
            'is_interstate' => (bool) $this->is_interstate,
            'reverse_charge' => (bool) $this->reverse_charge,

            // Ship-to
            'has_separate_ship_to' => $this->hasSeparateShipTo(),
            'ship_to_name' => $this->ship_to_name,
            'ship_to_address_line1' => $this->ship_to_address_line1,
            'ship_to_address_line2' => $this->ship_to_address_line2,
            'ship_to_city' => $this->ship_to_city,
            'ship_to_state_id' => $this->ship_to_state_id,
            'ship_to_postal_code' => $this->ship_to_postal_code,
            'ship_to_gstin' => $this->ship_to_gstin,

            // Transport / e-way
            'transporter_name' => $this->transporter_name,
            'transporter_id' => $this->transporter_id,
            'vehicle_number' => $this->vehicle_number,
            'transport_mode' => $this->transport_mode,
            'eway_bill_number' => $this->eway_bill_number,

            // Money
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'total_cgst' => (float) $this->total_cgst,
            'total_sgst' => (float) $this->total_sgst,
            'total_igst' => (float) $this->total_igst,
            'total_tax' => (float) $this->total_tax,
            'round_off' => (float) $this->round_off,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'credited_amount' => (float) $this->credited_amount,
            'balance' => (float) $this->balance,
            'effective_balance' => (float) $this->effectiveBalance(),

            'notes' => $this->notes,
            'terms' => $this->terms,
            'style' => $this->style,

            // Capability flags so the app can show/hide actions without
            // re-implementing the rules.
            'can' => [
                'edit' => $this->isEditable(),
                'soft_edit' => $this->isSoftEditable(),
                'finalize' => $this->isDraft(),
                'cancel' => $this->canBeCancelled(),
                'delete' => $this->isEditable() || $this->isCancelled(),
            ],

            'finalized_at' => optional($this->finalized_at)->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),

            'whatsapp_link' => $this->whatsAppShareLink(),
        ];
    }
}
