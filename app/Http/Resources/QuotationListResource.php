<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_number' => $this->quote_number,
            'display_number' => $this->displayNumber(),
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'quote_date' => optional($this->quote_date)->toDateString(),
            'valid_until' => optional($this->valid_until)->toDateString(),
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'grand_total' => (float) $this->grand_total,
            'currency' => $this->currency,
        ];
    }
}
