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

    public function create(Request $request): View
    {
        $product = new Product([
            'kind' => 'goods',
            'unit' => 'NOS',
            'gst_rate' => 18,
            'is_active' => true,
        ]);
        // Pass company so the view can decide whether HSN/SAC is required —
        // Rule 46(g) only applies to GST-registered suppliers. Non-registered
        // shopkeepers shouldn't be forced to enter HSN at the catalogue level.
        $company = $request->user()->ensureCompany();
        return view('products.edit', compact('product', 'company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        $data = $this->validated($request, $company->id);
        $data['user_id'] = $user->id;
        $data['company_id'] = $company->id;
        // DB column is NOT NULL — coerce missing HSN to empty string. Validation
        // already enforced "required" for GST-registered suppliers; if we land
        // here without HSN, the user is un-registered and the field is optional.
        $data['hsn_sac'] = $data['hsn_sac'] ?? '';

        $product = Product::create($data);
        return redirect()->route('products.index')
            ->with('status', "Product '{$product->name}' added.");
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorize($request, $product);
        // Same rationale as create() — view needs the company's GSTIN status.
        $company = $request->user()->ensureCompany();
        return view('products.edit', compact('product', 'company'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize($request, $product);
        $data = $this->validated($request, $product->company_id, $product->id);
        // Same NOT-NULL coercion as store() — see comment there.
        $data['hsn_sac'] = $data['hsn_sac'] ?? '';
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

    /**
     * Lightweight JSON endpoint for "+ Add new product" inline-create from
     * inside the invoice form. Only name + rate + gst_rate are required; HSN,
     * SKU, kind, unit get sane defaults so a shopkeeper can save in two
     * keystrokes and fill the formal product record later from Products.
     *
     * Indian SME UX: most users at this point just want "name and price". The
     * full taxonomy (kind goods/service, UQC unit, 4-8 digit HSN) is captured
     * later in Products → Edit when they have time.
     */
    public function quickStore(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $gstRates = config('gst.allowed_values');
        $uqcCodes = collect(config('uqc_units.codes'))->pluck('code')->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // HSN/SAC is required by Rule 46(g) on the invoice line, but at the
            // product level we let the user defer it. They'll still type it into
            // the invoice row if missing. 4-8 digits if provided.
            'hsn_sac' => ['nullable', 'string', 'regex:/^[0-9]{4,8}$/'],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'gst_rate' => ['required', 'numeric', Rule::in($gstRates)],
            'unit' => ['nullable', 'string', Rule::in($uqcCodes)],
            'kind' => ['nullable', 'in:goods,service'],
        ]);

        $product = Product::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => $data['name'],
            'hsn_sac' => $data['hsn_sac'] ?? '',
            'rate' => $data['rate'],
            'gst_rate' => $data['gst_rate'],
            'unit' => $data['unit'] ?? 'NOS',
            'kind' => $data['kind'] ?? 'goods',
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'hsn_sac' => $product->hsn_sac,
            'unit' => $product->unit,
            'rate' => $product->rate,
            'gst_rate' => $product->gst_rate,
            'message' => "Product '{$product->name}' added.",
        ], 201);
    }

    private function authorize(Request $request, Product $product): void
    {
        abort_unless($product->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $uqcCodes = collect(config('uqc_units.codes'))->pluck('code')->all();
        $gstRates = config('gst.allowed_values');

        // HSN is required only if the supplier (company) is GST-registered —
        // Rule 46(g) doesn't apply to non-registered dealers, so forcing the
        // field would just be friction. Unregistered shopkeepers can still
        // add an HSN if they want; it just won't block save when missing.
        $company = \App\Models\Company::find($companyId);
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
            // HSN: 4/6/8 digits (goods). SAC: 6 digits starting with 99 (service).
            // Allow either to keep the form simple; shopkeepers commonly enter 4.
            // Required only when supplier is GST-registered (see above).
            'hsn_sac' => [$hsnRequired ? 'required' : 'nullable', 'string', 'regex:/^[0-9]{4,8}$/'],
            'unit' => ['required', 'string', Rule::in($uqcCodes)],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'gst_rate' => ['required', 'numeric', Rule::in($gstRates)],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
