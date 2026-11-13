<?php

namespace App\Http\Controllers\Api;

use App\Services\ProductRules;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Company;
use App\Models\Product;
use App\Services\StockLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->ensureCompany();

        $products = $company->products()
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->whereLiteralLike('name', $s)
                  ->orWhereLiteralLike('sku', $s)
                  ->orWhereLiteralLike('hsn_sac', $s);
            }))
            ->when($request->kind, fn ($q, $k) => $q->where('kind', $k))
            ->tap(fn ($q) => ProductRules::stockFilter($q, $request->stock))
            ->when($request->boolean('only_inactive'), fn ($q) => $q->where('is_active', false))
            ->when(! $request->boolean('only_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ProductResource::collection($products);
    }

    /** Autocomplete for the invoice line-item picker. */
    public function search(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();
        $q = trim((string) $request->query('q', ''));

        $products = $company->products()
            ->where('is_active', true)
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->whereLiteralLike('name', $q)
                  ->orWhereLiteralLike('sku', $q)
                  ->orWhereLiteralLike('hsn_sac', $q);
            }))
            ->orderBy('name')
            ->limit(50)
            ->withSum('stockMovements', 'quantity')
            ->get(['id', 'name', 'sku', 'hsn_sac', 'unit', 'rate', 'gst_rate', 'track_stock', 'opening_stock']);

        // Same fields as the website's item search, including stock on hand
        // (null for items that do not track stock), so the app can warn
        // before a line sells more than is on the shelf.
        return response()->json($products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'hsn_sac' => $p->hsn_sac,
            'unit' => $p->unit,
            'rate' => $p->rate,
            'gst_rate' => $p->gst_rate,
            'stock' => $p->track_stock ? $p->stockOnHand() : null,
        ])->values());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        $data = $this->validated($request, $company->id);
        $data['user_id'] = $user->id;
        $data['company_id'] = $company->id;

        // refresh(): column defaults (is_active, opening_stock) come back as stored
        $product = Product::create(ProductRules::prepare($data))->refresh();

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorizeProduct($request, $product);

        return new ProductResource($product);
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $this->authorizeProduct($request, $product);
        $data = $this->validated($request, $product->company_id, $product->id);
        $product->update(ProductRules::prepare($data));

        return new ProductResource($product);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        // Same rule as the web: an item billed, bought, quoted or with stock
        // entries is archived (history and stock ledger kept), never deleted.
        $usedOn = $product->usedOn();
        if ($usedOn !== null) {
            $product->update(['is_active' => false]);

            return response()->json([
                'message' => "'{$product->name}' is used on {$usedOn}, so it was archived, not deleted. It will not appear on new invoices. Restore it from the Archived tab on the web app.",
                'archived' => true,
            ]);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.', 'archived' => false]);
    }

    /** Put an archived item back in the catalogue (same as the web Restore). */
    public function restore(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);
        $product->update(['is_active' => true]);

        return response()->json([
            'message' => "'{$product->name}' restored. It will appear on new invoices again.",
            'data' => new ProductResource($product),
        ]);
    }

    /** Stock history of one item, latest first, with the running balance. */
    public function stock(Request $request, Product $product, StockLedger $ledger): JsonResponse
    {
        $this->authorizeProduct($request, $product);
        $history = $ledger->history($product);

        return response()->json([
            'product' => new ProductResource($product),
            'opening_stock' => (float) $product->opening_stock,
            'on_hand' => $history['on_hand'],
            'movements' => StockMovementResource::collection($history['rows']),
        ]);
    }

    /**
     * Manual stock adjustment: direction in | out | count, quantity, moved_on,
     * reason. Same rules as the web (StockLedger::applyAdjustment).
     */
    public function adjustStock(Request $request, Product $product, StockLedger $ledger): JsonResponse
    {
        $this->authorizeProduct($request, $product);
        abort_unless($product->track_stock, 422, 'Turn on stock tracking for this item first.');
        $direction = is_string($request->input('direction')) ? $request->input('direction') : null;
        $data = $request->validate($ledger->adjustmentRules($direction), $ledger->adjustmentMessages($direction));

        $done = $ledger->applyAdjustment($product, $data, $request->user());

        return response()->json([
            'message' => $done['message'],
            'changed' => $done['movement'] !== null,
            'movement' => $done['movement'] ? new StockMovementResource($done['movement']) : null,
            'on_hand' => $ledger->onHand($product),
        ], $done['movement'] ? 201 : 200);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate(ProductRules::rules($companyId, $ignoreId));
    }

    /** Units (UQC) the item form can pick from. */
    public function units(): JsonResponse
    {
        return response()->json(['data' => ProductRules::units()]);
    }
}
