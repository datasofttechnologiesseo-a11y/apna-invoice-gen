<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\State;
use App\Services\InvoiceCalculator;
use App\Services\Reminders\ReminderService;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    /**
     * Parse a ?from / ?to query value into a date, or null.
     *
     * Returns null for anything unparseable rather than throwing: these come
     * off links, bookmarks and hand-edited URLs, and a mistyped date should
     * quietly widen the list, not 500 the invoice page.
     */
    private function parseFilterDate(?string $value): ?\Illuminate\Support\Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        $invoices = $company->invoices()
            ->with(['customer', 'company'])
            ->when($request->status, function ($q, $s) {
                // "outstanding" is a virtual filter — final/partially-paid invoices
                // that still have a balance. The collections workflow lands here
                // from the Outstanding KPI card on the dashboard.
                if ($s === 'outstanding') {
                    return $q->whereIn('status', ['final', 'partially_paid'])
                             ->where('balance', '>', 0);
                }
                return $q->where('status', $s);
            })
            // Invoice::scopeSearch — shared with the search box's typeahead so
            // the dropdown can never offer a bill this filter then fails to find.
            ->when($request->search, fn ($q, $s) => $q->search($s))
            // ?from / ?to bound the list by invoice date - the dashboard's
            // "Invoiced this month" card links straight in with them. They
            // arrive from links and bookmarks rather than a validated form, so
            // unparseable input is ignored instead of thrown at the user.
            ->when($this->parseFilterDate($request->query('from')), fn ($q, $d) => $q->where('invoice_date', '>=', $d->toDateString()))
            ->when($this->parseFilterDate($request->query('to')), fn ($q, $d) => $q->where('invoice_date', '<=', $d->toDateString()))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Section-tab stats — total + a "needs attention" sub-stat per doc
        // type. Two extra count() queries; cheap and lets the tab strip show
        // useful info ("3 outstanding", "4 this month") without nav.
        $salesStats = [
            'invoices' => [
                'total' => $company->invoices()->count(),
                // Outstanding = finalised but unpaid (or partially paid)
                'open' => $company->invoices()
                    ->whereIn('status', ['final', 'partially_paid'])
                    ->where('balance', '>', 0)
                    ->count(),
            ],
            'purchases' => [
                'total' => $company->purchaseBills()->count(),
                // Unpaid = still owed to vendors, the purchase-side "outstanding".
                'unpaid' => $company->purchaseBills()->where('balance', '>', 0)->count(),
            ],
        ];

        return view('invoices.index', compact('invoices', 'company', 'salesStats'));
    }

    public function templatePreview(Request $request, string $template): Response|RedirectResponse
    {
        $templates = config('invoice_templates');
        abort_unless(isset($templates[$template]), 404);
        $tpl = $templates[$template];

        $user = $request->user();
        $company = $user->ensureCompany();

        // Build an ephemeral invoice for preview (not saved).
        // Templates now start with a blank items row to avoid confusion when
        // the user clicks "Use this template", but a preview PDF with zero
        // amounts would be misleading — so we inject one clearly-labelled
        // SAMPLE row just for the rendered preview.
        $calc = app(\App\Services\InvoiceCalculator::class);
        $isInterstate = false; // Preview uses same-state customer
        $previewItems = [[
            'description' => 'Sample item',
            'hsn_sac' => '998313',
            'quantity' => 1,
            'unit' => 'unit',
            'rate' => 10000,
            'gst_rate' => $tpl['items'][0]['gst_rate'] ?? 18,
        ]];
        $result = $calc->recalculate(new Invoice(), $previewItems, $isInterstate);

        $fakeState = $company->state ?? State::first();
        // Build a sample GSTIN whose first two digits match the customer's state
        // code, so the rendered preview is internally consistent (a careful
        // reader noticing a 27-prefix GSTIN on a Haryana customer would think
        // the template is buggy).
        $sampleStateCode = str_pad((string) ($fakeState?->gst_code ?? '27'), 2, '0', STR_PAD_LEFT);
        $sampleGstin = $sampleStateCode . 'ABCDE1234F1Z5';

        $fakeCustomer = new Customer([
            'name' => 'Sample Customer Pvt. Ltd.',
            'gstin' => $sampleGstin,
            'address_line1' => '123 Demo Street',
            'address_line2' => 'Sample Area',
            'city' => $fakeState?->name ? explode(' ', $fakeState->name)[0] : 'Sample City',
            'state_id' => $fakeState?->id,
            'postal_code' => '400001',
            'country' => 'India',
            'email' => 'sample@customer.com',
            'phone' => '+91 98765 43210',
        ]);
        $fakeCustomer->setRelation('state', $fakeState);

        $invoice = new Invoice([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => 0,
            'invoice_number' => $company->invoice_prefix . '-SAMPLE',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'place_of_supply_state_id' => $fakeState?->id,
            'is_interstate' => $isInterstate,
            'reverse_charge' => false,
            'currency' => $tpl['currency'] ?? $company->default_currency,
            'exchange_rate' => 1,
            'status' => 'draft',
            'terms' => $company->default_terms ?? "Sample terms.\nYour own terms print here.",
            ...$result['totals'],
            'paid_amount' => 0,
            'balance' => $result['totals']['grand_total'],
        ]);
        $invoice->id = 0;
        $invoice->setRelation('company', $company);
        $invoice->setRelation('customer', $fakeCustomer);
        $invoice->setRelation('placeOfSupply', $fakeState);

        $items = collect($result['items'])->map(fn ($i) => new InvoiceItem($i));
        $invoice->setRelation('items', $items);

        $amountInWords = \App\Support\NumberToWords::indianRupees(
            (float) $invoice->grand_total,
            $invoice->currency
        );

        $style = $tpl['style'] ?? 'classic';

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'amountInWords', 'style'))
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        return $pdf->stream('preview-' . $template . '.pdf');
    }

    public function templates(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        // Setup is no longer a hard gate. The form itself shows nudges for missing
        // company state / customers, and lets the user create a customer inline.
        // Anyone who clicks "Browse templates" expects to see templates — show them.

        $calc = $this->calculator;
        $templates = collect(config('invoice_templates'))->map(function ($tpl) use ($calc) {
            $result = $calc->recalculate(new Invoice(), $tpl['items'], isInterstate: false);
            $tpl['totals'] = $result['totals'];
            $tpl['computed_items'] = $result['items'];
            return $tpl;
        })->all();

        return view('invoices.templates', [
            'templates' => $templates,
            'company' => $company,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        // Setup is NOT a hard gate. New users land on a blank invoice and the
        // form's inline nudges + "+ New customer" modal let them fill missing
        // pieces without leaving. Missing company state shows a banner above
        // the form (handled in the view) — the calculator defaults to
        // intra-state (CGST/SGST) when company state is null, and finalize()
        // blocks issuing a taxed invoice until the state is set.

        // Eager-load state so the combobox can show "Acme — Maharashtra" without N+1.
        $customers = $company->customers()->with('state:id,name')->orderBy('name')->get();
        $states = State::orderBy('name')->get();

        $templateKey = $request->query('template');
        $templates = config('invoice_templates');
        $template = $templateKey && isset($templates[$templateKey]) ? $templates[$templateKey] : null;

        $invoice = new Invoice([
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => $template['currency'] ?? $company->default_currency,
            'terms' => $company->default_terms,
            'status' => 'draft',
            'style' => $template['style'] ?? 'classic',
        ]);

        // Pre-fill items from template (ephemeral — not persisted until submit)
        $templateItems = collect($template['items'] ?? [])->map(fn ($i, $idx) => (new InvoiceItem($i))->forceFill(['id' => null]));
        $invoice->setRelation('items', $templateItems);

        return view('invoices.edit', [
            'invoice' => $invoice,
            'company' => $company,
            'customers' => $customers,
            'states' => $states,
            'previewNumber' => $company->nextInvoiceNumber(),
            'templateLabel' => $template['label'] ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validateInvoice($request, $company);
        // A closed year takes no new documents, drafts included: a draft dated
        // there could never be issued.
        if ($company->isBooksLockedOn($data['invoice_date'])) {
            return back()->withInput()->withErrors(['invoice_date' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. Date the invoice after that."]);
        }
        // Safety net: backfill blank/"0" descriptions from the product name.
        $data = $this->hydrateProductDescriptions($data, $company->id);

        $data = $this->applyInvoiceDiscount($data);
        $customer = $company->customers()->findOrFail($data['customer_id']);
        // Exports / SEZ are inter-state and, under a LUT, carry no tax.
        $supplyType = $data['supply_type'] ?? 'regular';
        \App\Support\ZeroRatedSupply::guard($supplyType, $data, $customer);
        [$isInterstate, $noTax] = $this->calculator->taxBasis($company->state_id, $customer->state_id, $supplyType, (bool) ($data['reverse_charge'] ?? false));
        $calc = $this->calculator->recalculate(new Invoice(), $data['items'], $isInterstate, $noTax);
        $this->guardPaidAmount($data, $calc);

        $invoice = DB::transaction(function () use ($user, $company, $customer, $data, $calc, $isInterstate, $supplyType) {
            $invoice = $user->invoices()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                // Non-GST businesses may pre-pick a custom number (kept at issue);
                // blank/GST-registered stays null and auto-numbers at finalize.
                'invoice_number' => ($data['invoice_number'] ?? null) ?: null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
                ...\App\Support\ZeroRatedSupply::attributes($supplyType, $data),
                // Transporter / e-way bill fields — previously missing on the create
                // path, causing user input to validate then silently vanish on draft save.
                'transporter_name' => $data['transporter_name'] ?? null,
                'transporter_id' => $data['transporter_id'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'eway_bill_number' => $data['eway_bill_number'] ?? null,
                'ship_to_name' => $data['ship_to_name'] ?? null,
                'ship_to_address_line1' => $data['ship_to_address_line1'] ?? null,
                'ship_to_address_line2' => $data['ship_to_address_line2'] ?? null,
                'ship_to_city' => $data['ship_to_city'] ?? null,
                'ship_to_state_id' => $data['ship_to_state_id'] ?? null,
                'ship_to_postal_code' => $data['ship_to_postal_code'] ?? null,
                'ship_to_gstin' => $data['ship_to_gstin'] ?? null,
                'currency' => 'INR',
                'exchange_rate' => 1,
                'status' => 'draft',
                'style' => $data['style'] ?? 'classic',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                ...$calc['totals'],
                // Clamp to >= 0: an advance larger than the total must not record a
                // negative balance. Payments recorded later recompute from the
                // payments sum (see recordPayment) and clamp the same way.
                'balance' => max(0, round($calc['totals']['grand_total'] - (float) ($data['paid_amount'] ?? 0), 2)),
                'paid_amount' => (float) ($data['paid_amount'] ?? 0),
            ]);

            $invoice->items()->createMany($calc['items']);

            return $invoice;
        });

        // "Save & Download PDF" one-click flow: the form posts post_save_action=pdf.
        // Send the user to the show page with ?download=1; an unobtrusive script
        // on that page triggers the PDF download once the page is rendered.
        $action = $request->input('post_save_action');
        $redirect = redirect()->route('invoices.show', ['invoice' => $invoice, 'download' => $action === 'pdf' ? 1 : null]);
        return $redirect->with('status', $action === 'pdf' ? 'Draft saved. Downloading PDF.' : 'Draft invoice created.');
    }

    /**
     * Duplicate any invoice into a fresh editable DRAFT. Removes the re-keying
     * friction of repeat/recurring billing: copies the customer, line items and
     * presentation, but resets the number, dates, status, and all payment/credit
     * history so the copy is a clean new draft. Shipment-specific fields
     * (transporter, e-way bill, ship-to) are intentionally NOT copied.
     */
    public function duplicate(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load('items');

        // Preserve the original payment-term gap (due − issue), default 30 days.
        $termDays = ($invoice->invoice_date && $invoice->due_date)
            ? (int) $invoice->invoice_date->diffInDays($invoice->due_date)
            : 30;

        $copy = DB::transaction(function () use ($request, $invoice, $termDays) {
            $new = $request->user()->invoices()->create([
                'company_id' => $invoice->company_id,
                'customer_id' => $invoice->customer_id,
                'invoice_number' => null,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays($termDays)->toDateString(),
                'place_of_supply_state_id' => $invoice->place_of_supply_state_id,
                'is_interstate' => $invoice->is_interstate,
                'reverse_charge' => $invoice->reverse_charge,
                'supply_type' => $invoice->supply_type ?: 'regular',
                'currency' => $invoice->currency,
                'exchange_rate' => $invoice->exchange_rate,
                'status' => 'draft',
                'style' => $invoice->style,
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                // Money snapshot copied as-is (identical items → identical totals);
                // the editor recomputes on the next save regardless.
                'subtotal' => $invoice->subtotal,
                'total_cgst' => $invoice->total_cgst,
                'total_sgst' => $invoice->total_sgst,
                'total_igst' => $invoice->total_igst,
                'total_tax' => $invoice->total_tax,
                'round_off' => $invoice->round_off,
                'grand_total' => $invoice->grand_total,
                'paid_amount' => 0,
                'credited_amount' => 0,
                'balance' => $invoice->grand_total,
            ]);

            foreach ($invoice->items as $item) {
                $new->items()->create($item->only([
                    'product_id', 'description', 'hsn_sac', 'quantity', 'unit', 'rate',
                    'discount', 'amount', 'gst_rate', 'cgst_amount', 'sgst_amount',
                    'igst_amount', 'total', 'sort_order',
                ]));
            }

            return $new;
        });

        \App\Models\AuditLog::record('invoice.duplicated',
            "Draft #{$copy->id} created by duplicating " . $invoice->displayNumber(),
            $copy
        );

        return redirect()->route('invoices.edit', $copy)
            ->with('status', 'Invoice copied to a new draft. Check the date and details, then save.');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);
        // Eager-load items.product so the PDF/show description fallback (when
        // legacy data has description="0" or empty) can render product->name
        // without N+1 queries.
        $invoice->load(['items.product:id,name', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);

        // First-invoice celebration: cheap COUNT, only matters once in a user's
        // lifetime. The view also gates on localStorage so revisits don't repeat
        // the moment. Both gates together = banner fires exactly once for real.
        $isFirstFinalized = $invoice->finalized_at !== null
            && $request->user()->invoices()->whereNotNull('finalized_at')->count() === 1;

        return view('invoices.show', compact('invoice', 'amountInWords', 'isFirstFinalized'));
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isSoftEditable(), 403, 'This invoice cannot be edited.');

        // Scope the customer dropdown and counter preview to the invoice's OWN
        // company, not the currently-active company. Lets users edit invoices
        // that belong to a non-active company without needing to switch first.
        $company = $invoice->company;
        $customers = $company->customers()->with('state:id,name')->orderBy('name')->get();
        $states = State::orderBy('name')->get();
        $invoice->load('items');

        return view('invoices.edit', [
            'invoice' => $invoice,
            'company' => $company,
            'customers' => $customers,
            'states' => $states,
            'previewNumber' => $company->nextInvoiceNumber(),
            'restricted' => ! $invoice->isEditable(),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isSoftEditable(), 403, 'This invoice cannot be edited.');

        // An issued invoice in a closed year is final, notes and due date included.
        if (! $invoice->isEditable() && $invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return back()->with('error', "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. This invoice can no longer be changed.");
        }

        // Finalized invoices: only notes, terms, due_date, and transporter fields
        // may change. Amounts, items, customer, and invoice number are immutable.
        if (! $invoice->isEditable()) {
            $soft = $request->validate([
                // An issued invoice's due date can move, but never before the invoice date.
                'due_date' => ['nullable', 'date', 'after_or_equal:' . $invoice->invoice_date->toDateString()],
                'notes' => ['nullable', 'string'],
                'terms' => ['nullable', 'string'],
                'transporter_name' => ['nullable', 'string', 'max:120'],
                'transporter_id' => ['nullable', 'string', 'max:40'],
                'vehicle_number' => ['nullable', 'string', 'max:30'],
                'transport_mode' => ['nullable', 'string', 'in:Road,Rail,Air,Ship'],
                'eway_bill_number' => ['nullable', 'string', 'max:30'],
            ]);

            $invoice->update([
                'due_date' => $soft['due_date'] ?? null,
                'notes' => $soft['notes'] ?? null,
                'terms' => $soft['terms'] ?? null,
                'transporter_name' => $soft['transporter_name'] ?? null,
                'transporter_id' => $soft['transporter_id'] ?? null,
                'vehicle_number' => $soft['vehicle_number'] ?? null,
                'transport_mode' => $soft['transport_mode'] ?? null,
                'eway_bill_number' => $soft['eway_bill_number'] ?? null,
            ]);

            return redirect()->route('invoices.show', $invoice)
                ->with('status', 'Invoice details updated. Amounts, items and customer cannot be changed after issue.');
        }

        $company = $invoice->company;
        $data = $this->validateInvoice($request, $company);
        // Safety net: backfill blank/"0" descriptions from the product name.
        $data = $this->hydrateProductDescriptions($data, $company->id);
        $data = $this->applyInvoiceDiscount($data);
        $user = $request->user();
        $customer = $company->customers()->findOrFail($data['customer_id']);
        $supplyType = $data['supply_type'] ?? ($invoice->supply_type ?: 'regular');
        \App\Support\ZeroRatedSupply::guard($supplyType, $data, $customer);
        [$isInterstate, $noTax] = $this->calculator->taxBasis($company->state_id, $customer->state_id, $supplyType, (bool) ($data['reverse_charge'] ?? false));
        $calc = $this->calculator->recalculate($invoice, $data['items'], $isInterstate, $noTax);
        $this->guardPaidAmount($data, $calc);

        DB::transaction(function () use ($invoice, $customer, $data, $calc, $isInterstate, $supplyType) {
            $paid = (float) ($data['paid_amount'] ?? 0);
            $invoice->update([
                'customer_id' => $customer->id,
                // Draft-only path (issued invoices take the soft-edit branch above),
                // so the custom number is still freely editable here.
                'invoice_number' => ($data['invoice_number'] ?? null) ?: null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
                ...\App\Support\ZeroRatedSupply::attributes($supplyType, $data),
                'transporter_name' => $data['transporter_name'] ?? null,
                'transporter_id' => $data['transporter_id'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'eway_bill_number' => $data['eway_bill_number'] ?? null,
                'ship_to_name' => $data['ship_to_name'] ?? null,
                'ship_to_address_line1' => $data['ship_to_address_line1'] ?? null,
                'ship_to_address_line2' => $data['ship_to_address_line2'] ?? null,
                'ship_to_city' => $data['ship_to_city'] ?? null,
                'ship_to_state_id' => $data['ship_to_state_id'] ?? null,
                'ship_to_postal_code' => $data['ship_to_postal_code'] ?? null,
                'ship_to_gstin' => $data['ship_to_gstin'] ?? null,
                'currency' => 'INR',
                'exchange_rate' => 1,
                'style' => $data['style'] ?? $invoice->style ?? 'classic',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                ...$calc['totals'],
                'paid_amount' => $paid,
                'balance' => max(0, round($calc['totals']['grand_total'] - $paid, 2)),
            ]);

            $invoice->items()->delete();
            $invoice->items()->createMany($calc['items']);
        });

        $action = $request->input('post_save_action');
        $redirect = redirect()->route('invoices.show', ['invoice' => $invoice, 'download' => $action === 'pdf' ? 1 : null]);
        return $redirect->with('status', $action === 'pdf' ? 'Invoice saved. Downloading PDF.' : 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);

        // Only a draft can be deleted (it never had a number). An issued
        // invoice stays in the books, cancelled or not: its number belongs to
        // the series reported in GSTR-1 Table 13, so deleting the row would
        // leave an unexplained gap. Cancelling is how an issued invoice is
        // voided. See Invoice::canBeDeleted().
        if ($invoice->isCancelled() && ! $invoice->canBeDeleted()) {
            return redirect()->route('invoices.show', $invoice)->with(
                'error',
                "Invoice {$invoice->invoice_number} is cancelled and stays on record for GST. An issued invoice number cannot be deleted."
            );
        }
        abort_unless(
            $invoice->canBeDeleted(),
            403,
            'Issued invoices cannot be deleted. Cancel the invoice instead; it stays on record for GST.'
        );

        // Books-period lock: anything dated inside a closed FY stays.
        if ($invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return redirect()->back()->with(
                'error',
                "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. This draft cannot be deleted."
            );
        }

        $logMessage = "Draft #{$invoice->id} deleted";

        \App\Models\AuditLog::record('invoice.deleted',
            $logMessage,
            $invoice,
            $invoice->only(['invoice_number', 'invoice_date', 'grand_total', 'status', 'cancelled_at', 'cancellation_reason'])
        );

        // A draft made from a quotation: deleting it must reopen the quote, or
        // the quote stays "converted" pointing at nothing and can never be
        // converted again.
        $reopened = \App\Models\Quotation::where('converted_to_invoice_id', $invoice->id)
            ->update(['status' => 'accepted', 'converted_to_invoice_id' => null, 'converted_at' => null]);

        $invoice->delete();

        $successMessage = 'Draft deleted.' . ($reopened ? ' Its quotation is open again and can be converted.' : '');

        return redirect()->route('invoices.index')->with('status', $successMessage);
    }

    public function finalize(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isDraft(), 403);

        if ($invoice->items()->count() === 0) {
            return back()->withErrors(['finalize' => 'Cannot issue an invoice with no line items.']);
        }
        if ((float) $invoice->grand_total <= 0) {
            return back()->withErrors(['finalize' => 'Cannot issue a zero-amount invoice.']);
        }

        // GST place-of-supply integrity. When the company's own state is unknown,
        // the calculator cannot tell an intra-state sale (CGST/SGST) from an
        // inter-state one (IGST) and defaults to intra-state — so a taxed invoice
        // could be issued with the wrong tax heads. Block here. The draft is
        // editable: once the operator sets the state and saves the invoice again,
        // recalculate() applies the correct split, then finalisation is allowed.
        if (! $invoice->company->state_id && ((float) $invoice->total_tax > 0 || $invoice->company->gstin)) {
            return redirect()->back()->with('error', 'Business state is not set, so CGST/SGST or IGST cannot be worked out. Set it in Company settings, then open and save this draft before issuing it.');
        }

        // Books-period lock: cannot issue an invoice into a closed FY.
        if ($invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return redirect()->back()->with('error', "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. Cannot issue an invoice dated on or before that.");
        }

        // A tax invoice is issued on its date (s.31): one dated after today
        // cannot be issued yet, and the e-invoice portal refuses future dates.
        if ($invoice->invoice_date && $invoice->invoice_date->isAfter(now()->endOfDay())) {
            return back()->withErrors(['finalize' => 'This invoice is dated ' . $invoice->invoice_date->format('d M Y') . '. Issue it on that day, or change its date to today.']);
        }

        // The tax split was worked out when the draft was saved. If the
        // customer's state (or yours) has changed since, issuing now would print
        // CGST+SGST on what is now an inter-state sale, or the other way round.
        if ($invoice->taxBasisIsStale()) {
            return redirect()->back()->with('error', "The customer's state or your business state has changed since this draft was saved. Open the draft and save it again to update the GST split, then issue it.");
        }

        $issued = DB::transaction(function () use ($invoice) {
            $company = $invoice->company()->lockForUpdate()->first();

            // A double-click or a second tab sends "issue" twice. The second
            // request waits on the lock above; by then the invoice is issued,
            // and numbering it again would silently turn INV-0001 into INV-0002.
            $invoice->refresh();
            if (! $invoice->isDraft()) {
                return false;
            }

            if (filled($invoice->invoice_number)) {
                // The business chose a number on the draft - keep it, whether or
                // not it is GST-registered. Overwriting it here was the half of
                // the change that would have been missed: the field accepts a
                // number, the draft stores it, and then issuing silently
                // replaced it with the auto series.
                //
                // The series is carried forward from it so the next auto-numbered
                // bill continues (5464 -> 5465) instead of restarting at 0001.
                $number = $invoice->invoice_number;
                $company->learnSeriesFrom($number);
            } else {
                // Atomic: resets counter on FY boundary, increments, stamps format.
                // Skip past any number a custom-numbered invoice already claimed,
                // otherwise the unique (company_id, invoice_number) index would
                // reject the insert with a 500.
                do {
                    $number = $company->bumpCounterForFinalize($invoice->invoice_date?->toDateString());
                } while (Invoice::where('company_id', $company->id)
                    ->where('id', '!=', $invoice->id)
                    ->where('invoice_number', $number)
                    ->exists());
            }

            $invoice->update([
                'invoice_number' => $number,
                'place_of_supply_state_id' => $invoice->placeOfSupplyForIssue(),
                'status' => (float) $invoice->paid_amount >= (float) $invoice->grand_total ? 'paid' : ((float) $invoice->paid_amount > 0 ? 'partially_paid' : 'final'),
                'finalized_at' => now(),
            ]);

            // The recipient is frozen as printed; later customer edits must not
            // rewrite an issued document (Rule 46(d)/(e), GSTR-1 integrity).
            $invoice->snapshotCustomer();

            // The advance typed on the draft becomes a real receipt, so the
            // first recorded payment no longer wipes it (paid is recomputed
            // from the payments table from here on).
            $invoice->bookAdvanceAsPayment();
            // Items that track stock leave the shelf when the invoice is issued.
            app(\App\Services\StockLedger::class)->recordInvoice($invoice);

            return true;
        });

        if (! $issued) {
            return redirect()->route('invoices.show', $invoice)
                ->with('status', 'This invoice was already issued as ' . $invoice->invoice_number . '.');
        }

        \App\Models\AuditLog::record('invoice.finalized',
            "Invoice {$invoice->fresh()->invoice_number} finalized · ₹" . inr($invoice->grand_total),
            $invoice
        );

        // Review invite, "alternate invoice" moment: after the user's 2nd, 4th,
        // 6th… issued invoice, until they have left the review. The invoice
        // page shows it a moment after loading, and it closes with one click
        // so the share buttons are never more than a click away.
        $reviewPrompt = app(\App\Services\ReviewPrompt::class);
        $user = $request->user();
        $issuedCount = $user->invoices()->whereNotNull('finalized_at')->count();
        $askForReview = $reviewPrompt->shouldPromptAfterIssue($user, $issuedCount);
        if ($askForReview) {
            $reviewPrompt->markShown($user);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('status', 'Invoice ' . $invoice->fresh()->invoice_number . ' issued.')
            ->with('review_invite', $askForReview);
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);

        // Payments are only legally meaningful on issued invoices. Blocking
        // payment against drafts keeps the audit trail clean.
        abort_if($invoice->isDraft(), 422, 'Issue the invoice before recording payments.');
        abort_if($invoice->status === 'cancelled', 422, 'Cancelled invoices cannot accept payments.');

        // Remaining also subtracts credited_amount — a credit note reduces how
        // much is actually owed, so the payment cap shouldn't let users overpay
        // into a negative balance just because paid alone hasn't hit grand_total.
        // Round to paise before it becomes the "max" validation bound — an
        // unrounded float tail (e.g. 999.9999998) would reject an exact full
        // payment of 1000.00 by a hair.
        $remaining = round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount - (float) $invoice->credited_amount), 2);
        $methods = array_keys(config('payment_methods.methods'));

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$remaining}"],
            'method' => ['required', 'string', 'in:' . implode(',', $methods)],
            // Money cannot be received for a bill before the bill exists.
            'received_at' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $invoice->invoice_date->toDateString()],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            // Section 51 / 194-x of Income Tax Act: TDS deducted at source by the
            // customer. Stored for Form 26AS reconciliation. 30% cap is the
            // maximum legal TDS rate in India (lottery 194B + 206AA penalty).
            'tds_amount' => ['nullable', 'numeric', 'min:0', 'lt:amount'],
            'tds_section' => ['nullable', 'string', 'max:16'],
            'tds_rate' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);

        // A receipt dated inside a closed period would change that period's
        // books. Money received today against an old invoice is fine - the
        // lock is on the receipt date, not the invoice date.
        if ($invoice->company->isBooksLockedOn($data['received_at'])) {
            return back()->withErrors(['received_at' => "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. Date the receipt after that."])->withInput();
        }

        $payment = DB::transaction(function () use ($invoice, $data, $request) {
            // Lock the company row and increment the receipt counter atomically
            // so concurrent payments can never get the same receipt number.
            $company = $invoice->company()->lockForUpdate()->first();

            // The cap above was read before the lock. A double-submitted or
            // two-tab payment must be checked again against what is owed now,
            // or both go through and the invoice is paid twice.
            $invoice->refresh();
            $owed = round((float) $invoice->grand_total - (float) $invoice->payments()->sum('amount') - (float) $invoice->credited_amount, 2);
            if ($invoice->isDraft() || $invoice->status === 'cancelled' || (float) $data['amount'] > $owed + 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => $owed > 0
                        ? 'A payment was just recorded. Only ₹' . inr(max(0, $owed)) . ' is still owed on this invoice.'
                        : 'A payment was just recorded. Nothing more is owed on this invoice.',
                ]);
            }
            // A part payment passes the amount check twice; its bank reference
            // does not. Checked under the lock so two tabs cannot both pass.
            if ($duplicate = $invoice->duplicatePaymentReference($data['reference_number'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reference_number' => $duplicate]);
            }

            $company->increment('receipt_counter');

            $receiptNumber = $company->receipt_prefix . '-' .
                str_pad((string) $company->receipt_counter, $company->receipt_number_padding, '0', STR_PAD_LEFT);

            $payment = Payment::create([
                'user_id' => $invoice->user_id,
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'receipt_number' => $receiptNumber,
                'received_at' => $data['received_at'],
                'amount' => $data['amount'],
                'tds_amount' => (float) ($data['tds_amount'] ?? 0),
                'tds_section' => ! empty($data['tds_amount']) ? ($data['tds_section'] ?? null) : null,
                'tds_rate' => ! empty($data['tds_amount']) ? ($data['tds_rate'] ?? null) : null,
                'method' => $data['method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Recompute from authoritative sources (payments + credit notes),
            // not by nudging existing fields. Credit notes reduce the effective
            // amount owed — a paid-plus-credited total that covers grand_total
            // means the invoice is settled, same rule as CreditNoteController.
            $paid = (float) $invoice->payments()->sum('amount');
            $credited = (float) $invoice->credited_amount;
            $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
            $settled = ($paid + $credited) >= (float) $invoice->grand_total;
            $status = $settled ? 'paid' : ($paid > 0 ? 'partially_paid' : 'final');

            $invoice->update([
                'paid_amount' => $paid,
                'balance' => $balance,
                'status' => $status,
            ]);

            return $payment;
        });

        \App\Models\AuditLog::record('payment.recorded',
            "₹" . inr($payment->amount) . " · " . strtoupper($payment->method) . " · Receipt {$payment->receipt_number} · Invoice {$invoice->invoice_number}",
            $payment
        );

        return back()->with('status', "Payment recorded. Receipt {$payment->receipt_number} issued.");
    }

    public function receipt(Request $request, Payment $payment): Response
    {
        $this->authorizePayment($request, $payment);
        $payment->load(['invoice.company.state', 'invoice.customer.state']);

        $pdf = Pdf::loadView('payments.receipt', ['payment' => $payment])
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $safeNumber = preg_replace('~[\\\\/\\:\\*\\?"<>\\|\\s]+~', '-', $payment->receipt_number);
        return $pdf->download('receipt-' . $safeNumber . '.pdf');
    }

    public function deletePayment(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizePayment($request, $payment);
        $invoice = $payment->invoice;

        // Books-lock: cannot reverse a payment whose date or whose underlying
        // invoice is in a closed FY — would silently re-open a settled balance.
        $company = $invoice->company;
        if ($company->isBooksLockedOn($payment->received_at)) {
            return redirect()->back()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This payment cannot be reversed.");
        }

        // Capture snapshot BEFORE delete for the audit log.
        $snapshot = $payment->only([
            'receipt_number', 'amount', 'tds_amount', 'tds_section',
            'method', 'reference_number', 'received_at',
        ]);

        DB::transaction(function () use ($payment, $invoice) {
            $payment->delete();

            $paid = (float) $invoice->payments()->sum('amount');
            $credited = (float) $invoice->credited_amount;
            $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
            $settled = ($paid + $credited) >= (float) $invoice->grand_total;
            $status = $settled ? 'paid' : ($paid > 0 ? 'partially_paid' : 'final');

            $invoice->update([
                'paid_amount' => $paid,
                'balance' => $balance,
                'status' => $status,
            ]);
        });

        \App\Models\AuditLog::record('payment.reversed',
            "Receipt {$snapshot['receipt_number']} reversed · ₹" . inr($snapshot['amount']) . " · Invoice {$invoice->invoice_number}",
            $invoice,
            $snapshot
        );

        return redirect()->route('invoices.show', $invoice)
            ->with('status', 'Payment reversed.');
    }

    private function authorizePayment(Request $request, Payment $payment): void
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
    }

    public function sendReminder(Request $request, Invoice $invoice, ReminderService $reminders): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($reminders->isEligible($invoice), 422, 'This invoice is not eligible for reminders.');

        $data = $request->validate([
            'channel' => ['required', 'in:email,whatsapp,sms'],
        ]);

        $record = $reminders->send($invoice, $data['channel'], 'manual');

        if ($record->status === 'sent') {
            return back()->with('status', "Reminder sent via {$data['channel']} to {$record->recipient}.");
        }
        if ($record->status === 'skipped') {
            // Not a failure — the invoice was paid (or cancelled) between the
            // eligibility check and the send. Tell the user politely, not with
            // a red error banner.
            return back()->with('status', $record->error ?: 'No reminder sent. This invoice is already settled.');
        }
        return back()->withErrors(['reminder' => $record->error ?: 'Failed to send reminder.']);
    }

    /**
     * Export invoices as a GSTR-1-friendly CSV for the user's CA.
     *
     * Format columns map directly to the GSTR-1 B2B / B2C(L) sections:
     *   Invoice No, Date, Customer GSTIN, Customer Name, State, B2B/B2C,
     *   Reverse Charge, Place of Supply, Invoice Type, Taxable Value,
     *   CGST, SGST, IGST, Cess, Total, HSN/SAC summary
     *
     * Period accepted via ?from=YYYY-MM-DD&to=YYYY-MM-DD (defaults to current month).
     */
    public function gstr1Csv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $company = $request->user()->ensureCompany();
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        // Same build as the GSTR-1 page, the offline-tool workbook and GSTR-3B.
        $g = app(\App\Services\Gstr1Builder::class)->build($company, $from, $to);

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['customer.state', 'placeOfSupply', 'items'])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
        $notes = \App\Models\CreditNote::where('company_id', $company->id)
            ->whereBetween('credit_note_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['invoice.customer.state', 'invoice.placeOfSupply'])
            ->orderBy('credit_note_date')->orderBy('id')
            ->get();

        $filename = 'gstr1-' . $from->format('Y-m') . '-to-' . $to->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($invoices, $notes, $g, $company, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            $n = fn ($v) => number_format((float) $v, 2, '.', '');
            $table = function (Invoice $inv): string {
                if ($inv->isExport()) {
                    return 'Export (6A)';
                }
                if ($inv->isSez()) {
                    return 'SEZ (6B)';
                }
                if (! empty($inv->billedCustomer()?->gstin)) {
                    return $inv->reverse_charge ? 'B2B (4B)' : 'B2B (4A)';
                }

                return ($inv->is_interstate && (float) $inv->grand_total > \App\Services\Gstr1Builder::B2CL_LIMIT) ? 'B2CL (5)' : 'B2CS (7)';
            };

            // Context block
            csv_put($out, ["GSTR-1 Outward Supplies"]);
            csv_put($out, ["Supplier", $company->name . ($company->gstin ? ' (GSTIN: ' . $company->gstin . ')' : '')]);
            csv_put($out, ["Period", $from->format('d-M-Y') . ' to ' . $to->format('d-M-Y')]);
            csv_put($out, ["Generated", now()->format('d-M-Y H:i')]);
            csv_put($out, []);

            // Invoice register
            csv_put($out, [
                'Invoice No', 'Date', 'Customer Name', 'Customer GSTIN', 'Customer State',
                'Type', 'Reverse Charge', 'Place of Supply', 'Invoice Type',
                'Taxable Value (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)', 'IGST (Rs.)', 'Cess (Rs.)', 'Round-off', 'Total (Rs.)',
                'GSTR-1 Table',
            ]);

            $totalTaxable = $totalCgst = $totalSgst = $totalIgst = $totalGrand = 0;
            $b2bCount = $b2cCount = 0;

            foreach ($invoices as $inv) {
                // As issued, not as the customer row reads today: a GSTIN added
                // in June must not turn April's B2C invoices into B2B.
                $customer = $inv->billedCustomer();
                $isB2B = ! empty($customer?->gstin);
                $isB2B ? $b2bCount++ : $b2cCount++;

                $totalTaxable += (float) $inv->subtotal;
                $totalCgst += (float) $inv->total_cgst;
                $totalSgst += (float) $inv->total_sgst;
                $totalIgst += (float) $inv->total_igst;
                $totalGrand += (float) $inv->grand_total;

                // A reverse-charge invoice carries ₹0 tax but is still a tax
                // invoice (the recipient pays), and so is a zero-rated export or
                // SEZ supply under LUT ("supply meant for export under LUT"):
                // only composition dealers and exempt/nil supplies issue a
                // bill of supply (Rule 49).
                $invoiceType = ($company->composition_dealer
                    || (($inv->total_tax ?? 0) == 0 && ! $inv->reverse_charge && ! $inv->isZeroRated()))
                    ? 'Bill of Supply' : 'Tax Invoice';

                csv_put($out, [
                    $inv->invoice_number,
                    $inv->invoice_date?->format('d-M-Y'),
                    $customer?->name,
                    $customer?->gstin ?: '',
                    $customer?->state?->name ?: '',
                    $isB2B ? 'B2B' : 'B2C',
                    $inv->reverse_charge ? 'Yes' : 'No',
                    $inv->placeOfSupply?->name ?: '',
                    $invoiceType,
                    $n($inv->subtotal),
                    $n($inv->total_cgst),
                    $n($inv->total_sgst),
                    $n($inv->total_igst),
                    '0.00',
                    $n($inv->round_off ?? 0),
                    $n($inv->grand_total),
                    $table($inv),
                ]);
            }

            csv_put($out, []);
            csv_put($out, [
                '', '', '', '', '', 'TOTAL', '', '', '',
                $n($totalTaxable), $n($totalCgst), $n($totalSgst), $n($totalIgst), '0.00', '', $n($totalGrand), '',
            ]);
            csv_put($out, []);
            csv_put($out, ["Summary"]);
            csv_put($out, ["B2B Invoices (with GSTIN)", $b2bCount]);
            csv_put($out, ["B2C Invoices (without GSTIN)", $b2cCount]);
            csv_put($out, ["Total Invoices", $b2bCount + $b2cCount]);

            // Credit notes (Table 9B; notes on small B2C invoices net inside Table 7)
            csv_put($out, []);
            csv_put($out, ["Credit Notes (GSTR-1 Table 9B)"]);
            csv_put($out, ['Note No', 'Date', 'Against Invoice', 'Invoice Date', 'Customer Name', 'Customer GSTIN',
                'Taxable Value (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)', 'IGST (Rs.)', 'Note Value (Rs.)', 'GSTR-1 Table']);
            foreach ($notes as $cn) {
                $inv = $cn->invoice;
                $customer = $inv?->billedCustomer();
                $where = ! $inv ? '' : (! empty($customer?->gstin) ? 'CDNR (9B)'
                    : (in_array($table($inv), ['B2CL (5)', 'Export (6A)'], true) ? 'CDNUR (9B)' : 'Netted in B2CS (7)'));
                csv_put($out, [
                    $cn->credit_note_number, $cn->credit_note_date?->format('d-M-Y'),
                    $inv?->invoice_number, $inv?->invoice_date?->format('d-M-Y'),
                    $customer?->name, $customer?->gstin ?: '',
                    $n($cn->taxable_value), $n($cn->total_cgst), $n($cn->total_sgst), $n($cn->total_igst), $n($cn->amount), $where,
                ]);
            }
            if ($notes->isEmpty()) {
                csv_put($out, ['None in this period']);
            }

            // Table-wise totals and the GSTR-3B tie-out
            csv_put($out, []);
            csv_put($out, ["GSTR-1 Table-wise Totals"]);
            csv_put($out, ['Table', 'Section', 'Records', 'Taxable Value (Rs.)', 'IGST (Rs.)', 'CGST (Rs.)', 'SGST/UTGST (Rs.)']);
            foreach ($g['tables'] as $t) {
                csv_put($out, [$t['table'], $t['label'], $t['records'], $n($t['taxable']), $n($t['igst']), $n($t['cgst']), $n($t['sgst'])]);
            }
            $s = $g['summary'];
            csv_put($out, []);
            csv_put($out, ['GSTR-3B 3.1(a) - net taxable outward (excl. reverse charge)', '', '', $n($s['taxable']), $n($s['igst']), $n($s['cgst']), $n($s['sgst'])]);
            csv_put($out, ['GSTR-3B 3.1(c) - nil rated', '', '', $n($s['nil'])]);

            // HSN-wise summary (Table 12), B2B and B2C separately
            csv_put($out, []);
            csv_put($out, ["HSN/SAC Summary (GSTR-1 Table 12)"]);
            csv_put($out, ['Tab', 'HSN/SAC', 'Description', 'UQC', 'Total Quantity', 'Rate (%)', 'Total Value (Rs.)', 'Taxable Value (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)', 'IGST (Rs.)']);
            foreach ($g['hsn'] as $h) {
                csv_put($out, [
                    $h['tab'], $h['hsn'] ?: '-', $h['description'], $h['uqc'],
                    number_format((float) $h['qty'], 2, '.', ''), 0 + (float) $h['rate'],
                    $n($h['value']), $n($h['taxable']), $n($h['cgst']), $n($h['sgst']), $n($h['igst']),
                ]);
            }

            // Documents issued (Table 13)
            csv_put($out, []);
            csv_put($out, ["Documents Issued (GSTR-1 Table 13)"]);
            csv_put($out, ['Nature of Document', 'Sr. No. From', 'Sr. No. To', 'Total Number', 'Cancelled']);
            foreach ($g['docs'] as $d) {
                csv_put($out, [$d['nature'], $d['from'], $d['to'], $d['total'], $d['cancelled']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['items.product:id,name', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);
        $style = $invoice->style ?: 'classic';

        // Default to ink-saving "print" mode for downloads. Users who want the
        // colourful version can append ?color=1 to the URL.
        $print = ! $request->boolean('color');

        // CGST Rule 48(1): goods → 3 copies, services → 2 copies. Auto-detected
        // from line items. Caller can override the count with ?copies=1|2|3, or
        // pick exactly which copies with ?copyTypes=recipient,transporter,supplier.
        $copyLabels = $request->filled('copyTypes')
            ? $invoice->copyLabelsFor(array_filter(array_map('trim', explode(',', (string) $request->input('copyTypes')))))
            : $invoice->copyLabels(
                $request->has('copies')
                    ? max(1, min(3, (int) $request->input('copies')))
                    : $invoice->defaultCopyCount()
            );

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'amountInWords', 'style', 'print', 'copyLabels'))
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $filename = 'invoice-' . $invoice->filenameSafeNumber() . '.pdf';

        return $pdf->download($filename);
    }

    public function printView(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['items.product:id,name', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);

        // Thermal 80mm receipt layout for kirana/retail counter printers (2"/3").
        // Same data, different template — @page size: 80mm + single-column flow.
        // Selected via ?format=thermal so the existing "Print view" button keeps
        // its A4 behaviour and the new "Thermal print" button passes the flag.
        $format = $request->query('format') === 'thermal' ? 'thermal' : 'a4';
        if ($format === 'thermal') {
            return view('invoices.print-thermal', compact('invoice', 'amountInWords'));
        }

        return view('invoices.print', compact('invoice', 'amountInWords'));
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }

    /**
     * Server-side safety net for line-item descriptions.
     *
     * If the JS combobox failed to propagate the product name into the
     * description field — or if the user typed a placeholder like "0" — fall
     * back to the product's name so the PDF doesn't end up showing "0" or
     * an empty description column. We never overwrite a real, meaningful
     * description that the user typed; we only fill the obviously-bad ones.
     */
    /**
     * The web form sends one invoice-level discount instead of a discount per
     * line. Spread it across the lines before tax. Requests that still send
     * per-line discounts (the API, older clients) are left exactly as they are.
     */
    /**
     * An advance on a draft cannot exceed the bill: at issue it becomes a
     * receipt, and a receipt larger than the invoice would leave a negative
     * balance nobody asked for.
     */
    private function guardPaidAmount(array $data, array $calc): void
    {
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);
        $grand = (float) $calc['totals']['grand_total'];
        if ($paid > $grand + 0.001) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'paid_amount' => 'Paid amount (₹' . inr($paid) . ') cannot be more than the grand total (₹' . inr($grand) . ').',
            ]);
        }
    }

    private function applyInvoiceDiscount(array $data): array
    {
        if (! array_key_exists('invoice_discount', $data)) {
            return $data;
        }

        // Against what the lines come to after their own discounts, so the two
        // together can never exceed the items total.
        $gross = $this->calculator->valueAfterLineDiscounts($data['items']);
        if ((float) $data['invoice_discount'] > $gross + 0.001) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'invoice_discount' => 'Discount (₹' . inr((float) $data['invoice_discount']) . ') cannot be more than the items total (₹' . inr($gross) . ').',
            ]);
        }

        $data['items'] = $this->calculator->apportionDiscount($data['items'], (float) ($data['invoice_discount'] ?? 0));

        return $data;
    }

    private function hydrateProductDescriptions(array $data, int $companyId): array
    {
        if (empty($data['items']) || ! is_array($data['items'])) {
            return $data;
        }

        // Collect product ids referenced by any item to fetch in one query.
        $productIds = collect($data['items'])
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return $data;
        }

        $productNames = \App\Models\Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        foreach ($data['items'] as $i => $item) {
            $pid = $item['product_id'] ?? null;
            if (! $pid || ! isset($productNames[$pid])) {
                continue;
            }
            $desc = trim((string) ($item['description'] ?? ''));
            // Treat empty / "0" / "0.0" / "0.00" etc. as "needs fallback" — these
            // are the failure modes we've seen from a broken combobox or a stray
            // numeric paste. A real description like "Item #0" passes through.
            $looksLikeJunk = $desc === '' || preg_match('/^0+(\.0+)?$/', $desc) === 1;
            if ($looksLikeJunk) {
                $data['items'][$i]['description'] = $productNames[$pid];
            }
        }

        return $data;
    }

    /**
     * Validate the invoice form.
     *
     * Accepts the full Company instance (not just id) so HSN/SAC validation
     * can be conditional: HSN is legally required ONLY when at least one
     * party is GST-registered (Rule 46(g)). Cash sales between two
     * unregistered parties don't need HSN — forcing it would be friction
     * without compliance value.
     */
    private function validateInvoice(Request $request, \App\Models\Company $company): array
    {
        $companyId = $company->id;
        $customerId = (int) $request->input('customer_id');
        $customer = $customerId ? $company->customers()->find($customerId) : null;
        $hsnRequired = ! empty($company->gstin) || ! empty($customer?->gstin);

        $rules = [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            // Custom invoice number, available to every business.
            //
            // Rule 46(b) requires a consecutive series unique to the financial
            // year, and a GST-registered supplier remains responsible for that.
            // But the series belongs to the business, not to us: a firm moving
            // over mid-year has to continue the numbering its books and its
            // buyers already carry, and refusing that forces them to either
            // restart their series or stay on their old tool.
            //
            // So the field is editable and the one thing that would actually
            // produce a defective document - two invoices sharing a number,
            // which breaks the buyer's input credit - is refused outright.
            // A custom number also teaches the counter (see
            // Company::learnSeriesFrom) so later auto-numbers continue the
            // same series rather than diverging from it.
            // Rule 46(b): at most 16 characters, letters/digits/"-"/"/" only.
            'invoice_number' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9\/-]+$/',
                Rule::unique('invoices', 'invoice_number')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($request->route('invoice')?->id)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reverse_charge' => ['nullable', 'boolean'],
            ...\App\Support\ZeroRatedSupply::rules(),
            'style' => ['nullable', 'string', 'in:' . implode(',', array_keys(config('invoice_styles')))],
            'transporter_name' => ['nullable', 'string', 'max:120'],
            'transporter_id' => ['nullable', 'string', 'max:40'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'transport_mode' => ['nullable', 'string', 'in:Road,Rail,Air,Ship'],
            'eway_bill_number' => ['nullable', 'string', 'max:30'],
            // Ship-to override (goods delivered somewhere other than Bill-to).
            'ship_to_name' => ['nullable', 'string', 'max:255'],
            'ship_to_address_line1' => ['nullable', 'string', 'max:255'],
            'ship_to_address_line2' => ['nullable', 'string', 'max:255'],
            'ship_to_city' => ['nullable', 'string', 'max:100'],
            'ship_to_state_id' => ['nullable', 'exists:states,id'],
            'ship_to_postal_code' => ['nullable', 'string', 'max:10'],
            'ship_to_gstin' => ['nullable', 'string', new \App\Rules\ValidGstin(
                $request->input('ship_to_state_id') ? (int) $request->input('ship_to_state_id') : null
            )],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            // One discount for the whole invoice (the form's bottom box); it is
            // spread across the lines before tax - see apportionDiscount().
            'invoice_discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            // Description is OPTIONAL — if blank and a product is picked, the
            // server backfills it from the product's name (see hydrateProduct-
            // Descriptions). Cap at 150 — the invoice_items.description column
            // is VARCHAR(255), and 150 is plenty for a real line-item description.
            'items.*.description' => ['nullable', 'string', 'max:150'],
            // HSN required only when either party is GST-registered.
            'items.*.hsn_sac' => [$hsnRequired ? 'required' : 'nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:' . implode(',', config('gst.allowed_values'))],
        ];

        return $request->validate($rules, $this->itemErrorMessages($request));
    }

    /**
     * Human, row-numbered messages for the per-item rules. Turns Laravel's
     * default "The items.2.hsn_sac field is required" into "Row 3: enter the
     * HSN/SAC code" so a user with a long invoice can see which line failed.
     */
    private function itemErrorMessages(Request $request): array
    {
        $messages = [
            'items.required' => 'Add at least one line item to the invoice.',
            'items.min' => 'Add at least one line item to the invoice.',
            'invoice_number.unique' => 'You already have an invoice with this number. Pick a different one, or leave it blank to auto-number.',
        ];

        foreach (array_keys((array) $request->input('items', [])) as $i) {
            $n = ((int) $i) + 1;
            $messages["items.$i.hsn_sac.required"] = "Row {$n}: enter the HSN/SAC code (required because a GSTIN is present).";
            $messages["items.$i.hsn_sac.max"] = "Row {$n}: the HSN/SAC code is too long.";
            $messages["items.$i.quantity.required"] = "Row {$n}: enter a quantity.";
            $messages["items.$i.quantity.numeric"] = "Row {$n}: quantity must be a number.";
            $messages["items.$i.quantity.min"] = "Row {$n}: quantity must be more than zero.";
            $messages["items.$i.rate.required"] = "Row {$n}: enter a rate.";
            $messages["items.$i.rate.numeric"] = "Row {$n}: rate must be a number.";
            $messages["items.$i.rate.min"] = "Row {$n}: rate cannot be negative.";
            $messages["items.$i.discount.numeric"] = "Row {$n}: discount must be a number.";
            $messages["items.$i.discount.min"] = "Row {$n}: discount cannot be negative.";
            $messages["items.$i.gst_rate.required"] = "Row {$n}: select a GST rate.";
            $messages["items.$i.gst_rate.in"] = "Row {$n}: choose a valid GST rate.";
            $messages["items.$i.description.max"] = "Row {$n}: the description is too long (max 150 characters).";
            $messages["items.$i.product_id.exists"] = "Row {$n}: the selected product is not valid.";
        }

        return $messages;
    }
}
