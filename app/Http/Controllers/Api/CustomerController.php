<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Rules\ValidGstin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->ensureCompany();

        $customers = $company->customers()
            ->with('state')
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return CustomerResource::collection($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        $data = $this->validated($request);
        $data['company_id'] = $company->id;
        $customer = $user->customers()->create($data);
        $customer->load('state');

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        $this->authorizeCustomer($request, $customer);
        $customer->load('state');

        return new CustomerResource($customer);
    }

    /**
     * Customer ledger — chronological debits (invoices) and credits (payments +
     * credit notes) with a running balance. Mirrors the web CustomerController::ledger
     * exactly so the figures match the desktop report a CA reconciles against.
     */
    public function ledger(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($request, $customer);
        $customer->load('state');

        $invoices = $customer->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->with(['payments', 'creditNotes'])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $totals = [
            'invoiced' => (float) $invoices->sum('grand_total'),
            'received' => (float) $invoices->sum('paid_amount'),
            'credited' => (float) $invoices->sum('credited_amount'),
        ];
        $totals['outstanding'] = $totals['invoiced'] - $totals['received'] - $totals['credited'];

        $entries = collect();
        foreach ($invoices as $inv) {
            $entries->push([
                'date' => optional($inv->invoice_date)->toDateString() ?? (string) $inv->invoice_date,
                'type' => 'invoice',
                'ref' => $inv->invoice_number,
                'particulars' => 'Invoice raised',
                'debit' => (float) $inv->grand_total,
                'credit' => 0.0,
            ]);
            foreach ($inv->payments as $p) {
                $entries->push([
                    'date' => optional($p->received_at ?? $p->created_at)->toDateString(),
                    'type' => 'payment',
                    'ref' => $p->reference_number ?: 'Payment',
                    'particulars' => 'Payment received · ' . strtoupper($p->method ?? 'received'),
                    'debit' => 0.0,
                    'credit' => (float) $p->amount,
                ]);
            }
            foreach ($inv->creditNotes as $cn) {
                $entries->push([
                    'date' => optional($cn->credit_note_date)->toDateString() ?? (string) $cn->credit_note_date,
                    'type' => 'credit_note',
                    'ref' => $cn->credit_note_number,
                    'particulars' => 'Credit note · ' . ($cn->reason ?? ''),
                    'debit' => 0.0,
                    'credit' => (float) $cn->amount,
                ]);
            }
        }
        $entries = $entries->sortBy(['date', 'type'])->values();

        $running = 0.0;
        $entries = $entries->map(function ($e) use (&$running) {
            $running += ($e['debit'] - $e['credit']);
            $e['balance'] = round($running, 2);

            return $e;
        });

        return response()->json([
            'customer' => new CustomerResource($customer),
            'totals' => $totals,
            'entries' => $entries,
        ]);
    }

    public function update(Request $request, Customer $customer): CustomerResource
    {
        $this->authorizeCustomer($request, $customer);
        $customer->update($this->validated($request));
        $customer->load('state');

        return new CustomerResource($customer);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($request, $customer);

        if ($customer->invoices()->exists()) {
            return response()->json([
                'message' => 'Cannot delete — customer has invoices.',
            ], 422);
        }

        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    private function authorizeCustomer(Request $request, Customer $customer): void
    {
        // Match the web controller: ownership is by user_id, which lets a user
        // touch customers across any of their companies.
        abort_unless($customer->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', new ValidGstin($request->input('state_id') ? (int) $request->input('state_id') : null)],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_id' => ['required', 'exists:states,id'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $data['country'] = $data['country'] ?? 'India';

        return $data;
    }
}
