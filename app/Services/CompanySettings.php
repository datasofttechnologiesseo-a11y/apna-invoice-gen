<?php

namespace App\Services;

use App\Http\Controllers\CompanyController;
use App\Models\Company;
use App\Rules\ValidGstin;
use App\Rules\ValidPan;
use App\Support\BrandImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The business settings form, for the web (CompanyController) and the
 * mobile app (Api\CompanyController): what may be saved, the logo and
 * signature, and "set next invoice number". One set of rules, so the app
 * cannot save what the website refuses.
 */
class CompanySettings
{
    /**
     * Validate the settings form. Returns the fields to fill (never the
     * uploaded files; see applyImages()).
     *
     * @throws ValidationException
     */
    public function validate(Request $request, ?Company $company = null): array
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
            'logo' => self::LOGO_RULES,
            'signature' => self::SIGNATURE_RULES,
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
            $preview = CompanyController::previewInvoiceNumber(
                (string) ($data['invoice_prefix'] ?? ''),
                $data['invoice_number_format'] ?? null,
                (int) ($data['invoice_number_padding'] ?? 4),
            );
            if (strlen($preview) > 16 || ! preg_match('/^[A-Za-z0-9\/-]+$/', $preview)) {
                throw ValidationException::withMessages([
                    'invoice_number_format' => "GST invoice numbers must be 16 characters or fewer, using only letters, digits, \"-\" and \"/\" (CGST Rule 46(b)). This series would produce \"{$preview}\" (".strlen($preview).' characters).',
                ]);
            }
        }

        // IFSC and UPI print on every invoice and the UPI ID drives the pay QR,
        // so a typo costs the business its payments. Checked when entered or
        // changed only: a value saved before this check never blocks saving
        // the rest of the settings.
        if (! empty($data['bank_ifsc'])) {
            $data['bank_ifsc'] = strtoupper(preg_replace('/\s+/', '', $data['bank_ifsc']));
        }
        if (! empty($data['upi_id'])) {
            $data['upi_id'] = trim($data['upi_id']);
        }
        $errors = [];
        if (! empty($data['bank_ifsc']) && $data['bank_ifsc'] !== ($company?->bank_ifsc)
            && ! preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $data['bank_ifsc'])) {
            $errors['bank_ifsc'] = 'IFSC should be 11 characters: 4 letters, a zero, then 6 letters or digits (e.g. SBIN0001234).';
        }
        if (! empty($data['upi_id']) && $data['upi_id'] !== ($company?->upi_id)
            && ! preg_match('/^[A-Za-z0-9._-]{2,256}@[A-Za-z][A-Za-z0-9.-]{1,64}$/', $data['upi_id'])) {
            $errors['upi_id'] = 'UPI ID should look like name@bank (e.g. sharmatraders@okicici).';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    public const LOGO_RULES = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

    public const SIGNATURE_RULES = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'];

    /**
     * A new logo / signature replaces the old file; remove_logo /
     * remove_signature clears it. Returns the attributes to fill and the
     * activity-log words ("logo replaced"). Files must already be validated.
     *
     * @return array{0: array, 1: array<int, string>}
     */
    public function applyImages(Request $request, Company $company): array
    {
        $data = [];
        $changes = [];
        foreach (['logo' => 'storeLogo', 'signature' => 'storeSignature'] as $field => $store) {
            $column = $field.'_path';
            if ($request->hasFile($field)) {
                if ($company->{$column}) {
                    BrandImage::delete($company->{$column});
                }
                $data[$column] = BrandImage::{$store}($request->file($field));
                $changes[] = "{$field} replaced";
            } elseif ($request->boolean('remove_'.$field) && $company->{$column}) {
                BrandImage::delete($company->{$column});
                $data[$column] = null;
                $changes[] = "{$field} removed";
            }
        }

        return [$data, $changes];
    }

    /**
     * Optional one-shot "I'm migrating mid-FY from another tool, my next
     * invoice number should be N" override. Set invoice_counter to N-1 so the
     * next finalize bumps it to N.
     *
     * Refuses to set the counter to a value ≤ the counter already reached in
     * the current FY (would create duplicate numbers). A bad value never rolls
     * back the rest of the save: the caller shows the error beside the result.
     *
     * @return array{changed: bool, error?: string, next?: string}
     */
    public function setNextInvoiceNumber(Company $company, mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return ['changed' => false];
        }

        $v = Validator::make(
            ['next_invoice_number' => $raw],
            ['next_invoice_number' => ['integer', 'min:1', 'max:9999999']],
            ['next_invoice_number.*' => 'Next invoice number must be a positive whole number (e.g. 145).']
        );
        if ($v->fails()) {
            return ['changed' => false, 'error' => $v->errors()->first('next_invoice_number')];
        }

        $next = (int) $raw;
        $newCounter = $next - 1;
        $current = (int) ($company->invoice_counter ?? 0);
        if ($newCounter === $current) {
            return ['changed' => false]; // the existing counter already produces this number
        }

        // Refuse to go BACKWARD past invoice numbers already issued this FY.
        // We compare counter values, not parsed strings, since the format may
        // change over time; the counter is the source of truth.
        if ($newCounter < $current) {
            return ['changed' => false, 'error' => "Next invoice number cannot be set to {$next}, because the next invoice is already {$company->nextInvoiceNumber()}. Enter a higher number."];
        }

        // Bump the FY tag to the current FY too, so the FY-reset logic doesn't
        // wipe the new starting point on the next finalize.
        [$fyStartYear] = Company::financialYearFor(now());
        $company->update([
            'invoice_counter' => $newCounter,
            'invoice_counter_fy' => $fyStartYear,
        ]);

        return ['changed' => true, 'next' => $company->fresh()->nextInvoiceNumber()];
    }
}
