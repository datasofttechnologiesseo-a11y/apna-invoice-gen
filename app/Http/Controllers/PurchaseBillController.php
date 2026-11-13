<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Models\State;
use App\Services\PurchaseBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Purchase invoices - the supplier invoices you receive. The purchase side of
 * the ledger that GSTR-3B's input credit and 3.1(d) are built from, and the
 * payables you owe vendors.
 */
class PurchaseBillController extends Controller
{
    public function __construct(private readonly PurchaseBook $book) {}

    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        // From the vendors list: one vendor's purchase invoices.
        $vendor = $request->filled('vendor') ? $company->vendors()->find((int) $request->query('vendor')) : null;

        $bills = $this->book->listQuery($company, $request, $vendor)
            ->paginate(25)
            ->withQueryString();

        return view('purchases.index', [
            'company' => $company,
            'bills' => $bills,
            'vendor' => $vendor,
            'summary' => $this->book->summary($company),
            'salesStats' => $this->salesStats($company),
        ]);
    }

    public function create(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        // Arriving from the GSTR-2B match: the supplier's invoice number and
        // date are known; pick the vendor by GSTIN when they are already saved.
        $bill = new PurchaseBill([
            'bill_date' => $request->date('bill_date')?->toDateString() ?? now()->toDateString(),
            'bill_number' => mb_substr((string) $request->query('bill_number', ''), 0, 40) ?: null,
            'itc_eligible' => true,
        ]);
        if ($gstin = strtoupper(trim((string) $request->query('vendor_gstin', '')))) {
            $bill->vendor_id = $company->vendors()->where('gstin', $gstin)->value('id');
        }

        return view('purchases.form', $this->formData($company, $bill, false) + [
            'prefillVendor' => $bill->vendor_id ? null : array_filter([
                'name' => mb_substr((string) $request->query('vendor_name', ''), 0, 160),
                'gstin' => $gstin ?: null,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $bill = $this->book->create($company, $user, $this->book->validated($request, $company));

        return redirect()->route('purchases.show', $bill)->with('status', $this->book->createdMessage($bill));
    }

    public function show(Request $request, PurchaseBill $purchaseBill): View
    {
        $this->authorizeBill($request, $purchaseBill);
        $purchaseBill->load(['vendor.state', 'items.product:id,name,track_stock', 'payments', 'company.state']);

        return view('purchases.show', [
            'bill' => $purchaseBill,
            'company' => $purchaseBill->company,
            'methods' => config('payment_methods.methods', []),
            'creditNotes' => $this->book->creditNotes($purchaseBill),
        ]);
    }

    public function edit(Request $request, PurchaseBill $purchaseBill): View
    {
        $this->authorizeBill($request, $purchaseBill);
        $purchaseBill->load('items');

        return view('purchases.form', $this->formData($purchaseBill->company, $purchaseBill, true));
    }

    public function update(Request $request, PurchaseBill $purchaseBill): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        $data = $this->book->validated($request, $purchaseBill->company, $purchaseBill->id);
        $this->book->update($purchaseBill, $request->user(), $data);

        return redirect()->route('purchases.show', $purchaseBill)->with('status', 'Purchase invoice updated.');
    }

    public function destroy(Request $request, PurchaseBill $purchaseBill): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        $label = $this->book->delete($purchaseBill);

        return redirect()->route('purchases.index')->with('status', "Purchase invoice {$label} deleted.");
    }

    public function storePayment(Request $request, PurchaseBill $purchaseBill): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        $data = $request->validate($this->book->paymentRules($purchaseBill));
        $this->book->recordPayment($purchaseBill, $request->user(), $data);

        return back()->with('status', 'Payment of ₹' . inr($data['amount']) . " to {$purchaseBill->vendor->name} recorded.");
    }

    public function destroyPayment(Request $request, PurchaseBill $purchaseBill, PurchasePayment $purchasePayment): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        abort_unless($purchasePayment->purchase_bill_id === $purchaseBill->id, 404);

        $this->book->removePayment($purchaseBill, $purchasePayment);

        return back()->with('status', 'Payment removed. Purchase invoice balance restored.');
    }

    // ------------------------------------------------------------------

    private function formData($company, PurchaseBill $bill, bool $isEdit): array
    {
        $vendors = $company->vendors()->with('state:id,name,gst_code')->orderBy('name')->get();
        if ($bill->exists) {
            $bill->loadMissing('items.product:id,name');
        }
        $items = $bill->exists
            ? $bill->items->map(fn ($i) => [
                'product_id' => $i->product_id ? (string) $i->product_id : '',
                // Shown in the Item box even if the item was archived since.
                'product_name' => $i->product?->name,
                'description' => $i->description, 'hsn_sac' => $i->hsn_sac, 'quantity' => (float) $i->quantity,
                'unit' => $i->unit, 'rate' => (float) $i->rate, 'discount' => (float) $i->discount, 'gst_rate' => (float) $i->gst_rate,
            ])->values()->all()
            : [];

        return [
            'company' => $company,
            'bill' => $bill,
            'isEdit' => $isEdit,
            'vendors' => $vendors,
            'vendorStates' => $vendors->mapWithKeys(fn ($v) => [$v->id => $v->state_id]),
            'states' => State::orderBy('name')->get(),
            'utStateIds' => State::where('is_union_territory', true)->pluck('id')->values(),
            'items' => $items,
            // Items a line can be linked to (goods): picking one fills the line,
            // and for items that track stock the purchase brings stock in.
            'products' => $company->products()->where('kind', 'goods')->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'sku', 'hsn_sac', 'unit', 'gst_rate', 'rate', 'purchase_rate', 'track_stock'])
                ->map(fn ($p) => [
                    'id' => (string) $p->id, 'name' => $p->name, 'sku' => (string) $p->sku, 'hsn_sac' => $p->hsn_sac, 'unit' => $p->unit,
                    'gst_rate' => (float) $p->gst_rate, 'rate' => (float) ($p->purchase_rate ?? 0), 'track' => (bool) $p->track_stock,
                ])->values(),
            'categories' => config('expense_categories', []),
            'methods' => config('payment_methods.methods', []),
            // The form checks the s.16(4) time limit itself as the date is typed.
            'creditNotes' => $bill->exists ? array_diff_key($this->book->creditNotes($bill->loadMissing('payments')), ['time_bar' => true]) : [],
        ];
    }

    private function salesStats($company): array
    {
        return [
            'invoices' => [
                'total' => $company->invoices()->count(),
                'open' => $company->invoices()->whereIn('status', ['final', 'partially_paid'])->where('balance', '>', 0)->count(),
            ],
            'purchases' => [
                'total' => $company->purchaseBills()->count(),
                'unpaid' => $company->purchaseBills()->where('balance', '>', 0)->count(),
            ],
        ];
    }

    private function authorizeBill(Request $request, PurchaseBill $bill): void
    {
        abort_unless($bill->user_id === $request->user()->id, 403);
    }
}
