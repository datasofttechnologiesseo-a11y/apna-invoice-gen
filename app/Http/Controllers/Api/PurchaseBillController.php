<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseBillListResource;
use App\Http\Resources\PurchaseBillResource;
use App\Http\Resources\PurchasePaymentResource;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Services\PurchaseBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API for purchase invoices and the payments made against them. Same
 * rules as the web PurchaseBillController: both go through
 * App\Services\PurchaseBook (validation, tax, books lock, stock, expense
 * mirror, audit log). A write inside the locked period answers 422.
 */
class PurchaseBillController extends Controller
{
    public function __construct(private readonly PurchaseBook $book) {}

    /** Filters: search, status (unpaid|paid|overdue), vendor, from, to; per_page. */
    public function index(Request $request)
    {
        $company = $request->user()->ensureCompany();
        $vendor = $request->filled('vendor') ? $company->vendors()->find((int) $request->query('vendor')) : null;

        $bills = $this->book->listQuery($company, $request, $vendor)
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return PurchaseBillListResource::collection($bills)
            ->additional(['meta' => [
                'summary' => $this->book->summary($company),
                'categories' => collect(config('expense_categories', []))
                    ->map(fn ($c, $key) => ['value' => $key, 'label' => $c['label']])->values(),
                'methods' => collect(config('payment_methods.methods', []))
                    ->map(fn ($m, $key) => ['value' => $key, 'label' => $m['label']])->values(),
            ]]);
    }

    public function show(Request $request, PurchaseBill $purchaseBill): PurchaseBillResource
    {
        $this->authorizeBill($request, $purchaseBill);

        return new PurchaseBillResource($this->loaded($purchaseBill));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();

        $bill = $this->book->create($company, $user, $this->book->validated($request, $company));

        return (new PurchaseBillResource($this->loaded($bill)))
            ->additional(['message' => $this->book->createdMessage($bill)])
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, PurchaseBill $purchaseBill): PurchaseBillResource
    {
        $this->authorizeBill($request, $purchaseBill);
        $data = $this->book->validated($request, $purchaseBill->company, $purchaseBill->id);
        $this->book->update($purchaseBill, $request->user(), $data);

        return new PurchaseBillResource($this->loaded($purchaseBill->fresh()));
    }

    public function destroy(Request $request, PurchaseBill $purchaseBill): JsonResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        $label = $this->book->delete($purchaseBill);

        return response()->json(['message' => "Purchase invoice {$label} deleted."]);
    }

    public function storePayment(Request $request, PurchaseBill $purchaseBill): JsonResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        $data = $request->validate($this->book->paymentRules($purchaseBill));
        $payment = $this->book->recordPayment($purchaseBill, $request->user(), $data);

        return (new PurchasePaymentResource($payment))
            ->additional(['bill' => new PurchaseBillListResource($purchaseBill->fresh('vendor'))])
            ->response()->setStatusCode(201);
    }

    public function destroyPayment(Request $request, PurchaseBill $purchaseBill, PurchasePayment $purchasePayment): JsonResponse
    {
        $this->authorizeBill($request, $purchaseBill);
        abort_unless($purchasePayment->purchase_bill_id === $purchaseBill->id, 404);

        $this->book->removePayment($purchaseBill, $purchasePayment);

        return response()->json([
            'message' => 'Payment removed. Purchase invoice balance restored.',
            'bill' => new PurchaseBillListResource($purchaseBill->fresh('vendor')),
        ]);
    }

    private function loaded(PurchaseBill $bill): PurchaseBill
    {
        return $bill->load(['vendor.state:id,name,gst_code', 'items.product:id,name,track_stock', 'payments', 'company.state']);
    }

    private function authorizeBill(Request $request, PurchaseBill $bill): void
    {
        abort_unless($bill->user_id === $request->user()->id, 403);
    }
}
