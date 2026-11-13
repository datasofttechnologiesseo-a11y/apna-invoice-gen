<?php

namespace App\Services;

use App\Models\CreditNote;
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

    /**
     * A sales return puts the goods back: stock in for each line of the
     * credit note linked to a tracked goods item, dated the note's date.
     * Notes for other reasons (a price reduction) and notes without lines
     * move no stock.
     */
    public function recordCreditNote(CreditNote $cn): void
    {
        if (! $cn->isSalesReturn() || $cn->stockMovements()->where('kind', 'sale_return')->exists()) {
            return;
        }
        foreach ($this->trackedLines($cn->items()->get(), $cn->company_id) as [$product, $qty]) {
            $this->post($cn->company_id, $product, $qty, 'sale_return', $cn, $cn->credit_note_date,
                'Credit note ' . $cn->credit_note_number . ' (goods returned)');
        }
    }

    /** Reversing a credit note takes back the stock its return put in. */
    public function reverseCreditNote(CreditNote $cn): void
    {
        $cn->stockMovements()->where('kind', 'sale_return')->delete();
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

    /**
     * Stock history of one item, latest first, each movement with the running
     * balance after it, and the stock on hand at the end.
     *
     * @return array{rows: \Illuminate\Support\Collection<int, array{movement: StockMovement, balance: float}>, on_hand: float}
     */
    public function history(Product $product): array
    {
        $movements = $product->stockMovements()->with('source')->orderBy('moved_on')->orderBy('id')->get();
        $running = (float) $product->opening_stock;
        $rows = $movements->map(function ($m) use (&$running) {
            $running = round($running + (float) $m->quantity, 3);

            return ['movement' => $m, 'balance' => $running];
        })->reverse()->values();

        return ['rows' => $rows, 'on_hand' => $running];
    }

    /** Rules for a manual adjustment: in, out, or a physical count. */
    public function adjustmentRules(?string $direction): array
    {
        return [
            'direction' => ['required', 'in:in,out,count'],
            // A count can be zero (the shelf is empty); adding or removing nothing cannot.
            'quantity' => ['required', 'numeric', $direction === 'count' ? 'min:0' : 'min:0.001', 'max:999999999'],
            'moved_on' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:200'],
        ];
    }

    public function adjustmentMessages(?string $direction): array
    {
        return [
            'quantity.min' => $direction === 'count' ? 'The count cannot be below zero.' : 'Enter a quantity above zero.',
        ];
    }

    /**
     * Add or remove stock by hand (damage, a return), or enter a physical
     * count: the shop counts the shelf and the books are set to that number,
     * the difference posted as one adjustment. Input is validated against
     * adjustmentRules(); a date in the closed period is refused.
     *
     * @return array{movement: ?StockMovement, message: string}
     */
    public function applyAdjustment(Product $product, array $data, ?User $user = null): array
    {
        $company = $product->company;
        if ($company && $company->isBooksLockedOn($data['moved_on'])) {
            throw new \App\Exceptions\BooksLockedException("Books are locked up to {$company->books_locked_until->format('d M Y')}. Date the adjustment after that.", 'moved_on');
        }
        $unit = $product->unit;
        $num = fn (float $v) => 0 + round($v, 3);

        if ($data['direction'] === 'count') {
            $counted = round((float) $data['quantity'], 3);
            $books = $this->onHand($product, $data['moved_on']);
            $qty = round($counted - $books, 3);
            if (abs($qty) < 0.0005) {
                return ['movement' => null, 'message' => "Stock already matches the count: {$num($counted)} {$unit}. Nothing changed."];
            }
            $movement = $this->adjust($product, $data['moved_on'], $qty,
                $data['reason'] . ": counted {$num($counted)}, books showed {$num($books)}", $user);

            return ['movement' => $movement, 'message' => 'Count saved. ' . ($qty > 0 ? 'Added ' : 'Removed ') . $num(abs($qty)) . " {$unit} to match. In stock now: " . $num($product->stockOnHand()) . '.'];
        }

        $qty = (float) $data['quantity'] * ($data['direction'] === 'out' ? -1 : 1);
        $movement = $this->adjust($product, $data['moved_on'], $qty, $data['reason'], $user);

        return ['movement' => $movement, 'message' => ($qty > 0 ? 'Added ' : 'Removed ') . $num(abs($qty)) . ' ' . $unit . '. In stock now: ' . $num($product->stockOnHand()) . '.'];
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
