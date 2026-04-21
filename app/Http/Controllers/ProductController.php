<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
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
            ->paginate(20)
            ->withQueryString();

        return view('products.index', compact('products', 'company'));
    }

    public function create(): View
    {
        $product = new Product([
            'kind' => 'goods',
            'unit' => 'NOS',
            'gst_rate' => 18,
            'is_active' => true,
        ]);
        return view('products.edit', compact('product'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        $data = $this->validated($request, $company->id);
        $data['user_id'] = $user->id;
        $data['company_id'] = $company->id;

        $product = Product::create($data);
        return redirect()->route('products.index')
            ->with('status', "Product '{$product->name}' added.");
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorize($request, $product);
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize($request, $product);
        $data = $this->validated($request, $product->company_id, $product->id);
        $product->update($data);
        return redirect()->route('products.index')
            ->with('status', "Product '{$product->name}' updated.");
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize($request, $product);

        // Keep historical invoice items intact (GST audit trail). Soft-disable
        // if the product has ever been used; hard-delete only if never billed.
        if ($product->invoiceItems()->exists()) {
            $product->update(['is_active' => false]);
            return redirect()->route('products.index')
                ->with('status', "Product archived (has invoice history). It won't appear in new invoices.");
        }

        $product->delete();
        return redirect()->route('products.index')->with('status', 'Product deleted.');
    }

    /**
     * JSON endpoint used by the invoice form for autocomplete.
     * Scoped to the current active company.
     */
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

    private function authorize(Request $request, Product $product): void
    {
        abort_unless($product->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $uqcCodes = collect(config('uqc_units.codes'))->pluck('code')->all();
        $gstRates = config('gst.allowed_values');

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable', 'string', 'max:60',
                Rule::unique('products', 'sku')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($ignoreId),
            ],
            'kind' => ['required', 'in:goods,service'],
            // HSN: 4/6/8 digits (goods). SAC: 6 digits starting with 99 (service).
            // Allow either to keep the form simple; shopkeepers commonly enter 4.
            'hsn_sac' => ['required', 'string', 'regex:/^[0-9]{4,8}$/'],
            'unit' => ['required', 'string', Rule::in($uqcCodes)],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'gst_rate' => ['required', 'numeric', Rule::in($gstRates)],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
