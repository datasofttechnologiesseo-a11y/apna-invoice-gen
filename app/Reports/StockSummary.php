<?php

namespace App\Reports;

use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Stock summary for items that track stock: opening, in, out and closing for
 * the period, and the closing value (at purchase price when one is set, else
 * at sale price). With no dates it is simply stock on hand today.
 */
class StockSummary extends Report
{
    public function group(): string
    {
        return 'Stock';
    }

    public function title(): string
    {
        return 'Stock Summary';
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Item', 'width' => 30],
            ['key' => 'sku', 'label' => 'Item Code'],
            ['key' => 'hsn', 'label' => 'HSN'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'opening', 'label' => 'Opening', 'type' => 'qty'],
            ['key' => 'in', 'label' => 'In', 'type' => 'qty'],
            ['key' => 'out', 'label' => 'Out', 'type' => 'qty'],
            ['key' => 'closing', 'label' => 'Closing', 'type' => 'qty'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'price', 'label' => 'Valued at', 'type' => 'money', 'unit_price' => true],
            ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'total' => true],
        ];
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();
        $products = $this->company->products()->where('track_stock', true)
            ->when($this->request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%")))
            ->orderBy('name')->get();
        if ($products->isEmpty()) {
            return [];
        }

        $base = fn () => StockMovement::where('company_id', $this->company->id)->whereIn('product_id', $products->pluck('id'));
        $before = $from
            ? $base()->whereDate('moved_on', '<', $from->toDateString())->groupBy('product_id')->pluck(DB::raw('SUM(quantity)'), 'product_id')
            : collect();
        $period = $base()
            ->when($from, fn ($q) => $q->whereDate('moved_on', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('moved_on', '<=', $to->toDateString()))
            ->groupBy('product_id')
            ->select('product_id',
                DB::raw('SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as qty_in'),
                DB::raw('SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END) as qty_out'))
            ->get()->keyBy('product_id');

        $lowOnly = $this->request->query('status') === 'low';
        foreach ($products as $p) {
            $opening = round((float) $p->opening_stock + (float) ($before[$p->id] ?? 0), 3);
            $in = round((float) ($period[$p->id]->qty_in ?? 0), 3);
            $out = round((float) ($period[$p->id]->qty_out ?? 0), 3);
            $closing = round($opening + $in - $out, 3);
            $low = $p->low_stock_level !== null && $closing <= (float) $p->low_stock_level;
            if ($lowOnly && ! $low) {
                continue;
            }
            $price = (float) ($p->purchase_rate ?? $p->rate);
            yield [
                '_url' => route('products.stock', $p),
                'name' => $p->name,
                'sku' => $p->sku,
                'hsn' => $p->hsn_sac,
                'unit' => $p->unit,
                'opening' => $opening,
                'in' => $in,
                'out' => $out,
                'closing' => $closing,
                'status' => $closing < 0 ? 'Negative' : ($low ? 'Low' : 'OK'),
                'price' => $price,
                'value' => round(max(0, $closing) * $price, 2),
            ];
        }
    }
}
