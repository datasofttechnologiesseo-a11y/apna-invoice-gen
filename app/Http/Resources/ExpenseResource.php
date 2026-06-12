<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_date' => optional($this->entry_date)->toDateString(),
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'category_color' => $this->categoryColor(),
            'vendor_name' => $this->vendor_name,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'gst_amount' => (float) $this->gst_amount,
            'total' => $this->cashOutflow(),
            'is_interstate' => (bool) $this->is_interstate,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            // A cash-memo-linked expense is managed via its memo; flag so the app
            // can disable edit/delete and point the user there instead.
            'cash_memo_id' => $this->cash_memo_id,
        ];
    }
}
