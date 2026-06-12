<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMemoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'memo_number' => $this->memo_number,
            'memo_date' => optional($this->memo_date)->toDateString(),
            'seller_name' => $this->seller_name,
            'seller_address' => $this->seller_address,
            'seller_gstin' => $this->seller_gstin,
            'seller_phone' => $this->seller_phone,
            'seller_state' => $this->seller_state,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'taxable_value' => (float) $this->taxable_value,
            'total_cgst' => (float) $this->total_cgst,
            'total_sgst' => (float) $this->total_sgst,
            'total_igst' => (float) $this->total_igst,
            'round_off' => (float) $this->round_off,
            'grand_total' => (float) $this->grand_total,
            'is_interstate' => $this->isInterstate(),
            'payment_mode' => $this->payment_mode,
            'reference_number' => $this->reference_number,
            'expense_category' => $this->expense_category,
            'notes' => $this->notes,
            'items' => CashMemoItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
