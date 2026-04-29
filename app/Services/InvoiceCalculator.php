<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceCalculator
{
    /**
     * Calculate totals for an invoice from its line items.
     * Mutates item fields (amount, cgst_amount, sgst_amount, igst_amount, total)
     * and invoice aggregates.
     */
    public function recalculate(Invoice $invoice, array $items, bool $isInterstate): array
    {
        $subtotal = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;

        $computed = [];

        foreach ($items as $item) {
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

            if ($gstRate > 0) {
                $tax = round($amount * ($gstRate / 100), 2);
                if ($isInterstate) {
                    $igst = $tax;
                } else {
                    $cgst = round($tax / 2, 2);
                    $sgst = round($tax - $cgst, 2);
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
                'sort_order' => (int) ($item['sort_order'] ?? 0),
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
     * Determine if a transaction is interstate based on company and customer states.
     */
    public function isInterstate(?int $companyStateId, ?int $customerStateId): bool
    {
        if ($companyStateId === null || $customerStateId === null) {
            return false;
        }
        return $companyStateId !== $customerStateId;
    }
}
