<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\State;
use App\Rules\ValidGstin;
use App\Rules\ValidPan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $companies = $user->companies()
            ->withCount(['customers', 'invoices'])
            ->orderBy('name')
            ->get();
        $active = $user->ensureCompany();

        return view('companies.index', compact('companies', 'active'));
    }

    public function create(): View
    {
        $company = new Company([
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'invoice_number_padding' => 4,
        ]);
        $states = State::orderBy('name')->get();

        return view('company.edit', compact('company', 'states'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $company = $user->companies()->create($data);

        if ($request->hasFile('logo')) {
            $company->update(['logo_path' => $request->file('logo')->store('logos', 'public')]);
        }
        if ($request->hasFile('signature')) {
            $company->update(['signature_path' => $request->file('signature')->store('signatures', 'public')]);
        }

        // Make the new company the active one so follow-up actions target it
        $user->switchCompany($company);

        return redirect()->route('companies.index')
            ->with('status', "Company '{$company->name}' created and made active.");
    }

    public function edit(Request $request, Company $company): View
    {
        abort_unless($company->user_id === $request->user()->id, 403);
        $states = State::orderBy('name')->get();

        return view('company.edit', compact('company', 'states'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->user_id === $request->user()->id, 403);

        $data = $this->validated($request);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        if ($request->hasFile('signature')) {
            if ($company->signature_path) {
                Storage::disk('public')->delete($company->signature_path);
            }
            $data['signature_path'] = $request->file('signature')->store('signatures', 'public');
        }
        unset($data['logo'], $data['signature']);

        $company->update($data);

        return redirect()->route('companies.index')->with('status', "'{$company->name}' saved.");
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->user_id === $request->user()->id, 403);

        if ($company->invoices()->exists()) {
            return back()->withErrors(['company' => 'Cannot delete a company that has invoices. Archive instead.']);
        }

        $user = $request->user();
        $willDeleteActive = $user->active_company_id === $company->id;

        $company->customers()->delete(); // cascade-delete unused customers tied to this company
        $company->delete();

        if ($willDeleteActive) {
            $next = $user->companies()->orderBy('id')->first();
            $user->forceFill(['active_company_id' => $next?->id])->save();
        }

        return redirect()->route('companies.index')->with('status', "Company deleted.");
    }

    public function switch(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->user_id === $request->user()->id, 403);
        $request->user()->switchCompany($company);

        return back()->with('status', "Switched to {$company->name}.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', new ValidGstin($request->input('state_id') ? (int) $request->input('state_id') : null)],
            'pan' => ['nullable', 'string', new ValidPan],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_id' => ['nullable', 'exists:states,id'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'default_currency' => ['required', 'string', 'size:3'],
            'default_terms' => ['nullable', 'string'],
            'declaration' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:15'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'upi_id' => ['nullable', 'string', 'max:60'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'invoice_number_padding' => ['required', 'integer', 'min:1', 'max:8'],
            'invoice_number_format' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-\/{} ]+$/'],
        ]);

        unset($data['logo'], $data['signature']);
        return $data;
    }
}
