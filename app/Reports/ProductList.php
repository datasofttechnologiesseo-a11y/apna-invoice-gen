<?php

namespace App\Reports;

/** Products & services master (price list). */
class ProductList extends Report
{
    public function usesDates(): bool
    {
        return false;
    }

    public function group(): string
    {
        return 'Masters';
    }

    public function title(): string
    {
        return 'Products & Services';
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'width' => 32],
            ['key' => 'sku', 'label' => 'Item Code'],
            ['key' => 'kind', 'label' => 'Type'],
            ['key' => 'hsn', 'label' => 'HSN/SAC'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'rate', 'label' => 'Sale Price (excl. GST)', 'type' => 'money', 'unit_price' => true],
            ['key' => 'gst', 'label' => 'GST %', 'type' => 'percent'],
            ['key' => 'price_incl', 'label' => 'Price incl. GST', 'type' => 'money', 'unit_price' => true],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    public function rows(): iterable
    {
        $s = $this->request->query('search');
        $status = $this->request->query('status', 'active');

        $query = $this->company->products()
            ->when($s, fn ($q) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%")->orWhere('hsn_sac', 'like', "%{$s}%");
            }))
            ->when($this->request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'archived', fn ($q) => $q->where('is_active', false))
            ->orderBy('name');

        foreach ($query->cursor() as $p) {
            $rate = (float) $p->rate;
            $gst = (float) $p->gst_rate;
            yield [
                '_url' => route('products.edit', $p),
                'name' => $p->name,
                'sku' => $p->sku,
                'kind' => $p->kind === 'service' ? 'Service' : 'Goods',
                'hsn' => $p->hsn_sac,
                'unit' => $p->unit,
                'rate' => $rate,
                'gst' => $gst,
                'price_incl' => round($rate * (1 + $gst / 100), 2),
                'status' => $p->is_active ? 'Active' : 'Archived',
            ];
        }
    }
}
