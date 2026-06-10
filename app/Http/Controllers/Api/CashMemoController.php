<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashMemoListResource;
use App\Http\Resources\CashMemoResource;
use App\Models\AuditLog;
use App\Models\CashMemo;
use App\Models\Expense;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * JSON API for cash memos (self-prepared purchase vouchers). Mirrors the web
 * CashMemoController: each memo also writes a linked Expense entry so the
 * purchase shows up in the P&L and is deleted alongside the memo.
 */
class CashMemoController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->ensureCompany();

        $memos = $company->cashMemos()
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('memo_number', 'like', "%{$s}%")
                    ->orWhere('seller_name', 'like', "%{$s}%");
            }))
            ->when($request->from, fn ($q, $d) => $q->where('memo_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('memo_date', '<=', $d))
            ->orderByDesc('memo_date')
            ->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return CashMemoListResource::collection($memos);
    }

    public function show(Request $request, CashMemo $cashMemo): CashMemoResource
    {
        $this->authorize($request, $cashMemo);
        $cashMemo->load(['items', 'company.state']);

        return new CashMemoResource($cashMemo);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validated($request, $company->id);

        if ($company->isBooksLockedOn($data['memo_date'])) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. Cannot create a cash memo dated on or before that.",
            ], 422);
        }

        $memo = DB::transaction(function () use ($company, $user, $data) {
            $company = $company->lockForUpdate()->find($company->id);
            $computed = $this->compute($data);

            $expense = $company->expenses()->create([
                'user_id' => $user->id,
                'entry_date' => $data['memo_date'],
                'category' => $data['expense_category'] ?? 'misc',
                'vendor_name' => $data['seller_name'],
                'description' => 'Cash purchase from ' . $data['seller_name'],
                'amount' => $computed['taxable_value'],
                'gst_amount' => $computed['total_cgst'] + $computed['total_sgst'] + $computed['total_igst'],
                'payment_method' => $data['payment_mode'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $memoNumber = ! empty($data['memo_number'])
                ? trim($data['memo_number'])
                : $company->bumpCashMemoCounter($data['memo_date']);

            $memo = $company->cashMemos()->create([
                'user_id' => $user->id,
                'memo_number' => $memoNumber,
                'memo_date' => $data['memo_date'],
                'seller_name' => $data['seller_name'],
                'seller_address' => $data['seller_address'] ?? null,
                'seller_gstin' => $data['seller_gstin'] ?? null,
                'seller_phone' => $data['seller_phone'] ?? null,
                'seller_state' => $data['seller_state'] ?? null,
                'subtotal' => $computed['subtotal'],
                'discount' => $computed['discount'],
                'taxable_value' => $computed['taxable_value'],
                'total_cgst' => $computed['total_cgst'],
                'total_sgst' => $computed['total_sgst'],
                'total_igst' => $computed['total_igst'],
                'round_off' => $computed['round_off'],
                'grand_total' => $computed['grand_total'],
                'amount_in_words' => NumberToWords::indianRupees($computed['grand_total']),
                'payment_mode' => $data['payment_mode'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'expense_category' => $data['expense_category'] ?? 'misc',
                'notes' => $data['notes'] ?? null,
                'expense_id' => $expense->id,
            ]);

            foreach ($data['items'] as $idx => $row) {
                $memo->items()->create([
                    'sort_order' => $idx,
                    'description' => $row['description'],
                    'hsn_sac' => $row['hsn_sac'] ?? null,
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'] ?? null,
                    'rate' => $row['rate'],
                    'amount' => (float) $row['quantity'] * (float) $row['rate'],
                ]);
            }

            $expense->update(['cash_memo_id' => $memo->id]);

            AuditLog::record('cash_memo.created',
                "Cash Memo {$memoNumber} · ₹" . number_format($memo->grand_total, 2) . " · " . $memo->seller_name,
                $memo
            );

            return $memo;
        });

        return (new CashMemoResource($memo->load('items')))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, CashMemo $cashMemo): JsonResponse
    {
        $this->authorize($request, $cashMemo);

        $company = $cashMemo->company;
        if ($company->isBooksLockedOn($cashMemo->memo_date)) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. This cash memo cannot be deleted.",
            ], 422);
        }

        $snapshot = $cashMemo->only(['memo_number', 'memo_date', 'seller_name', 'grand_total', 'expense_id']);

        DB::transaction(function () use ($cashMemo) {
            if ($cashMemo->expense_id) {
                Expense::where('id', $cashMemo->expense_id)->delete();
            }
            $cashMemo->delete();
        });

        AuditLog::record('cash_memo.deleted',
            "Cash Memo {$snapshot['memo_number']} deleted · ₹" . number_format($snapshot['grand_total'], 2) . " · " . $snapshot['seller_name'],
            null,
            $snapshot
        );

        return response()->json(['message' => 'Cash memo deleted (linked expense removed too).']);
    }

    public function pdf(Request $request, CashMemo $cashMemo): Response
    {
        $this->authorize($request, $cashMemo);
        $cashMemo->load(['items', 'company.state']);

        $pdf = Pdf::loadView('finance.cash-memos.pdf', ['memo' => $cashMemo])->setPaper('a4');

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', $cashMemo->memo_number);

        return $pdf->download('cash-memo-' . $safeNumber . '.pdf');
    }

    private function authorize(Request $request, CashMemo $cashMemo): void
    {
        abort_unless($cashMemo->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request, int $companyId): array
    {
        return $request->validate([
            'memo_date' => ['required', 'date'],
            'memo_number' => [
                'nullable', 'string', 'max:40',
                Rule::unique('cash_memos', 'memo_number')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'seller_name' => ['required', 'string', 'max:160'],
            'seller_address' => ['nullable', 'string', 'max:500'],
            'seller_gstin' => ['nullable', 'string', 'max:20'],
            'seller_phone' => ['nullable', 'string', 'max:30'],
            'seller_state' => ['nullable', 'string', 'max:80'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:28'],
            'is_interstate' => ['nullable', 'boolean'],
            'payment_mode' => ['required', 'string', 'in:cash,upi,card,bank,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:60'],
            'expense_category' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
        ]);
    }

    /** Derive subtotal, discount, GST split, round-off, grand total from input. */
    private function compute(array $data): array
    {
        $subtotal = 0.0;
        foreach ($data['items'] as $row) {
            $subtotal += (float) $row['quantity'] * (float) $row['rate'];
        }

        $discount = (float) ($data['discount'] ?? 0);
        $taxable = max(0, $subtotal - $discount);

        $rate = (float) ($data['gst_rate'] ?? 0);
        $gstAmount = round($taxable * $rate / 100, 2);

        $cgst = $sgst = $igst = 0.0;
        if ($rate > 0) {
            if (! empty($data['is_interstate'])) {
                $igst = $gstAmount;
            } else {
                $cgst = round($gstAmount / 2, 2);
                $sgst = $gstAmount - $cgst;
            }
        }

        $preRound = $taxable + $cgst + $sgst + $igst;
        $grandTotal = round($preRound);
        $roundOff = round($grandTotal - $preRound, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'taxable_value' => round($taxable, 2),
            'total_cgst' => $cgst,
            'total_sgst' => $sgst,
            'total_igst' => $igst,
            'round_off' => $roundOff,
            'grand_total' => $grandTotal,
        ];
    }
}
