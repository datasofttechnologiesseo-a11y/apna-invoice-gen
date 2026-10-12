<?php

namespace App\Imports;

use Illuminate\Support\Str;

class ProductImporter extends Importer
{
    private ?array $existing = null;

    public function key(): string
    {
        return 'products';
    }

    public function title(): string
    {
        return 'Products & Services';
    }

    public function listRoute(): string
    {
        return route('products.index');
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'Item Name', 'required' => true, 'help' => 'As it should print on the invoice line', 'example' => 'Intel Core Ultra 9 285K', 'aliases' => ['name', 'product', 'product name', 'item', 'service', 'description']],
            'sku' => ['label' => 'Item Code', 'help' => 'Your SKU / item code (optional, must be unique)', 'example' => 'CPU-285K', 'aliases' => ['sku', 'code', 'product code']],
            'kind' => ['label' => 'Type', 'help' => 'Goods or Service. Left blank: SAC codes starting 99 are services, others goods', 'example' => 'Goods', 'aliases' => ['kind', 'goods/service', 'category']],
            'hsn_sac' => ['label' => 'HSN/SAC', 'help' => '4-8 digits', 'example' => '84733010', 'aliases' => ['hsn', 'sac', 'hsn code', 'sac code', 'hsn/sac code']],
            'unit' => ['label' => 'Unit (UQC)', 'help' => 'GST unit code, e.g. NOS, PCS, KGS, BOX, HRS. Blank = NOS', 'example' => 'NOS', 'aliases' => ['unit', 'uqc', 'uom']],
            'rate' => ['label' => 'Sale Price', 'required' => true, 'help' => 'Price before GST, in ₹', 'example' => '49576.27', 'aliases' => ['rate', 'price', 'selling price', 'sale rate', 'mrp']],
            'gst_rate' => ['label' => 'GST %', 'required' => true, 'help' => '0, 5, 18 or 40 (GST 2.0); 0.25, 1.5, 3 for special items', 'example' => '18', 'aliases' => ['gst', 'gst rate', 'tax rate', 'tax %', 'gst%']],
            'purchase_rate' => ['label' => 'Purchase Price', 'help' => 'What you pay the supplier, before GST, in ₹ (optional, used for stock value)', 'example' => '42000', 'aliases' => ['purchase price', 'purchase rate', 'cost', 'cost price', 'buying price']],
            'opening_stock' => ['label' => 'Opening Stock', 'help' => 'Quantity in hand today (goods only). Filling it turns on stock tracking', 'example' => '25', 'aliases' => ['opening stock', 'stock', 'qty', 'quantity', 'stock in hand', 'closing stock']],
            'low_stock_level' => ['label' => 'Low Stock Alert', 'help' => 'Warn when stock falls to this quantity (goods only)', 'example' => '5', 'aliases' => ['low stock', 'reorder level', 'minimum stock', 'min stock', 'reorder point']],
            'description' => ['label' => 'Description', 'help' => 'Optional extra line on the invoice', 'example' => '', 'aliases' => ['details', 'notes']],
        ];
    }

    public function prepare(array $row): array
    {
        $errors = [];
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Item Name is required.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Item Name is longer than 255 characters.';
        }

        $hsn = self::digits($row['hsn_sac'] ?? '');
        if ($hsn !== '' && (strlen($hsn) < 4 || strlen($hsn) > 8)) {
            $errors[] = "HSN/SAC \"{$row['hsn_sac']}\" should be 4 to 8 digits.";
        }
        if ($hsn === '' && ! empty($this->company->gstin)) {
            $errors[] = 'HSN/SAC is required for a GST-registered business.';
        }

        $kindRaw = Str::lower(trim($row['kind'] ?? ''));
        $kind = match (true) {
            in_array($kindRaw, ['service', 'services', 's', 'sac'], true) => 'service',
            in_array($kindRaw, ['goods', 'good', 'g', 'product', 'hsn'], true) => 'goods',
            $kindRaw === '' => str_starts_with($hsn, '99') ? 'service' : 'goods',
            default => null,
        };
        if ($kind === null) {
            $errors[] = "Type \"{$row['kind']}\" should be Goods or Service.";
        }

        $units = collect(config('uqc_units.codes'))->pluck('code')->all();
        $unit = strtoupper(trim($row['unit'] ?? '')) ?: ($kind === 'service' ? 'OTH' : 'NOS');
        if (! in_array($unit, $units, true)) {
            $errors[] = "Unit \"{$unit}\" is not a GST unit code (" . implode(', ', array_slice($units, 0, 8)) . '…).';
        }

        $rate = self::number($row['rate'] ?? '');
        if ($rate === null || $rate < 0) {
            $errors[] = 'Sale Price must be a number of 0 or more.';
        }

        $gst = self::number($row['gst_rate'] ?? '');
        $allowed = array_map('floatval', config('gst.allowed_values'));
        if ($gst === null || ! in_array((float) $gst, $allowed, true)) {
            $errors[] = 'GST % "' . ($row['gst_rate'] ?? '') . '" is not a GST rate (' . implode(', ', array_map(fn ($r) => rtrim(rtrim(number_format($r, 2), '0'), '.'), $allowed)) . ').';
        }

        $sku = trim($row['sku'] ?? '');
        if (mb_strlen($sku) > 60) {
            $errors[] = 'Item Code is longer than 60 characters.';
        }

        $purchase = self::number($row['purchase_rate'] ?? '');
        if ($purchase !== null && $purchase < 0) {
            $errors[] = 'Purchase Price must be a number of 0 or more.';
        }
        $opening = self::number($row['opening_stock'] ?? '');
        $lowLevel = self::number($row['low_stock_level'] ?? '');
        if ($opening !== null && $opening < 0) {
            $errors[] = 'Opening Stock cannot be negative.';
        }
        if ($lowLevel !== null && $lowLevel < 0) {
            $errors[] = 'Low Stock Alert cannot be negative.';
        }
        // Stock is counted for goods only; a service with a quantity is ignored, not refused.
        $tracksStock = $kind === 'goods' && ($opening !== null || $lowLevel !== null);

        if ($errors) {
            return ['data' => null, 'errors' => $errors, 'duplicate' => null];
        }

        $data = [
            'name' => $name,
            'sku' => $sku ?: null,
            'kind' => $kind,
            'hsn_sac' => $hsn,
            'unit' => $unit,
            'rate' => round($rate, 2),
            'gst_rate' => (float) $gst,
            'description' => trim($row['description'] ?? '') ?: null,
            'purchase_rate' => $purchase === null ? null : round($purchase, 2),
            'track_stock' => $tracksStock,
            'opening_stock' => $tracksStock ? round($opening ?? 0, 3) : 0,
            'low_stock_level' => $tracksStock && $lowLevel !== null ? round($lowLevel, 3) : null,
            'is_active' => true,
        ];

        return ['data' => $data, 'errors' => [], 'duplicate' => $this->duplicateOf($data)];
    }

    public function fileKey(array $data): string
    {
        return $data['sku'] ? 'sku:' . Str::lower($data['sku']) : 'name:' . Str::lower($data['name']);
    }

    private function duplicateOf(array $data): ?string
    {
        if ($this->existing === null) {
            $this->existing = ['sku' => [], 'name' => []];
            foreach ($this->company->products()->get(['name', 'sku']) as $p) {
                if ($p->sku) {
                    $this->existing['sku'][Str::lower($p->sku)] = $p->name;
                }
                $this->existing['name'][Str::lower(trim($p->name))] = $p->name;
            }
        }
        if ($data['sku'] && isset($this->existing['sku'][Str::lower($data['sku'])])) {
            return 'Item Code ' . $data['sku'] . ' already used by ' . $this->existing['sku'][Str::lower($data['sku'])];
        }
        if (isset($this->existing['name'][Str::lower($data['name'])])) {
            return 'Already an item named ' . $this->existing['name'][Str::lower($data['name'])];
        }

        return null;
    }

    public function create(array $data): void
    {
        $this->company->products()->create(array_merge($data, ['user_id' => $this->user->id]));
    }
}
