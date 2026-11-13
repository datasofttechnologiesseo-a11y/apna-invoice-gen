<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use App\Rules\ValidGstin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * Vendors (suppliers). The web pages and the mobile app both go through here,
 * so a vendor is validated, listed and refused deletion by the same rules
 * whichever door it came in by.
 */
class VendorBook
{
    /** The vendors list: with what is owed to each and how many bills, optionally searched. */
    public function listQuery(Company $company, ?string $search): HasMany|Builder
    {
        return $company->vendors()
            ->with('state:id,name,gst_code')
            ->withSum(['purchaseBills as payable' => fn ($q) => $q->where('balance', '>', 0)], 'balance')
            ->withCount('purchaseBills')
            ->when($search, fn ($q, $s) => $q->search($s))
            ->orderBy('name');
    }

    public function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'gstin' => ['nullable', 'string', 'max:15', new ValidGstin($request->input('state_id') ? (int) $request->input('state_id') : null)],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['country'] = $data['country'] ?? 'India';
        // State left blank but a GSTIN given: the GSTIN says where they are,
        // and that decides IGST vs CGST + SGST on every purchase from them.
        if (! empty($data['gstin'])) {
            $data['gstin'] = strtoupper(trim($data['gstin']));
            if (empty($data['state_id'])) {
                $data['state_id'] = State::where('gst_code', substr($data['gstin'], 0, 2))->value('id');
            }
        }

        return $data;
    }

    public function create(Company $company, User $user, array $data): Vendor
    {
        $vendor = $company->vendors()->create(array_merge($data, ['user_id' => $user->id]));

        AuditLog::record('vendor.created', "Vendor {$vendor->name} added", $vendor);

        return $vendor;
    }

    /** Why this vendor cannot be deleted, or null when it can. */
    public function deleteProblem(Vendor $vendor): ?string
    {
        return $vendor->purchaseBills()->exists()
            ? "{$vendor->name} has purchase invoices on record and cannot be deleted. Delete those purchase invoices first."
            : null;
    }
}
