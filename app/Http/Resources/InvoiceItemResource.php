<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'cgst_amount' => (float) $this->cgst_amount,
            'sgst_amount' => (float) $this->sgst_amount,
            'igst_amount' => (float) $this->igst_amount,
            'total' => (float) $this->total,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
