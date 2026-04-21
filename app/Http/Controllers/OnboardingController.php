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
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state_id' => ['required', 'exists:states,id'],
            'postal_code' => ['required', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'default_currency' => ['required', 'string', 'size:3'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:15'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'upi_id' => ['nullable', 'string', 'max:60'],
        ]);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        unset($data['logo']);

        $company->update($data);

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
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
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
