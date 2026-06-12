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
            ->additional(['meta' => ['active_company_id' => $user->active_company_id]]);
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
        $company->update($this->validated($request));
        $company->load('state');

        return new CompanyResource($company);
    }

    public function switch(Request $request, Company $company): CompanyResource
    {
        $this->authorizeCompany($request, $company);
        $request->user()->switchCompany($company);
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
        return $request->validate([
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
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:15'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'upi_id' => ['nullable', 'string', 'max:60'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'invoice_number_padding' => ['required', 'integer', 'min:1', 'max:8'],
            'invoice_number_format' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-\/{} ]+$/'],
        ]);
    }
}
