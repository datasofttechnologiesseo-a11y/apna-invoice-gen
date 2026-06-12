<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gstin' => $this->gstin,
            'composition_dealer' => (bool) $this->composition_dealer,
            'pan' => $this->pan,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state_id' => $this->state_id,
            'state' => $this->whenLoaded('state', fn () => $this->state ? [
                'id' => $this->state->id,
                'name' => $this->state->name,
                'gst_code' => $this->state->gst_code,
            ] : null),
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_ifsc' => $this->bank_ifsc,
            'bank_branch' => $this->bank_branch,
            'upi_id' => $this->upi_id,
            'default_currency' => $this->default_currency,
            'default_terms' => $this->default_terms,
            'declaration' => $this->declaration,
            'invoice_prefix' => $this->invoice_prefix,
            'next_invoice_number' => $this->nextInvoiceNumber(),
            'is_onboarded' => $this->isOnboarded(),
            'is_business_complete' => $this->isBusinessComplete(),
            'customers_count' => $this->whenCounted('customers'),
            'invoices_count' => $this->whenCounted('invoices'),
        ];
    }
}
