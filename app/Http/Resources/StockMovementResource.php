<?php

namespace App\Http\Resources;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\PurchaseBill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One stock movement. Pass ['movement' => StockMovement, 'balance' => float]
 * (a row of StockLedger::history) to include the running balance.
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $m = is_array($this->resource) ? $this->resource['movement'] : $this->resource;
        $source = $m->relationLoaded('source') ? $m->source : null;

        return [
            'id' => $m->id,
            'moved_on' => optional($m->moved_on)->toDateString(),
            'kind' => $m->kind,
            'kind_label' => $m->kindLabel(),
            'quantity' => (float) $m->quantity,
            'balance' => is_array($this->resource) ? (float) $this->resource['balance'] : null,
            'note' => $m->note,
            'source' => $source ? [
                'type' => match (true) {
                    $source instanceof Invoice => 'invoice',
                    $source instanceof PurchaseBill => 'purchase_bill',
                    $source instanceof CreditNote => 'credit_note',
                    default => class_basename($source),
                },
                'id' => $source->getKey(),
                'number' => match (true) {
                    $source instanceof Invoice => $source->invoice_number,
                    $source instanceof PurchaseBill => $source->bill_number,
                    $source instanceof CreditNote => $source->credit_note_number,
                    default => null,
                },
            ] : null,
        ];
    }
}
