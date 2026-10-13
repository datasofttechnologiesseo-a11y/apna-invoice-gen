<?php

namespace App\Services;

use App\Models\CreditNoteItem;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

/**
 * A credit note built from invoice lines: "1 of the 7 almirahs came back".
 *
 * Each picked line credits its own taxable value and tax for the quantity,
 * pro rata to the invoice line (taxable value after any invoice discount,
 * plus its GST):
 *
 *     taxable = round(line taxable × qty ÷ line qty, 2), each tax head alike.
 *
 * The note that credits the last of a line takes the exact remainder of that
 * line, so the notes on a line never add up to more or less than the line.
 * The note's amount and tax totals are the sum of its lines; the only
 * exception is the invoice round-off: when the lines differ from what is
 * left to credit by less than a rupee (the paise the grand total was rounded
 * by), the note amount is what is left, as an amount-only note would be.
 *
 * Notes without lines (price reductions, and every older note) keep using
 * CreditNoteSplit, unchanged.
 */
class CreditNoteLines
{
    /**
     * What earlier notes already credited, per invoice line.
     *
     * @return array<int, array{qty: float, taxable: float, cgst: float, sgst: float, igst: float}>
     */
    public function creditedSoFar(Invoice $invoice): array
    {
        if (! \App\Models\CreditNote::linesAvailable()) {
            return [];
        }
        // reorder(): the relation sorts by date, which MySQL refuses inside an
        // aggregate subquery under ONLY_FULL_GROUP_BY.
        $noteIds = $invoice->creditNotes()->reorder()->select('id');

        return CreditNoteItem::query()
            ->whereIn('credit_note_id', $noteIds)
            ->whereNotNull('invoice_item_id')
            ->groupBy('invoice_item_id')
            ->selectRaw('invoice_item_id, SUM(quantity) as qty, SUM(taxable_value) as taxable, SUM(cgst) as cgst, SUM(sgst) as sgst, SUM(igst) as igst')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->invoice_item_id => [
                'qty' => (float) $r->qty, 'taxable' => (float) $r->taxable,
                'cgst' => (float) $r->cgst, 'sgst' => (float) $r->sgst, 'igst' => (float) $r->igst,
            ]])
            ->all();
    }

    /**
     * The quantities a note is built from: rounded to 3 decimals as stored,
     * blanks and zeros dropped. The controllers decide "this note has lines"
     * from these same figures, so a quantity that rounds away (0.0004) can
     * never switch off the amount checks and leave a typed amount unchecked.
     *
     * @param  array<int|string, mixed>  $input
     * @return array<int|string, float>
     */
    public static function picked(array $input): array
    {
        return array_filter(
            array_map(fn ($q) => is_numeric($q) ? round((float) $q, 3) : 0.0, $input),
            fn ($q) => $q > 0,
        );
    }

    /**
     * Validation for one picked quantity (lines.*): blank, 0, or at least
     * 0.001, the smallest quantity a note line can hold.
     *
     * @return array<int, mixed>
     */
    public static function quantityRules(): array
    {
        return ['nullable', 'numeric', 'min:0', function (string $attribute, mixed $value, \Closure $fail) {
            if (is_numeric($value) && (float) $value > 0 && round((float) $value, 3) <= 0) {
                $fail('Enter a quantity of 0.001 or more, or leave it blank.');
            }
        }];
    }

    /**
     * Turn picked quantities (invoice item id => quantity) into note lines.
     * Quantities of 0 or blank are ignored. Returns null when nothing was
     * picked, so the caller falls back to the amount-only note.
     *
     * @param  array<int|string, mixed>  $picked
     * @return array{lines: array<int, array<string, mixed>>, amount: float, taxable_value: float, total_cgst: float, total_sgst: float, total_igst: float}|null
     *
     * @throws ValidationException
     */
    public function build(Invoice $invoice, array $picked): ?array
    {
        $picked = self::picked($picked);
        if (! $picked || ! \App\Models\CreditNote::linesAvailable()) {
            return null;
        }

        $items = $invoice->items()->get()->keyBy('id');
        $prior = $this->creditedSoFar($invoice);
        // After an amount-only note (a price reduction spread over every line)
        // goods that come back are worth what is left on their line, not the
        // original price: valued at the original price, a return on a
        // mixed-rate invoice gave back more tax at one rate than it charged.
        $leftNow = $invoice->creditNotes()->reorder()->whereDoesntHave('items')->exists()
            ? app(CreditNoteSplit::class)->leftByLine($invoice, null, true)
            : null;

        $lines = [];
        foreach ($picked as $itemId => $qty) {
            $item = $items->get((int) $itemId);
            if (! $item) {
                throw ValidationException::withMessages(['lines' => 'A selected line is not on this invoice. Reload the page and try again.']);
            }
            $done = $prior[$item->id] ?? ['qty' => 0.0, 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
            $lineQty = (float) $item->quantity;
            $left = round($lineQty - $done['qty'], 3);
            $name = trim((string) $item->description) ?: 'this line';
            if ($left <= 0) {
                throw ValidationException::withMessages(["lines.{$item->id}" => "{$name} has already been credited in full."]);
            }
            if ($qty > $left + 0.0005) {
                $leftStr = rtrim(rtrim(number_format($left, 3, '.', ''), '0'), '.');
                throw ValidationException::withMessages(["lines.{$item->id}" => "Only {$leftStr} of {$name} can still be credited."]);
            }

            if ($leftNow !== null && isset($leftNow[$item->id])) {
                // A share of what is left on the line, all of it for the last.
                $qty = $qty >= $left - 0.0005 ? $left : $qty;
                $f = min(1.0, $qty / $left);
                $l = $leftNow[$item->id];
                $fig = [
                    'taxable_value' => round($l['taxable_value'] * $f, 2),
                    'cgst' => round($l['total_cgst'] * $f, 2),
                    'sgst' => round($l['total_sgst'] * $f, 2),
                    'igst' => round($l['total_igst'] * $f, 2),
                ];
            } elseif ($qty >= $left - 0.0005) {
                // The last of this line: the exact remainder, to the paisa.
                $qty = $left;
                $fig = [
                    'taxable_value' => max(0.0, round((float) $item->amount - $done['taxable'], 2)),
                    'cgst' => max(0.0, round((float) $item->cgst_amount - $done['cgst'], 2)),
                    'sgst' => max(0.0, round((float) $item->sgst_amount - $done['sgst'], 2)),
                    'igst' => max(0.0, round((float) $item->igst_amount - $done['igst'], 2)),
                ];
            } else {
                $f = $lineQty > 0 ? $qty / $lineQty : 0.0;
                $fig = [
                    'taxable_value' => round((float) $item->amount * $f, 2),
                    'cgst' => round((float) $item->cgst_amount * $f, 2),
                    'sgst' => round((float) $item->sgst_amount * $f, 2),
                    'igst' => round((float) $item->igst_amount * $f, 2),
                ];
            }

            $lines[] = [
                'invoice_item_id' => $item->id,
                'product_id' => $item->product_id,
                'hsn_sac' => $item->hsn_sac,
                'description' => mb_substr((string) $item->description, 0, 255),
                'quantity' => $qty,
                'unit' => $item->unit,
                'gst_rate' => (float) $item->gst_rate,
            ] + $fig;
        }

        $totals = ['taxable_value' => 0.0, 'total_cgst' => 0.0, 'total_sgst' => 0.0, 'total_igst' => 0.0];
        foreach ($lines as $l) {
            $totals['taxable_value'] += $l['taxable_value'];
            $totals['total_cgst'] += $l['cgst'];
            $totals['total_sgst'] += $l['sgst'];
            $totals['total_igst'] += $l['igst'];
        }
        $totals = array_map(fn ($v) => round($v, 2), $totals);
        $amount = round(array_sum($totals), 2);

        // Only the invoice round-off separates the lines from what is left to
        // credit. Over by more than that means amount-only notes already took
        // part of this invoice: the owner must type the amount instead.
        $creditable = round(max(0, (float) $invoice->grand_total - (float) $invoice->creditNotes()->reorder()->sum('amount')), 2);
        $clearsAll = $items->every(function ($item) use ($prior, $picked) {
            $credited = ($prior[$item->id]['qty'] ?? 0.0) + ($picked[$item->id] ?? 0.0);

            return $credited >= (float) $item->quantity - 0.0005;
        });
        if ($amount > $creditable + 0.001) {
            if ($amount - $creditable >= 1.0) {
                throw ValidationException::withMessages(['lines' => 'These lines come to ₹' . inr($amount)
                    . ', but only ₹' . inr($creditable) . ' of this invoice can still be credited. Clear the lines and type the amount instead.']);
            }
            $amount = $creditable;
        } elseif ($clearsAll && $creditable - $amount < 1.0) {
            // Everything has now come back: credit what is left, round-off included.
            $amount = $creditable;
        }

        return ['lines' => $lines, 'amount' => $amount] + $totals;
    }
}
