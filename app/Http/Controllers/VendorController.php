<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\State;
use App\Models\Vendor;
use App\Rules\ValidGstin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        $vendors = $company->vendors()
            ->with('state:id,name,gst_code')
            ->withSum(['purchaseBills as payable' => fn ($q) => $q->where('balance', '>', 0)], 'balance')
            ->withCount('purchaseBills')
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('vendors.index', compact('company', 'vendors'));
    }

    public function create(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        return view('vendors.form', [
            'company' => $company,
            'vendor' => new Vendor(['country' => 'India']),
            'states' => State::orderBy('name')->get(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validated($request);

        $vendor = $company->vendors()->create(array_merge($data, ['user_id' => $user->id]));

        AuditLog::record('vendor.created', "Vendor {$vendor->name} added", $vendor);

        // Quick-add from the purchase bill form: hand back the row so the
        // form can select it without a reload.
        if ($request->expectsJson()) {
            return response()->json([
                'id' => $vendor->id,
                'name' => $vendor->name,
                'gstin' => $vendor->gstin,
                // Cast: the request value is a string until the model is reloaded,
                // and the form compares it with the company's integer state id.
                'state_id' => $vendor->state_id ? (int) $vendor->state_id : null,
                'state_name' => $vendor->state?->name,
            ], 201);
        }

        return redirect()->route('vendors.index')->with('status', "Vendor {$vendor->name} added.");
    }

    public function edit(Request $request, Vendor $vendor): View
    {
        $this->authorizeVendor($request, $vendor);

        return view('vendors.form', [
            'company' => $vendor->company,
            'vendor' => $vendor,
            'states' => State::orderBy('name')->get(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeVendor($request, $vendor);
        $vendor->update($this->validated($request));

        return redirect()->route('vendors.index')->with('status', "Vendor {$vendor->name} updated.");
    }

    public function destroy(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeVendor($request, $vendor);

        if ($vendor->purchaseBills()->exists()) {
            return back()->with('error', "{$vendor->name} has purchase invoices on record and cannot be deleted. Delete those purchase invoices first.");
        }

        $name = $vendor->name;
        $vendor->delete();

        return redirect()->route('vendors.index')->with('status', "Vendor {$name} deleted.");
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        abort_unless($vendor->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request): array
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
                $data['state_id'] = \App\Models\State::where('gst_code', substr($data['gstin'], 0, 2))->value('id');
            }
        }

        return $data;
    }
}
