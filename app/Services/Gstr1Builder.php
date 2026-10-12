<?php

namespace App\Services;

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
            foreach ($this->byRate($inv->items) as $rate => $g) {
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
                $this->addHsn($hsn, in_array($kind, ['b2b', 'sez'], true) ? 'B2B' : 'B2C', $item, 1.0);
            }
        }

        foreach ($notes as $cn) {
            $inv = $cn->invoice;
            $kind = $this->kind($inv);
            $pos = $this->pos($inv);
            $lines = $cn->lines();
            // No lines: today's pro-rata over the invoice, unchanged.
            $share = $lines->isEmpty() ? $this->creditShare($cn, $inv) : [];
            $rates = $lines->isEmpty() ? $this->byRate($inv->items, $share) : $this->linesByRate($lines);
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
                foreach ($inv->items as $item) {
                    $this->addHsn($hsn, $tab, $item, -($share[$item->id] ?? 0.0));
                }
            } else {
                foreach ($lines as $line) {
                    $this->addHsnCreditLine($hsn, $tab, $line, $cn->isSalesReturn());
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
            'docs' => $this->docs($issued, $notes),
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
    private function byRate(Collection $items, ?array $share = null): array
    {
        $out = [];
        foreach ($items as $item) {
            $f = $share === null ? 1.0 : ($share[$item->id] ?? 0.0);
            if ($f == 0.0) {
                continue;
            }
            $rate = (string) (0 + (float) $item->gst_rate);
            $out[$rate] ??= ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
            $out[$rate]['taxable'] += (float) $item->amount * $f;
            $out[$rate]['igst'] += (float) $item->igst_amount * $f;
            $out[$rate]['cgst'] += (float) $item->cgst_amount * $f;
            $out[$rate]['sgst'] += (float) $item->sgst_amount * $f;
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
     * @return array<int, float>
     */
    private function creditShare(CreditNote $cn, Invoice $inv): array
    {
        $subtotal = (float) $inv->subtotal;
        if ($subtotal <= 0) {
            return [];
        }
        $fraction = min(1.0, (float) $cn->taxable_value / $subtotal);

        return $inv->items->mapWithKeys(fn ($i) => [$i->id => $fraction])->all();
    }

    /**
     * A lined credit note grouped by GST rate, from its own figures.
     *
     * @return array<string, array{taxable: float, igst: float, cgst: float, sgst: float}>
     */
    private function linesByRate(Collection $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $rate = (string) (0 + (float) $line->gst_rate);
            $out[$rate] ??= ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
            $out[$rate]['taxable'] += (float) $line->taxable_value;
            $out[$rate]['igst'] += (float) $line->igst;
            $out[$rate]['cgst'] += (float) $line->cgst;
            $out[$rate]['sgst'] += (float) $line->sgst;
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

    private function addHsn(array &$hsn, string $tab, $item, float $sign): void
    {
        if ($sign == 0.0) {
            return;
        }
        $rate = (float) $item->gst_rate;
        $key = $tab . '|' . trim((string) $item->hsn_sac) . '|' . strtoupper((string) ($item->unit ?: 'OTH')) . '|' . $rate;
        $hsn[$key] ??= ['tab' => $tab, 'hsn' => trim((string) $item->hsn_sac), 'description' => $item->description,
            'uqc' => strtoupper((string) ($item->unit ?: 'OTH')), 'qty' => 0.0, 'value' => 0.0, 'rate' => $rate,
            'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0];
        // Credit notes reduce value but not quantity (a price correction moves no goods).
        if ($sign > 0) {
            $hsn[$key]['qty'] += (float) $item->quantity;
        }
        $hsn[$key]['value'] += $sign * (float) $item->total;
        $hsn[$key]['taxable'] += $sign * (float) $item->amount;
        $hsn[$key]['igst'] += $sign * (float) $item->igst_amount;
        $hsn[$key]['cgst'] += $sign * (float) $item->cgst_amount;
        $hsn[$key]['sgst'] += $sign * (float) $item->sgst_amount;
    }

    /**
     * One line of a credit note off Table 12: its HSN, taxable value and tax.
     * The quantity comes off only for a sales return (goods came back); a
     * price correction on a line moves no goods.
     */
    private function addHsnCreditLine(array &$hsn, string $tab, $line, bool $goodsReturned): void
    {
        $rate = (float) $line->gst_rate;
        $uqc = strtoupper((string) ($line->unit ?: 'OTH'));
        $code = trim((string) $line->hsn_sac);
        $key = $tab . '|' . $code . '|' . $uqc . '|' . $rate;
        $hsn[$key] ??= ['tab' => $tab, 'hsn' => $code, 'description' => $line->description,
            'uqc' => $uqc, 'qty' => 0.0, 'value' => 0.0, 'rate' => $rate,
            'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0];
        if ($goodsReturned) {
            $hsn[$key]['qty'] -= (float) $line->quantity;
        }
        $tax = (float) $line->igst + (float) $line->cgst + (float) $line->sgst;
        $hsn[$key]['value'] -= (float) $line->taxable_value + $tax;
        $hsn[$key]['taxable'] -= (float) $line->taxable_value;
        $hsn[$key]['igst'] -= (float) $line->igst;
        $hsn[$key]['cgst'] -= (float) $line->cgst;
        $hsn[$key]['sgst'] -= (float) $line->sgst;
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
        usort($rows, fn ($a, $b) => [$a['tab'], $a['hsn'], $a['rate']] <=> [$b['tab'], $b['hsn'], $b['rate']]);

        return $rows;
    }

    /** Table 13: serial ranges issued, with cancelled invoices counted. */
    private function docs(Collection $issued, Collection $notes): array
    {
        $rows = [];
        if ($issued->isNotEmpty()) {
            $sorted = $issued->sortBy(fn ($i) => [$i->finalized_at?->timestamp ?? 0, $i->id])->values();
            $rows[] = ['nature' => 'Invoices for outward supply', 'from' => $sorted->first()->invoice_number,
                'to' => $sorted->last()->invoice_number, 'total' => $issued->count(),
                'cancelled' => $issued->where('status', 'cancelled')->count()];
        }
        if ($notes->isNotEmpty()) {
            $rows[] = ['nature' => 'Credit Note', 'from' => $notes->first()->credit_note_number,
                'to' => $notes->last()->credit_note_number, 'total' => $notes->count(), 'cancelled' => 0];
        }

        return $rows;
    }
}
