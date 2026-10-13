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
            // Calculate on the quantity and rate as they are stored and printed
            // (3 and 2 decimals), so quantity x rate on the invoice reproduces
            // the line value. 0.1234 x 1,000 was billed 123.40 but printed as
            // 0.12 x 1,000.
            $qty = round((float) ($item['quantity'] ?? 0), 3);
            $rate = round((float) ($item['rate'] ?? 0), 2);
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
     * line's value after any discount already on the line, adding the share to
     * that line's `discount`.
     *
     * A discount on a tax invoice reduces the taxable value BEFORE GST
     * (s.15(3)(a) CGST Act), so it cannot simply be knocked off the grand
     * total. Splitting it pro rata keeps every line's taxable value - and so
     * the HSN summary and GSTR-1 rate-wise figures - correct when lines carry
     * different GST rates.
     *
     * Two rules a customer's bill depends on:
     *  - a discount already on a line is kept; the invoice discount comes on
     *    top (replacing it billed the customer more than agreed);
     *  - the parts always add back to the discount exactly. The rounding
     *    remainder goes, paisa by paisa, to the lines with room left, largest
     *    first; giving it all to the last line lost paise when that line was
     *    too small to take them. No line goes below zero.
     */
    /**
     * What the lines come to after any discount already on them: the most an
     * invoice-level discount can take off. Same rounding as recalculate().
     */
    public function valueAfterLineDiscounts(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $gross = round(round((float) ($item['quantity'] ?? 0), 3) * round((float) ($item['rate'] ?? 0), 2), 2);
            $total += max(0.0, $gross - max(0.0, min((float) ($item['discount'] ?? 0), $gross)));
        }

        return round($total, 2);
    }

    public function apportionDiscount(array $items, float $discount): array
    {
        $discount = round(max(0.0, $discount), 2);
        $existing = [];
        $base = [];
        foreach ($items as $idx => $item) {
            $gross = round(round((float) ($item['quantity'] ?? 0), 3) * round((float) ($item['rate'] ?? 0), 2), 2);
            $existing[$idx] = round(max(0.0, min((float) ($item['discount'] ?? 0), $gross)), 2);
            $base[$idx] = round(max(0.0, $gross - $existing[$idx]), 2);
        }
        $totalBase = round(array_sum($base), 2);
        $discount = min($discount, $totalBase);

        $share = [];
        foreach ($base as $idx => $b) {
            $share[$idx] = ($discount > 0 && $b > 0) ? min($b, round($discount * $b / $totalBase, 2)) : 0.0;
        }

        // Settle the rounding remainder (a few paise either way) one paisa at a
        // time on the lines that can take it, largest first.
        $left = (int) round(($discount - array_sum($share)) * 100);
        $order = array_keys($base);
        usort($order, fn ($a, $b) => $base[$b] <=> $base[$a]);
        while ($left !== 0) {
            $moved = false;
            foreach ($order as $idx) {
                if ($left > 0 && $share[$idx] + 0.01 <= $base[$idx] + 0.0001) {
                    $share[$idx] = round($share[$idx] + 0.01, 2);
                    $left--;
                    $moved = true;
                } elseif ($left < 0 && $share[$idx] >= 0.01) {
                    $share[$idx] = round($share[$idx] - 0.01, 2);
                    $left++;
                    $moved = true;
                }
                if ($left === 0) {
                    break;
                }
            }
            if (! $moved) {
                break; // nothing can take it (cannot happen while discount <= total)
            }
        }

        foreach ($items as $idx => $item) {
            $items[$idx]['discount'] = round($existing[$idx] + $share[$idx], 2);
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
