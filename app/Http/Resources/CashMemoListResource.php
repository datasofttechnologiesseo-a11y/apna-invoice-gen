<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMemoListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'memo_number' => $this->memo_number,
            'memo_date' => optional($this->memo_date)->toDateString(),
            'seller_name' => $this->seller_name,
            'grand_total' => (float) $this->grand_total,
            'payment_mode' => $this->payment_mode,
        ];
    }
}
