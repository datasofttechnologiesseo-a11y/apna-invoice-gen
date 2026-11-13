<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashMemo;
use App\Models\Expense;
use App\Support\NumberToWords;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cash memo rules shared by the web (CashMemoController) and the mobile app
 * (Api\CashMemoController): the form's validation, the money figures, and
 * editing a memo together with the expense entry it wrote.
 */
class CashMemoBook
{
    /** Validation rules; memo numbers are unique per business (ignoring the memo being edited). */
    public function rules(int $companyId, ?int $ignoreId = null): array
    {
        return [
            'memo_date' => ['required', 'date'],
            'memo_number' => [
                'nullable', 'string', 'max:40',
                Rule::unique('cash_memos', 'memo_number')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($ignoreId),
            ],
            'seller_name' => ['required', 'string', 'max:160'],
            'seller_address' => ['nullable', 'string', 'max:500'],
            'seller_gstin' => ['nullable', 'string', 'max:20'],
            'seller_phone' => ['nullable', 'string', 'max:30'],
            'seller_state' => ['nullable', 'string', 'max:80'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'gst_rate' => ['nullable', 'numeric', 'in:'.implode(',', config('gst.allowed_values'))],
            'is_interstate' => ['nullable', 'boolean'],
            'itc_eligible' => ['nullable', 'boolean'],
            'payment_mode' => ['required', 'string', 'in:cash,upi,card,bank,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:60'],
            'expense_category' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    /** Derive subtotal, discount, GST split, round-off, grand total from the input. */
    public function compute(array $data): array
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
                $sgst = round($gstAmount - $cgst, 2); // absorb rounding diff
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

    /**
     * Save an edited memo: replace its lines and keep the linked expense in
     * step (memo + expense are one accounting entry). $data is validated
     * input with itc_eligible resolved. The caller checks the books lock.
     */
    public function update(CashMemo $cashMemo, array $data): CashMemo
    {
        DB::transaction(function () use ($cashMemo, $data) {
            $computed = $this->compute($data);

            $cashMemo->update([
                'memo_number' => ! empty($data['memo_number']) ? trim($data['memo_number']) : $cashMemo->memo_number,
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
                'itc_eligible' => $data['itc_eligible'],
                'payment_mode' => $data['payment_mode'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'expense_category' => $data['expense_category'] ?? 'misc',
                'notes' => $data['notes'] ?? null,
            ]);

            // Replace line items.
            $cashMemo->items()->delete();
            foreach ($data['items'] as $idx => $row) {
                $cashMemo->items()->create([
                    'sort_order' => $idx,
                    'description' => $row['description'],
                    'hsn_sac' => $row['hsn_sac'] ?? null,
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'] ?? null,
                    'rate' => $row['rate'],
                    'amount' => (float) $row['quantity'] * (float) $row['rate'],
                ]);
            }

            if ($cashMemo->expense_id) {
                Expense::where('id', $cashMemo->expense_id)->update([
                    'entry_date' => $data['memo_date'],
                    'category' => $data['expense_category'] ?? 'misc',
                    'vendor_name' => $data['seller_name'],
                    'description' => 'Cash purchase from '.$data['seller_name'],
                    'amount' => $computed['taxable_value'],
                    'gst_amount' => $computed['total_cgst'] + $computed['total_sgst'] + $computed['total_igst'],
                    'itc_eligible' => $data['itc_eligible'],
                    'payment_method' => $data['payment_mode'] ?? 'cash',
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
            }
        });

        $fresh = $cashMemo->fresh();
        AuditLog::record('cash_memo.updated',
            "Cash Memo {$fresh->memo_number} updated · ₹".inr($fresh->grand_total).' · '.$cashMemo->seller_name,
            $cashMemo
        );

        return $fresh;
    }
}
