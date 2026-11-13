<?php

namespace App\Http\Resources;

use App\Services\PurchaseBook;
use Illuminate\Http\Request;

/**
 * A purchase invoice with its vendor, lines, payments and the input-credit
 * warnings the web page shows (s.16(4) time bar, Rule 37 reversal).
 */
class PurchaseBillResource extends PurchaseBillListResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'vendor' => $this->whenLoaded('vendor', fn () => new VendorResource($this->vendor)),
            'is_interstate' => (bool) $this->is_interstate,
            'state_tax_label' => $this->stateTaxLabel(),
            'total_cgst' => (float) $this->total_cgst,
            'total_sgst' => (float) $this->total_sgst,
            'total_igst' => (float) $this->total_igst,
            'round_off' => (float) $this->round_off,
            'expense_category' => $this->expense_category,
            'expense_category_label' => config("expense_categories.{$this->expense_category}.label"),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'items' => PurchaseBillItemResource::collection($this->whenLoaded('items')),
            'payments' => PurchasePaymentResource::collection($this->whenLoaded('payments')),
            'credit_notes' => $this->whenLoaded('payments', fn () => (object) app(PurchaseBook::class)->creditNotes($this->resource)),
        ]);
    }
}
