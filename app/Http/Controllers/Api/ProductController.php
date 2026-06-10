<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Company;
use App\Models\Product;
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
                $w->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%")
                  ->orWhere('hsn_sac', 'like', "%{$s}%");
            }))
            ->when($request->kind, fn ($q, $k) => $q->where('kind', $k))
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
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('sku', 'like', "%{$q}%")
                  ->orWhere('hsn_sac', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'sku', 'hsn_sac', 'unit', 'rate', 'gst_rate']);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        $data = $this->validated($request, $company->id);
        $data['user_id'] = $user->id;
        $data['company_id'] = $company->id;
        $data['hsn_sac'] = $data['hsn_sac'] ?? '';

        $product = Product::create($data);

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
        $data['hsn_sac'] = $data['hsn_sac'] ?? '';
        $product->update($data);

        return new ProductResource($product);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        // Keep historical invoice items intact (GST audit trail). Soft-disable
        // if the product has ever been used; hard-delete only if never billed.
        if ($product->invoiceItems()->exists()) {
            $product->update(['is_active' => false]);

            return response()->json([
                'message' => "Product archived (has invoice history). It won't appear in new invoices.",
                'archived' => true,
            ]);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.', 'archived' => false]);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $uqcCodes = collect(config('uqc_units.codes'))->pluck('code')->all();
        $gstRates = config('gst.allowed_values');

        $company = Company::find($companyId);
        $hsnRequired = ! empty($company?->gstin);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable', 'string', 'max:60',
                Rule::unique('products', 'sku')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($ignoreId),
            ],
            'kind' => ['required', 'in:goods,service'],
            'hsn_sac' => [$hsnRequired ? 'required' : 'nullable', 'string', 'regex:/^[0-9]{4,8}$/'],
            'unit' => ['required', 'string', Rule::in($uqcCodes)],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'gst_rate' => ['required', 'numeric', Rule::in($gstRates)],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
