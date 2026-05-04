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
                $w->where('quote_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%"));
            }))
            ->orderByDesc('quote_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Section-tab stats — see InvoiceController for the full schema.
        $salesStats = [
            'invoices' => [
                'total' => $company->invoices()->count(),
                'open' => $company->invoices()
                    ->whereIn('status', ['final', 'partially_paid'])
                    ->where('balance', '>', 0)
                    ->count(),
            ],
            'quotations' => [
                'total' => $company->quotations()->count(),
                'awaiting' => $company->quotations()->where('status', 'sent')->count(),
            ],
        ];

        return view('quotations.index', compact('company', 'quotations', 'salesStats'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        if (! $company->state_id) {
            return redirect()->route('company.edit')
                ->with('status', 'Set your company state before creating quotations.');
        }

        $customers = $company->customers()->orderBy('name')->get();
        if ($customers->isEmpty()) {
            return redirect()->route('customers.create')
                ->with('status', 'Add at least one customer before creating a quotation.');
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

        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $result = $this->calculator->recalculate(new \App\Models\Invoice(), $data['items'], $isInterstate);

        $quotation = DB::transaction(function () use ($user, $company, $customer, $data, $result, $isInterstate) {
            $quote = Quotation::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'customer_id' => $customer->id,
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
            "Quotation draft created · ₹" . number_format($quotation->grand_total, 2) . " · " . $customer->name,
            $quotation
        );

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation draft saved.');
    }

    public function show(Request $request, Quotation $quotation): View
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        $quotation->load(['customer', 'company.state', 'items', 'convertedInvoice']);
        $amountInWords = NumberToWords::indianRupees((float) $quotation->grand_total, $quotation->currency);
        return view('quotations.show', compact('quotation', 'amountInWords'));
    }

    public function edit(Request $request, Quotation $quotation): View|RedirectResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', 'This quotation can no longer be edited. Drafts only — once sent, accepted, or converted, quotes become read-only.');
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

        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $result = $this->calculator->recalculate(new \App\Models\Invoice(), $data['items'], $isInterstate);

        DB::transaction(function () use ($quotation, $customer, $data, $result, $isInterstate) {
            $quotation->update([
                'customer_id' => $customer->id,
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
            return redirect()->back()->with('error', 'Only draft quotations can be deleted. Sent quotations are kept for record.');
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
            $number = $company->bumpQuoteCounter($quotation->quote_date);
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
            ->with('status', "Quotation {$quotation->fresh()->quote_number} is now ready to share. Use the WhatsApp / Email links on this page.");
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
            ->with('status', 'Marked as accepted. You can now convert it to a tax invoice.');
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
        abort_unless($quotation->canBeConverted(), 422, 'This quotation cannot be converted (already converted or declined).');

        $invoice = DB::transaction(function () use ($quotation) {
            $invoice = Invoice::create([
                'user_id' => $quotation->user_id,
                'company_id' => $quotation->company_id,
                'customer_id' => $quotation->customer_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'place_of_supply_state_id' => $quotation->customer?->state_id,
                'is_interstate' => $quotation->is_interstate,
                'reverse_charge' => false,
                'currency' => $quotation->currency,
                'exchange_rate' => $quotation->exchange_rate,
                'status' => 'draft',
                'subtotal' => $quotation->subtotal,
                'total_cgst' => $quotation->total_cgst,
                'total_sgst' => $quotation->total_sgst,
                'total_igst' => $quotation->total_igst,
                'total_tax' => $quotation->total_tax,
                'round_off' => $quotation->round_off,
                'grand_total' => $quotation->grand_total,
                'paid_amount' => 0,
                'balance' => $quotation->grand_total,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'style' => $quotation->style,
            ]);

            foreach ($quotation->items as $qi) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $qi->product_id,
                    'description' => $qi->description,
                    'hsn_sac' => $qi->hsn_sac,
                    'quantity' => $qi->quantity,
                    'unit' => $qi->unit,
                    'rate' => $qi->rate,
                    'discount' => $qi->discount,
                    'gst_rate' => $qi->gst_rate,
                    'amount' => $qi->amount,
                    'cgst_amount' => $qi->cgst_amount,
                    'sgst_amount' => $qi->sgst_amount,
                    'igst_amount' => $qi->igst_amount,
                ]);
            }

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
            ->with('status', "Quotation converted to a draft invoice. Review and click Finalize to issue the tax invoice.");
    }

    public function pdf(Request $request, Quotation $quotation): Response
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
        $quotation->load(['customer.state', 'company.state', 'items']);
        $amountInWords = NumberToWords::indianRupees((float) $quotation->grand_total, $quotation->currency);

        // Default to ink-saver ($print=true). Pass ?color=1 to get the
        // full-colour navy/saffron version for digital sharing.
        $print = ! $request->boolean('color');

        $pdf = Pdf::loadView('quotations.pdf', compact('quotation', 'amountInWords', 'print'))
            ->setPaper('a4');

        $filename = ($quotation->quote_number ?: 'quotation-draft-' . $quotation->id) . '.pdf';
        return $pdf->download($filename);
    }

    private function validateQuotation(Request $request, int $companyId): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
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
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer'],
        ]);
    }
}
