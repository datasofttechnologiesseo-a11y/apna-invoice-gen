<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\State;
use App\Services\InvoiceCalculator;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Quotation lifecycle controller.
 *
 * Quotations are pre-sale proposals — they share the same line-item math as
 * invoices (we reuse InvoiceCalculator for CGST/SGST/IGST split) but live in
 * their OWN model + table to keep them firmly out of GSTR-1, GSTR-3B, and
 * the books-locked-until period close. Once a quote is accepted, the user
 * clicks "Convert to Invoice" which creates a new draft Invoice with the
 * same data — the original quote stays read-only and links forward.
 */
class QuotationController extends Controller
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        $quotations = $company->quotations()
            ->with(['customer'])
            ->when($request->status, function ($q, $s) {
                if ($s === 'expired') {
                    return $q->whereIn('status', ['draft', 'sent'])
                        ->whereNotNull('valid_until')
                        ->where('valid_until', '<', now()->toDateString());
                }
                return $q->where('status', $s);
            })
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->whereLiteralLike('quote_number', $s)
                    ->orWhereHas('customer', fn ($c) => $c->whereLiteralLike('name', $s)
                        ->orWhereLiteralLike('gstin', strtoupper($s)));
            }))
            ->orderByDesc('quote_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('quotations.index', compact('company', 'quotations'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        if (! $company->state_id) {
            return redirect()->route('company.edit')
                ->with('warning', 'Set your business state before creating a quotation.');
        }

        $customers = $company->customers()->orderBy('name')->get();
        if ($customers->isEmpty()) {
            return redirect()->route('customers.create')
                ->with('warning', 'Add at least one customer before creating a quotation.');
        }

        $states = State::orderBy('name')->get();
        $productIndex = $company->products()->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'sku', 'hsn_sac', 'unit', 'rate', 'gst_rate']);

        $quotation = new Quotation([
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => $company->default_currency ?? 'INR',
            'terms' => $company->default_terms,
            'status' => 'draft',
            'style' => 'classic',
        ]);
        $quotation->setRelation('items', collect());

        return view('quotations.edit', [
            'quotation' => $quotation,
            'company' => $company,
            'customers' => $customers,
            'states' => $states,
            'productIndex' => $productIndex,
            'previewNumber' => $company->nextQuoteNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validateQuotation($request, $company->id);
        $data = $this->hydrateProductDescriptions($data, $company->id);

        $data = $this->applyQuoteDiscount($data);
        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $result = $this->calculator->recalculate(new \App\Models\Invoice(), $data['items'], $isInterstate);

        $quotation = DB::transaction(function () use ($user, $company, $customer, $data, $result, $isInterstate) {
            $quote = Quotation::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                // Blank means "use the auto series", assigned when sent.
                'quote_number' => filled($data['quote_number'] ?? null) ? trim($data['quote_number']) : null,
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'subject' => $data['subject'] ?? null,
                'reference' => $data['reference'] ?? null,
                'delivery_period' => $data['delivery_period'] ?? null,
                'is_interstate' => $isInterstate,
                'currency' => $data['currency'] ?? 'INR',
                'exchange_rate' => 1,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'style' => $data['style'] ?? 'classic',
                ...$result['totals'],
            ]);

            foreach ($result['items'] as $row) {
                $quote->items()->create($row);
            }
            return $quote;
        });

        AuditLog::record('quotation.created',
            "Quotation draft created · ₹" . inr($quotation->grand_total) . " · " . $customer->name,
            $quotation
        );

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation draft saved.');
    }

    public function show(Request $request, Quotation $quotation): View
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        $quotation->load(['customer', 'company.state', 'items.product:id,name', 'convertedInvoice']);
        $amountInWords = NumberToWords::indianRupees((float) $quotation->grand_total, $quotation->currency);
        return view('quotations.show', compact('quotation', 'amountInWords'));
    }

    public function edit(Request $request, Quotation $quotation): View|RedirectResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', 'This quotation can no longer be edited. Only drafts can be changed.');
        }

        $company = $quotation->company;
        $customers = $company->customers()->orderBy('name')->get();
        $states = State::orderBy('name')->get();
        $productIndex = $company->products()->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'sku', 'hsn_sac', 'unit', 'rate', 'gst_rate']);

        return view('quotations.edit', [
            'quotation' => $quotation->load('items'),
            'company' => $company,
            'customers' => $customers,
            'states' => $states,
            'productIndex' => $productIndex,
            'previewNumber' => $quotation->quote_number ?: $company->nextQuoteNumber(),
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        abort_unless($quotation->isEditable(), 403, 'This quotation can no longer be edited.');

        $company = $quotation->company;
        $data = $this->validateQuotation($request, $company->id);
        $data = $this->hydrateProductDescriptions($data, $company->id);

        $data = $this->applyQuoteDiscount($data);
        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $result = $this->calculator->recalculate(new \App\Models\Invoice(), $data['items'], $isInterstate);

        DB::transaction(function () use ($quotation, $customer, $data, $result, $isInterstate) {
            $quotation->update([
                'customer_id' => $customer->id,
                // Blank means "use the auto series", assigned when sent.
                'quote_number' => filled($data['quote_number'] ?? null) ? trim($data['quote_number']) : null,
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'subject' => $data['subject'] ?? null,
                'reference' => $data['reference'] ?? null,
                'delivery_period' => $data['delivery_period'] ?? null,
                'is_interstate' => $isInterstate,
                'currency' => $data['currency'] ?? 'INR',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'style' => $data['style'] ?? 'classic',
                ...$result['totals'],
            ]);

            // Replace items in one shot — quotes are short-lived, no audit
            // requirement to keep historical line items per row like invoices.
            $quotation->items()->delete();
            foreach ($result['items'] as $row) {
                $quotation->items()->create($row);
            }
        });

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation updated.');
    }

    public function destroy(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        if (! $quotation->isDraft()) {
            return redirect()->back()->with('error', 'Only draft quotations can be deleted. Sent quotations are kept on record.');
        }
        $snapshot = $quotation->only(['quote_number', 'quote_date', 'grand_total', 'customer_id']);
        $quotation->delete();
        AuditLog::record('quotation.deleted', "Draft quotation deleted", null, $snapshot);
        return redirect()->route('quotations.index')->with('status', 'Draft quotation deleted.');
    }

    /**
     * Mark quotation as sent — assigns the next quote_number atomically and
     * locks the quote against further edits. After this point, the only ways
     * the quote can change state are: customer accepts, customer declines,
     * it expires, or it gets converted to an invoice.
     */
    public function send(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        abort_unless($quotation->isDraft(), 422, 'Only drafts can be sent.');

        DB::transaction(function () use ($quotation) {
            $company = $quotation->company()->lockForUpdate()->first();

            // A number the user typed on the draft wins, and the auto counter
            // is left alone so it is not silently burned. Only fall back to
            // the generated series when they did not choose one.
            $number = filled($quotation->quote_number)
                ? $quotation->quote_number
                : $company->bumpQuoteCounter($quotation->quote_date);

            $quotation->update([
                'quote_number' => $number,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        });

        AuditLog::record('quotation.sent',
            "Quotation {$quotation->fresh()->quote_number} marked as sent",
            $quotation
        );

        return redirect()->route('quotations.show', $quotation)
            ->with('status', "Quotation {$quotation->fresh()->quote_number} is ready to share by WhatsApp or email.");
    }

    public function accept(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        abort_unless(in_array($quotation->status, ['sent']), 422, 'Only sent quotations can be marked as accepted.');

        $quotation->update(['status' => 'accepted', 'accepted_at' => now()]);
        AuditLog::record('quotation.accepted',
            "Quotation {$quotation->quote_number} marked as accepted",
            $quotation
        );
        return redirect()->route('quotations.show', $quotation)
            ->with('status', 'Quotation marked as accepted. It can now be converted to a tax invoice.');
    }

    public function decline(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        abort_unless(in_array($quotation->status, ['sent', 'accepted']), 422, 'Only sent or accepted quotations can be marked as declined.');

        $reason = $request->validate(['decline_reason' => ['nullable', 'string', 'max:500']])['decline_reason'] ?? null;
        $quotation->update([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);
        AuditLog::record('quotation.declined',
            "Quotation {$quotation->quote_number} marked as declined" . ($reason ? " — {$reason}" : ''),
            $quotation
        );
        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation marked as declined.');
    }

    /**
     * Convert an accepted quotation into a draft Tax Invoice. Creates a new
     * Invoice + InvoiceItems with the same line data, links the quote forward,
     * and marks the quote as `converted` so it can't be converted again.
     * The new invoice is a DRAFT — the user reviews and finalizes manually so
     * the legal invoice number is assigned at finalize time, not now.
     */
    public function convertToInvoice(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        abort_unless($quotation->canBeConverted(), 422, 'This quotation is already converted or declined, so it cannot be converted.');

        $invoice = DB::transaction(function () use ($quotation) {
            // A second tab or a double click sends "convert" again. Re-read the
            // quotation under a lock and check again, or both requests pass the
            // check above and the quotation becomes two invoices.
            $locked = \App\Models\Quotation::whereKey($quotation->id)->lockForUpdate()->first();
            abort_unless($locked && $locked->canBeConverted(), 422, 'This quotation has just been converted to an invoice.');
            // Taxes are recomputed at conversion, not copied: the quote may be
            // weeks old, and the customer's state - hence CGST/SGST vs IGST and
            // the place of supply - is read as it stands today, exactly as a
            // new invoice would.
            $isInterstate = $this->calculator->isInterstate($quotation->company?->state_id, $quotation->customer?->state_id);
            $calc = $this->calculator->recalculate(new Invoice(), $quotation->items->map(fn ($qi) => [
                'product_id' => $qi->product_id,
                'description' => $qi->description,
                'hsn_sac' => $qi->hsn_sac,
                'quantity' => $qi->quantity,
                'unit' => $qi->unit,
                'rate' => $qi->rate,
                'discount' => $qi->discount,
                'gst_rate' => $qi->gst_rate,
            ])->all(), $isInterstate, false);
            $t = $calc['totals'];

            $invoice = Invoice::create([
                'user_id' => $quotation->user_id,
                'company_id' => $quotation->company_id,
                'customer_id' => $quotation->customer_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'place_of_supply_state_id' => $quotation->customer?->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => false,
                'currency' => $quotation->currency,
                'exchange_rate' => $quotation->exchange_rate,
                'status' => 'draft',
                'subtotal' => $t['subtotal'],
                'total_cgst' => $t['total_cgst'],
                'total_sgst' => $t['total_sgst'],
                'total_igst' => $t['total_igst'],
                'total_tax' => $t['total_tax'],
                'round_off' => $t['round_off'],
                'grand_total' => $t['grand_total'],
                'paid_amount' => 0,
                'balance' => $t['grand_total'],
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'style' => $quotation->style,
            ]);

            // Lines come from the same calculation, so amount, tax heads and
            // the GST-inclusive total all agree with the invoice totals.
            $invoice->items()->createMany($calc['items']);

            $quotation->update([
                'status' => 'converted',
                'converted_to_invoice_id' => $invoice->id,
                'converted_at' => now(),
            ]);

            return $invoice;
        });

        AuditLog::record('quotation.converted',
            "Quotation {$quotation->quote_number} converted to Invoice draft #{$invoice->id}",
            $quotation,
            ['invoice_id' => $invoice->id]
        );

        return redirect()->route('invoices.edit', $invoice)
            ->with('status', "Quotation converted to a draft invoice. Check it, then click Issue.");
    }

    public function pdf(Request $request, Quotation $quotation): Response
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        $quotation->load(['customer.state', 'company.state', 'items.product:id,name']);
        $amountInWords = NumberToWords::indianRupees((float) $quotation->grand_total, $quotation->currency);

        // Default to ink-saver ($print=true). Pass ?color=1 to get the
        // full-colour teal/amber version for digital sharing.
        $print = ! $request->boolean('color');

        $pdf = Pdf::loadView('quotations.pdf', compact('quotation', 'amountInWords', 'print'))
            ->setPaper('a4');

        $filename = ($quotation->quote_number ?: 'quotation-draft-' . $quotation->id) . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Server-side safety net: if the line-item description is blank or just "0",
     * fill it from the picked product's name. Mirrors InvoiceController so
     * quotations don't end up with "0" in the description column on the PDF
     * when the JS combobox fails to propagate the product name.
     */
    private function hydrateProductDescriptions(array $data, int $companyId): array
    {
        if (empty($data['items']) || ! is_array($data['items'])) {
            return $data;
        }
        $productIds = collect($data['items'])->pluck('product_id')->filter()->unique()->values();
        if ($productIds->isEmpty()) {
            return $data;
        }
        $productNames = \App\Models\Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');
        foreach ($data['items'] as $i => $item) {
            $pid = $item['product_id'] ?? null;
            if (! $pid || ! isset($productNames[$pid])) continue;
            $desc = trim((string) ($item['description'] ?? ''));
            $looksLikeJunk = $desc === '' || preg_match('/^0+(\.0+)?$/', $desc) === 1;
            if ($looksLikeJunk) {
                $data['items'][$i]['description'] = $productNames[$pid];
            }
        }
        return $data;
    }

    /**
     * Spread the quote-level discount across the lines before tax (same rule
     * as invoices). Requests carrying per-line discounts are left unchanged.
     */
    private function applyQuoteDiscount(array $data): array
    {
        if (! array_key_exists('quote_discount', $data)) {
            return $data;
        }
        $gross = $this->calculator->valueAfterLineDiscounts($data['items']);
        if ((float) $data['quote_discount'] > $gross + 0.001) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quote_discount' => 'Discount cannot be more than the items total (₹' . inr($gross) . ').',
            ]);
        }
        $data['items'] = $this->calculator->apportionDiscount($data['items'], (float) ($data['quote_discount'] ?? 0));

        return $data;
    }

    private function validateQuotation(Request $request, int $companyId): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            // Quotations are not statutory documents, so the number is the
            // user's to choose. Still unique per company — the quotations
            // table carries a unique index on (company_id, quote_number).
            'quote_number' => ['nullable', 'string', 'max:50',
                Rule::unique('quotations', 'quote_number')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($request->route('quotation')?->id)],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            'subject' => ['nullable', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:100'],
            'delivery_period' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'style' => ['nullable', 'string', 'in:classic,bold,minimal,retail,warm'],
            'items' => ['required', 'array', 'min:1'],
            // Description optional — server backfills from product name if blank.
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            // One discount for the whole quote (bottom box), spread before tax.
            'quote_discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:' . implode(',', config('gst.allowed_values'))],
            'items.*.product_id' => ['nullable', 'integer', "exists:products,id,company_id,{$companyId}"],
        ]);
    }
}
