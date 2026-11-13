<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanySettings;
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
                // Businesses this user works in as a team member (switch to them by
                // company_id; leave with POST /api/team/leave/{member_id}).
                'memberships' => $user->memberships()->with('company:id,name,gstin')->get()
                    ->filter(fn ($m) => $m->company)
                    ->map(fn ($m) => ['member_id' => $m->id, 'company_id' => $m->company->id, 'name' => $m->company->name, 'gstin' => $m->company->gstin, 'role' => $m->role, 'role_label' => $m->roleLabel()])
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

    /**
     * Save the business settings: the same fields and checks as the web
     * settings page (CompanySettings), including lut_number, an optional
     * next_invoice_number and, sent as multipart (POST with _method=PATCH),
     * a logo / signature or remove_logo / remove_signature.
     *
     * A next_invoice_number that cannot be used does not undo the rest of the
     * save (as on the web): it comes back in meta.warnings.
     */
    public function update(Request $request, Company $company): CompanyResource
    {
        $this->authorizeCompany($request, $company);
        $settings = app(CompanySettings::class);
        $data = $settings->validate($request, $company);
        [$images, $imageChanges] = $settings->applyImages($request, $company);

        $company->fill($images + $data);
        // Logged like the web settings: bank account (masked) and books lock
        // with the old and new values.
        \App\Support\CompanyChangeLog::record($company, $imageChanges);
        $company->save();

        $next = $settings->setNextInvoiceNumber($company, $request->input('next_invoice_number'));
        $company->refresh()->load('state');

        return (new CompanyResource($company))->additional(['meta' => [
            'warnings' => isset($next['error']) ? ['next_invoice_number' => $next['error']] : (object) [],
        ]]);
    }

    /**
     * Logo and signature on their own (multipart): logo, signature,
     * remove_logo, remove_signature. The same files and checks as the web
     * settings page, without resending the rest of the form.
     */
    public function branding(Request $request, Company $company): CompanyResource
    {
        $this->authorizeCompany($request, $company);
        $request->validate([
            'logo' => CompanySettings::LOGO_RULES,
            'signature' => CompanySettings::SIGNATURE_RULES,
            'remove_logo' => ['nullable', 'boolean'],
            'remove_signature' => ['nullable', 'boolean'],
        ]);

        [$images, $imageChanges] = app(CompanySettings::class)->applyImages($request, $company);
        $company->fill($images);
        \App\Support\CompanyChangeLog::record($company, $imageChanges);
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

    /** The web settings rules (CompanySettings); uploaded files are handled by the caller. */
    private function validated(Request $request): array
    {
        return app(CompanySettings::class)->validate($request, $request->route('company'));
    }
}
