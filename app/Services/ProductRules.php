<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Product (item) rules shared by the web ProductController and the API,
 * so the app and the website accept exactly the same items.
 */
class ProductRules
{
    /** Validation rules for creating/updating an item in a company. */
    public static function rules(int $companyId, ?int $ignoreId = null): array
    {
        $uqcCodes = collect(config('uqc_units.codes'))->pluck('code')->all();

        // HSN is required only if the supplier is GST-registered (Rule 46(g)).
        $hsnRequired = ! empty(Company::find($companyId)?->gstin);

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable', 'string', 'max:60',
                Rule::unique('products', 'sku')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($ignoreId),
            ],
            'kind' => ['required', 'in:goods,service'],
            // HSN: 4/6/8 digits (goods). SAC: 6 digits starting with 99 (service).
            'hsn_sac' => [$hsnRequired ? 'required' : 'nullable', 'string', 'regex:/^[0-9]{4,8}$/'],
            'unit' => ['required', 'string', Rule::in($uqcCodes)],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'gst_rate' => ['required', 'numeric', Rule::in(config('gst.allowed_values'))],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Stock (optional). Services never track stock.
            'track_stock' => ['nullable', 'boolean'],
            'opening_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'low_stock_level' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'purchase_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /** Validated data as stored: NOT NULL HSN, and stock fields normalised. */
    public static function prepare(array $data): array
    {
        // DB column is NOT NULL — an unregistered seller may leave HSN empty.
        $data['hsn_sac'] = $data['hsn_sac'] ?? '';

        if (array_key_exists('track_stock', $data)) {
            $track = ($data['kind'] ?? 'goods') === 'goods' && ! empty($data['track_stock']);
            $data['track_stock'] = $track;
            $data['opening_stock'] = $track ? (float) ($data['opening_stock'] ?? 0) : ($data['opening_stock'] ?? 0);
            if (array_key_exists('low_stock_level', $data) && $data['low_stock_level'] === '') {
                $data['low_stock_level'] = null;
            }
        }

        return $data;
    }

    /** Filter a product query by stock state: 'low' (at or under the alert level) or 'out'. */
    public static function stockFilter(Builder $q, ?string $stock): Builder
    {
        if (! in_array($stock, ['low', 'out'], true)) {
            return $q;
        }
        // Real stock on hand: opening stock plus every movement.
        $onHand = '(products.opening_stock + COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.product_id = products.id), 0))';
        $q->where('track_stock', true);

        return $stock === 'out'
            ? $q->whereRaw("{$onHand} <= 0")
            : $q->whereNotNull('low_stock_level')->whereRaw("{$onHand} > 0")->whereRaw("{$onHand} <= products.low_stock_level");
    }

    /** Allowed units, for pickers. */
    public static function units(): array
    {
        return collect(config('uqc_units.codes'))->values()->all();
    }
}
