<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = $request->user()
            ->customers()
            ->with('state')
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create(): View
    {
        $customer = new Customer(['country' => 'India']);
        $states = State::orderBy('name')->get();
        return view('customers.edit', compact('customer', 'states'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $customer = $request->user()->customers()->create($data);
        return redirect()->route('customers.index')->with('status', "Customer '{$customer->name}' added.");
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
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
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
