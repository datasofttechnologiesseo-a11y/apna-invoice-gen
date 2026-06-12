<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight invoice payload for list screens — no line items / payments.
 */
class InvoiceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'display_number' => $this->displayNumber(),
            'status' => $this->status,
            'invoice_date' => optional($this->invoice_date)->toDateString(),
            'due_date' => optional($this->due_date)->toDateString(),
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'balance' => (float) $this->balance,
            'effective_balance' => (float) $this->effectiveBalance(),
            'currency' => $this->currency,
        ];
    }
}
