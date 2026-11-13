<?php

namespace App\Http\Controllers;

use App\Models\State;
use App\Models\Vendor;
use App\Services\VendorBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(private readonly VendorBook $book) {}

    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        $vendors = $this->book->listQuery($company, $request->search)
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
        $vendor = $this->book->create($company, $user, $this->book->validated($request));

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
        $vendor->update($this->book->validated($request));

        return redirect()->route('vendors.index')->with('status', "Vendor {$vendor->name} updated.");
    }

    public function destroy(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeVendor($request, $vendor);

        if ($problem = $this->book->deleteProblem($vendor)) {
            return back()->with('error', $problem);
        }

        $name = $vendor->name;
        $vendor->delete();

        return redirect()->route('vendors.index')->with('status', "Vendor {$name} deleted.");
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        abort_unless($vendor->user_id === $request->user()->id, 403);
    }
}
