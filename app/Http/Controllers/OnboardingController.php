<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\State;
use App\Rules\ValidGstin;
use App\Rules\ValidPan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        if (! $company->isBusinessComplete()) {
            return redirect()->route('onboarding.business');
        }
        if ($user->customers()->count() === 0) {
            return redirect()->route('onboarding.customer');
        }
        return redirect()->route('onboarding.done');
    }

    public function business(Request $request): View|RedirectResponse
    {
        $company = $request->user()->ensureCompany();

        // If already complete, skip forward unless explicit edit via ?edit=1
        if ($company->isBusinessComplete() && ! $request->boolean('edit')) {
            return redirect()->route('onboarding.index');
        }

        $states = State::orderBy('name')->get();

        return view('onboarding.business', compact('company', 'states'));
    }

    public function saveBusiness(Request $request): RedirectResponse
    {
        $company = $request->user()->ensureCompany();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', new ValidGstin($request->input('state_id') ? (int) $request->input('state_id') : null)],
            'pan' => ['nullable', 'string', new ValidPan],
            // Address is mandatory on a tax invoice (Rule 46), so GST-registered
            // businesses (GSTIN given) must provide it. Unregistered sellers can
            // fill it in later — fewer required fields = fewer drop-offs on the
            // way to the first bill.
            'address_line1' => ['required_with:gstin', 'nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required_with:gstin', 'nullable', 'string', 'max:100'],
            'state_id' => ['required', 'exists:states,id'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            // Prefix lives behind the collapsed "more details" panel on the
            // setup form, so a user who never opens it must not be blocked by
            // a required-field error on a control they cannot see.
            'invoice_prefix' => ['nullable', 'string', 'max:10'],
            'default_currency' => ['required', 'string', 'size:3'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:15'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'upi_id' => ['nullable', 'string', 'max:60'],
        ], [
            'address_line1.required_with' => 'A registered address is required on GST tax invoices. Please add your address (or clear the GSTIN and add it later).',
            'city.required_with' => 'City is required on GST tax invoices when a GSTIN is set.',
        ]);

        $data['invoice_prefix'] = trim((string) ($data['invoice_prefix'] ?? ''))
            ?: ($company->invoice_prefix ?: 'INV');

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        unset($data['logo']);

        $company->update($data);

        // Promote new users to FY-reset numbering by default (Rule 46(a) best
        // practice — series reset on 1 April). The user keeps their chosen
        // prefix; we just expand it into a {FY}-aware format so they don't
        // have to know about the format string. They can edit it later in
        // Company settings if they want a different layout. We only set this
        // when format is empty so we never trample a returning user.
        if (empty($company->invoice_number_format)) {
            $company->update([
                'invoice_number_format' => trim($data['invoice_prefix']) . '/{FY}/{N}',
            ]);
        }

        return redirect()->route('onboarding.customer')->with('status', 'Business details saved.');
    }

    public function customer(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        // Prerequisites
        if (! $company->isBusinessComplete()) {
            return redirect()->route('onboarding.business');
        }
        // If already has customers, skip forward unless explicit add via ?more=1
        if ($user->customers()->count() > 0 && ! $request->boolean('more')) {
            return redirect()->route('onboarding.done');
        }

        $states = State::orderBy('name')->get();
        $customer = new Customer(['country' => 'India']);

        return view('onboarding.customer', compact('customer', 'states'));
    }

    public function saveCustomer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', new ValidGstin($request->input('state_id') ? (int) $request->input('state_id') : null)],
            // Only name + state are needed to bill correctly (state drives the
            // CGST/SGST vs IGST split). Address can be completed later from the
            // customer's page — same policy as the quick-add modal.
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_id' => ['required', 'exists:states,id'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $user = $request->user();
        $data['company_id'] = $user->ensureCompany()->id;
        $user->customers()->create($data);

        return redirect()->route('onboarding.done');
    }

    public function skipCustomer(Request $request): RedirectResponse
    {
        return redirect()->route('onboarding.done');
    }

    public function done(Request $request): View
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $hasCustomer = $user->customers()->exists();

        if (! $company->isOnboarded()) {
            $company->update(['onboarded_at' => now()]);
        }

        return view('onboarding.done', compact('company', 'hasCustomer'));
    }
}
