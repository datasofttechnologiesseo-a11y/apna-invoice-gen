<?php

namespace App\Services;

use App\Models\CreditNote;
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
 *
 * After a sales return (a note with lines) the pro rata is over what is
 * still left on each line, not over goods that already came back: a ₹525
 * price cut on the 5% rice, once the 18% rack was returned, reverses 5% tax
 * only. GSTR-1 reports the note on the same basis (Gstr1Builder::creditShare).
 */
class CreditNoteSplit
{
    private const HEADS = ['taxable_value', 'total_cgst', 'total_sgst', 'total_igst'];

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

        // After a sales return, spread over what each line still has left.
        // Without one, every earlier note came off all lines alike, so the
        // invoice's own proportions still hold and the base stays the invoice.
        $byLine = $this->leftByLine($invoice);
        if ($byLine !== null) {
            $base = array_fill_keys(self::HEADS, 0.0);
            foreach ($byLine as $line) {
                foreach (self::HEADS as $k) {
                    $base[$k] += $line[$k];
                }
            }
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

    /**
     * What is still left to credit on each invoice line after earlier notes,
     * or null when none of them has lines (then every note came off all lines
     * alike and the invoice-wide pro rata stands, exactly as it always has).
     *
     * Notes are replayed in the order they were issued: a note with lines
     * takes its own lines off; an amount-only note comes off every line pro
     * rata, head by head, as it was spread.
     *
     * @param  int|null  $beforeNoteId  only notes issued before this one, to
     *                                  report a note on the basis it was split
     * @param  bool  $always  work it out even when no earlier note has lines
     *                        (a sales return valued after amount-only notes)
     * @return array<int, array{taxable_value: float, total_cgst: float, total_sgst: float, total_igst: float}>|null
     */
    public function leftByLine(Invoice $invoice, ?int $beforeNoteId = null, bool $always = false): ?array
    {
        if (! CreditNote::linesAvailable()) {
            return null;
        }
        $notes = $invoice->creditNotes()->reorder()->orderBy('id')
            ->when($beforeNoteId, fn ($q) => $q->where('id', '<', $beforeNoteId))
            ->with('items')
            ->get();
        if (! $always && $notes->every(fn ($n) => $n->items->isEmpty())) {
            return null;
        }

        $items = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->get();
        $left = [];
        foreach ($items as $item) {
            $left[$item->id] = [
                'taxable_value' => (float) $item->amount,
                'total_cgst' => (float) $item->cgst_amount,
                'total_sgst' => (float) $item->sgst_amount,
                'total_igst' => (float) $item->igst_amount,
            ];
        }

        foreach ($notes as $note) {
            if ($note->items->isNotEmpty()) {
                foreach ($note->items as $line) {
                    if (isset($left[$line->invoice_item_id])) {
                        $left[$line->invoice_item_id]['taxable_value'] -= (float) $line->taxable_value;
                        $left[$line->invoice_item_id]['total_cgst'] -= (float) $line->cgst;
                        $left[$line->invoice_item_id]['total_sgst'] -= (float) $line->sgst;
                        $left[$line->invoice_item_id]['total_igst'] -= (float) $line->igst;
                    }
                }

                continue;
            }
            foreach (self::HEADS as $k) {
                $pool = array_sum(array_map(fn ($l) => max(0.0, $l[$k]), $left));
                if ($pool <= 0) {
                    continue;
                }
                $f = min(1.0, (float) $note->{$k} / $pool);
                foreach ($left as $id => $l) {
                    $left[$id][$k] -= max(0.0, $l[$k]) * $f;
                }
            }
        }

        return array_map(fn ($l) => array_map(fn ($v) => max(0.0, round($v, 2)), $l), $left);
    }
}
