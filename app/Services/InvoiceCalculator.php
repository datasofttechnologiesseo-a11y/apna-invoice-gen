<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceCalculator
{
    /**
     * Calculate totals for an invoice from its line items.
     * Mutates item fields (amount, cgst_amount, sgst_amount, igst_amount, total)
     * and invoice aggregates.
     *
     * @param  bool  $reverseCharge  Section 9(3)/9(4) CGST: when true, the
     *         supplier does NOT collect GST — the recipient self-assesses and
     *         pays it in their GSTR-3B. We force CGST/SGST/IGST to zero on
     *         every line, but keep gst_rate on the line so the recipient knows
     *         what they need to remit.
     */
    public function recalculate(Invoice $invoice, array $items, bool $isInterstate, bool $reverseCharge = false): array
    {
        $subtotal = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;

        $computed = [];

        // Track position so sort_order reflects the order the rows arrived in.
        // Without this, every row writes sort_order=0 and the relation order
        // becomes non-deterministic across re-saves (update() deletes + recreates).
        $position = 0;
        foreach ($items as $item) {
            $position++;
            $qty = (float) ($item['quantity'] ?? 0);
            $rate = (float) ($item['rate'] ?? 0);
            $gstRate = (float) ($item['gst_rate'] ?? 0);
            // Section 15(3) CGST: pre-tax discount reduces the *taxable value*.
            // Clamped to the line subtotal so a discount can't go negative.
            $gross = round($qty * $rate, 2);
            $discount = max(0.0, min((float) ($item['discount'] ?? 0), $gross));
            $amount = round($gross - $discount, 2);

            $cgst = 0.0;
            $sgst = 0.0;
            $igst = 0.0;

            // RCM: supplier collects nothing; recipient pays the tax.
            if ($gstRate > 0 && ! $reverseCharge) {
                if ($isInterstate) {
                    $igst = round($amount * ($gstRate / 100), 2);
                } else {
                    // Each head at half the rate, rounded on its own - the way
                    // the NIC e-invoice schema computes it (SGST = taxable ×
                    // rate/2). Rounding the combined tax and halving it leaves
                    // CGST ≠ SGST on odd paise (₹100.30 @ 18% gave 9.03/9.02),
                    // which the GSTR-1 validations reject.
                    $cgst = round($amount * ($gstRate / 200), 2);
                    $sgst = $cgst;
                }
            }

            $total = round($amount + $cgst + $sgst + $igst, 2);

            $subtotal += $amount;
            $totalCgst += $cgst;
            $totalSgst += $sgst;
            $totalIgst += $igst;

            $computed[] = [
                'product_id' => ! empty($item['product_id']) ? (int) $item['product_id'] : null,
                'description' => (string) ($item['description'] ?? ''),
                'hsn_sac' => (string) ($item['hsn_sac'] ?? ''),
                'quantity' => $qty,
                'unit' => $item['unit'] ?? null,
                'rate' => $rate,
                'discount' => $discount,
                'amount' => $amount,
                'gst_rate' => $gstRate,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total' => $total,
                // Use explicit sort_order if supplied (e.g. drag-to-reorder UI later);
                // otherwise stamp from the position so order is stable across saves.
                'sort_order' => (int) ($item['sort_order'] ?? $position),
            ];
        }

        $subtotal = round($subtotal, 2);
        $totalCgst = round($totalCgst, 2);
        $totalSgst = round($totalSgst, 2);
        $totalIgst = round($totalIgst, 2);
        $totalTax = round($totalCgst + $totalSgst + $totalIgst, 2);

        $rawGrandTotal = $subtotal + $totalTax;
        $grandTotal = round($rawGrandTotal);
        $roundOff = round($grandTotal - $rawGrandTotal, 2);

        return [
            'items' => $computed,
            'totals' => [
                'subtotal' => $subtotal,
                'total_cgst' => $totalCgst,
                'total_sgst' => $totalSgst,
                'total_igst' => $totalIgst,
                'total_tax' => $totalTax,
                'round_off' => $roundOff,
                'grand_total' => $grandTotal,
            ],
        ];
    }

    /**
     * Spread one invoice-level discount across the lines in proportion to each
     * line's value (qty × rate), writing it into each line's `discount`.
     *
     * A discount on a tax invoice reduces the taxable value BEFORE GST
     * (s.15(3)(a) CGST Act), so it cannot simply be knocked off the grand
     * total. Splitting it pro rata keeps every line's taxable value - and so
     * the HSN summary and GSTR-1 rate-wise figures - correct when lines carry
     * different GST rates. The last line takes the rounding remainder so the
     * parts always add back to the discount exactly; no line goes below zero.
     */
    public function apportionDiscount(array $items, float $discount): array
    {
        $discount = round(max(0.0, $discount), 2);
        $gross = array_map(fn ($i) => round((float) ($i['quantity'] ?? 0) * (float) ($i['rate'] ?? 0), 2), $items);
        $totalGross = round(array_sum($gross), 2);
        $discount = min($discount, $totalGross);

        $lastIdx = null;
        foreach ($gross as $idx => $g) {
            if ($g > 0) {
                $lastIdx = $idx;
            }
        }

        $allocated = 0.0;
        foreach ($items as $idx => $item) {
            if ($discount <= 0 || $gross[$idx] <= 0) {
                $items[$idx]['discount'] = 0;
                continue;
            }
            $share = $idx === $lastIdx
                ? round($discount - $allocated, 2)
                : round($discount * $gross[$idx] / $totalGross, 2);
            $share = min($share, $gross[$idx]);
            $items[$idx]['discount'] = $share;
            $allocated = round($allocated + $share, 2);
        }

        return $items;
    }

    /**
     * Determine if a transaction is interstate based on company and customer states.
     */
    /**
     * How an invoice is taxed: [inter-state?, collect no tax?].
     *
     * Exports and supplies to an SEZ are inter-state by law (s.7(5) IGST Act)
     * whatever the states; under a LUT/bond no tax is charged (like reverse
     * charge, the rate stays on the line).
     *
     * @return array{0: bool, 1: bool}
     */
    public function taxBasis(?int $companyStateId, ?int $customerStateId, string $supplyType, bool $reverseCharge): array
    {
        $zeroRated = $supplyType !== '' && $supplyType !== 'regular';

        return [
            $zeroRated || $this->isInterstate($companyStateId, $customerStateId),
            $reverseCharge || str_ends_with($supplyType, '_lut'),
        ];
    }

    public function isInterstate(?int $companyStateId, ?int $customerStateId): bool
    {
        if ($companyStateId === null || $customerStateId === null) {
            return false;
        }
        return $companyStateId !== $customerStateId;
    }
}
