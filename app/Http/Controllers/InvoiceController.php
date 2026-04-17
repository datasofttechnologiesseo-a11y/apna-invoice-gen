<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Services\InvoiceCalculator;
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
        $invoices = $request->user()->invoices()
            ->with(['customer', 'company'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('invoice_number', 'like', "%{$s}%"))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        if (! $company->state_id) {
            return redirect()->route('company.edit')
                ->with('status', 'Set your company state before creating invoices — GST place-of-supply needs it.');
        }

        $customers = $user->customers()->orderBy('name')->get();
        if ($customers->isEmpty()) {
            return redirect()->route('customers.create')
                ->with('status', 'Add at least one customer before creating an invoice.');
        }

        $states = State::orderBy('name')->get();

        $invoice = new Invoice([
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => $company->default_currency,
            'terms' => $company->default_terms,
            'status' => 'draft',
        ]);
        $invoice->setRelation('items', collect());

        return view('invoices.edit', [
            'invoice' => $invoice,
            'company' => $company,
            'customers' => $customers,
            'states' => $states,
            'previewNumber' => $company->nextInvoiceNumber() . ' (preview)',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInvoice($request);
        $user = $request->user();
        $company = $user->ensureCompany();

        $customer = $user->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $calc = $this->calculator->recalculate(new Invoice(), $data['items'], $isInterstate);

        $invoice = DB::transaction(function () use ($user, $company, $customer, $data, $calc, $isInterstate) {
            $invoice = $user->invoices()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number' => 'DRAFT-' . Str::upper(Str::random(12)),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
                'currency' => $data['currency'],
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'status' => 'draft',
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
        $invoice->load(['items', 'customer.state', 'company.state', 'placeOfSupply']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);

        return view('invoices.show', compact('invoice', 'amountInWords'));
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isEditable(), 403, 'Finalized invoices cannot be edited.');

        $user = $request->user();
        $company = $user->ensureCompany();
        $customers = $user->customers()->orderBy('name')->get();
        $states = State::orderBy('name')->get();
        $invoice->load('items');

        return view('invoices.edit', [
            'invoice' => $invoice,
            'company' => $company,
            'customers' => $customers,
            'states' => $states,
            'previewNumber' => null,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isEditable(), 403, 'Finalized invoices cannot be edited.');

        $data = $this->validateInvoice($request);
        $user = $request->user();
        $company = $invoice->company;
        $customer = $user->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $calc = $this->calculator->recalculate($invoice, $data['items'], $isInterstate);

        DB::transaction(function () use ($invoice, $customer, $data, $calc, $isInterstate) {
            $paid = (float) ($data['paid_amount'] ?? 0);
            $invoice->update([
                'customer_id' => $customer->id,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
                'currency' => $data['currency'],
                'exchange_rate' => $data['exchange_rate'] ?? 1,
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

        DB::transaction(function () use ($invoice) {
            $company = $invoice->company()->lockForUpdate()->first();
            $company->increment('invoice_counter');
            $invoice->update([
                'invoice_number' => $company->invoice_prefix . '-' . str_pad((string) $company->invoice_counter, $company->invoice_number_padding, '0', STR_PAD_LEFT),
                'status' => (float) $invoice->paid_amount >= (float) $invoice->grand_total ? 'paid' : ((float) $invoice->paid_amount > 0 ? 'partially_paid' : 'final'),
                'finalized_at' => now(),
            ]);
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice finalized. Number: ' . $invoice->fresh()->invoice_number);
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $remaining = max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$remaining}"],
        ]);

        $paid = (float) $invoice->paid_amount + (float) $data['amount'];
        $balance = (float) $invoice->grand_total - $paid;

        $status = $invoice->status;
        if (! $invoice->isDraft()) {
            $status = $paid >= (float) $invoice->grand_total ? 'paid' : 'partially_paid';
        }

        $invoice->update([
            'paid_amount' => $paid,
            'balance' => $balance,
            'status' => $status,
        ]);

        return back()->with('status', 'Payment recorded.');
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['items', 'customer.state', 'company.state', 'placeOfSupply']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'amountInWords'))
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $filename = 'invoice-' . ($invoice->isDraft() ? 'DRAFT' : $invoice->invoice_number) . '.pdf';

        return $pdf->download($filename);
    }

    public function printView(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['items', 'customer.state', 'company.state', 'placeOfSupply']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);

        return view('invoices.print', compact('invoice', 'amountInWords'));
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }

    private function validateInvoice(Request $request): array
    {
        $userId = $request->user()->id;
        return $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('user_id', $userId)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'reverse_charge' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.hsn_sac' => ['required', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:0,0.10,0.25,3,5,12,18,28'],
        ]);
    }
}
