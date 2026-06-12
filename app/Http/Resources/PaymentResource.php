<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'receipt_number' => $this->receipt_number,
            'received_at' => optional($this->received_at)->toDateString(),
            'amount' => (float) $this->amount,
            'tds_amount' => (float) $this->tds_amount,
            'tds_section' => $this->tds_section,
            'tds_rate' => $this->tds_rate !== null ? (float) $this->tds_rate : null,
            'net_received' => (float) $this->netReceived(),
            'method' => $this->method,
            'method_label' => $this->methodLabel(),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
        ];
    }
}
