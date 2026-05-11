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

        // "Set next invoice number" — handled separately so it can be validated
        // against already-issued invoices in the current FY (preventing
        // duplicates) without polluting the main validation rules.
        $statusExtra = $this->applyNextInvoiceNumber($request, $company);

        $status = "'{$company->name}' saved." . $statusExtra;
        return $this->redirectAfterSave($request, $company, $status);
    }

    /**
     * Optional one-shot "I'm migrating mid-FY from another tool, my next
     * invoice number should be N" override. Set invoice_counter to N-1 so the
     * next finalize bumps it to N.
     *
     * Refuses to set the counter to a value ≤ the highest invoice_number
     * already issued in the current FY (would create duplicate numbers).
     * Returns a status fragment for the flash message, or '' on no-op.
     */
    private function applyNextInvoiceNumber(Request $request, Company $company): string
    {
        $raw = $request->input('next_invoice_number');
        if ($raw === null || $raw === '') {
            return '';
        }

        // Soft validate: must be a positive integer. We use a separate
        // validator so a bad value here doesn't roll back the whole form save
        // — the user just sees an inline error and the rest of their edits
        // (logo, terms, GSTIN, etc.) still persist.
        $v = \Illuminate\Support\Facades\Validator::make(
            ['next_invoice_number' => $raw],
            ['next_invoice_number' => ['integer', 'min:1', 'max:9999999']],
            ['next_invoice_number.*' => 'Next invoice number must be a positive whole number (e.g. 145).']
        );
        if ($v->fails()) {
            session()->flash('error', $v->errors()->first('next_invoice_number'));
            return '';
        }

        $next = (int) $raw;

        $newCounter = $next - 1;
        $current = (int) ($company->invoice_counter ?? 0);
        if ($newCounter === $current) {
            return ''; // no-op, the existing counter already produces this number
        }

        // Refuse to go BACKWARD past invoice numbers already issued this FY.
        // We compare counter values, not parsed strings, since the format may
        // change over time; the counter is the source of truth.
        if ($newCounter < $current) {
            session()->flash('error', "Cannot set next invoice number to {$next} — your current counter is at {$current}, so the next invoice would already be #{$company->nextInvoiceNumber()}. Set a higher number to skip ahead.");
            return '';
        }

        // Bump the FY tag to the current FY too, so the FY-reset logic doesn't
        // wipe the new starting point on the next finalize.
        [$fyStartYear] = \App\Models\Company::financialYearFor(now());
        $company->update([
            'invoice_counter' => $newCounter,
            'invoice_counter_fy' => $fyStartYear,
        ]);

        $preview = $company->fresh()->nextInvoiceNumber();
        return " Next invoice will be {$preview}.";
    }

    /**
     * Send the user back to wherever they came from after a save. The form
     * carries an optional `from` field; we whitelist a few safe values so a
     * crafted hidden input can never bounce the user to an open redirect.
     */
    private function redirectAfterSave(Request $request, Company $company, string $status): RedirectResponse
    {
        $from = (string) $request->input('from');
        $target = match ($from) {
            'settings' => route('settings.index'),
            'dashboard' => route('dashboard'),
            default => route('companies.index'),
        };
        return redirect()->to($target)->with('status', $status);
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
            'composition_dealer' => ['nullable', 'boolean'],
            'books_locked_until' => ['nullable', 'date'],
            'pan' => ['nullable', 'string', new ValidPan],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            // State drives CGST/SGST vs IGST on every invoice — make it required
            // here too, matching the onboarding rule. A company with no state breaks
            // GST determination downstream.
            'state_id' => ['required', 'exists:states,id'],
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
