<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds GSTR-1 for a period in the tables (and column order) of the GST
 * portal's offline tool - b2b, b2cl, b2cs, cdnr, cdnur, exemp, hsn(b2b),
 * hsn(b2c), docs - and the outward totals GSTR-3B 3.1 is built from, so the
 * two returns can never disagree.
 *
 * Rules applied:
 *  - Issued invoices only; drafts never, cancelled ones only in "docs".
 *  - B2B = the recipient had a GSTIN when the invoice was issued (snapshot).
 *  - B2CL = unregistered, inter-state, invoice value > ₹1,00,000
 *    (Rule 59(4) as amended by Notfn 12/2024-CT, from 1 Aug 2024); other B2C
 *    is aggregated in b2cs by place of supply and rate.
 *  - 0% lines are nil-rated: reported in exemp, not in b2cs, and in GSTR-3B
 *    3.1(c), not 3.1(a).
 *  - Credit notes are reported in the period of the credit note date. A note
 *    with lines (a return of 1 almirah out of 7) is reported from its own
 *    lines: rate-wise from their tax, HSN-wise by their HSN, and for a sales
 *    return with the quantity that came back. A note without lines (a price
 *    reduction, and every note issued before lines existed) is spread across
 *    the original invoice's lines in proportion to their taxable value.
 *    B2B notes go to cdnr, B2CL notes to cdnur, other B2C notes reduce b2cs
 *    (as the portal expects).
 *  - Reverse-charge invoices are reported (b2b with Reverse Charge = Y) but
 *    carry no liability for the supplier in GSTR-3B.
 */
class Gstr1Builder
{
    public const B2CL_LIMIT = 100000;

    public function build(Company $company, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        // Issued = given a final number. Older rows may carry an issued status
        // without the finalized_at stamp; they count too. A draft never does.
        $issued = $company->invoices()
            ->where(fn ($q) => $q->whereNotNull('finalized_at')
                ->orWhereIn('status', ['final', 'partially_paid', 'paid']))
            ->where('status', '!=', 'draft')
            ->whereBetween('invoice_date', [$from, $to])
            ->with(['items', 'placeOfSupply', 'customer.state'])
            ->orderBy('invoice_date')->orderBy('id')
            ->get();
        $live = $issued->where('status', '!=', 'cancelled');

        $notes = CreditNote::where('company_id', $company->id)
            ->whereBetween('credit_note_date', [$from, $to])
            ->with(array_merge(CreditNote::linesAvailable() ? ['items'] : [], ['invoice.items', 'invoice.placeOfSupply', 'invoice.customer.state']))
            ->orderBy('credit_note_date')->orderBy('id')
            ->get()
            ->filter(fn ($cn) => $cn->invoice !== null);

        $b2b = $b2cl = $b2csMap = $cdnr = $cdnur = $exp = $hsn = [];
        $exemp = $this->emptyExemp();
        $summary = [
            'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0,
            'nil' => 0.0, 'rcm_taxable' => 0.0,
            // Zero-rated (exports + SEZ) for GSTR-3B 3.1(b).
            'zero_taxable' => 0.0, 'zero_igst' => 0.0,
            'invoices' => $live->count(), 'credit_notes' => $notes->count(),
        ];

        foreach ($live as $inv) {
            $kind = $this->kind($inv);
            $pos = $this->pos($inv);
            foreach ($this->byRate($inv->items, null, $this->rcmFlag($inv, $kind)) as $rate => $g) {
                if ($kind === 'exp') {
                    // Table 6A - every line of an export, whatever its rate.
                    $exp[] = [
                        'exp_typ' => $inv->isUnderLut() ? 'WOPAY' : 'WPAY',
                        'number' => $inv->invoice_number, 'date' => $inv->invoice_date, 'value' => (float) $inv->grand_total,
                        'port_code' => $inv->export_port_code, 'shipping_bill_number' => $inv->shipping_bill_number,
                        'shipping_bill_date' => $inv->shipping_bill_date,
                        'rate' => $rate, 'taxable' => $g['taxable'], 'igst' => $g['igst'], 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0,
                    ];
                    $this->addSummary($summary, $inv, $rate, $g, 1);

                    continue;
                }
                if ($kind === 'sez') {
                    // Table 6B - reported in b2b with the SEZ invoice type.
                    $b2b[] = [
                        'gstin' => $inv->billedCustomer()->gstin, 'name' => $inv->billedCustomer()->name,
                        'number' => $inv->invoice_number, 'date' => $inv->invoice_date, 'value' => (float) $inv->grand_total,
                        'pos' => $pos, 'rcm' => 'N',
                        'type' => $inv->isUnderLut() ? 'SEZ supplies without payment' : 'SEZ supplies with payment',
                        'inv_typ' => $inv->isUnderLut() ? 'SEWOP' : 'SEWP',
                        'rate' => $rate, 'taxable' => $g['taxable'], 'cess' => 0.0,
                        'igst' => $g['igst'], 'cgst' => 0.0, 'sgst' => 0.0,
                    ];
                    $this->addSummary($summary, $inv, $rate, $g, 1);

                    continue;
                }
                if ($kind === 'b2b' && $rate > 0) {
                    $b2b[] = [
                        'gstin' => $inv->billedCustomer()->gstin, 'name' => $inv->billedCustomer()->name,
                        'number' => $inv->invoice_number, 'date' => $inv->invoice_date, 'value' => (float) $inv->grand_total,
                        'pos' => $pos, 'rcm' => $inv->reverse_charge ? 'Y' : 'N', 'type' => 'Regular B2B', 'inv_typ' => 'R',
                        'rate' => $rate, 'taxable' => $g['taxable'], 'cess' => 0.0,
                        'igst' => $g['igst'], 'cgst' => $g['cgst'], 'sgst' => $g['sgst'],
                    ];
                } elseif ($rate > 0 && $kind === 'b2cl') {
                    $b2cl[] = [
                        'number' => $inv->invoice_number, 'date' => $inv->invoice_date, 'value' => (float) $inv->grand_total,
                        'pos' => $pos, 'rate' => $rate, 'taxable' => $g['taxable'], 'cess' => 0.0,
                        'igst' => $g['igst'], 'cgst' => $g['cgst'], 'sgst' => $g['sgst'],
                    ];
                } elseif ($rate > 0) {
                    $this->addB2cs($b2csMap, $pos, $rate, $g['taxable'], $g['igst'], $g['cgst'], $g['sgst']);
                }
                if ($rate <= 0) {
                    $this->addExemp($exemp, $inv, $g['taxable']);
                }
                $this->addSummary($summary, $inv, $rate, $g, 1);
            }
            foreach ($inv->items as $item) {
                $this->addHsn($hsn, in_array($kind, ['b2b', 'sez'], true) ? 'B2B' : 'B2C', $item, 1.0, $this->rcmFlag($inv, $kind));
            }
        }

        foreach ($notes as $cn) {
            $inv = $cn->invoice;
            $kind = $this->kind($inv);
            $pos = $this->pos($inv);
            $lines = $cn->lines();
            // No lines: today's pro-rata over the invoice, unchanged.
            $share = $lines->isEmpty() ? $this->creditShare($cn, $inv) : [];
            $rcm = $this->rcmFlag($inv, $kind);
            $rates = $lines->isEmpty()
                ? $this->settleToNote($this->byRate($inv->items, $share, $rcm), $cn, $rcm !== null)
                : $this->linesByRate($lines, $rcm);
            foreach ($rates as $rate => $g) {
                $row = [
                    'number' => $cn->credit_note_number, 'date' => $cn->credit_note_date, 'type' => 'C',
                    'pos' => $pos, 'value' => (float) $cn->amount, 'rate' => $rate, 'taxable' => $g['taxable'], 'cess' => 0.0,
                    'invoice' => $inv->invoice_number, 'invoice_date' => $inv->invoice_date,
                    'igst' => $g['igst'], 'cgst' => $g['cgst'], 'sgst' => $g['sgst'],
                ];
                if ($kind === 'exp') {
                    $cdnur[] = $row + ['ur_type' => $inv->isUnderLut() ? 'EXPWOP' : 'EXPWP'];

                    continue;
                }
                if ($kind === 'sez') {
                    $cdnr[] = $row + ['gstin' => $inv->billedCustomer()->gstin, 'name' => $inv->billedCustomer()->name, 'rcm' => 'N',
                        'supply_type' => $inv->isUnderLut() ? 'SEZ supplies without payment' : 'SEZ supplies with payment',
                        'inv_typ' => $inv->isUnderLut() ? 'SEWOP' : 'SEWP'];

                    continue;
                }
                if ($kind === 'b2b' && $rate > 0) {
                    $cdnr[] = $row + ['gstin' => $inv->billedCustomer()->gstin, 'name' => $inv->billedCustomer()->name,
                        'rcm' => $inv->reverse_charge ? 'Y' : 'N', 'supply_type' => 'Regular B2B', 'inv_typ' => 'R'];
                } elseif ($kind === 'b2cl' && $rate > 0) {
                    $cdnur[] = $row + ['ur_type' => 'B2CL'];
                } elseif ($rate > 0) {
                    $this->addB2cs($b2csMap, $pos, $rate, -$g['taxable'], -$g['igst'], -$g['cgst'], -$g['sgst']);
                }
                if ($rate <= 0) {
                    $this->addExemp($exemp, $inv, -$g['taxable']);
                }
            }
            $this->addCreditToSummary($summary, $cn, $inv, $share);
            $tab = in_array($kind, ['b2b', 'sez'], true) ? 'B2B' : 'B2C';
            if ($lines->isEmpty()) {
                // Spread over the invoice lines like the rate rows above, then
                // settled to them, so Table 12 takes off exactly what Tables
                // 9B and 8 take off for this note.
                $noteHsn = [];
                foreach ($inv->items as $item) {
                    $this->addHsn($noteHsn, $tab, $item, -($share[$item->id] ?? 0.0), $rcm);
                }
                $this->mergeHsn($hsn, $this->settleHsnToRates($noteHsn, $rates));
            } else {
                foreach ($lines as $line) {
                    $this->addHsnCreditLine($hsn, $tab, $line, $cn->isSalesReturn(), $rcm);
                }
            }
        }

        foreach ($summary as $k => $v) {
            $summary[$k] = is_float($v) ? round($v, 2) : $v;
        }

        $out = [
            'b2b' => $b2b,
            'b2cl' => $b2cl,
            // A B2CS line wholly reversed by credit notes nets to nil: drop it.
            'b2cs' => array_values(array_filter(
                array_map(fn ($r) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $r), $b2csMap),
                fn ($r) => abs($r['taxable']) >= 0.005 || abs($r['igst'] + $r['cgst'] + $r['sgst']) >= 0.005,
            )),
            'cdnr' => $cdnr,
            'cdnur' => $cdnur,
            'exp' => $exp,
            'exemp' => array_values($exemp),
            'hsn' => $this->finishHsn($hsn),
            'docs' => $this->docs($company, $issued, $notes, $from, $to),
            'summary' => $summary,
        ];
        $out['tables'] = $this->tables($out);

        return $out;
    }

    /**
     * Table-wise totals, as the portal's GSTR-1 summary shows them: records,
     * taxable value and tax per table. Credit-note tables are positive here
     * (the portal shows them as their own table, not netted).
     */
    private function tables(array $d): array
    {
        $sum = function (array $rows, string $countKey = 'number') {
            $t = ['records' => count(array_unique(array_map(fn ($r) => $r[$countKey] ?? '', $rows))),
                'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
            foreach ($rows as $r) {
                foreach (['taxable', 'igst', 'cgst', 'sgst'] as $k) {
                    $t[$k] += (float) ($r[$k] ?? 0);
                }
            }
            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $k) {
                $t[$k] = round($t[$k], 2);
            }

            return $t;
        };
        $hsnTab = fn (string $tab) => array_values(array_filter($d['hsn'], fn ($r) => $r['tab'] === $tab));
        $nil = array_sum(array_column($d['exemp'], 'nil'));

        $b2cs = $sum($d['b2cs'], 'pos');
        $b2cs['records'] = count($d['b2cs']);

        return [
            'b2b'     => ['table' => '4A', 'label' => 'B2B invoices'] + $sum(array_filter($d['b2b'], fn ($r) => $r['rcm'] === 'N' && $r['inv_typ'] === 'R')),
            'b2b_rcm' => ['table' => '4B', 'label' => 'B2B (reverse charge)'] + $sum(array_filter($d['b2b'], fn ($r) => $r['rcm'] === 'Y')),
            'b2cl'    => ['table' => '5', 'label' => 'B2C large (inter-state, over Rs 1 lakh)'] + $sum($d['b2cl']),
            'exp'     => ['table' => '6A', 'label' => 'Exports'] + $sum($d['exp']),
            'sez'     => ['table' => '6B', 'label' => 'Supplies to SEZ'] + $sum(array_filter($d['b2b'], fn ($r) => in_array($r['inv_typ'], ['SEWP', 'SEWOP'], true))),
            'b2cs'    => ['table' => '7', 'label' => 'B2C others'] + $b2cs,
            'exemp'   => ['table' => '8', 'label' => 'Nil rated, exempt, non-GST', 'records' => count(array_filter($d['exemp'], fn ($r) => abs($r['nil']) >= 0.005)),
                'taxable' => round($nil, 2), 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'cdnr'    => ['table' => '9B', 'label' => 'Credit notes (registered)'] + $sum($d['cdnr']),
            'cdnur'   => ['table' => '9B', 'label' => 'Credit notes (unregistered: B2CL, exports)'] + $sum($d['cdnur']),
            'hsn_b2b' => ['table' => '12', 'label' => 'HSN summary (B2B)'] + $sum($hsnTab('B2B'), 'key'),
            'hsn_b2c' => ['table' => '12', 'label' => 'HSN summary (B2C)'] + $sum($hsnTab('B2C'), 'key'),
        ];
    }

    // ------------------------------------------------------------------

    private function kind(Invoice $inv): string
    {
        if ($inv->isExport()) {
            return 'exp';
        }
        if ($inv->isSez()) {
            return 'sez';
        }
        $cust = $inv->billedCustomer();
        if (! empty($cust?->gstin)) {
            return 'b2b';
        }

        return ($inv->is_interstate && (float) $inv->grand_total > self::B2CL_LIMIT) ? 'b2cl' : 'b2cs';
    }

    /** Place of supply as the portal writes it: "27-Maharashtra". */
    private function pos(Invoice $inv): string
    {
        $state = $inv->placeOfSupply ?? $inv->billedCustomer()?->state;

        return $state ? $state->gst_code . '-' . $state->name : '';
    }

    /**
     * Group invoice lines by GST rate. With $share (item id => fraction of
     * that line credited) the figures are the credited portion instead.
     *
     * @return array<string, array{taxable: float, igst: float, cgst: float, sgst: float}>
     */
    private function byRate(Collection $items, ?array $share = null, ?bool $rcmInterstate = null): array
    {
        $out = [];
        foreach ($items as $item) {
            $f = $share === null ? 1.0 : ($share[$item->id] ?? 0.0);
            if ($f == 0.0) {
                continue;
            }
            $rate = (string) (0 + (float) $item->gst_rate);
            $tax = $this->lineTax($rate, (float) $item->amount, $item->igst_amount, $item->cgst_amount, $item->sgst_amount, $rcmInterstate);
            $out[$rate] ??= ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
            $out[$rate]['taxable'] += (float) $item->amount * $f;
            $out[$rate]['igst'] += $tax['igst'] * $f;
            $out[$rate]['cgst'] += $tax['cgst'] * $f;
            $out[$rate]['sgst'] += $tax['sgst'] * $f;
        }
        foreach ($out as $r => $g) {
            $out[$r] = array_map(fn ($v) => round($v, 2), $g);
        }

        return $out;
    }

    /**
     * Fraction of each invoice line covered by a credit note. The note's
     * taxable value is spread over the lines in proportion to their taxable
     * value (the same pro-rata the credit note itself uses for tax heads).
     *
     * After a sales return the note was split over what each line still had
     * left (CreditNoteSplit::leftByLine), so it is reported on that basis
     * too: nothing on goods that came back, and rows that add up to the tax
     * the note carries, as GSTR-3B takes it.
     *
     * @return array<int, float>
     */
    private function creditShare(CreditNote $cn, Invoice $inv): array
    {
        $subtotal = (float) $inv->subtotal;
        if ($subtotal <= 0) {
            return [];
        }

        $left = app(CreditNoteSplit::class)->leftByLine($inv, $cn->id);
        $pool = $left ? array_sum(array_column($left, 'taxable_value')) : 0.0;
        if ($pool > 0) {
            $ratio = min(1.0, (float) $cn->taxable_value / $pool);

            return $inv->items->mapWithKeys(fn ($i) => [$i->id => (float) $i->amount > 0
                ? min(1.0, ($left[$i->id]['taxable_value'] ?? 0.0) * $ratio / (float) $i->amount)
                : 0.0])->all();
        }

        $fraction = min(1.0, (float) $cn->taxable_value / $subtotal);

        return $inv->items->mapWithKeys(fn ($i) => [$i->id => $fraction])->all();
    }

    /**
     * The tax on a reverse-charge supply as the recipient pays it: rate x
     * taxable value, IGST across states, CGST and SGST at half the rate each
     * within one. Reported in Table 4B (and 9B for its notes); never part of
     * the supplier's own GSTR-3B liability.
     *
     * @return array{igst: float, cgst: float, sgst: float}
     */
    private function recipientTax(string $rate, float $taxable, bool $interstate): array
    {
        $r = (float) $rate;
        if ($interstate) {
            return ['igst' => round($taxable * $r / 100, 2), 'cgst' => 0.0, 'sgst' => 0.0];
        }
        $half = round($taxable * $r / 200, 2);

        return ['igst' => 0.0, 'cgst' => $half, 'sgst' => $half];
    }

    /**
     * A line's tax as reported: as charged, or for a reverse-charge supply
     * (rcmInterstate not null) the tax the recipient pays, worked out per line
     * like every other line so Tables 4B, 9B and 12 agree to the paisa.
     *
     * @return array{igst: float, cgst: float, sgst: float}
     */
    private function lineTax(string $rate, float $taxable, $igst, $cgst, $sgst, ?bool $rcmInterstate): array
    {
        return $rcmInterstate === null
            ? ['igst' => (float) $igst, 'cgst' => (float) $cgst, 'sgst' => (float) $sgst]
            : $this->recipientTax($rate, $taxable, $rcmInterstate);
    }

    /** For a reverse-charge B2B supply: is it inter-state? Null for every other supply. */
    private function rcmFlag(Invoice $inv, string $kind): ?bool
    {
        return $inv->reverse_charge && $kind === 'b2b' ? (bool) $inv->is_interstate : null;
    }

    /**
     * A note spread over several GST rates, each rate rounded on its own, can
     * total a paisa away from the note (10.08 + 40.32 + 40.32 = 90.72 against
     * a note of 90.73). Put the difference on the largest rate of that head,
     * so the rows add up to the note exactly and GSTR-1 agrees with the
     * registers and GSTR-3B.
     *
     * @param  array<string, array{taxable: float, igst: float, cgst: float, sgst: float}>  $rates
     * @return array<string, array{taxable: float, igst: float, cgst: float, sgst: float}>
     */
    private function settleToNote(array $rates, CreditNote $cn, bool $reverseCharge = false): array
    {
        if (! $rates) {
            return $rates;
        }
        // A reverse-charge note stores no tax (the supplier collected none);
        // its reported tax is the recipient's, so only the taxable is settled.
        $note = ['taxable' => (float) $cn->taxable_value] + ($reverseCharge ? [] : [
            'igst' => (float) $cn->total_igst,
            'cgst' => (float) $cn->total_cgst,
            'sgst' => (float) $cn->total_sgst,
        ]);
        foreach ($note as $head => $total) {
            $diff = round($total - array_sum(array_column($rates, $head)), 2);
            if ($diff == 0.0 || abs($diff) >= 1) {
                continue; // nothing to settle, or not a rounding difference
            }
            $largest = array_key_first($rates);
            foreach ($rates as $rate => $g) {
                if (abs($g[$head]) > abs($rates[$largest][$head])) {
                    $largest = $rate;
                }
            }
            $rates[$largest][$head] = round($rates[$largest][$head] + $diff, 2);
        }

        return $rates;
    }

    /**
     * A lined credit note grouped by GST rate, from its own figures.
     *
     * @return array<string, array{taxable: float, igst: float, cgst: float, sgst: float}>
     */
    private function linesByRate(Collection $lines, ?bool $rcmInterstate = null): array
    {
        $out = [];
        foreach ($lines as $line) {
            $rate = (string) (0 + (float) $line->gst_rate);
            $tax = $this->lineTax($rate, (float) $line->taxable_value, $line->igst, $line->cgst, $line->sgst, $rcmInterstate);
            $out[$rate] ??= ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
            $out[$rate]['taxable'] += (float) $line->taxable_value;
            $out[$rate]['igst'] += $tax['igst'];
            $out[$rate]['cgst'] += $tax['cgst'];
            $out[$rate]['sgst'] += $tax['sgst'];
        }
        foreach ($out as $r => $g) {
            $out[$r] = array_map(fn ($v) => round($v, 2), $g);
        }

        return $out;
    }

    private function addB2cs(array &$map, string $pos, string $rate, float $taxable, float $igst, float $cgst, float $sgst): void
    {
        $key = $pos . '|' . $rate;
        $map[$key] ??= ['type' => 'OE', 'pos' => $pos, 'rate' => (float) $rate, 'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0];
        $map[$key]['taxable'] += $taxable;
        $map[$key]['igst'] += $igst;
        $map[$key]['cgst'] += $cgst;
        $map[$key]['sgst'] += $sgst;
    }

    private function emptyExemp(): array
    {
        $rows = [];
        foreach (['Inter-State supplies to registered persons', 'Intra-State supplies to registered persons',
            'Inter-State supplies to unregistered persons', 'Intra-State supplies to unregistered persons'] as $d) {
            $rows[$d] = ['description' => $d, 'nil' => 0.0, 'exempt' => 0.0, 'non_gst' => 0.0];
        }

        return $rows;
    }

    private function addExemp(array &$exemp, Invoice $inv, float $taxable): void
    {
        $registered = ! empty($inv->billedCustomer()?->gstin);
        $key = ($inv->is_interstate ? 'Inter' : 'Intra') . '-State supplies to ' . ($registered ? 'registered' : 'unregistered') . ' persons';
        $exemp[$key]['nil'] = round($exemp[$key]['nil'] + $taxable, 2);
    }

    /** Outward totals for GSTR-3B 3.1: (a) taxable non-RCM rate > 0, (c) nil. */
    private function addSummary(array &$s, Invoice $inv, string $rate, array $g, int $sign): void
    {
        if ($inv->reverse_charge) {
            $s['rcm_taxable'] += $sign * $g['taxable'];

            return;
        }
        if ($inv->isZeroRated()) {
            $s['zero_taxable'] += $sign * $g['taxable'];
            $s['zero_igst'] += $sign * $g['igst'];

            return;
        }
        if ((float) $rate <= 0) {
            $s['nil'] += $sign * $g['taxable'];

            return;
        }
        $s['taxable'] += $sign * $g['taxable'];
        $s['igst'] += $sign * $g['igst'];
        $s['cgst'] += $sign * $g['cgst'];
        $s['sgst'] += $sign * $g['sgst'];
    }

    /**
     * A credit note reduces GSTR-3B by exactly what it says: its own taxable
     * value and tax heads (so the return ties to the document), with the part
     * that credited 0% lines moved to nil-rated.
     */
    private function addCreditToSummary(array &$s, CreditNote $cn, Invoice $inv, array $share): void
    {
        if ($inv->reverse_charge) {
            $s['rcm_taxable'] -= (float) $cn->taxable_value;

            return;
        }
        if ($inv->isZeroRated()) {
            $s['zero_taxable'] -= (float) $cn->taxable_value;
            $s['zero_igst'] -= (float) $cn->total_igst;

            return;
        }
        $nil = 0.0;
        if ($cn->lines()->isNotEmpty()) {
            foreach ($cn->lines() as $line) {
                if ((float) $line->gst_rate <= 0) {
                    $nil += (float) $line->taxable_value;
                }
            }
        } else {
            foreach ($inv->items as $item) {
                if ((float) $item->gst_rate <= 0) {
                    $nil += (float) $item->amount * ($share[$item->id] ?? 0.0);
                }
            }
        }
        $nil = round($nil, 2);
        $s['nil'] -= $nil;
        $s['taxable'] -= (float) $cn->taxable_value - $nil;
        $s['igst'] -= (float) $cn->total_igst;
        $s['cgst'] -= (float) $cn->total_cgst;
        $s['sgst'] -= (float) $cn->total_sgst;
    }

    /**
     * The unit as GSTR-1 Table 12 reports it: a code from the GSTN UQC list
     * (config/uqc_units.php), with common spellings mapped ("kg" is KGS). A
     * service (SAC 99...) always takes NA; a unit not on the list is OTH.
     * Table 12 is one row per HSN + UQC + rate, so its rows are keyed by this
     * code, not by the unit as typed ("NOS" and "HRS" on one SAC are one row).
     */
    public static function uqc(?string $unit, ?string $hsn = null): string
    {
        if (str_starts_with(trim((string) $hsn), '99')) {
            return 'NA';
        }
        $code = strtoupper(trim((string) $unit));
        $code = config('uqc_units.aliases')[$code] ?? $code;

        return in_array($code, array_column(config('uqc_units.codes'), 'code'), true) ? $code : 'OTH';
    }

    private function addHsn(array &$hsn, string $tab, $item, float $sign, ?bool $rcmInterstate = null): void
    {
        if ($sign == 0.0) {
            return;
        }
        $rate = (float) $item->gst_rate;
        $code = trim((string) $item->hsn_sac);
        $uqc = self::uqc($item->unit, $code);
        $key = $tab . '|' . $code . '|' . $uqc . '|' . $rate;
        $hsn[$key] ??= ['tab' => $tab, 'hsn' => $code, 'description' => $item->description,
            'uqc' => $uqc, 'qty' => 0.0, 'value' => 0.0, 'rate' => $rate,
            'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0];
        // Credit notes reduce value but not quantity (a price correction moves
        // no goods). A service is reported with no quantity at all.
        if ($sign > 0 && $uqc !== 'NA') {
            $hsn[$key]['qty'] += (float) $item->quantity;
        }
        $tax = $this->lineTax((string) (0 + $rate), (float) $item->amount, $item->igst_amount, $item->cgst_amount, $item->sgst_amount, $rcmInterstate);
        $hsn[$key]['value'] += $sign * (float) $item->total;
        $hsn[$key]['taxable'] += $sign * (float) $item->amount;
        $hsn[$key]['igst'] += $sign * $tax['igst'];
        $hsn[$key]['cgst'] += $sign * $tax['cgst'];
        $hsn[$key]['sgst'] += $sign * $tax['sgst'];
    }

    /**
     * One line of a credit note off Table 12: its HSN, taxable value and tax.
     * The quantity comes off only for a sales return (goods came back); a
     * price correction on a line moves no goods.
     */
    private function addHsnCreditLine(array &$hsn, string $tab, $line, bool $goodsReturned, ?bool $rcmInterstate = null): void
    {
        $rate = (float) $line->gst_rate;
        $code = trim((string) $line->hsn_sac);
        $uqc = self::uqc($line->unit, $code);
        $key = $tab . '|' . $code . '|' . $uqc . '|' . $rate;
        $hsn[$key] ??= ['tab' => $tab, 'hsn' => $code, 'description' => $line->description,
            'uqc' => $uqc, 'qty' => 0.0, 'value' => 0.0, 'rate' => $rate,
            'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0];
        if ($goodsReturned && $uqc !== 'NA') {
            $hsn[$key]['qty'] -= (float) $line->quantity;
        }
        // Value is what the note credits (tax charged); tax columns are as reported.
        $charged = (float) $line->igst + (float) $line->cgst + (float) $line->sgst;
        $t = $this->lineTax((string) (0 + $rate), (float) $line->taxable_value, $line->igst, $line->cgst, $line->sgst, $rcmInterstate);
        $hsn[$key]['value'] -= (float) $line->taxable_value + $charged;
        $hsn[$key]['taxable'] -= (float) $line->taxable_value;
        $hsn[$key]['igst'] -= $t['igst'];
        $hsn[$key]['cgst'] -= $t['cgst'];
        $hsn[$key]['sgst'] -= $t['sgst'];
    }

    /**
     * A note without lines, spread over the invoice lines, rounds each HSN
     * row on its own and can total a paisa away from the rate rows that
     * settleToNote() made add up to the note (660.80 + 220.27 off Table 12
     * against 660.79 + 220.27 off 9B and 8, for a note of 881.06). Round each
     * row, then put the difference at each rate on that rate's largest row,
     * so the HSN rows at a rate take off exactly what the rate row does.
     *
     * @param  array<string, array>  $rows  the note's HSN rows (negative)
     * @param  array<string, array{taxable: float, igst: float, cgst: float, sgst: float}>  $rates  the note's settled rate rows
     */
    private function settleHsnToRates(array $rows, array $rates): array
    {
        foreach ($rows as $key => $r) {
            foreach (['value', 'taxable', 'igst', 'cgst', 'sgst'] as $head) {
                $rows[$key][$head] = round($r[$head], 2);
            }
        }
        foreach ($rates as $rate => $g) {
            $keys = array_keys(array_filter($rows, fn ($r) => (string) (0 + $r['rate']) === (string) $rate));
            if (! $keys) {
                continue;
            }
            foreach (['taxable', 'igst', 'cgst', 'sgst'] as $head) {
                $diff = round(-$g[$head] - array_sum(array_map(fn ($k) => $rows[$k][$head], $keys)), 2);
                if ($diff == 0.0 || abs($diff) >= 1) {
                    continue; // nothing to settle, or not a rounding difference
                }
                $largest = $keys[0];
                foreach ($keys as $k) {
                    if (abs($rows[$k][$head]) > abs($rows[$largest][$head])) {
                        $largest = $k;
                    }
                }
                $rows[$largest][$head] = round($rows[$largest][$head] + $diff, 2);
                $rows[$largest]['value'] = round($rows[$largest]['value'] + $diff, 2);
            }
        }

        return $rows;
    }

    /** Add one document's HSN rows to Table 12. */
    private function mergeHsn(array &$hsn, array $rows): void
    {
        foreach ($rows as $key => $r) {
            if (! isset($hsn[$key])) {
                $hsn[$key] = $r;

                continue;
            }
            foreach (['qty', 'value', 'taxable', 'igst', 'cgst', 'sgst'] as $k) {
                $hsn[$key][$k] += $r[$k];
            }
        }
    }

    private function finishHsn(array $hsn): array
    {
        $rows = [];
        foreach ($hsn as $key => $r) {
            $rows[] = ['key' => $key] + array_map(fn ($v) => is_float($v) ? round($v, 3) : $v, $r);
        }
        foreach ($rows as &$r) {
            foreach (['value', 'taxable', 'igst', 'cgst', 'sgst'] as $k) {
                $r[$k] = round($r[$k], 2);
            }
        }
        usort($rows, fn ($a, $b) => [$a['tab'], $a['hsn'], $a['rate'], $a['uqc']] <=> [$b['tab'], $b['hsn'], $b['rate'], $b['uqc']]);

        return $rows;
    }

    /**
     * Table 13: documents issued, one row per numbering series, each from its
     * lowest number to its highest (a document dated back before an earlier
     * one does not turn the range round). A business that changes its
     * invoice format mid-month has two series, reported on two rows.
     * Cancelled invoices stay in the count, as cancelled.
     */
    private function docs(Company $company, Collection $issued, Collection $notes, Carbon $from, Carbon $to): array
    {
        $rows = [];
        foreach ($issued->groupBy(fn ($i) => $this->serial((string) $i->invoice_number)[0]) as $series) {
            $sorted = $series->sortBy(fn ($i) => [...$this->serial((string) $i->invoice_number)[1], $i->id])->values();
            $rows[] = ['nature' => 'Invoices for outward supply', 'from' => $sorted->first()->invoice_number,
                'to' => $sorted->last()->invoice_number, 'total' => $series->count(),
                'cancelled' => $series->where('status', 'cancelled')->count()];
        }

        return array_merge($rows, $this->creditNoteDocs($company, $notes, $from, $to));
    }

    /**
     * An invoice number's series and its place in it. The series is the
     * number with its digits masked, so "AUD/26-27/0001" and "SHP/26-27/0003"
     * are two series; the place is its groups of digits as numbers, so 0010
     * comes after 0009 wherever the running number sits in the format.
     *
     * @return array{0: string, 1: int[]}
     */
    private function serial(string $number): array
    {
        preg_match_all('/\d+/', $number, $m);

        return [preg_replace('/\d+/', '#', $number), array_map('intval', $m[0])];
    }

    /**
     * Table 13 for credit notes, series by series. Reversing a note deletes
     * it, but its number stays used (the counter never gives it out again),
     * so it is reported as issued and cancelled and the range agrees with
     * the count: every number between the lowest and highest of the series
     * that no note holds any more is cancelled, as is a note dated in the
     * period whose reversal the activity log recorded (a reversed last note
     * leaves no gap to see).
     */
    private function creditNoteDocs(Company $company, Collection $notes, Carbon $from, Carbon $to): array
    {
        // Numbers still held, whatever their date: one used by a note of
        // another period is not a gap.
        $held = [];
        foreach (CreditNote::where('company_id', $company->id)->pluck('credit_note_number') as $number) {
            [$series, $n] = $this->counter((string) $number);
            $held[$series][$n] = true;
        }

        $live = $gone = [];
        foreach ($notes as $cn) {
            [$series, $n] = $this->counter((string) $cn->credit_note_number);
            $live[$series][$n] = $cn->credit_note_number;
        }
        foreach ($this->reversedNotes($company, $from, $to) as $number) {
            [$series, $n] = $this->counter($number);
            if (! isset($held[$series][$n])) {
                $gone[$series][$n] = $number;
            }
        }

        $rows = [];
        foreach (array_keys($live + $gone) as $series) {
            $numbers = ($live[$series] ?? []) + ($gone[$series] ?? []);
            ksort($numbers);
            $low = array_key_first($numbers);
            $high = array_key_last($numbers);
            // Numbers in the range held by notes dated in other periods.
            $elsewhere = count(array_filter(array_keys($held[$series] ?? []),
                fn ($n) => $n >= $low && $n <= $high && ! isset($live[$series][$n])));
            $total = $high - $low + 1 - $elsewhere;
            $rows[] = ['nature' => 'Credit Note', 'from' => $numbers[$low], 'to' => $numbers[$high],
                'total' => $total, 'cancelled' => $total - count($live[$series] ?? [])];
        }

        return $rows;
    }

    /**
     * A credit note number's series and running number: "CRN-0007" is series
     * "CRN-", number 7 (notes are always numbered prefix and counter). A
     * number with no digits at the end is a series of its own.
     *
     * @return array{0: string, 1: int}
     */
    private function counter(string $number): array
    {
        return preg_match('/^(.*?)(\d+)$/', $number, $m) ? [$m[1], (int) $m[2]] : [$number, 0];
    }

    /**
     * Numbers of the credit notes dated in the period that were reversed
     * since, from the activity log (CreditNoteController::destroy records
     * the note's number and date there before the row is deleted).
     *
     * @return string[]
     */
    private function reversedNotes(Company $company, Carbon $from, Carbon $to): array
    {
        return AuditLog::where('company_id', $company->id)
            ->where('action', 'credit_note.deleted')
            // A note is reversed on or after its date: skip older entries.
            ->where('created_at', '>=', $from->copy()->subDay())
            ->pluck('changes')
            ->filter(function ($c) use ($from, $to) {
                if (! is_array($c) || empty($c['credit_note_number']) || empty($c['credit_note_date'])) {
                    return false;
                }
                // The date was saved as JSON (midnight IST written in UTC).
                $date = rescue(fn () => Carbon::parse($c['credit_note_date'])->setTimezone(config('app.timezone'))->toDateString(), null, false);

                return $date !== null && $date >= $from->toDateString() && $date <= $to->toDateString();
            })
            ->map(fn ($c) => (string) $c['credit_note_number'])
            ->values()
            ->all();
    }
}
