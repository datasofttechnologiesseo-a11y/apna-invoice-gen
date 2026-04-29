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

    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        $invoices = $company->invoices()
            ->with(['customer', 'company'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $s) {
                $term = trim($s);
                // Normalize phone: strip spaces, dashes, + and parentheses so that
                // "+91 98765-43210" matches a stored "9876543210" (and vice versa).
                $digits = preg_replace('/[^0-9]/', '', $term);

                $q->where(function ($w) use ($term, $digits) {
                    $w->where('invoice_number', 'like', "%{$term}%")
                      ->orWhereHas('customer', function ($c) use ($term, $digits) {
                          $c->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                          if ($digits !== '' && strlen($digits) >= 4) {
                              // Match the digit-only form of the stored phone too.
                              $c->orWhereRaw(
                                  "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'+',''),'(',''),')','') LIKE ?",
                                  ["%{$digits}%"]
                              );
                          }
                      });
                });
            })
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', compact('invoices', 'company'));
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
            'description' => 'Sample line item — your real entries go here',
            'hsn_sac' => '998313',
            'quantity' => 1,
            'unit' => 'unit',
            'rate' => 10000,
            'gst_rate' => $tpl['items'][0]['gst_rate'] ?? 18,
        ]];
        $result = $calc->recalculate(new Invoice(), $previewItems, $isInterstate);

        $fakeState = $company->state ?? State::first();

        $fakeCustomer = new Customer([
            'name' => 'Sample Customer Pvt. Ltd.',
            'gstin' => '27ABCDE1234F1Z5',
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
            'terms' => $company->default_terms ?? "Sample preview — this is how your invoice will look.\nReplace with your own terms.",
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

        if (! $company->state_id) {
            return redirect()->route('company.edit')
                ->with('status', 'Set your company state before creating invoices — GST place-of-supply needs it.');
        }
        if ($company->customers()->count() === 0) {
            return redirect()->route('customers.create')
                ->with('status', 'Add at least one customer to ' . $company->name . ' before creating an invoice.');
        }

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

        if (! $company->state_id) {
            return redirect()->route('company.edit')
                ->with('status', 'Set your company state before creating invoices — GST place-of-supply needs it.');
        }

        $customers = $company->customers()->orderBy('name')->get();
        if ($customers->isEmpty()) {
            return redirect()->route('customers.create')
                ->with('status', 'Add at least one customer before creating an invoice.');
        }

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
        $data = $this->validateInvoice($request, $company->id);

        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $reverseCharge = (bool) ($data['reverse_charge'] ?? false);
        $calc = $this->calculator->recalculate(new Invoice(), $data['items'], $isInterstate, $reverseCharge);

        $invoice = DB::transaction(function () use ($user, $company, $customer, $data, $calc, $isInterstate) {
            $invoice = $user->invoices()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number' => null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
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
                'balance' => $calc['totals']['grand_total'] - (float) ($data['paid_amount'] ?? 0),
                'paid_amount' => (float) ($data['paid_amount'] ?? 0),
            ]);

            $invoice->items()->createMany($calc['items']);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice created.');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['items', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);

        return view('invoices.show', compact('invoice', 'amountInWords'));
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isSoftEditable(), 403, 'This invoice cannot be edited.');

        // Scope the customer dropdown and counter preview to the invoice's OWN
        // company, not the currently-active company. Lets users edit invoices
        // that belong to a non-active company without needing to switch first.
        $company = $invoice->company;
        $customers = $company->customers()->orderBy('name')->get();
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

        // Finalized invoices: only notes, terms, due_date, and transporter fields
        // may change. Amounts, items, customer, and invoice number are immutable.
        if (! $invoice->isEditable()) {
            $soft = $request->validate([
                'due_date' => ['nullable', 'date'],
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
                ->with('status', 'Invoice details updated. (Amounts, items and customer are locked after finalisation.)');
        }

        $company = $invoice->company;
        $data = $this->validateInvoice($request, $company->id);
        $user = $request->user();
        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $reverseCharge = (bool) ($data['reverse_charge'] ?? false);
        $calc = $this->calculator->recalculate($invoice, $data['items'], $isInterstate, $reverseCharge);

        DB::transaction(function () use ($invoice, $customer, $data, $calc, $isInterstate) {
            $paid = (float) ($data['paid_amount'] ?? 0);
            $invoice->update([
                'customer_id' => $customer->id,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
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
                'balance' => $calc['totals']['grand_total'] - $paid,
            ]);

            $invoice->items()->delete();
            $invoice->items()->createMany($calc['items']);
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isEditable(), 403, 'Finalized invoices cannot be deleted.');

        // Books-period lock: even drafts dated inside a closed FY shouldn't be silently dropped.
        if ($invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return redirect()->back()->with('error', "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. This draft cannot be deleted.");
        }

        \App\Models\AuditLog::record('invoice.deleted',
            "Draft #{$invoice->id} deleted",
            $invoice,
            $invoice->only(['invoice_number', 'invoice_date', 'grand_total', 'status'])
        );

        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Draft deleted.');
    }

    public function finalize(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isDraft(), 403);

        if ($invoice->items()->count() === 0) {
            return back()->withErrors(['finalize' => 'Cannot finalize an invoice with no line items.']);
        }
        if ((float) $invoice->grand_total <= 0) {
            return back()->withErrors(['finalize' => 'Cannot finalize a zero-amount invoice.']);
        }

        // Books-period lock: cannot finalize an invoice into a closed FY.
        if ($invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return redirect()->back()->with('error', "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. Cannot finalize an invoice dated on or before that.");
        }

        DB::transaction(function () use ($invoice) {
            $company = $invoice->company()->lockForUpdate()->first();
            // Atomic: resets counter on FY boundary, increments, stamps format.
            $number = $company->bumpCounterForFinalize($invoice->invoice_date?->toDateString());

            $invoice->update([
                'invoice_number' => $number,
                'status' => (float) $invoice->paid_amount >= (float) $invoice->grand_total ? 'paid' : ((float) $invoice->paid_amount > 0 ? 'partially_paid' : 'final'),
                'finalized_at' => now(),
            ]);
        });

        \App\Models\AuditLog::record('invoice.finalized',
            "Invoice {$invoice->fresh()->invoice_number} finalized · ₹" . number_format($invoice->grand_total, 2),
            $invoice
        );

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice finalized. Number: ' . $invoice->fresh()->invoice_number);
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);

        // Payments are only legally meaningful on issued invoices. Blocking
        // payment against drafts keeps the audit trail clean.
        abort_if($invoice->isDraft(), 422, 'Finalize the invoice before recording payments.');
        abort_if($invoice->status === 'cancelled', 422, 'Cancelled invoices cannot accept payments.');

        // Remaining also subtracts credited_amount — a credit note reduces how
        // much is actually owed, so the payment cap shouldn't let users overpay
        // into a negative balance just because paid alone hasn't hit grand_total.
        $remaining = max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount - (float) $invoice->credited_amount);
        $methods = array_keys(config('payment_methods.methods'));

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$remaining}"],
            'method' => ['required', 'string', 'in:' . implode(',', $methods)],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            // Section 51 / 194-x of Income Tax Act: TDS deducted at source by the
            // customer. Stored for Form 26AS reconciliation. 30% cap is the
            // maximum legal TDS rate in India (lottery 194B + 206AA penalty).
            'tds_amount' => ['nullable', 'numeric', 'min:0', 'lt:amount'],
            'tds_section' => ['nullable', 'string', 'max:16'],
            'tds_rate' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);

        $payment = DB::transaction(function () use ($invoice, $data, $request) {
            // Lock the company row and increment the receipt counter atomically
            // so concurrent payments can never get the same receipt number.
            $company = $invoice->company()->lockForUpdate()->first();
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
            "₹" . number_format($payment->amount, 2) . " · " . strtoupper($payment->method) . " · Receipt {$payment->receipt_number} · Invoice {$invoice->invoice_number}",
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
        if ($company->isBooksLockedOn($payment->received_at) || $company->isBooksLockedOn($invoice->invoice_date)) {
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
            "Receipt {$snapshot['receipt_number']} reversed · ₹" . number_format($snapshot['amount'], 2) . " · Invoice {$invoice->invoice_number}",
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

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->with(['customer.state', 'placeOfSupply', 'items'])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $filename = 'gstr1-' . $from->format('Y-m') . '-to-' . $to->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($invoices, $company, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            // Context block
            fputcsv($out, ["GSTR-1 Outward Supplies"]);
            fputcsv($out, ["Supplier", $company->name . ($company->gstin ? ' (GSTIN: ' . $company->gstin . ')' : '')]);
            fputcsv($out, ["Period", $from->format('d-M-Y') . ' to ' . $to->format('d-M-Y')]);
            fputcsv($out, ["Generated", now()->format('d-M-Y H:i')]);
            fputcsv($out, []);

            // Detailed B2B + B2C
            fputcsv($out, [
                'Invoice No', 'Date', 'Customer Name', 'Customer GSTIN', 'Customer State',
                'Type', 'Reverse Charge', 'Place of Supply', 'Invoice Type',
                'Taxable Value (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)', 'IGST (Rs.)', 'Cess (Rs.)', 'Round-off', 'Total (Rs.)',
            ]);

            $totalTaxable = $totalCgst = $totalSgst = $totalIgst = $totalGrand = 0;
            $b2bCount = $b2cCount = 0;

            foreach ($invoices as $inv) {
                $customer = $inv->customer;
                $isB2B = ! empty($customer?->gstin);
                $isB2B ? $b2bCount++ : $b2cCount++;

                $cgst = (float) $inv->total_cgst;
                $sgst = (float) $inv->total_sgst;
                $igst = (float) $inv->total_igst;
                $taxable = (float) $inv->subtotal;
                $grand = (float) $inv->grand_total;

                $totalTaxable += $taxable;
                $totalCgst += $cgst;
                $totalSgst += $sgst;
                $totalIgst += $igst;
                $totalGrand += $grand;

                $invoiceType = ($company->composition_dealer || ($inv->total_tax ?? 0) == 0) ? 'Bill of Supply' : 'Tax Invoice';

                fputcsv($out, [
                    $inv->invoice_number,
                    $inv->invoice_date?->format('d-M-Y'),
                    $customer?->name,
                    $customer?->gstin ?: '',
                    $customer?->state?->name ?: '',
                    $isB2B ? 'B2B' : 'B2C',
                    $inv->reverse_charge ? 'Yes' : 'No',
                    $inv->placeOfSupply?->name ?: '',
                    $invoiceType,
                    number_format($taxable, 2, '.', ''),
                    number_format($cgst, 2, '.', ''),
                    number_format($sgst, 2, '.', ''),
                    number_format($igst, 2, '.', ''),
                    '0.00',
                    number_format((float) ($inv->round_off ?? 0), 2, '.', ''),
                    number_format($grand, 2, '.', ''),
                ]);
            }

            // Totals row
            fputcsv($out, []);
            fputcsv($out, [
                '', '', '', '', '', 'TOTAL', '', '', '',
                number_format($totalTaxable, 2, '.', ''),
                number_format($totalCgst, 2, '.', ''),
                number_format($totalSgst, 2, '.', ''),
                number_format($totalIgst, 2, '.', ''),
                '0.00',
                '',
                number_format($totalGrand, 2, '.', ''),
            ]);
            fputcsv($out, []);
            fputcsv($out, ["Summary"]);
            fputcsv($out, ["B2B Invoices (with GSTIN)", $b2bCount]);
            fputcsv($out, ["B2C Invoices (without GSTIN)", $b2cCount]);
            fputcsv($out, ["Total Invoices", $b2bCount + $b2cCount]);

            // HSN-wise summary (Table 12 in GSTR-1)
            $hsnMap = [];
            foreach ($invoices as $inv) {
                foreach ($inv->items as $item) {
                    $key = $item->hsn_sac ?: '—';
                    if (! isset($hsnMap[$key])) {
                        $hsnMap[$key] = ['qty' => 0, 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
                    }
                    $hsnMap[$key]['qty'] += (float) $item->quantity;
                    $hsnMap[$key]['taxable'] += (float) $item->amount;
                    $hsnMap[$key]['cgst'] += (float) ($item->cgst_amount ?? 0);
                    $hsnMap[$key]['sgst'] += (float) ($item->sgst_amount ?? 0);
                    $hsnMap[$key]['igst'] += (float) ($item->igst_amount ?? 0);
                }
            }

            fputcsv($out, []);
            fputcsv($out, ["HSN/SAC Summary (GSTR-1 Table 12)"]);
            fputcsv($out, ['HSN/SAC', 'Total Quantity', 'Taxable Value (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)', 'IGST (Rs.)']);
            foreach ($hsnMap as $hsn => $totals) {
                fputcsv($out, [
                    $hsn,
                    number_format($totals['qty'], 2, '.', ''),
                    number_format($totals['taxable'], 2, '.', ''),
                    number_format($totals['cgst'], 2, '.', ''),
                    number_format($totals['sgst'], 2, '.', ''),
                    number_format($totals['igst'], 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['items', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);
        $style = $invoice->style ?: 'classic';

        // Default to ink-saving "print" mode for downloads. Users who want the
        // colourful version can append ?color=1 to the URL.
        $print = ! $request->boolean('color');

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'amountInWords', 'style', 'print'))
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $filename = 'invoice-' . $invoice->filenameSafeNumber() . '.pdf';

        return $pdf->download($filename);
    }

    public function printView(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['items', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);

        return view('invoices.print', compact('invoice', 'amountInWords'));
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }

    private function validateInvoice(Request $request, int $companyId): array
    {
        return $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reverse_charge' => ['nullable', 'boolean'],
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            // Cap at 150 — the invoice_items.description column is VARCHAR(255),
            // and 150 is plenty for a real line-item description. Keeping validation
            // below the column size prevents a DB 1406 leaking a 500 page.
            'items.*.description' => ['required', 'string', 'max:150'],
            'items.*.hsn_sac' => ['required', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:' . implode(',', config('gst.allowed_values'))],
        ]);
    }
}
