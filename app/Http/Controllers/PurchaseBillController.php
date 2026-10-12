<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Models\State;
use App\Models\Vendor;
use App\Services\InvoiceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Purchase invoices - the supplier invoices you receive. The purchase side of
 * the ledger that GSTR-3B's input credit and 3.1(d) are built from, and the
 * payables you owe vendors.
 */
class PurchaseBillController extends Controller
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        $bills = $company->purchaseBills()
            ->with('vendor:id,name,gstin')
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('bill_number', 'like', "%{$s}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$s}%")
                        ->orWhere('gstin', 'like', '%' . strtoupper($s) . '%'));
            }))
            ->when($request->status === 'unpaid', fn ($q) => $q->where('balance', '>', 0))
            ->when($request->status === 'paid', fn ($q) => $q->where('balance', '<=', 0))
            ->when($request->status === 'overdue', fn ($q) => $q->where('balance', '>', 0)->whereDate('due_date', '<', today()))
            ->when($request->from, fn ($q, $d) => $q->whereDate('bill_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('bill_date', '<=', $d))
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $open = $company->purchaseBills()->where('balance', '>', 0);
        $summary = [
            'payable' => (float) (clone $open)->sum('balance'),
            'open_count' => (clone $open)->count(),
            'overdue' => (float) (clone $open)->whereDate('due_date', '<', today())->sum('balance'),
            'due_7_days' => (float) (clone $open)->whereDate('due_date', '>=', today())->whereDate('due_date', '<=', today()->addDays(7))->sum('balance'),
            'itc_this_month' => (float) $company->purchaseBills()
                ->where('itc_eligible', true)
                ->whereBetween('bill_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('total_tax'),
        ];

        return view('purchases.index', [
            'company' => $company,
            'bills' => $bills,
            'summary' => $summary,
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
        $data = $this->validated($request, $company);

        if ($company->isBooksLockedOn($data['bill_date'])) {
            return back()->withInput()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. Cannot add a purchase invoice dated on or before that.");
        }
        // A payment made with the bill is checked like any other payment.
        if ((float) ($data['paid_now'] ?? 0) > 0 && $company->isBooksLockedOn($data['paid_on'] ?? $data['bill_date'])) {
            return back()->withInput()->withErrors(['paid_on' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. Date the payment after that."]);
        }

        $bill = DB::transaction(function () use ($company, $user, $data) {
            $bill = $this->persist($company, $user, new PurchaseBill(), $data);

            // "Paid now": the common case of a bill settled on the spot.
            if ((float) ($data['paid_now'] ?? 0) > 0) {
                $bill->payments()->create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'paid_on' => $data['paid_on'] ?? $data['bill_date'],
                    'amount' => min((float) $data['paid_now'], (float) $bill->grand_total),
                    'method' => $data['payment_method'] ?? 'neft',
                    'reference_number' => $data['reference_number'] ?? null,
                ]);
                $bill->syncPaymentTotals();
            }

            return $bill;
        });

        AuditLog::record('purchase_bill.created',
            "Purchase invoice {$bill->bill_number} from {$bill->vendor->name} · ₹" . inr((float) $bill->grand_total),
            $bill);

        return redirect()->route('purchases.show', $bill)
            ->with('status', "Purchase invoice {$bill->bill_number} recorded and added to expenses.");
    }

    public function show(Request $request, PurchaseBill $purchaseBill): View
    {
        $this->authorizeBill($request, $purchaseBill);
        $purchaseBill->load(['vendor.state', 'items', 'payments', 'company.state']);

        return view('purchases.show', [
            'bill' => $purchaseBill,
            'company' => $purchaseBill->company,
            'methods' => config('payment_methods.methods', []),
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
        $company = $purchaseBill->company;
        $data = $this->validated($request, $company, $purchaseBill->id);

        if ($company->isBooksLockedOn($purchaseBill->bill_date) || $company->isBooksLockedOn($data['bill_date'])) {
            return back()->withInput()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This purchase invoice cannot be changed.");
        }

        DB::transaction(function () use ($company, $request, $purchaseBill, $data) {
            $this->persist($company, $request->user(), $purchaseBill, $data);
            $purchaseBill->syncPaymentTotals();
            // Cutting the bill below what is already paid would leave a
            // negative balance that no payables list or aging shows.
            if ((float) $purchaseBill->paid_amount > (float) $purchaseBill->grand_total + 0.004) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Payments of ₹' . inr((float) $purchaseBill->paid_amount)
                        . ' are more than the new total of ₹' . inr((float) $purchaseBill->grand_total)
                        . '. Remove a payment on the purchase invoice page first, then edit.',
                ]);
            }
        });

        return redirect()->route('purchases.show', $purchaseBill)->with('status', 'Purchase invoice updated.');
    }

    public function destroy(Request $request, PurchaseBill $purchaseBill): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        $company = $purchaseBill->company;

        if ($company->isBooksLockedOn($purchaseBill->bill_date)) {
            return back()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This purchase invoice cannot be deleted.");
        }
        // Deleting the bill deletes its payments too - not if one sits in the closed period.
        if ($company->books_locked_until && $purchaseBill->payments()->whereDate('paid_on', '<=', $company->books_locked_until)->exists()) {
            return back()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This purchase invoice has a payment in that period and cannot be deleted.");
        }

        $label = "{$purchaseBill->bill_number} from {$purchaseBill->vendor->name}";
        DB::transaction(function () use ($purchaseBill) {
            if ($purchaseBill->expense_id) {
                \App\Models\Expense::where('id', $purchaseBill->expense_id)->delete();
            }
            app(\App\Services\StockLedger::class)->removePurchase($purchaseBill);
            $purchaseBill->delete();
        });

        AuditLog::record('purchase_bill.deleted', "Purchase invoice {$label} deleted", null);

        return redirect()->route('purchases.index')->with('status', "Purchase invoice {$label} deleted.");
    }

    public function storePayment(Request $request, PurchaseBill $purchaseBill): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        $remaining = round(max(0, (float) $purchaseBill->balance), 2);
        $methods = array_keys(config('payment_methods.methods', []));

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$remaining}"],
            'method' => ['required', 'string', 'in:' . implode(',', $methods)],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($purchaseBill->company->isBooksLockedOn($data['paid_on'])) {
            return back()->withErrors(['paid_on' => "Books are locked up to {$purchaseBill->company->books_locked_until->format('d M Y')}. Date the payment after that."])->withInput();
        }

        DB::transaction(function () use ($request, $purchaseBill, $data) {
            $purchaseBill->payments()->create(array_merge($data, [
                'user_id' => $request->user()->id,
                'company_id' => $purchaseBill->company_id,
            ]));
            $purchaseBill->syncPaymentTotals();
        });

        return back()->with('status', 'Payment of ₹' . inr($data['amount']) . " to {$purchaseBill->vendor->name} recorded.");
    }

    public function destroyPayment(Request $request, PurchaseBill $purchaseBill, PurchasePayment $purchasePayment): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        abort_unless($purchasePayment->purchase_bill_id === $purchaseBill->id, 404);

        if ($purchaseBill->company->isBooksLockedOn($purchasePayment->paid_on)) {
            return back()->with('error', "Books are locked up to {$purchaseBill->company->books_locked_until->format('d M Y')}. This payment cannot be removed.");
        }

        DB::transaction(function () use ($purchaseBill, $purchasePayment) {
            $purchasePayment->delete();
            $purchaseBill->syncPaymentTotals();
        });

        return back()->with('status', 'Payment removed. Purchase invoice balance restored.');
    }

    // ------------------------------------------------------------------

    /**
     * Write the bill, its lines and the mirrored expense row from validated
     * input. Taxes come from InvoiceCalculator so a purchase line is rounded
     * exactly like a sales line (each head at half the rate).
     */
    private function persist($company, $user, PurchaseBill $bill, array $data): PurchaseBill
    {
        $vendor = $company->vendors()->findOrFail($data['vendor_id']);
        // A vendor saved with a GSTIN but no state still has a state: the
        // GSTIN's first two digits. Without it the bill fell back to CGST+SGST.
        $vendorStateId = $vendor->state_id
            ?: ($vendor->gstin ? \App\Models\State::where('gst_code', substr($vendor->gstin, 0, 2))->value('id') : null);
        $isInterstate = array_key_exists('is_interstate', $data)
            ? (bool) $data['is_interstate']
            : $this->calculator->isInterstate($company->state_id, $vendorStateId);
        $reverseCharge = (bool) ($data['reverse_charge'] ?? false);

        // Under reverse charge the heads are the tax YOU pay, so they are
        // computed normally; only the amount owed to the vendor leaves them out.
        $calc = $this->calculator->recalculate(new Invoice(), $data['items'], $isInterstate, false);
        $t = $calc['totals'];

        $owedToVendor = $reverseCharge ? $t['subtotal'] : $t['subtotal'] + $t['total_tax'];
        $grandTotal = round($owedToVendor);
        $roundOff = round($grandTotal - $owedToVendor, 2);

        $bill->fill([
            'user_id' => $bill->user_id ?? $user->id,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'bill_number' => trim($data['bill_number']),
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? null,
            'is_interstate' => $isInterstate,
            'reverse_charge' => $reverseCharge,
            'itc_eligible' => (bool) ($data['itc_eligible'] ?? true),
            'subtotal' => $t['subtotal'],
            'total_cgst' => $t['total_cgst'],
            'total_sgst' => $t['total_sgst'],
            'total_igst' => $t['total_igst'],
            'total_tax' => $t['total_tax'],
            'round_off' => $roundOff,
            'grand_total' => $grandTotal,
            'expense_category' => $data['expense_category'] ?? 'misc',
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        if (! $bill->exists) {
            $bill->balance = $grandTotal;
            $bill->status = PurchaseBill::STATUS_UNPAID;
        }
        $bill->save();

        $bill->items()->delete();
        // Lines linked to an item of this company keep the link: that is what
        // brings stock in for items that track it.
        $ownProducts = $company->products()->pluck('id')->all();
        $bill->items()->createMany(array_map(function ($row) use ($ownProducts) {
            $row['product_id'] = in_array((int) ($row['product_id'] ?? 0), $ownProducts, true) ? (int) $row['product_id'] : null;

            return $row;
        }, $calc['items']));
        app(\App\Services\StockLedger::class)->syncPurchase($bill);

        // Mirror into the expense ledger so the P&L and expense exports see
        // it (same pattern as cash memos). GST that is not claimable is still
        // part of what the purchase cost, so it travels as gst_amount with
        // itc_eligible = false and the P&L treats it as cost.
        $expenseAttrs = [
            'user_id' => $bill->user_id,
            'purchase_bill_id' => $bill->id,
            'entry_date' => $bill->bill_date,
            'category' => $bill->expense_category ?: 'misc',
            'vendor_name' => $vendor->name,
            'description' => "Purchase invoice {$bill->bill_number} from {$vendor->name}",
            'amount' => $t['subtotal'],
            'gst_amount' => $t['total_tax'],
            'is_interstate' => $isInterstate,
            'itc_eligible' => $bill->itc_eligible,
            // The expense ledger has its own, shorter list of payment methods.
            'payment_method' => match ($data['payment_method'] ?? 'neft') {
                'neft', 'rtgs', 'imps' => 'bank',
                'upi', 'card', 'cash', 'cheque' => $data['payment_method'],
                default => 'other',
            },
            'reference_number' => $bill->reference_number,
            'notes' => $bill->notes,
        ];
        if ($bill->expense_id && ($expense = \App\Models\Expense::find($bill->expense_id))) {
            $expense->update($expenseAttrs);
        } else {
            $expense = $company->expenses()->create($expenseAttrs);
            $bill->forceFill(['expense_id' => $expense->id])->save();
        }

        return $bill->load('vendor');
    }

    private function formData($company, PurchaseBill $bill, bool $isEdit): array
    {
        $vendors = $company->vendors()->with('state:id,name,gst_code')->orderBy('name')->get();
        $items = $bill->exists
            ? $bill->items->map(fn ($i) => [
                'product_id' => $i->product_id ? (string) $i->product_id : '',
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
                ->get(['id', 'name', 'hsn_sac', 'unit', 'gst_rate', 'rate', 'purchase_rate', 'track_stock'])
                ->map(fn ($p) => [
                    'id' => (string) $p->id, 'name' => $p->name, 'hsn_sac' => $p->hsn_sac, 'unit' => $p->unit,
                    'gst_rate' => (float) $p->gst_rate, 'rate' => (float) ($p->purchase_rate ?? 0), 'track' => (bool) $p->track_stock,
                ])->values(),
            'categories' => config('expense_categories', []),
            'methods' => config('payment_methods.methods', []),
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

    private function validated(Request $request, $company, ?int $ignoreId = null): array
    {
        $methods = array_keys(config('payment_methods.methods', []));
        $categories = array_keys(config('expense_categories', []));

        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'bill_number' => ['required', 'string', 'max:40',
                // One vendor cannot give you the same bill number twice.
                \Illuminate\Validation\Rule::unique('purchase_bills', 'bill_number')
                    ->where(fn ($q) => $q->where('company_id', $company->id)->where('vendor_id', (int) $request->input('vendor_id')))
                    ->ignore($ignoreId)],
            'bill_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'is_interstate' => ['nullable', 'boolean'],
            'reverse_charge' => ['nullable', 'boolean'],
            'itc_eligible' => ['nullable', 'boolean'],
            'expense_category' => ['nullable', 'string', 'in:' . implode(',', $categories)],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'paid_now' => ['nullable', 'numeric', 'min:0'],
            // One discount for the whole bill (bottom box), spread before tax.
            'bill_discount' => ['nullable', 'numeric', 'min:0'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', 'string', 'in:' . implode(',', $methods)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:' . implode(',', config('gst.allowed_values'))],
        ]);

        if (array_key_exists('bill_discount', $data)) {
            $gross = collect($data['items'])->sum(fn ($i) => round((float) $i['quantity'] * (float) $i['rate'], 2));
            if ((float) $data['bill_discount'] > $gross + 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bill_discount' => 'Discount cannot be more than the items total (₹' . inr($gross) . ').',
                ]);
            }
            $data['items'] = $this->calculator->apportionDiscount($data['items'], (float) ($data['bill_discount'] ?? 0));
        }

        // Checkboxes: absent means unticked. ITC defaults to ticked in the form.
        $data['itc_eligible'] = $request->boolean('itc_eligible');
        $data['reverse_charge'] = $request->boolean('reverse_charge');
        if ($request->has('is_interstate')) {
            $data['is_interstate'] = $request->boolean('is_interstate');
        }

        return $data;
    }
}
