<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A supplier. `payable` / `purchase_bills_count` are present on the list. */
class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gstin' => $this->gstin,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state_id' => $this->state_id ? (int) $this->state_id : null,
            'state_name' => $this->whenLoaded('state', fn () => $this->state?->name),
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'payable' => $this->when(array_key_exists('payable', $this->getAttributes()), fn () => (float) $this->payable),
            'purchase_bills_count' => $this->when(array_key_exists('purchase_bills_count', $this->getAttributes()), fn () => (int) $this->purchase_bills_count),
        ];
    }
}
