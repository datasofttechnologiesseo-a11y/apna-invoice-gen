<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\State;
use App\Rules\ValidGstin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        $customers = $company->customers()
            ->with('state')
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers', 'company'));
    }

    public function create(): View
    {
        $customer = new Customer(['country' => 'India']);
        $states = State::orderBy('name')->get();
        return view('customers.edit', compact('customer', 'states'));
    }

    /**
     * Customer ledger — every invoice + payment + balance for one customer.
     * What a CA opens first when reconciling a customer's account.
     */
    public function ledger(Request $request, Customer $customer): View
    {
        $company = $request->user()->ensureCompany();
        abort_unless($customer->company_id === $company->id, 403);

        $customer->load('state');

        $invoices = $customer->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $totals = [
            'invoiced' => (float) $invoices->sum('grand_total'),
            'received' => (float) $invoices->sum('paid_amount'),
            'credited' => (float) $invoices->sum('credited_amount'),
        ];
        $totals['outstanding'] = $totals['invoiced'] - $totals['received'] - $totals['credited'];

        // Build a chronological ledger of debits (invoices) and credits (payments + credit notes)
        $entries = collect();
        foreach ($invoices as $inv) {
            $entries->push([
                'date' => $inv->invoice_date,
                'type' => 'invoice',
                'ref' => $inv->invoice_number,
                'particulars' => 'Invoice raised',
                'debit' => (float) $inv->grand_total,
                'credit' => 0.0,
                'invoice' => $inv,
            ]);
            foreach ($inv->payments as $p) {
                $entries->push([
                    'date' => $p->received_at ?? $p->created_at,
                    'type' => 'payment',
                    'ref' => $p->reference_number ?: 'Payment',
                    'particulars' => 'Payment received · ' . strtoupper($p->method ?? 'received'),
                    'debit' => 0.0,
                    'credit' => (float) $p->amount,
                ]);
            }
            foreach ($inv->creditNotes as $cn) {
                $entries->push([
                    'date' => $cn->credit_note_date,
                    'type' => 'credit_note',
                    'ref' => $cn->credit_note_number,
                    'particulars' => 'Credit note · ' . ($cn->reason ?? ''),
                    'debit' => 0.0,
                    'credit' => (float) $cn->amount,
                ]);
            }
        }
        $entries = $entries->sortBy(['date', 'type'])->values();

        // Running balance
        $running = 0.0;
        foreach ($entries as $i => $e) {
            $running += ($e['debit'] - $e['credit']);
            $entries[$i] = array_merge($e, ['balance' => $running]);
        }

        return view('customers.ledger', compact('customer', 'company', 'entries', 'totals'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        $data = $this->validated($request);
        $data['company_id'] = $company->id;
        $customer = $user->customers()->create($data);
        return redirect()->route('customers.index')->with('status', "Customer '{$customer->name}' added to {$company->name}.");
    }

    public function edit(Request $request, Customer $customer): View
    {
        abort_unless($customer->user_id === $request->user()->id, 403);
        $states = State::orderBy('name')->get();
        return view('customers.edit', compact('customer', 'states'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->user_id === $request->user()->id, 403);

        $customer->update($this->validated($request));
        return redirect()->route('customers.index')->with('status', "Customer '{$customer->name}' updated.");
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->user_id === $request->user()->id, 403);

        if ($customer->invoices()->exists()) {
            return back()->withErrors(['customer' => 'Cannot delete — customer has invoices.']);
        }

        $customer->delete();
        return redirect()->route('customers.index')->with('status', 'Customer deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', new ValidGstin($request->input('state_id') ? (int) $request->input('state_id') : null)],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_id' => ['required', 'exists:states,id'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
