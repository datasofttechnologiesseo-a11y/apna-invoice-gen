<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_number' => $this->quote_number,
            'display_number' => $this->displayNumber(),
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'is_expired' => $this->isExpired(),
            'days_until_expiry' => $this->daysUntilExpiry(),
            'quote_date' => optional($this->quote_date)->toDateString(),
            'valid_until' => optional($this->valid_until)->toDateString(),
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'subject' => $this->subject,
            'reference' => $this->reference,
            'delivery_period' => $this->delivery_period,
            'is_interstate' => (bool) $this->is_interstate,
            'currency' => $this->currency,

            'subtotal' => (float) $this->subtotal,
            'total_cgst' => (float) $this->total_cgst,
            'total_sgst' => (float) $this->total_sgst,
            'total_igst' => (float) $this->total_igst,
            'total_tax' => (float) $this->total_tax,
            'round_off' => (float) $this->round_off,
            'grand_total' => (float) $this->grand_total,

            'notes' => $this->notes,
            'terms' => $this->terms,
            'style' => $this->style,

            'converted_to_invoice_id' => $this->converted_to_invoice_id,
            'sent_at' => optional($this->sent_at)->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'declined_at' => optional($this->declined_at)->toIso8601String(),
            'decline_reason' => $this->decline_reason,

            'can' => [
                'edit' => $this->isEditable(),
                'send' => $this->isDraft(),
                'accept' => $this->isSent(),
                'decline' => in_array($this->status, ['sent', 'accepted'], true),
                'convert' => $this->canBeConverted(),
                'delete' => $this->isDraft(),
            ],

            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'whatsapp_link' => $this->whatsAppShareLink(),
        ];
    }
}
