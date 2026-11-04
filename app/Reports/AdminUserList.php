<?php

namespace App\Reports;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin download of registered users: contact details, verification
 * dates and where the business is (from its business details; the account
 * itself has no address). Platform-wide, so it is not tied to a company;
 * the exporter's company line reads "Apna Invoice".
 *
 * ?scope=verified (default): email or mobile verified. ?scope=all: everyone.
 * Erased accounts (DPDP right to erasure) are never included.
 */
class AdminUserList extends Report
{
    public function __construct(Request $request)
    {
        parent::__construct((new Company())->forceFill(['name' => 'Apna Invoice', 'gstin' => null]), $request);
    }

    public function allUsers(): bool
    {
        return $this->request->query('scope') === 'all';
    }

    public function title(): string
    {
        return $this->allUsers() ? 'All users' : 'Verified users';
    }

    public function subtitle(): string
    {
        $parts = [$this->allUsers() ? 'Every registered account' : 'Email or mobile verified'];
        foreach ($this->filterLabels() as $label) {
            $parts[] = $label;
        }

        return implode(' · ', $parts);
    }

    public function filename(): string
    {
        return ($this->allUsers() ? 'apna-invoice-all-users-' : 'apna-invoice-verified-users-') . now()->format('Y-m-d');
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function landscape(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'width' => 24],
            ['key' => 'email', 'label' => 'Email', 'width' => 30],
            ['key' => 'email_verified', 'label' => 'Email verified on', 'type' => 'date'],
            ['key' => 'phone', 'label' => 'Mobile', 'width' => 16],
            ['key' => 'phone_verified', 'label' => 'Mobile verified on', 'type' => 'date'],
            ['key' => 'business', 'label' => 'Business name', 'width' => 28],
            ['key' => 'gstin', 'label' => 'GSTIN', 'width' => 18],
            ['key' => 'address', 'label' => 'Address', 'width' => 36],
            ['key' => 'city', 'label' => 'City', 'width' => 16],
            ['key' => 'state', 'label' => 'State', 'width' => 18],
            ['key' => 'pin', 'label' => 'PIN code', 'width' => 10],
            ['key' => 'signup', 'label' => 'Signed up with', 'width' => 14],
            ['key' => 'registered', 'label' => 'Registered on', 'type' => 'date'],
            ['key' => 'businesses', 'label' => 'Businesses', 'type' => 'number'],
            ['key' => 'invoices', 'label' => 'Invoices issued', 'type' => 'number'],
            ['key' => 'marketing', 'label' => 'Marketing emails', 'width' => 12],
        ];
    }

    public function rows(): iterable
    {
        // The latest marketing choice per user decides "Marketing emails".
        $marketing = DB::table('user_consents as c')
            ->where('c.consent_type', 'marketing')
            ->whereRaw('c.id = (select max(c2.id) from user_consents c2 where c2.user_id = c.user_id and c2.consent_type = ?)', ['marketing'])
            ->pluck('c.given', 'c.user_id');

        $query = User::query()
            ->whereNull('erased_at')
            ->when(! $this->allUsers(), fn ($q) => $q->where(fn ($w) => $w->whereNotNull('email_verified_at')->orWhereNotNull('phone_verified_at')))
            ->when($this->request->query('search'), function ($q, $s) {
                $q->where(function ($w) use ($s) {
                    $w->whereLiteralLike('name', $s)->orWhereLiteralLike('email', $s);
                    if (($digits = preg_replace('/\D/', '', (string) $s)) !== '') {
                        $w->orWhereLiteralLike('phone', $digits);
                    }
                });
            })
            ->withCount(['companies', 'invoices as issued_count' => fn ($q) => $q->whereNotNull('finalized_at')])
            ->with(['companies' => fn ($q) => $q->with('state')->orderBy('id')])
            ->orderByDesc('created_at')
            ->orderByDesc('id'); // unique tie-break: lazy() pages by offset

        foreach ($query->lazy(500) as $u) {
            // The business the user works in now, else their first one.
            $company = $u->companies->firstWhere('id', $u->active_company_id) ?? $u->companies->first();

            yield [
                'name' => $u->name,
                'email' => $u->email,
                'email_verified' => $u->email_verified_at,
                'phone' => $u->phone,
                'phone_verified' => $u->phone_verified_at,
                'business' => $company?->name,
                'gstin' => $company?->gstin,
                'address' => $company ? collect([$company->address_line1, $company->address_line2])->filter()->implode(', ') : null,
                'city' => $company?->city,
                'state' => $company?->state?->name,
                'pin' => $company?->postal_code,
                'signup' => $u->google_id ? 'Google' : 'Email',
                'registered' => $u->created_at,
                'businesses' => (int) $u->companies_count,
                'invoices' => (int) $u->issued_count,
                'marketing' => isset($marketing[$u->id]) ? ($marketing[$u->id] ? 'Yes' : 'No') : 'No',
            ];
        }
    }
}
