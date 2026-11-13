<?php

namespace App\Http\Controllers;

use App\Services\ProductRules;

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

        // active | archived | all.
        //
        // This used to be a single "Show archived" checkbox, which did not say
        // what it did: ticking it did not add the archived products to the list,
        // it replaced the list with only them. Three named states say plainly
        // which set you are looking at.
        //
        // `only_inactive=1` was the old spelling and is still sitting in
        // bookmarks and browser history, so it keeps working and means archived.
        $status = $request->query('status');
        if (! in_array($status, ['active', 'archived', 'all'], true)) {
            $status = $request->boolean('only_inactive') ? 'archived' : 'active';
        }

        $products = $company->products()
            // The list asks each row whether it has any history, to decide
            // between Archive and Delete. Counting here keeps that one query
            // instead of one per row.
            ->withCount(['invoiceItems', 'purchaseBillItems', 'quotationItems', 'stockMovements'])
            // Stock on hand for items that track it, in the same query.
            ->withSum('stockMovements', 'quantity')
            // Stock filters use the real stock on hand: opening stock plus every movement.
            ->tap(fn ($q) => ProductRules::stockFilter($q, $request->stock))
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->whereLiteralLike('name', $s)
                  ->orWhereLiteralLike('sku', $s)
                  ->orWhereLiteralLike('hsn_sac', $s);
            }))
            ->when($request->kind, fn ($q, $k) => $q->where('kind', $k))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'archived', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Shown on the Archived tab. Without it there is no way to tell an
        // empty archive from a filter that is hiding things from you.
        $archivedCount = $company->products()->where('is_active', false)->count();

        // Inventory at a glance, for items that track stock: what it is worth,
        // what is running low and what has run out.
        // The value counts archived items too: their goods are still on the
        // shelf, and the card opens the Stock summary, which counts them.
        $allTracked = $company->products()->where('track_stock', true)
            ->withSum('stockMovements', 'quantity')
            ->get(['id', 'is_active', 'opening_stock', 'low_stock_level', 'purchase_rate', 'rate']);
        $tracked = $allTracked->where('is_active', true);
        $inventory = $tracked->isEmpty() ? null : [
            'items' => $tracked->filter(fn ($p) => $p->stockOnHand() > 0)->count(),
            'value' => round($allTracked->sum(fn ($p) => round(max(0, $p->stockOnHand()) * (float) ($p->purchase_rate ?? $p->rate), 2)), 2),
            'low' => $tracked->filter(fn ($p) => $p->low_stock_level !== null && $p->stockOnHand() > 0 && $p->stockOnHand() <= (float) $p->low_stock_level)->count(),
            'out' => $tracked->filter(fn ($p) => $p->stockOnHand() <= 0)->count(),
        ];

        return view('products.index', compact('products', 'company', 'status', 'archivedCount', 'inventory'));
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
        $data = ProductRules::prepare($data);

        $product = Product::create($data);
        return redirect()->route('products.index')
            ->with('status', "Item '{$product->name}' added.");
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
        $data = ProductRules::prepare($data);
        $product->update($data);
        return redirect()->route('products.index')
            ->with('status', "Item '{$product->name}' updated.");
    }

    /** Stock history of one item, with a running balance, and the adjust form. */
    public function stock(Request $request, Product $product): View
    {
        $this->authorize($request, $product);
        $history = app(\App\Services\StockLedger::class)->history($product);

        return view('products.stock', [
            'product' => $product,
            'rows' => $history['rows'],
            'onHand' => $history['on_hand'],
        ]);
    }

    /**
     * Add or remove stock by hand (damage, a return), or enter a physical
     * count (see StockLedger::applyAdjustment).
     */
    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $this->authorize($request, $product);
        abort_unless($product->track_stock, 422, 'Turn on stock tracking for this item first.');
        $ledger = app(\App\Services\StockLedger::class);
        $direction = $request->input('direction');
        $data = $request->validate($ledger->adjustmentRules(is_string($direction) ? $direction : null), $ledger->adjustmentMessages(is_string($direction) ? $direction : null));

        $done = $ledger->applyAdjustment($product, $data, $request->user());

        return redirect()->route('products.stock', $product)->with('status', $done['message']);
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize($request, $product);

        // Keep history intact (GST audit trail, stock ledger). Archive the item
        // if it has ever been billed, bought or quoted, or has stock movements;
        // delete it only if it was never used. Deleting would also erase its
        // stock history, which cascades with the item.
        $usedOn = $product->usedOn();
        if ($usedOn !== null) {
            $product->update(['is_active' => false]);
            return redirect()->route('products.index')
                ->with('status', "'{$product->name}' is used on {$usedOn}, so it was archived, not deleted. It will not appear on new invoices. Restore it any time from the Archived tab.");
        }

        $product->delete();
        return redirect()->route('products.index')
            ->with('status', "'{$product->name}' deleted.");
    }

    /**
     * Put an archived product back in the catalogue.
     *
     * Archiving was reachable from the list but un-archiving was not. The only
     * route back was to tick a checkbox labelled "Show archived", open Edit on
     * the row, and happen to notice an "Active" tick box further down the form.
     * People reasonably read that as "archived means gone", which is the
     * opposite of what archiving is for.
     */
    public function restore(Request $request, Product $product): RedirectResponse
    {
        $this->authorize($request, $product);

        $product->update(['is_active' => true]);

        return redirect()->route('products.index')
            ->with('status', "'{$product->name}' restored. It will appear on new invoices again.");
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
                $w->whereLiteralLike('name', $q)
                  ->orWhereLiteralLike('sku', $q)
                  ->orWhereLiteralLike('hsn_sac', $q);
            }))
            ->orderBy('name')
            ->limit(50)
            ->withSum('stockMovements', 'quantity')
            ->get()
            // "stock" is on hand for items that track it, null otherwise; the
            // invoice line shows it and warns when selling more than that.
            ->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'hsn_sac' => $p->hsn_sac, 'unit' => $p->unit,
                'rate' => $p->rate, 'gst_rate' => $p->gst_rate, 'purchase_rate' => $p->purchase_rate,
                'stock' => $p->track_stock ? $p->stockOnHand() : null,
            ]);

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
            'message' => "Item '{$product->name}' added.",
        ], 201);
    }

    private function authorize(Request $request, Product $product): void
    {
        abort_unless($product->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate(ProductRules::rules($companyId, $ignoreId));
    }
}
