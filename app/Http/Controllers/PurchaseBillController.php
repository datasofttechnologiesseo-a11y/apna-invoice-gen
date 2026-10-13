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

        // From the vendors list: one vendor's purchase invoices.
        $vendor = $request->filled('vendor') ? $company->vendors()->find((int) $request->query('vendor')) : null;

        $bills = $company->purchaseBills()
            ->with('vendor:id,name,gstin')
            ->when($vendor, fn ($q) => $q->where('vendor_id', $vendor->id))
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->whereLiteralLike('bill_number', $s)
                    ->orWhereHas('vendor', fn ($v) => $v->whereLiteralLike('name', $s)
                        ->orWhereLiteralLike('gstin', strtoupper($s)));
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
            // The credit this month's GSTR-3B takes on purchase invoices: by the
            // month a bill is booked in (a late one counts when it is entered),
            // leaving out credit past the s.16(4) time limit.
            'itc_this_month' => round((float) $company->purchaseBills()
                ->where('itc_eligible', true)
                ->whereBetween(DB::raw('COALESCE(booked_on, bill_date)'), [now()->startOfMonth(), now()->endOfMonth()])
                ->get(['id', 'bill_date', 'booked_on', 'total_tax'])
                ->reject(fn (PurchaseBill $b) => $b->isItcTimeBarred(now()->endOfMonth()))
                ->sum('total_tax'), 2),
        ];

        return view('purchases.index', [
            'company' => $company,
            'bills' => $bills,
            'vendor' => $vendor,
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

        // A supplier invoice that arrives after its month is locked keeps its
        // own date but is booked today, so the locked month's figures (and
        // the GSTR-3B already filed for it) do not change. Its input credit is
        // claimed in this month's return (s.16(4) allows a later month).
        $lateBooking = false;
        if ($company->isBooksLockedOn($data['bill_date'])) {
            if ($company->isBooksLockedOn(now()->toDateString())) {
                return back()->withInput()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. Cannot add a purchase invoice dated on or before that.");
            }
            $data['booked_on'] = now()->toDateString();
            $lateBooking = true;
        }
        // A payment made with the bill is checked like any other payment.
        if ((float) ($data['paid_now'] ?? 0) > 0 && $company->isBooksLockedOn($data['paid_on'] ?? ($data['booked_on'] ?? $data['bill_date']))) {
            return back()->withInput()->withErrors(['paid_on' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. Date the payment after that."]);
        }

        $bill = DB::transaction(function () use ($company, $user, $data) {
            $bill = $this->persist($company, $user, new PurchaseBill(), $data);

            // "Paid now": the common case of a bill settled on the spot.
            if ((float) ($data['paid_now'] ?? 0) > 0) {
                $bill->payments()->create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'paid_on' => $data['paid_on'] ?? ($data['booked_on'] ?? $data['bill_date']),
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

        $status = $lateBooking
            ? "Purchase invoice {$bill->bill_number} recorded. Its date ({$bill->bill_date->format('d M Y')}) is in a locked period, so it is booked on {$bill->booked_on->format('d M Y')} and its input tax credit counts in " . $bill->booked_on->format('F Y') . "'s GSTR-3B."
            : "Purchase invoice {$bill->bill_number} recorded and added to expenses.";
        // Recorded all the same (it is a real cost), but its credit is lost.
        if ($bill->itc_eligible && $bill->isItcTimeBarred()) {
            $status = "Purchase invoice {$bill->bill_number} recorded"
                . ($lateBooking ? " and booked on {$bill->booked_on->format('d M Y')}, as its date is in a locked period. " : ' and added to expenses. ')
                . $this->timeBarNote($bill);
        }

        return redirect()->route('purchases.show', $bill)->with('status', $status);
    }

    public function show(Request $request, PurchaseBill $purchaseBill): View
    {
        $this->authorizeBill($request, $purchaseBill);
        $purchaseBill->load(['vendor.state', 'items.product:id,name,track_stock', 'payments', 'company.state']);

        return view('purchases.show', [
            'bill' => $purchaseBill,
            'company' => $purchaseBill->company,
            'methods' => config('payment_methods.methods', []),
            'creditNotes' => $this->creditNotes($purchaseBill),
        ]);
    }

    /**
     * Soft warnings about the input credit on a bill, for its page and its
     * edit form: past the s.16(4) time limit, or reversed under Rule 37
     * because the supplier was not paid within 180 days. Nothing is blocked.
     *
     * @return array{time_bar?: string, rule37?: string}
     */
    private function creditNotes(PurchaseBill $bill): array
    {
        if (! $bill->exists || ! $bill->itc_eligible) {
            return [];
        }
        if ($bill->isItcTimeBarred()) {
            return ['time_bar' => $this->timeBarNote($bill)];
        }
        $notes = [];
        if ($bill->rule37Applies() && (float) $bill->balance > 0 && now()->gt($bill->rule37PayBy()->endOfDay())) {
            $payBy = $bill->rule37PayBy()->toDateString();
            $paidInTime = (float) $bill->payments->filter(fn ($p) => $p->paid_on->toDateString() <= $payBy)->sum('amount');
            $unpaid = max(0.0, round((float) $bill->grand_total - $paidInTime, 2));
            $credit = round((float) $bill->total_tax * $unpaid / (float) $bill->grand_total, 2);
            $notes['rule37'] = 'Not paid within 180 days of the invoice date (by ' . $bill->rule37PayBy()->format('d M Y') . '). Input credit of ₹' . inr($credit)
                . ' on the unpaid part is reversed in the GSTR-3B for ' . $bill->rule37ReversalOn()->format('F Y')
                . ' (Rule 37). It is claimed again in the month you pay.';
        }

        return $notes;
    }

    /** Why the credit on a time-barred bill is left out of GSTR-3B. */
    private function timeBarNote(PurchaseBill $bill): string
    {
        $deadline = PurchaseBill::itcPeriodDeadline($bill->bill_date);

        return 'Its input tax credit is time-barred: under Section 16(4) credit on an invoice dated '
            . $bill->bill_date->format('d M Y') . ' can be claimed only up to the GSTR-3B for ' . $deadline->format('F Y')
            . '. The GST is left out of GSTR-3B and stays a cost.';
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

        $lockedNow = $purchaseBill->booked_on
            // Booked late: its booked date decides, and its own (locked) date
            // may stay as it is, but cannot move to another locked date.
            ? $company->isBooksLockedOn($purchaseBill->booked_on)
                || ($data['bill_date'] !== $purchaseBill->bill_date->toDateString() && $company->isBooksLockedOn($data['bill_date']))
            : $company->isBooksLockedOn($purchaseBill->bill_date) || $company->isBooksLockedOn($data['bill_date']);
        if ($lockedNow) {
            return back()->withInput()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This purchase invoice cannot be changed.");
        }

        $before = $this->auditSnapshot($purchaseBill);
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

        // The bill carries input tax credit, so an edit is logged with the
        // figures before and after.
        $after = $this->auditSnapshot($purchaseBill);
        $total = (float) $before['grand_total'] === (float) $after['grand_total']
            ? '₹' . inr((float) $after['grand_total'])
            : '₹' . inr((float) $before['grand_total']) . ' to ₹' . inr((float) $after['grand_total']);
        AuditLog::record('purchase_bill.updated',
            "Purchase invoice {$purchaseBill->bill_number} from {$purchaseBill->vendor->name} updated · {$total}",
            $purchaseBill,
            ['before' => $before, 'after' => $after]);

        return redirect()->route('purchases.show', $purchaseBill)->with('status', 'Purchase invoice updated.');
    }

    public function destroy(Request $request, PurchaseBill $purchaseBill): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        $company = $purchaseBill->company;

        if ($company->isBooksLockedOn($purchaseBill->bookedDate())) {
            return back()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This purchase invoice cannot be deleted.");
        }
        // Deleting the bill deletes its payments too - not if one sits in the closed period.
        if ($company->books_locked_until && $purchaseBill->payments()->whereDate('paid_on', '<=', $company->books_locked_until)->exists()) {
            return back()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This purchase invoice has a payment in that period and cannot be deleted.");
        }

        $label = "{$purchaseBill->bill_number} from {$purchaseBill->vendor->name}";
        $snapshot = $this->auditSnapshot($purchaseBill);
        DB::transaction(function () use ($purchaseBill) {
            if ($purchaseBill->expense_id) {
                \App\Models\Expense::where('id', $purchaseBill->expense_id)->delete();
            }
            app(\App\Services\StockLedger::class)->removePurchase($purchaseBill);
            $purchaseBill->delete();
        });

        AuditLog::record('purchase_bill.deleted', "Purchase invoice {$label} deleted · ₹" . inr((float) $snapshot['grand_total']), $purchaseBill, $snapshot);

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
        $reference = trim((string) ($data['reference_number'] ?? ''));
        $data['reference_number'] = $reference !== '' ? $reference : null;

        $payment = DB::transaction(function () use ($request, $purchaseBill, $data, $reference) {
            // Lock the bill row, then check again: the cap above was read
            // before the lock, so a double submit or a second tab would
            // otherwise pay the vendor twice and leave a negative payable.
            $bill = PurchaseBill::whereKey($purchaseBill->id)->lockForUpdate()->firstOrFail();

            // The bank reference identifies one transfer. A part payment sent
            // twice passes the cap, so the reference is what stops it.
            if ($reference !== '' && $bill->payments()->whereRaw('LOWER(reference_number) = ?', [mb_strtolower($reference)])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reference_number' => "A payment with reference {$reference} is already recorded on this purchase invoice.",
                ]);
            }
            $owed = round((float) $bill->grand_total - (float) $bill->payments()->sum('amount'), 2);
            if ((float) $data['amount'] > $owed + 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => $owed > 0
                        ? 'A payment was just recorded. Only ₹' . inr($owed) . ' is still owed on this purchase invoice.'
                        : 'A payment was just recorded. Nothing more is owed on this purchase invoice.',
                ]);
            }

            $payment = $bill->payments()->create(array_merge($data, [
                'user_id' => $request->user()->id,
                'company_id' => $bill->company_id,
            ]));
            $bill->syncPaymentTotals();
            $purchaseBill->setRawAttributes($bill->getAttributes(), true);

            return $payment;
        });

        AuditLog::record('purchase_payment.recorded',
            '₹' . inr((float) $payment->amount) . ' paid · ' . $this->methodLabel($payment->method)
                . ($payment->reference_number ? " · Ref {$payment->reference_number}" : '')
                . " · Purchase invoice {$purchaseBill->bill_number} from {$purchaseBill->vendor->name}",
            $payment,
            $this->paymentSnapshot($payment, $purchaseBill));

        return back()->with('status', 'Payment of ₹' . inr($data['amount']) . " to {$purchaseBill->vendor->name} recorded.");
    }

    public function destroyPayment(Request $request, PurchaseBill $purchaseBill, PurchasePayment $purchasePayment): RedirectResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        abort_unless($purchasePayment->purchase_bill_id === $purchaseBill->id, 404);

        if ($purchaseBill->company->isBooksLockedOn($purchasePayment->paid_on)) {
            return back()->with('error', "Books are locked up to {$purchaseBill->company->books_locked_until->format('d M Y')}. This payment cannot be removed.");
        }

        $snapshot = $this->paymentSnapshot($purchasePayment, $purchaseBill);
        DB::transaction(function () use ($purchaseBill, $purchasePayment) {
            $purchasePayment->delete();
            $purchaseBill->syncPaymentTotals();
        });

        // The payment row is gone; the entry keeps what it was.
        AuditLog::record('purchase_payment.removed',
            '₹' . inr((float) $snapshot['amount']) . ' payment removed · ' . $this->methodLabel($snapshot['method'])
                . " · Purchase invoice {$purchaseBill->bill_number} from {$purchaseBill->vendor->name}",
            $purchaseBill,
            $snapshot);

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
            'booked_on' => $data['booked_on'] ?? $bill->booked_on,
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
            'expense_category' => $data['expense_category'] ?? ($bill->expense_category ?: $this->defaultCategory($company, $data['items'])),
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
            'entry_date' => $bill->bookedDate(),
            'category' => $bill->expense_category ?: 'misc',
            'vendor_name' => $vendor->name,
            'description' => "Purchase invoice {$bill->bill_number} from {$vendor->name}",
            'amount' => $t['subtotal'],
            'gst_amount' => $t['total_tax'],
            'is_interstate' => $isInterstate,
            // Credit past the s.16(4) time limit is not claimable either: its
            // GST is a cost, as GSTR-3B leaves it out.
            'itc_eligible' => $bill->itc_eligible && ! $bill->isItcTimeBarred(),
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

    /**
     * Category when none was chosen: goods bought for stock when a line is
     * linked to a goods item (and that category exists), otherwise
     * Miscellaneous as before.
     */
    private function defaultCategory($company, array $items): string
    {
        $ids = collect($items)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($ids && array_key_exists('purchases', config('expense_categories', []))
            && $company->products()->whereIn('id', $ids)->where('kind', 'goods')->exists()) {
            return 'purchases';
        }

        return 'misc';
    }

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
            'creditNotes' => $bill->exists ? array_diff_key($this->creditNotes($bill->loadMissing('payments')), ['time_bar' => true]) : [],
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

    /** The figures an activity-log entry keeps for a purchase invoice. */
    private function auditSnapshot(PurchaseBill $bill): array
    {
        return [
            'bill_number' => $bill->bill_number,
            'bill_date' => $bill->bill_date?->toDateString(),
            'vendor_id' => $bill->vendor_id,
            'subtotal' => (float) $bill->subtotal,
            'total_tax' => (float) $bill->total_tax,
            'grand_total' => (float) $bill->grand_total,
            'itc_eligible' => (bool) $bill->itc_eligible,
            'reverse_charge' => (bool) $bill->reverse_charge,
        ];
    }

    private function paymentSnapshot(PurchasePayment $payment, PurchaseBill $bill): array
    {
        return [
            'purchase_bill' => $bill->bill_number,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'paid_on' => $payment->paid_on?->toDateString(),
            'reference_number' => $payment->reference_number,
        ];
    }

    private function methodLabel(?string $method): string
    {
        return config("payment_methods.methods.{$method}.label") ?? strtoupper((string) $method);
    }

    private function authorizeBill(Request $request, PurchaseBill $bill): void
    {
        abort_unless($bill->user_id === $request->user()->id, 403);
    }

    /**
     * One supplier invoice is booked once. The supplier is its GSTIN, which
     * can sit on two vendor records, and the number is compared the way
     * GSTR-2B matching compares it ("DC/2026/0117" is "DC-2026-117"), within
     * the financial year of the bill date (suppliers restart numbering each
     * year). Booking it twice claimed its input credit twice.
     */
    private function refuseSameSupplierInvoice($company, array $data, ?int $ignoreId): void
    {
        $gstin = $company->vendors()->whereKey((int) $data['vendor_id'])->value('gstin');
        if (! $gstin) {
            return;
        }
        $date = \Illuminate\Support\Carbon::parse($data['bill_date']);
        $fyStart = \Illuminate\Support\Carbon::create($date->month >= 4 ? $date->year : $date->year - 1, 4, 1);
        $matcher = app(\App\Services\Gstr2bMatcher::class);
        $key = $matcher->key($gstin, (string) $data['bill_number']);

        $same = $company->purchaseBills()
            ->with('vendor:id,name')
            ->whereHas('vendor', fn ($v) => $v->where('gstin', strtoupper($gstin)))
            ->whereBetween('bill_date', [$fyStart->copy()->startOfDay(), $fyStart->copy()->addYear()->subDay()->endOfDay()])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get(['id', 'vendor_id', 'bill_number', 'bill_date'])
            ->first(fn (PurchaseBill $b) => $matcher->key($gstin, $b->bill_number) === $key);

        if ($same) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bill_number' => "This invoice is already recorded as purchase invoice {$same->bill_number} dated {$same->bill_date->format('d M Y')}"
                    . " from {$same->vendor->name} (GSTIN {$gstin}). A supplier invoice can be recorded only once.",
            ]);
        }
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
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:' . implode(',', config('gst.allowed_values'))],
        ], [
            // Shown under the field, so they name the problem and the fix.
            'vendor_id.required' => 'Select the vendor.',
            'bill_number.required' => "Enter the vendor's invoice number.",
            'bill_number.unique' => 'This invoice number from this vendor is already recorded.',
            'bill_date.before_or_equal' => 'The invoice date cannot be in the future.',
            'due_date.after_or_equal' => 'The due date cannot be before the invoice date.',
            'paid_on.before_or_equal' => 'The payment date cannot be in the future.',
            'items.required' => 'Add at least one line.',
            'items.*.description.required' => 'Enter a description.',
            'items.*.quantity.min' => 'Quantity must be more than 0.',
            'items.*.gst_rate.in' => 'Choose a GST rate from the list.',
        ], [
            'items.*.description' => 'description',
            'items.*.hsn_sac' => 'HSN/SAC',
            'items.*.quantity' => 'quantity',
            'items.*.unit' => 'unit',
            'items.*.rate' => 'rate',
            'items.*.gst_rate' => 'GST rate',
            'items.*.product_id' => 'item',
        ]);

        if (array_key_exists('bill_discount', $data)) {
            $gross = $this->calculator->valueAfterLineDiscounts($data['items']);
            if ((float) $data['bill_discount'] > $gross + 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bill_discount' => 'Discount cannot be more than the items total (₹' . inr($gross) . ').',
                ]);
            }
            $data['items'] = $this->calculator->apportionDiscount($data['items'], (float) ($data['bill_discount'] ?? 0));
        }

        $this->refuseSameSupplierInvoice($company, $data, $ignoreId);

        // Checkboxes: absent means unticked. ITC defaults to ticked in the form.
        $data['itc_eligible'] = $request->boolean('itc_eligible');
        $data['reverse_charge'] = $request->boolean('reverse_charge');
        if ($request->has('is_interstate')) {
            $data['is_interstate'] = $request->boolean('is_interstate');
        }

        return $data;
    }
}
