<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Carbon;

/**
 * The GSTR-3B figures for a period, computed from the books: outward supplies
 * (3.1(a)/(c), from the same build as GSTR-1 so the two returns agree), inward
 * reverse charge (3.1(d)), eligible ITC (4(A)(3) and 4(A)(5)) and the cash to
 * pay after set-off (6.1). One source for the web page, its PDF/CSV and the API.
 */
class Gstr3bBuilder
{
    public function build(Company $company, Carbon $from, Carbon $to): array
    {
        // 3.1(a) and 3.1(c) come from the same GSTR-1 build the GSTR-1
        // export uses, so the two returns always agree: issued, non-cancelled,
        // non-reverse-charge invoices; 0% lines are nil-rated and go to 3.1(c);
        // credit notes are netted in the period of their own date, by the
        // amounts printed on them. Outward reverse-charge supplies carry no
        // liability for the seller (they are reported in GSTR-1 Table 4B).
        $gstr1 = app(Gstr1Builder::class)->build($company, $from, $to);
        $s = $gstr1['summary'];
        $outward = [
            'taxable' => $s['taxable'],
            'igst'    => $s['igst'],
            'cgst'    => $s['cgst'],
            'sgst'    => $s['sgst'],
        ];
        $outwardNil = $s['nil'];
        // 3.1(b) - zero rated: exports and supplies to SEZ. IGST paid on them
        // (to be refunded) is still a liability for this month's set-off.
        $outwardZero = ['taxable' => $s['zero_taxable'], 'igst' => $s['zero_igst']];
        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['id']);

        // 3.1(d) — INWARD supplies liable to reverse charge: purchase bills on
        // which YOU pay the tax (s.9(3)/(4)). Outward RCM sales ($rcm) belong in
        // GSTR-1 Table 4B, not here; they carry no 3B liability for the seller.
        $billsInPeriod = $company->purchaseBills()
            // The month a bill is booked in: its own date, or the later day it
            // was recorded when it arrived after its month was locked.
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(booked_on, bill_date)'), [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('vendor:id,gstin')
            ->get();
        $rcmBills = $billsInPeriod->where('reverse_charge', true);
        $rcm_inward = [
            'taxable' => round((float) $rcmBills->sum('subtotal'), 2),
            'igst'    => round((float) $rcmBills->sum('total_igst'), 2),
            'cgst'    => round((float) $rcmBills->sum('total_cgst'), 2),
            'sgst'    => round((float) $rcmBills->sum('total_sgst'), 2),
        ];

        // 4(A)(5) — ITC available: GST paid on expenses + cash memos in the period.
        // For sub-₹5cr regular dealers this is the bulk of their ITC claim.
        //
        // Two correctness guards here:
        //  1. whereNull('cash_memo_id') — every cash memo creates a linked
        //     expense row carrying the same GST (see CashMemoController::store).
        //     Cash memos are summed separately below with their exact
        //     CGST/SGST/IGST split, so counting the linked expense too would
        //     double-count that GST. Excluding them fixes the double count.
        //  2. where('itc_eligible', true) — GST on "blocked credit" purchases
        //     (motor vehicles, staff food/catering, personal-use items, etc.)
        //     is NOT claimable under CGST Act §17(5). Users flag those rows so
        //     the helper doesn't overstate ITC.
        //
        // Standalone expenses use the `is_interstate` flag set on each row:
        //   - intra-state (default): GST splits 50/50 into CGST + SGST per §9(1)
        //   - inter-state:           GST goes entirely to IGST per IGST Act §5(1)
        $expensesInPeriod = $company->expenses()
            ->whereNull('cash_memo_id')
            ->whereNull('purchase_bill_id')   // mirrored bills are summed below with their exact heads
            ->where('itc_eligible', true)
            ->whereBetween('entry_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['gst_amount', 'is_interstate']);

        $intraExpenseGst = round((float) $expensesInPeriod->where('is_interstate', false)->sum('gst_amount'), 2);
        $interExpenseGst = round((float) $expensesInPeriod->where('is_interstate', true)->sum('gst_amount'), 2);
        // Intra-state GST paid is split so CGST + SGST add back to exactly what
        // was paid: rounding each half claimed 4.51 + 4.51 on ₹9.01.
        $expenseCgst = round($intraExpenseGst / 2, 2);
        $expenseSgst = round($intraExpenseGst - $expenseCgst, 2);

        $cashMemos = $company->cashMemos()
            ->whereBetween('memo_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();
        // Only ITC-eligible memos feed the claim; all memos still count for the
        // memo tally below. A memo or expense is booked on its own date, so its
        // credit is never past the s.16(4) limit below.
        $eligibleMemos = $cashMemos->where('itc_eligible', true);

        // Purchase bills: GST charged by the vendor, and GST you paid yourself
        // under reverse charge, are both creditable when the bill is eligible.
        // Except s.16(4): credit on an invoice can be taken only in a return
        // for a period ending by 31 October after the invoice's financial
        // year. A bill booked later (found in a drawer, entered after its
        // month was locked) stays in the books but out of the claim.
        [$timeBarredBills, $eligibleBills] = $billsInPeriod->where('itc_eligible', true)
            ->partition(fn ($bill) => $bill->isItcTimeBarred($to));
        $itcTimeBarred = $this->heads($timeBarredBills) + ['count' => $timeBarredBills->count()];
        $itcTimeBarred['total'] = round($itcTimeBarred['igst'] + $itcTimeBarred['cgst'] + $itcTimeBarred['sgst'], 2);

        // Rule 37: credit on supplier invoices not paid within 180 days is
        // reversed (4(B)(2)) and re-claimed once paid (part of 4(A)(5)).
        $rule37 = $this->rule37($company, $from, $to);
        $itcReversed = $rule37['reversed'];
        $itcReclaimed = $rule37['reclaimed'];

        $billHeads = $this->heads($eligibleBills);
        // Table 4(A): all credit available this period.
        $itcAvailable = [
            'igst' => round((float) $eligibleMemos->sum('total_igst') + $billHeads['igst'] + $interExpenseGst + $itcReclaimed['igst'], 2),
            'cgst' => round((float) $eligibleMemos->sum('total_cgst') + $billHeads['cgst'] + $expenseCgst + $itcReclaimed['cgst'], 2),
            'sgst' => round((float) $eligibleMemos->sum('total_sgst') + $billHeads['sgst'] + $expenseSgst + $itcReclaimed['sgst'], 2),
        ];
        // Table 4(C): net credit, 4(A) less 4(B). It can go below nil in a
        // head when a reversal is larger than the month's credit.
        $itc = [];
        foreach (['igst', 'cgst', 'sgst'] as $head) {
            $itc[$head] = round($itcAvailable[$head] - $itcReversed[$head], 2);
        }
        $itc['total'] = round($itc['igst'] + $itc['cgst'] + $itc['sgst'], 2);
        // Of which: credit of the tax paid under reverse charge - GSTR-3B
        // reports it on its own row, 4(A)(3); the rest is 4(A)(5).
        $itcRcm = $this->heads($eligibleBills->where('reverse_charge', true));
        $itcOther = [
            'igst' => round($itcAvailable['igst'] - $itcRcm['igst'], 2),
            'cgst' => round($itcAvailable['cgst'] - $itcRcm['cgst'], 2),
            'sgst' => round($itcAvailable['sgst'] - $itcRcm['sgst'], 2),
        ];

        // Credit taken on purchases from suppliers with no GSTIN: they can never
        // appear in GSTR-2B, so it will not match on the portal. Not removed
        // (the user may have a GSTIN we were not told about) - flagged.
        $noGstinBills = $eligibleBills->where('reverse_charge', false)
            ->filter(fn ($b) => empty($b->vendor?->gstin));
        $noGstinMemos = $eligibleMemos->filter(fn ($m) => empty(trim((string) $m->seller_gstin)));
        $itcNoGstin = round((float) $noGstinBills->sum('total_tax') + (float) $noGstinMemos->sum('total_cgst')
            + (float) $noGstinMemos->sum('total_sgst') + (float) $noGstinMemos->sum('total_igst'), 2);

        // 6.1 — cash payable after set-off in the legal order (s.49(5), Rule
        // 88A): IGST credit first against IGST, then CGST/SGST; CGST and SGST
        // credit against their own head, then IGST; never across each other.
        // Netting each head alone showed ₹18,000 payable on CGST 9k + SGST 9k
        // while ₹18,000 of IGST credit sat unused.
        $liability = [
            'igst' => round($outward['igst'] + $outwardZero['igst'], 2),
            'cgst' => $outward['cgst'],
            'sgst' => $outward['sgst'],
        ];
        // Negative liability: where a period's credit notes exceed its sales in
        // a head, the excess is not lost; it comes off the next periods' tax,
        // as the portal's negative liability statement does. Without it a year
        // of returns paid more than the year's net output tax. 'liability'
        // stays the period's own tax (3.1(a) + (b)); 'payable' is after the
        // carry, and is what the set-off works on.
        $carryIn = $this->negativeCarryIn($company, $from, $to);
        $payable = [];
        $carryOut = [];
        foreach ($liability as $head => $v) {
            $payable[$head] = round($v + $carryIn[$head], 2);
            $carryOut[$head] = min(0.0, $payable[$head]);
        }
        $setOff = $this->settleNet($payable, $itc);
        $netCash = $setOff['cash'];
        $itcCarried = $setOff['carried'];
        // Reverse-charge tax on inward supplies (3.1(d)) must be paid in cash -
        // input credit cannot be used against it (s.49(4) read with the GSTR-3B
        // instructions). Add it on top of the outward set-off.
        foreach (['igst', 'cgst', 'sgst'] as $head) {
            $netCash[$head] = round($netCash[$head] + $rcm_inward[$head], 2);
        }
        $netCash['total'] = round($netCash['igst'] + $netCash['cgst'] + $netCash['sgst'], 2);

        // Since 1 Jan 2022 (s.16(2)(aa)) credit on a supplier's invoice can be
        // taken only once it appears in GSTR-2B. The figures above are from the
        // books; when the 2B for the period has been uploaded, say how much of
        // that credit 2B confirms, so nobody copies a books figure the portal
        // will not accept. The books figures themselves are not changed.
        $itc2b = $this->checkAgainst2b($company, $from, $to, $eligibleBills, $itcRcm, $expenseCgst, $expenseSgst, $interExpenseGst, $eligibleMemos, $payable, $rcm_inward, $itcReversed, $itcReclaimed);

        return [
            'negative_carry_in' => $carryIn,
            'negative_carry_out' => $carryOut,
            'payable' => $payable,
            'itc_2b' => $itc2b,
            'itc_carried' => $itcCarried,
            'itc_used' => $setOff['used'],
            // Which credit paid which tax, from the same set-off as the cash
            // above (it includes a net credit below nil, see settleNet()).
            'set_off_matrix' => $setOff['matrix'],
            'itc_rcm' => $itcRcm,
            'itc_other' => $itcOther,
            // 4(B)(2): Rule 37 reversal; and the credit re-claimed under Rule 37
            // on bills paid late, already part of 4(A)(5) above.
            'itc_reversed' => $itcReversed + ['total' => round($itcReversed['igst'] + $itcReversed['cgst'] + $itcReversed['sgst'], 2)],
            'itc_reclaimed' => $itcReclaimed + ['total' => round($itcReclaimed['igst'] + $itcReclaimed['cgst'] + $itcReclaimed['sgst'], 2)],
            'itc_rule37_bills' => $rule37['bills'],
            // Credit on bills booked in the period but past the s.16(4) limit:
            // not part of 4(A), shown so it is not claimed by hand.
            'itc_time_barred' => $itcTimeBarred,
            'itc_no_gstin' => $itcNoGstin,
            'outward' => $outward,
            'outward_nil' => $outwardNil,
            'outward_zero' => $outwardZero,
            'liability' => $liability,
            'rcm_inward' => $rcm_inward,
            'purchaseBillCount' => $billsInPeriod->count(),
            'itc' => $itc,
            'netCash' => $netCash,
            'invoiceCount' => $invoices->count(),
            'creditNoteCount' => $gstr1['summary']['credit_notes'],
            // Standalone expenses only — cash-memo-linked rows are tallied as
            // cash memos below, so they'd otherwise be counted twice.
            'expenseCount' => $company->expenses()->whereNull('cash_memo_id')->whereNull('purchase_bill_id')->whereBetween('entry_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            'cashMemoCount' => $cashMemos->count(),
        ];
    }

    /**
     * Negative output liability carried into the period from earlier ones,
     * per head (zero or negative). Each period's net output tax is its issued
     * (non reverse-charge) invoices' tax less the tax on credit notes dated in
     * it - the same figures 3.1(a)/(b) use. Whatever a period leaves negative
     * is set against the following periods until used up. Periods follow the
     * return being prepared: months, or quarters for a QRMP return (a quarter
     * nets its own months before anything carries).
     *
     * @return array{igst: float, cgst: float, sgst: float}
     */
    private function negativeCarryIn(Company $company, Carbon $from, Carbon $to): array
    {
        $quarterly = $to->gt($from->copy()->endOfMonth());
        $key = fn ($date) => $quarterly
            ? $date->format('Y') . '-Q' . (int) ceil($date->month / 3)
            : $date->format('Y-m');
        $byMonth = [];
        $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->where('reverse_charge', false)
            ->whereDate('invoice_date', '<', $from->toDateString())
            ->get(['invoice_date', 'total_igst', 'total_cgst', 'total_sgst'])
            ->each(function ($inv) use (&$byMonth, $key) {
                $m = $key($inv->invoice_date);
                $byMonth[$m] ??= ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
                $byMonth[$m]['igst'] += (float) $inv->total_igst;
                $byMonth[$m]['cgst'] += (float) $inv->total_cgst;
                $byMonth[$m]['sgst'] += (float) $inv->total_sgst;
            });
        \App\Models\CreditNote::query()
            ->where('credit_notes.company_id', $company->id)
            ->whereDate('credit_note_date', '<', $from->toDateString())
            ->whereHas('invoice', fn ($q) => $q->where('reverse_charge', false))
            ->get(['credit_note_date', 'total_igst', 'total_cgst', 'total_sgst'])
            ->each(function ($cn) use (&$byMonth, $key) {
                $m = $key(\Illuminate\Support\Carbon::parse($cn->credit_note_date));
                $byMonth[$m] ??= ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
                $byMonth[$m]['igst'] -= (float) $cn->total_igst;
                $byMonth[$m]['cgst'] -= (float) $cn->total_cgst;
                $byMonth[$m]['sgst'] -= (float) $cn->total_sgst;
            });
        ksort($byMonth);

        $carry = ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
        foreach ($byMonth as $net) {
            foreach ($carry as $head => $c) {
                $carry[$head] = min(0.0, round($net[$head] + $c, 2));
            }
        }

        return $carry;
    }

    /**
     * The books' input credit checked against the GSTR-2B uploaded up to the
     * period. Null when no 2B has been uploaded for any month of the period.
     *
     * A supplier invoice is confirmed in the return for the later of the
     * month it is booked in and the first uploaded 2B that shows it. So a
     * bill booked late is confirmed by the 2B of its own month, and a bill
     * held because the supplier filed late is confirmed in the return of the
     * month it reaches 2B. Each bill is confirmed in one return only.
     *
     * - in_2b:      supplier invoices (not reverse charge) confirmed in this
     *               return. For a document whose 2B amounts differ, the lower
     *               of the two per tax head, since the portal allows no more
     *               than 2B shows. Of which held_earlier: bills booked in an
     *               earlier return, held there, that reached this period's 2B.
     * - not_available: in 2B, but 2B marks the credit not available (itcavl
     *               N, s.16(2)(aa) / Rule 36(4)): it cannot be claimed.
     * - not_in_2b:  supplier invoices in the books that no 2B up to this
     *               period shows yet (supplier has not filed): hold until it
     *               appears.
     * - rcm:        tax you paid under reverse charge; not in 2B, claimable.
     * - unchecked:  GST on expenses and cash memos, which carry no supplier
     *               invoice number to look up; check them on the portal.
     * - missing:    in 2B but not recorded in the books (credit not taken).
     * - cash_if_2b_only: cash payable if only in_2b + rcm credit is claimed,
     *               with this period's Rule 37 reversal and re-claim.
     *
     * @return array<string, mixed>|null
     */
    private function checkAgainst2b(Company $company, Carbon $from, Carbon $to, $eligibleBills, array $itcRcm,
        float $expenseCgst, float $expenseSgst, float $interExpenseGst, $eligibleMemos, array $liability, array $rcmInward,
        array $reversed, array $reclaimed): ?array
    {
        $months = [];
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $months[] = $m->format('Y-m');
        }
        $matcher = app(Gstr2bMatcher::class);
        $uploaded = [];
        $missing = ['count' => 0, 'tax' => 0.0];
        foreach ($months as $period) {
            $match = $matcher->match($company, $period);
            if (! $match['summary']['uploaded']) {
                continue;
            }
            $uploaded[] = $period;
            $missing['count'] += count($match['missing']);
            $missing['tax'] = round($missing['tax'] + $match['summary']['tax']['missing'], 2);
        }
        if (! $uploaded) {
            return null;
        }

        // Every invoice in the 2Bs uploaded up to the end of this period, by
        // the matcher's key, earliest 2B first. A bill can be up to two years
        // old when it turns up (the matcher's own window).
        $since = $from->copy()->startOfMonth()->subMonths(24);
        $index = [];
        \App\Models\Gstr2bEntry::where('company_id', $company->id)->where('kind', 'invoice')
            ->whereBetween('period', [$since->format('Y-m'), end($months)])
            ->orderBy('period')->orderBy('id')
            ->get()
            ->each(function ($entry) use (&$index, $matcher) {
                $index[$matcher->key((string) $entry->supplier_gstin, (string) $entry->document_number)][] = $entry;
            });
        // The first 2B showing this bill: none before the bill's own month,
        // since suppliers reuse invoice numbers from one year to the next.
        $first2b = function ($bill) use ($index, $matcher) {
            if (! $bill->vendor?->gstin) {
                return null;
            }
            foreach ($index[$matcher->key($bill->vendor->gstin, $bill->bill_number)] ?? [] as $entry) {
                if ($entry->period >= $bill->bill_date->format('Y-m')) {
                    return $entry;
                }
            }

            return null;
        };

        $zero = ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
        $in2b = $zero;
        $heldEarlier = $zero;
        $notAvailable = $zero;
        $notIn2b = $zero;
        $add = function (array &$sum, $bill, $entry = null): void {
            foreach (['igst', 'cgst', 'sgst'] as $head) {
                $books = (float) $bill->{'total_' . $head};
                $sum[$head] = round($sum[$head] + ($entry ? min($books, (float) $entry->{$head}) : $books), 2);
            }
        };

        // Bills booked in this period: confirmed by any 2B up to it.
        foreach ($eligibleBills->where('reverse_charge', false) as $bill) {
            $entry = $first2b($bill);
            if (! $entry) {
                $add($notIn2b, $bill);
            } elseif (! $entry->itc_available) {
                $add($notAvailable, $bill);
            } else {
                $add($in2b, $bill, $entry);
            }
        }

        // Bills booked in an earlier return and held there, which first show
        // up in this period's 2B: their credit is confirmed now. Only while
        // within the s.16(4) limit for this return; after it, never.
        $earlier = $company->purchaseBills()->with('vendor:id,gstin')
            ->where('itc_eligible', true)->where('reverse_charge', false)
            ->whereDate('bill_date', '>=', $since->toDateString())
            ->where(\Illuminate\Support\Facades\DB::raw('COALESCE(booked_on, bill_date)'), '<', $from->copy()->startOfDay())
            ->get();
        foreach ($earlier as $bill) {
            $entry = $first2b($bill);
            if (! $entry || ! in_array($entry->period, $months, true) || $bill->isItcTimeBarred($to)) {
                continue;
            }
            if (! $entry->itc_available) {
                $add($notAvailable, $bill);
            } else {
                $add($in2b, $bill, $entry);
                $add($heldEarlier, $bill, $entry);
            }
        }

        $unchecked = [
            'igst' => round((float) $eligibleMemos->sum('total_igst') + $interExpenseGst, 2),
            'cgst' => round((float) $eligibleMemos->sum('total_cgst') + $expenseCgst, 2),
            'sgst' => round((float) $eligibleMemos->sum('total_sgst') + $expenseSgst, 2),
        ];
        $sum = fn (array $a) => round($a['igst'] + $a['cgst'] + $a['sgst'], 2);

        $confirmed = [];
        $confirmedNet = [];
        foreach (['igst', 'cgst', 'sgst'] as $head) {
            $confirmed[$head] = round($in2b[$head] + (float) $itcRcm[$head], 2);
            $confirmedNet[$head] = round($confirmed[$head] + $reclaimed[$head] - $reversed[$head], 2);
        }
        $settled = $this->settleNet($liability, $confirmedNet);
        $cash = $settled['cash'];
        foreach (['igst', 'cgst', 'sgst'] as $head) {
            $cash[$head] = round($cash[$head] + (float) $rcmInward[$head], 2);
        }
        $cash['total'] = $sum($cash);

        return [
            'months' => $months,
            'uploaded' => $uploaded,
            'not_uploaded' => array_values(array_diff($months, $uploaded)),
            'in_2b' => $in2b + ['total' => $sum($in2b)],
            'held_earlier' => $heldEarlier + ['total' => $sum($heldEarlier)],
            'not_available' => $notAvailable + ['total' => $sum($notAvailable)],
            'not_in_2b' => $notIn2b + ['total' => $sum($notIn2b)],
            'rcm' => $itcRcm + ['total' => $sum($itcRcm)],
            'unchecked' => $unchecked + ['total' => $sum($unchecked)],
            'missing' => $missing,
            'confirmed' => $confirmed + ['total' => $sum($confirmed)],
            'cash_if_2b_only' => $cash,
        ];
    }

    /** IGST, CGST and SGST summed over purchase bills, to the paisa. */
    private function heads($bills): array
    {
        return [
            'igst' => round((float) $bills->sum('total_igst'), 2),
            'cgst' => round((float) $bills->sum('total_cgst'), 2),
            'sgst' => round((float) $bills->sum('total_sgst'), 2),
        ];
    }

    /**
     * Rule 37 read with the second proviso to s.16(2): credit taken on a
     * supplier's invoice that is not paid within 180 days of its date is
     * reversed, in proportion to the part unpaid (credit x unpaid / bill
     * total), in the return for the period the 181st day falls in (Table
     * 4(B)(2)). When the supplier is paid later, the credit on what is paid
     * is taken again in the return for the period of payment (4(A)(5)); the
     * s.16(4) time limit does not apply to that. Reverse-charge bills,
     * blocked credit and time-barred bills carry no credit to reverse.
     *
     * Re-claims are worked on the running total paid after the 180 days, so
     * once the bill is fully paid they add up to the reversal to the paisa.
     *
     * @return array{reversed: array{igst: float, cgst: float, sgst: float},
     *               reclaimed: array{igst: float, cgst: float, sgst: float}, bills: int}
     */
    public function rule37(Company $company, Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $zero = ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
        $reversed = $zero;
        $reclaimed = $zero;
        $count = 0;

        // Bills whose 181st day (or later booking) falls in the period, or
        // paid in the period. A bill dated within 181 days of the period's
        // end cannot have reached the limit.
        $bills = $company->purchaseBills()
            ->where('itc_eligible', true)->where('reverse_charge', false)
            ->whereDate('bill_date', '<=', $end->copy()->subDays(181)->toDateString())
            ->where(fn ($q) => $q
                ->whereDate('bill_date', '>=', $start->copy()->subDays(181)->toDateString())
                ->orWhere(fn ($b) => $b->whereDate('booked_on', '>=', $start->toDateString())->whereDate('booked_on', '<=', $end->toDateString()))
                ->orWhereHas('payments', fn ($p) => $p->whereDate('paid_on', '>=', $start->toDateString())->whereDate('paid_on', '<=', $end->toDateString())))
            ->with('payments')
            ->get();

        foreach ($bills as $bill) {
            if (! $bill->rule37Applies()) {
                continue;
            }
            $total = (float) $bill->grand_total;
            $payBy = $bill->rule37PayBy()->toDateString();
            $reverseOn = $bill->rule37ReversalOn();
            $paidInTime = (float) $bill->payments->filter(fn ($p) => $p->paid_on->toDateString() <= $payBy)->sum('amount');
            $unpaid = round($total - $paidInTime, 2);
            if ($unpaid <= 0) {
                continue;
            }
            // Credit on a part of the bill, per head.
            $share = fn (float $amount) => [
                'igst' => round((float) $bill->total_igst * $amount / $total, 2),
                'cgst' => round((float) $bill->total_cgst * $amount / $total, 2),
                'sgst' => round((float) $bill->total_sgst * $amount / $total, 2),
            ];

            if ($reverseOn->between($start, $end)) {
                foreach ($share($unpaid) as $head => $v) {
                    $reversed[$head] = round($reversed[$head] + $v, 2);
                }
                $count++;
            }

            // Paid after the 180 days: taken again when paid, never before the
            // reversal itself.
            $paidLate = 0.0;
            foreach ($bill->payments as $payment) {
                if ($payment->paid_on->toDateString() <= $payBy) {
                    continue;
                }
                $before = $share($paidLate);
                $paidLate = round(min($unpaid, $paidLate + (float) $payment->amount), 2);
                $on = $payment->paid_on->gt($reverseOn) ? $payment->paid_on : $reverseOn;
                if ($on->between($start, $end)) {
                    foreach ($share($paidLate) as $head => $v) {
                        $reclaimed[$head] = round($reclaimed[$head] + $v - $before[$head], 2);
                    }
                }
            }
        }

        return ['reversed' => $reversed, 'reclaimed' => $reclaimed, 'bills' => $count];
    }

    /**
     * Rule 88A set-off on the net credit of Table 4(C). Where reversals take
     * a head's net credit below nil, the shortfall is tax to pay on that head
     * (the portal adds a negative 4(C) to the liability in Table 6.1). A
     * negative liability carried from earlier periods keeps its own carry
     * and does not absorb it.
     */
    private function settleNet(array $payable, array $netItc): array
    {
        $due = [];
        $credit = [];
        foreach (['igst', 'cgst', 'sgst'] as $head) {
            $credit[$head] = max(0.0, (float) $netItc[$head]);
            $due[$head] = round(max(0.0, (float) $payable[$head]) + max(0.0, -(float) $netItc[$head]), 2);
        }

        return app(GstSetOff::class)->settle($due, $credit);
    }
}
