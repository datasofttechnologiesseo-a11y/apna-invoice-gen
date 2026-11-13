<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Lightweight purchase invoice payload for list screens - no lines / payments. */
class PurchaseBillListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_number' => $this->bill_number,
            'bill_date' => optional($this->bill_date)->toDateString(),
            'booked_on' => optional($this->booked_on)->toDateString(),
            'due_date' => optional($this->due_date)->toDateString(),
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->whenLoaded('vendor', fn () => $this->vendor?->name),
            'vendor_gstin' => $this->whenLoaded('vendor', fn () => $this->vendor?->gstin),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'is_overdue' => (bool) $this->isOverdue(),
            'subtotal' => (float) $this->subtotal,
            'total_tax' => (float) $this->total_tax,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'balance' => (float) $this->balance,
            'itc_eligible' => (bool) $this->itc_eligible,
            'reverse_charge' => (bool) $this->reverse_charge,
        ];
    }
}
