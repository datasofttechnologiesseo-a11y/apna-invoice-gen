<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\AuditLog;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API for expenses (P&L cost side). Mirrors the web FinanceController
 * expense CRUD — same validation, books-lock guard and audit logging.
 * Cash-memo-linked expenses are read-only here (managed via their memo).
 */
class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();

        $query = $company->expenses()
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->from, fn ($q, $d) => $q->where('entry_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('entry_date', '<=', $d))
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('vendor_name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            }));

        // Totals over the full filtered set (not just the current page).
        $totalsQuery = (clone $query);
        $totals = [
            'count' => (clone $totalsQuery)->count(),
            'amount' => (float) (clone $totalsQuery)->sum('amount'),
            'gst' => (float) (clone $totalsQuery)->sum('gst_amount'),
        ];
        $totals['outflow'] = $totals['amount'] + $totals['gst'];

        $expenses = $query->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return ExpenseResource::collection($expenses)
            ->additional(['meta' => [
                'totals' => $totals,
                'categories' => $this->categories(),
            ]])
            ->response();
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validated($request);

        if ($company->isBooksLockedOn($data['entry_date'])) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. Cannot add expense dated on or before that.",
            ], 422);
        }

        $expense = $company->expenses()->create(array_merge($data, ['user_id' => $user->id]));

        AuditLog::record('expense.created',
            "Expense ₹" . number_format($expense->amount, 2) . " · " . ($expense->vendor_name ?: $expense->description),
            $expense
        );

        return (new ExpenseResource($expense))->response()->setStatusCode(201);
    }

    public function update(Request $request, Expense $expense): ExpenseResource|JsonResponse
    {
        $this->authorize($request, $expense);

        if ($expense->cash_memo_id) {
            return response()->json(['message' => 'This expense is part of a cash memo — edit the memo instead.'], 422);
        }

        $company = $expense->company;
        if ($company->isBooksLockedOn($expense->entry_date)) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. This expense cannot be edited.",
            ], 422);
        }

        $data = $this->validated($request);
        if ($company->isBooksLockedOn($data['entry_date'])) {
            return response()->json([
                'message' => "Cannot move this expense into the locked period (up to {$company->books_locked_until->format('d M Y')}).",
            ], 422);
        }

        $expense->update($data);

        AuditLog::record('expense.updated',
            "Expense #{$expense->id} updated · ₹" . number_format($expense->amount, 2),
            $expense
        );

        return new ExpenseResource($expense->fresh());
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize($request, $expense);

        if ($expense->cash_memo_id) {
            return response()->json(['message' => 'This expense is part of a cash memo — delete the memo instead.'], 422);
        }

        $company = $expense->company;
        if ($company->isBooksLockedOn($expense->entry_date)) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. This expense cannot be deleted.",
            ], 422);
        }

        AuditLog::record('expense.deleted',
            "Expense #{$expense->id} deleted · ₹" . number_format($expense->amount, 2) . " · " . ($expense->vendor_name ?: $expense->description),
            $expense,
            $expense->toArray()
        );

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    private function authorize(Request $request, Expense $expense): void
    {
        abort_unless($expense->user_id === $request->user()->id, 403);
    }

    /** @return array<int, array{value:string,label:string,color:string}> */
    private function categories(): array
    {
        return collect(config('expense_categories'))
            ->map(fn ($c, $key) => ['value' => $key, 'label' => $c['label'], 'color' => $c['color'] ?? '#6b7280'])
            ->values()
            ->all();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(config('expense_categories')))],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
            'is_interstate' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', 'string', 'in:cash,bank,upi,card,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['gst_amount'] = $data['gst_amount'] ?? 0;
        $data['is_interstate'] = $request->boolean('is_interstate');

        return $data;
    }
}
