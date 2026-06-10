<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMemoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'hsn_sac' => $this->hsn_sac,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'rate' => (float) $this->rate,
            'amount' => (float) $this->amount,
            'sort_order' => $this->sort_order,
        ];
    }
}
