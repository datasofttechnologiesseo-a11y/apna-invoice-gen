<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Rules\ValidGstin;
use App\Rules\ValidPan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->ensureCompany();

        $companies = $user->companies()
            ->with('state')
            ->withCount(['customers', 'invoices'])
            ->orderBy('name')
            ->get();

        return CompanyResource::collection($companies)
            ->additional(['meta' => [
                'active_company_id' => $user->active_company_id,
                // Businesses this user works in as a team member (switch to them by id).
                'memberships' => $user->memberships()->with('company:id,name,gstin')->get()
                    ->filter(fn ($m) => $m->company)
                    ->map(fn ($m) => ['company_id' => $m->company->id, 'name' => $m->company->name, 'gstin' => $m->company->gstin, 'role' => $m->role, 'role_label' => $m->roleLabel()])
                    ->values(),
            ]]);
    }

    /** The currently active company for this user. */
    public function active(Request $request): CompanyResource
    {
        $company = $request->user()->ensureCompany();
        $company->load('state');

        return new CompanyResource($company);
    }

    public function show(Request $request, Company $company): CompanyResource
    {
        $this->authorizeCompany($request, $company);
        $company->load('state');

        return new CompanyResource($company);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->companies()->create($this->validated($request));
        $user->switchCompany($company);
        $company->load('state');

        return (new CompanyResource($company))->response()->setStatusCode(201);
    }

    public function update(Request $request, Company $company): CompanyResource
    {
        $this->authorizeCompany($request, $company);
        $company->fill($this->validated($request));
        // Logged like the web settings: bank account (masked) and books lock
        // with the old and new values.
        \App\Support\CompanyChangeLog::record($company);
        $company->save();
        $company->load('state');

        return new CompanyResource($company);
    }

    public function switch(Request $request, Company $company): CompanyResource
    {
        $request->user()->switchCompany($company); // owner or team member, else 403
        $company->load('state');

        return new CompanyResource($company);
    }

    private function authorizeCompany(Request $request, Company $company): void
    {
        abort_unless($company->user_id === $request->user()->id, 403);
    }

    /**
     * API validation mirrors the web rules, minus the file-upload fields
     * (logo/signature) which the mobile client uploads via a dedicated
     * multipart endpoint later.
     */
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
            'state_id' => ['required', 'exists:states,id'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
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

        // The same checks the web settings make, so the app cannot save what
        // the website refuses. Each is applied only when the value is new or
        // changed: a value saved before a check existed never blocks saving
        // the rest of the settings.
        $company = $request->route('company');
        $errors = [];

        // Rule 46(b): a tax invoice number is at most 16 characters, from
        // letters, digits, "-" and "/".
        $numberingChanged = ! $company
            || ($data['invoice_prefix'] ?? null) !== $company->invoice_prefix
            || ($data['invoice_number_format'] ?? null) !== $company->invoice_number_format
            || (int) ($data['invoice_number_padding'] ?? 0) !== (int) $company->invoice_number_padding;
        if ($numberingChanged) {
            $preview = \App\Http\Controllers\CompanyController::previewInvoiceNumber(
                (string) ($data['invoice_prefix'] ?? ''),
                $data['invoice_number_format'] ?? null,
                (int) ($data['invoice_number_padding'] ?? 4),
            );
            if (strlen($preview) > 16 || ! preg_match('/^[A-Za-z0-9\/-]+$/', $preview)) {
                $errors['invoice_number_format'] = "GST invoice numbers must be 16 characters or fewer, using only letters, digits, \"-\" and \"/\" (CGST Rule 46(b)). This series would produce \"{$preview}\" (" . strlen($preview) . ' characters).';
            }
        }

        if (! empty($data['bank_ifsc'])) {
            $data['bank_ifsc'] = strtoupper(preg_replace('/\s+/', '', $data['bank_ifsc']));
            if ($data['bank_ifsc'] !== $company?->bank_ifsc && ! preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $data['bank_ifsc'])) {
                $errors['bank_ifsc'] = 'IFSC should be 11 characters: 4 letters, a zero, then 6 letters or digits (e.g. SBIN0001234).';
            }
        }
        if (! empty($data['upi_id'])) {
            $data['upi_id'] = trim($data['upi_id']);
            if ($data['upi_id'] !== $company?->upi_id && ! preg_match('/^[A-Za-z0-9._-]{2,256}@[A-Za-z][A-Za-z0-9.-]{1,64}$/', $data['upi_id'])) {
                $errors['upi_id'] = 'UPI ID should look like name@bank (e.g. sharmatraders@okicici).';
            }
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        return $data;
    }
}
