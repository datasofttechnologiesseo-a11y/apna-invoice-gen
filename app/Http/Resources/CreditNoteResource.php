<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'credit_note_number' => $this->credit_note_number,
            'credit_note_date' => optional($this->credit_note_date)->toDateString(),
            'amount' => (float) $this->amount,
            'taxable_value' => (float) $this->taxable_value,
            'total_cgst' => (float) $this->total_cgst,
            'total_sgst' => (float) $this->total_sgst,
            'total_igst' => (float) $this->total_igst,
            'reason' => $this->reason,
            'reason_label' => $this->reasonLabel(),
            'notes' => $this->notes,
        ];
    }
}
