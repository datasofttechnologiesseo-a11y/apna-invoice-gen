<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cgst = (float) $this->cgst_amount;
        $sgst = (float) $this->sgst_amount;
        $igst = (float) $this->igst_amount;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'description' => $this->description,
            'hsn_sac' => $this->hsn_sac,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'rate' => (float) $this->rate,
            'discount' => (float) $this->discount,
            'amount' => (float) $this->amount,
            'gst_rate' => (float) $this->gst_rate,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            // QuotationItem has no stored `total` column — derive it.
            'total' => round((float) $this->amount + $cgst + $sgst + $igst, 2),
        ];
    }
}
