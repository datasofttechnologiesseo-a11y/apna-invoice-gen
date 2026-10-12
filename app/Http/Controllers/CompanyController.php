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
        // Businesses this user works in as a team member.
        $memberships = $user->memberships()->with('company')->get()->filter(fn ($m) => $m->company);

        return view('companies.index', compact('companies', 'active', 'memberships'));
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
            $company->update(['logo_path' => \App\Support\BrandImage::storeLogo($request->file('logo'))]);
        }
        if ($request->hasFile('signature')) {
            $company->update(['signature_path' => \App\Support\BrandImage::storeSignature($request->file('signature'))]);
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

        $data = $this->validated($request, $company);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = \App\Support\BrandImage::storeLogo($request->file('logo'));
        }
        if ($request->hasFile('signature')) {
            if ($company->signature_path) {
                Storage::disk('public')->delete($company->signature_path);
            }
            $data['signature_path'] = \App\Support\BrandImage::storeSignature($request->file('signature'));
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
            session()->flash('error', "Next invoice number cannot be set to {$next}, because the next invoice is already {$company->nextInvoiceNumber()}. Enter a higher number.");
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
            return back()->withErrors(['company' => 'This company has invoices and cannot be deleted.']);
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
        $request->user()->switchCompany($company); // owner or team member, else 403

        // Into someone else's business: start at the home page, which every
        // role can open (the page you were on may not be part of your role).
        return ((int) $company->user_id === (int) $request->user()->id ? back() : redirect()->route('dashboard'))
            ->with('status', "Switched to {$company->name}.");
    }

    /**
     * What the numbering settings will print for the current financial year,
     * using the same tokens as Company::nextInvoiceNumber ({FY}, {FY_SHORT},
     * {YYYY}, {N}). Used to check Rule 46(b) and for the on-page warning.
     */
    public static function previewInvoiceNumber(string $prefix, ?string $format, int $padding, ?\Illuminate\Support\Carbon $on = null): string
    {
        $on ??= now();
        [$fyStart, $fyEnd] = \App\Models\Company::financialYearFor($on);
        $padded = str_pad('1', max(1, $padding), '0', STR_PAD_LEFT);

        if (! filled($format)) {
            return $prefix . '-' . $padded;
        }

        return strtr($format, [
            '{FY}' => sprintf('%04d-%02d', $fyStart, $fyEnd % 100),
            '{FY_SHORT}' => sprintf('%02d-%02d', $fyStart % 100, $fyEnd % 100),
            '{YYYY}' => $on->format('Y'),
            '{N}' => $padded,
        ]);
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        // A GSTIN already on file and left as it is never blocks saving the
        // rest of the settings (one saved before the checksum check existed
        // would otherwise lock the whole form). A new or changed GSTIN is
        // checked in full.
        $unchangedGstin = $company && $company->gstin
            && strtoupper(trim((string) $request->input('gstin'))) === strtoupper((string) $company->gstin)
            && (int) $request->input('state_id') === (int) $company->state_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gstin' => $unchangedGstin
                ? ['nullable', 'string', 'max:15']
                : ['nullable', 'string', new ValidGstin($request->input('state_id') ? (int) $request->input('state_id') : null)],
            'composition_dealer' => ['nullable', 'boolean'],
            'books_locked_until' => ['nullable', 'date'],
            // Letter of Undertaking for zero-rated supplies without IGST (Rule 96A);
            // printed on every export / SEZ invoice issued under it.
            'lut_number' => ['nullable', 'string', 'max:40'],
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
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_account_type' => ['nullable', 'in:savings,current'],
            'bank_ifsc' => ['nullable', 'string', 'max:15'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'upi_id' => ['nullable', 'string', 'max:60'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'invoice_number_padding' => ['required', 'integer', 'min:1', 'max:8'],
            'invoice_number_format' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-\/{} ]+$/'],
        ]);

        unset($data['logo'], $data['signature']);

        // Rule 46(b): a tax-invoice number is at most 16 characters, from
        // letters, digits, "-" and "/". Enforced only when the numbering is
        // being changed, so a company whose current series is longer can
        // still save other settings - and sees a warning on the page instead.
        $company = $request->route('company');
        $numberingChanged = ! $company
            || ($data['invoice_prefix'] ?? null) !== $company->invoice_prefix
            || ($data['invoice_number_format'] ?? null) !== $company->invoice_number_format
            || (int) ($data['invoice_number_padding'] ?? 0) !== (int) $company->invoice_number_padding;
        if ($numberingChanged) {
            $preview = self::previewInvoiceNumber(
                (string) ($data['invoice_prefix'] ?? ''),
                $data['invoice_number_format'] ?? null,
                (int) ($data['invoice_number_padding'] ?? 4),
            );
            if (strlen($preview) > 16 || ! preg_match('/^[A-Za-z0-9\/-]+$/', $preview)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'invoice_number_format' => "GST invoice numbers must be 16 characters or fewer, using only letters, digits, \"-\" and \"/\" (CGST Rule 46(b)). This series would produce \"{$preview}\" (" . strlen($preview) . ' characters).',
                ]);
            }
        }

        return $data;
    }
}
