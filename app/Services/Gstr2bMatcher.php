<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Gstr2bEntry;
use Illuminate\Support\Carbon;

/**
 * Matches one month's GSTR-2B against the purchase invoices in the books.
 *
 * A 2B document and a purchase invoice are the same document when the
 * supplier GSTIN and the invoice number agree (the number compared without
 * spaces, punctuation or leading zeros: "INV/001" = "inv-1"). Then:
 *
 *  - matched       taxable value and tax agree within ₹1
 *  - mismatch      found, but the amounts differ - check before claiming
 *  - missing       in 2B, not in the books - an unrecorded purchase, or credit
 *                  you could claim and haven't
 *  - not_in_2b     in the books for this month, not in this 2B - the supplier
 *                  hasn't filed it yet; hold the credit (s.16(2)(aa))
 *  - no_gstin      purchases from suppliers without a GSTIN - never in 2B
 *  - no_itc        found, but 2B marks its credit not available (itcavl N,
 *                  s.16(2)(aa) / Rule 36(4)): in 2B, yet not claimable
 *
 * Supplier credit / debit notes from 2B are listed on their own.
 */
class Gstr2bMatcher
{
    public const TOLERANCE = 1.00;

    public function match(Company $company, string $period): array
    {
        $month = Carbon::createFromFormat('!Y-m', $period);
        $entries = Gstr2bEntry::where('company_id', $company->id)->where('period', $period)
            ->orderBy('supplier_name')->orderBy('document_date')->get();

        // Books: bills from the last two years can turn up in a late 2B.
        $bills = $company->purchaseBills()->with('vendor:id,name,gstin')
            ->whereDate('bill_date', '>=', $month->copy()->subMonths(24)->startOfMonth())
            ->whereDate('bill_date', '<=', $month->copy()->endOfMonth())
            ->get();
        $index = [];
        foreach ($bills as $b) {
            if ($b->vendor?->gstin) {
                $index[$this->key($b->vendor->gstin, $b->bill_number)][] = $b;
            }
        }

        $out = ['matched' => [], 'mismatch' => [], 'missing' => [], 'not_in_2b' => [], 'no_gstin' => [], 'notes' => [], 'no_itc' => []];
        $seen = [];
        foreach ($entries as $e) {
            if ($e->kind !== 'invoice') {
                $out['notes'][] = ['entry' => $e];

                continue;
            }
            $bill = $index[$this->key($e->supplier_gstin, $e->document_number)][0] ?? null;
            if (! $bill) {
                $out['missing'][] = ['entry' => $e];

                continue;
            }
            $seen[$bill->id] = true;
            $taxDiff = round($e->tax() - (float) $bill->total_tax, 2);
            $valueDiff = round((float) $e->taxable_value - (float) $bill->subtotal, 2);
            $row = ['entry' => $e, 'bill' => $bill, 'taxable_diff' => $valueDiff, 'tax_diff' => $taxDiff];
            if (! $e->itc_available) {
                // The document is in 2B, but its credit is not available to
                // you: not "matched", however well the amounts agree.
                $out['no_itc'][] = $row;
            } elseif (abs($taxDiff) <= self::TOLERANCE && abs($valueDiff) <= self::TOLERANCE) {
                $out['matched'][] = $row;
            } else {
                $out['mismatch'][] = $row;
            }
        }

        foreach ($bills as $b) {
            if (isset($seen[$b->id]) || $b->bill_date->format('Y-m') !== $period) {
                continue;
            }
            if (! $b->vendor?->gstin) {
                $out['no_gstin'][] = ['bill' => $b];
            } else {
                $out['not_in_2b'][] = ['bill' => $b];
            }
        }

        $tax = fn (array $rows, string $from) => round(array_sum(array_map(
            fn ($r) => $from === 'entry' ? $r['entry']->tax() : (float) $r['bill']->total_tax, $rows)), 2);

        $out['summary'] = [
            'period' => $period,
            'uploaded' => $entries->isNotEmpty(),
            'uploaded_at' => $entries->max('created_at'),
            'documents' => $entries->count(),
            'itc_in_2b' => round($entries->where('kind', 'invoice')->where('itc_available', true)->sum(fn ($e) => $e->tax()), 2),
            'counts' => array_map('count', array_intersect_key($out, array_flip(['matched', 'mismatch', 'missing', 'not_in_2b', 'no_gstin', 'notes']))),
            'tax' => [
                'matched' => $tax($out['matched'], 'entry'),
                'mismatch' => $tax($out['mismatch'], 'entry'),
                'missing' => $tax($out['missing'], 'entry'),
                'not_in_2b' => $tax($out['not_in_2b'], 'bill'),
                'no_gstin' => $tax($out['no_gstin'], 'bill'),
            ],
            // Kept out of 'counts' and 'tax', whose keys callers read as they are.
            'no_itc' => ['count' => count($out['no_itc']), 'tax' => $tax($out['no_itc'], 'entry')],
        ];

        return $out;
    }

    /** Supplier GSTIN + invoice number, compared loosely. */
    public function key(string $gstin, string $number): string
    {
        // Leading zeros go part by part, before the separators: "DC/2026/0117"
        // and "DC-2026-117" are the same invoice ("DC2026117"). Stripping the
        // separators first glued "2026" to "0117" and the zeros survived.
        $parts = preg_split('/[^A-Z0-9]+/', strtoupper($number), -1, PREG_SPLIT_NO_EMPTY);
        $n = implode('', array_map(fn ($p) => preg_replace('/(?<![0-9])0+(?=[0-9])/', '', $p), $parts));

        return strtoupper(trim($gstin)) . '|' . $n;
    }
}
