<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Money paid to a vendor against one purchase invoice. */
class PurchasePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_bill_id' => $this->purchase_bill_id,
            'paid_on' => optional($this->paid_on)->toDateString(),
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'method_label' => config("payment_methods.methods.{$this->method}.label") ?? strtoupper((string) $this->method),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
        ];
    }
}
