<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use App\Services\VendorBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API for vendors (suppliers). Same rules as the web VendorController:
 * both go through App\Services\VendorBook.
 */
class VendorController extends Controller
{
    public function __construct(private readonly VendorBook $book) {}

    public function index(Request $request)
    {
        $company = $request->user()->ensureCompany();

        $vendors = $this->book->listQuery($company, $request->search)
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return VendorResource::collection($vendors);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        $vendor = $this->book->create($company, $user, $this->book->validated($request));

        return (new VendorResource($vendor->load('state:id,name,gst_code')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Vendor $vendor): VendorResource
    {
        $this->authorizeVendor($request, $vendor);

        $vendor->load('state:id,name,gst_code')
            ->loadSum(['purchaseBills as payable' => fn ($q) => $q->where('balance', '>', 0)], 'balance')
            ->loadCount('purchaseBills');

        return new VendorResource($vendor);
    }

    public function update(Request $request, Vendor $vendor): VendorResource
    {
        $this->authorizeVendor($request, $vendor);
        $vendor->update($this->book->validated($request));

        return new VendorResource($vendor->fresh('state:id,name,gst_code'));
    }

    public function destroy(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorizeVendor($request, $vendor);

        if ($problem = $this->book->deleteProblem($vendor)) {
            return response()->json(['message' => $problem], 422);
        }

        $name = $vendor->name;
        $vendor->delete();

        return response()->json(['message' => "Vendor {$name} deleted."]);
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        abort_unless($vendor->user_id === $request->user()->id, 403);
    }
}
