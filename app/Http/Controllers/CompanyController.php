<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\State;
use App\Services\CompanySettings;
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

        // The books lock has its own small form on the settings page.
        if ($request->input('section') === 'books_lock') {
            return $this->updateBooksLock($request, $company);
        }

        $data = $this->validated($request, $company);

        [$images, $imageChanges] = app(CompanySettings::class)->applyImages($request, $company);
        $data = $images + $data;
        unset($data['logo'], $data['signature']);

        $company->fill($data);
        $this->recordChanges($company, $imageChanges);
        $company->save();

        // "Set next invoice number" — handled separately so it can be validated
        // against already-issued invoices in the current FY (preventing
        // duplicates) without polluting the main validation rules.
        $statusExtra = $this->applyNextInvoiceNumber($request, $company);

        $status = "'{$company->name}' saved." . $statusExtra;
        return $this->redirectAfterSave($request, $company, $status);
    }

    /**
     * Optional "set next invoice number" (CompanySettings::setNextInvoiceNumber).
     * A bad value does not roll back the rest of the save: it is flashed as an
     * error. Returns a status fragment for the flash message, or '' on no-op.
     */
    private function applyNextInvoiceNumber(Request $request, Company $company): string
    {
        $result = app(CompanySettings::class)->setNextInvoiceNumber($company, $request->input('next_invoice_number'));
        if (isset($result['error'])) {
            session()->flash('error', $result['error']);

            return '';
        }

        return $result['changed'] ? " Next invoice will be {$result['next']}." : '';
    }

    /**
     * After a save, stay on the settings form with the result at the top, so
     * the owner sees what was saved and can carry on editing. The optional
     * `from` value (settings, dashboard) is kept for the breadcrumb; it is
     * whitelisted so a crafted hidden input can never become an open redirect.
     */
    private function redirectAfterSave(Request $request, Company $company, string $status): RedirectResponse
    {
        $from = in_array($request->input('from'), ['settings', 'dashboard'], true) ? $request->input('from') : null;

        return redirect()->route('companies.edit', array_filter(['company' => $company->id, 'from' => $from]))
            ->with('status', $status);
    }

    /**
     * "Lock books up to": its own form, so changing it never resubmits (or
     * fails on) the rest of the settings. Recorded in the activity log.
     */
    private function updateBooksLock(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'books_locked_until' => ['nullable', 'date'],
        ]);
        $old = $company->books_locked_until?->toDateString();
        $new = filled($data['books_locked_until'] ?? null) ? \Illuminate\Support\Carbon::parse($data['books_locked_until'])->toDateString() : null;

        if ($old !== $new) {
            $company->update(['books_locked_until' => $new]);
            AuditLog::record(
                'company.books_lock_changed',
                $new ? "{$company->name}: books locked up to " . \Illuminate\Support\Carbon::parse($new)->format('d M Y') : "{$company->name}: books unlocked",
                $company,
                ['books_locked_until' => [$old, $new]],
            );
        }

        $status = $new
            ? 'Books locked up to ' . \Illuminate\Support\Carbon::parse($new)->format('d M Y') . '.'
            : 'Books unlocked. All entries can be edited.';

        return redirect()->route('companies.edit', array_filter(['company' => $company->id, 'from' => in_array($request->input('from'), ['settings', 'dashboard'], true) ? $request->input('from') : null]))
            ->withFragment('books-lock')
            ->with('status', $status);
    }

    /**
     * Write what changed to the activity log: business details and bank
     * details as separate entries, because a changed bank account on the
     * invoices is the change an owner most needs to be able to trace.
     * Account numbers are masked to their last four digits.
     */
    private function recordChanges(Company $company, array $imageChanges): void
    {
        $bankFields = [
            'bank_name' => 'bank name', 'bank_account_name' => 'payee name', 'bank_account_number' => 'account number',
            'bank_account_type' => 'account type', 'bank_ifsc' => 'IFSC', 'bank_branch' => 'branch', 'upi_id' => 'UPI ID',
        ];
        $labels = [
            'name' => 'business name', 'gstin' => 'GSTIN', 'pan' => 'PAN', 'composition_dealer' => 'composition scheme',
            'lut_number' => 'LUT number', 'address_line1' => 'address', 'address_line2' => 'address', 'city' => 'city',
            'state_id' => 'state', 'postal_code' => 'PIN code', 'country' => 'country', 'phone' => 'phone', 'email' => 'email',
            'website' => 'website', 'default_terms' => 'terms', 'declaration' => 'declaration',
            'invoice_prefix' => 'invoice prefix', 'invoice_number_padding' => 'number padding',
            'invoice_number_format' => 'number format', 'books_locked_until' => 'books lock',
        ];
        $mask = fn ($v) => $v === null || $v === '' ? $v : 'ending ' . substr((string) $v, -4);

        $dirty = array_keys($company->getDirty());
        $bank = [];
        $other = [];
        foreach ($dirty as $field) {
            $old = $company->getOriginal($field);
            $new = $company->getAttribute($field);
            if ((string) ($old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old) === (string) ($new instanceof \DateTimeInterface ? $new->format('Y-m-d') : $new)) {
                continue;
            }
            if (isset($bankFields[$field])) {
                $bank[$field] = $field === 'bank_account_number' ? [$mask($old), $mask($new)] : [$old, $new];
            } elseif (isset($labels[$field])) {
                $other[$field] = in_array($field, ['logo_path', 'signature_path'], true) ? null : [
                    $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old,
                    $new instanceof \DateTimeInterface ? $new->format('Y-m-d') : $new,
                ];
            }
        }

        if ($other || $imageChanges) {
            $what = collect(array_keys($other))->map(fn ($f) => $labels[$f])->merge($imageChanges)->unique()->implode(', ');
            AuditLog::record('company.updated', "{$company->name}: {$what}", $company, $other ?: null);
        }
        if ($bank) {
            $what = collect(array_keys($bank))->map(fn ($f) => $bankFields[$f])->unique()->implode(', ');
            AuditLog::record('company.bank_updated', "{$company->name}: {$what}", $company, $bank);
        }
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->user_id === $request->user()->id, 403);

        // Anything in the books (invoices, quotations, purchases, expenses,
        // payments, bank statements, stock) would be erased with the business
        // by ON DELETE CASCADE, input tax credit included. Refused, like invoices.
        if (\App\Support\BusinessBooks::exist($company)) {
            return back()->withErrors(['company' => "{$company->name} has invoices, purchases, expenses or other entries in its books, so it cannot be deleted."]);
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
        $user = $request->user();
        $user->switchCompany($company); // owner or team member, else 403

        // Into someone else's business: start at the first page the role
        // opens (the page you were on may not be part of it, and billing
        // staff have no dashboard).
        if ((int) $company->user_id === (int) $user->id) {
            return back()->with('status', "Switched to {$company->name}.");
        }
        $membership = \App\Models\CompanyMember::where('company_id', $company->id)->where('user_id', $user->id)->whereNotNull('accepted_at')->first();

        return redirect()->route(\App\Support\Team::homeRoute($membership))
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
        return app(CompanySettings::class)->validate($request, $company);
    }
}
