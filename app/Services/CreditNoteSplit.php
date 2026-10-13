<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * How much taxable value and tax a credit note reverses (s.34).
 *
 * Pro rata to the note amount over the invoice total, as before, with two
 * guards against paise leaking across several notes:
 *  - no head ever reverses more than the invoice charged, less earlier notes;
 *  - the note that clears what is left of the invoice takes the exact
 *    remainder, so the notes together reverse the invoice to the paisa.
 * (Two ₹59 notes on a ₹118 bill with CGST/SGST 9.03 each used to reverse
 * 4.52 + 4.52 twice - 18.08 of tax against 18.06 charged.)
 */
class CreditNoteSplit
{
    /** @return array{taxable_value: float, total_cgst: float, total_sgst: float, total_igst: float} */
    public function for(Invoice $invoice, float $amount): array
    {
        $grand = (float) $invoice->grand_total;
        // reorder(): the relation sorts by date, and MySQL refuses ORDER BY on
        // a plain aggregate query (error 1140 under ONLY_FULL_GROUP_BY).
        $prior = $invoice->creditNotes()->reorder()
            ->selectRaw('COALESCE(SUM(amount),0) as amount, COALESCE(SUM(taxable_value),0) as taxable_value,
                COALESCE(SUM(total_cgst),0) as total_cgst, COALESCE(SUM(total_sgst),0) as total_sgst, COALESCE(SUM(total_igst),0) as total_igst')
            ->first();

        $base = [
            'taxable_value' => (float) $invoice->subtotal,
            'total_cgst' => (float) $invoice->total_cgst,
            'total_sgst' => (float) $invoice->total_sgst,
            'total_igst' => (float) $invoice->total_igst,
        ];
        $left = [];
        foreach ($base as $k => $v) {
            $left[$k] = max(0.0, round($v - (float) $prior->{$k}, 2));
        }

        $clearsInvoice = $grand > 0 && round((float) $prior->amount + $amount, 2) >= round($grand, 2);
        if ($clearsInvoice) {
            return $left;
        }

        // Pro rata over taxable value plus tax, not the rounded grand total, so
        // the note's taxable value and tax add up to the note amount. (Over the
        // grand total, an invoice rounded down by 14 paise gave a ₹14,381.25
        // note ₹14,381.37 of value and tax.)
        $beforeRounding = array_sum($base);
        $ratio = $beforeRounding > 0 ? min(1.0, $amount / $beforeRounding) : 0.0;
        $out = [];
        foreach ($base as $k => $v) {
            $out[$k] = min($left[$k], round($v * $ratio, 2));
        }

        // Each head rounded on its own can leave the note a paisa off its
        // amount (90.73 + 4.64 + 4.64 = 100.01 on a ₹100 note). The tax heads
        // stay as worked out; the taxable value takes the difference, so a
        // note always reads taxable + tax = amount.
        $tax = $out['total_cgst'] + $out['total_sgst'] + $out['total_igst'];
        $taxable = round($amount - $tax, 2);
        if ($taxable >= 0 && $taxable <= $left['taxable_value'] && abs($taxable - $out['taxable_value']) < 1) {
            $out['taxable_value'] = $taxable;
        }

        return $out;
    }
}
