<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Keeps stock in step with the documents, for items with track_stock on:
 *
 *  - a sales invoice takes stock out when it is issued (a draft never does)
 *    and puts it back if the invoice is cancelled;
 *  - a purchase invoice brings stock in for every line linked to an item, and
 *    is re-posted when the bill is edited, removed when it is deleted;
 *  - an adjustment records anything else (damage, a return, a count).
 *
 * Stock on hand = opening stock + the sum of movements. Selling more than is
 * on hand is allowed (the goods may simply not have been entered yet); the
 * screens warn instead of blocking.
 */
class StockLedger
{
    public function recordInvoice(Invoice $invoice): void
    {
        if ($invoice->stockMovements()->where('kind', 'sale')->exists()) {
            return; // already posted
        }
        foreach ($this->trackedLines($invoice->items()->get(), $invoice->company_id) as [$product, $qty]) {
            $this->post($invoice->company_id, $product, -$qty, 'sale', $invoice, $invoice->invoice_date, 'Invoice ' . $invoice->invoice_number);
        }
    }

    public function reverseInvoice(Invoice $invoice): void
    {
        $sold = $invoice->stockMovements()->where('kind', 'sale')->get();
        if ($sold->isEmpty() || $invoice->stockMovements()->where('kind', 'sale_cancelled')->exists()) {
            return;
        }
        foreach ($sold as $m) {
            $this->post($invoice->company_id, $m->product_id, -(float) $m->quantity, 'sale_cancelled', $invoice, now(),
                'Invoice ' . $invoice->invoice_number . ' cancelled');
        }
    }

    public function syncPurchase(PurchaseBill $bill): void
    {
        $bill->stockMovements()->delete();
        $lines = $bill->items()->get();
        foreach ($this->trackedLines($lines, $bill->company_id) as [$product, $qty]) {
            $this->post($bill->company_id, $product, $qty, 'purchase', $bill, $bill->bill_date, 'Purchase ' . $bill->bill_number);
        }
        $this->fillMissingPurchasePrices($lines, $bill->company_id);
    }

    /**
     * An item with no purchase price takes it from the first bill it is bought
     * on (cost per unit after discount, before GST), so stock is valued at cost
     * rather than at the sale price. A price someone has entered is never changed.
     */
    private function fillMissingPurchasePrices(iterable $lines, int $companyId): void
    {
        $cost = [];
        foreach ($lines as $line) {
            if ($line->product_id && (float) $line->quantity > 0 && (float) $line->amount > 0 && ! isset($cost[$line->product_id])) {
                $cost[$line->product_id] = round((float) $line->amount / (float) $line->quantity, 2);
            }
        }
        if (! $cost) {
            return;
        }
        Product::where('company_id', $companyId)->whereNull('purchase_rate')->whereIn('id', array_keys($cost))->get()
            ->each(fn (Product $p) => $p->forceFill(['purchase_rate' => $cost[$p->id]])->saveQuietly());
    }

    public function removePurchase(PurchaseBill $bill): void
    {
        $bill->stockMovements()->delete();
    }

    public function adjust(Product $product, Carbon|string $date, float $quantity, string $note, ?User $user = null): StockMovement
    {
        return $this->post($product->company_id, $product, $quantity, 'adjustment', null, $date, $note, $user);
    }

    /** Stock on hand for one item, optionally as on a date. */
    public function onHand(Product $product, Carbon|string|null $asOn = null): float
    {
        $q = $product->stockMovements();
        if ($asOn) {
            $q->whereDate('moved_on', '<=', $asOn);
        }

        return round((float) $product->opening_stock + (float) $q->sum('quantity'), 3);
    }

    /**
     * Lines that move stock: linked to an item of this company that tracks
     * stock (goods only), quantities summed per item.
     *
     * @return array<int, array{0: Product, 1: float}>
     */
    private function trackedLines(iterable $lines, int $companyId): array
    {
        $qty = [];
        foreach ($lines as $line) {
            if ($line->product_id && (float) $line->quantity > 0) {
                $qty[$line->product_id] = ($qty[$line->product_id] ?? 0) + (float) $line->quantity;
            }
        }
        if (! $qty) {
            return [];
        }
        $products = Product::where('company_id', $companyId)->where('track_stock', true)->where('kind', 'goods')
            ->whereIn('id', array_keys($qty))->get()->keyBy('id');

        $out = [];
        foreach ($qty as $id => $q) {
            if ($products->has($id)) {
                $out[] = [$products[$id], round($q, 3)];
            }
        }

        return $out;
    }

    private function post(int $companyId, Product|int $product, float $quantity, string $kind, $source, Carbon|string|null $date, string $note, ?User $user = null): StockMovement
    {
        return StockMovement::create([
            'company_id' => $companyId,
            'product_id' => $product instanceof Product ? $product->id : $product,
            'user_id' => $user?->id ?? auth()->id(),
            'moved_on' => $date ? Carbon::parse($date)->toDateString() : now()->toDateString(),
            'quantity' => round($quantity, 3),
            'kind' => $kind,
            'source_type' => $source ? $source->getMorphClass() : null,
            'source_id' => $source?->getKey(),
            'note' => mb_substr($note, 0, 255),
        ]);
    }
}
