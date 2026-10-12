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
            ->whereBetween('bill_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
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

        $intraExpenseGst = (float) $expensesInPeriod->where('is_interstate', false)->sum('gst_amount');
        $interExpenseGst = (float) $expensesInPeriod->where('is_interstate', true)->sum('gst_amount');

        $cashMemos = $company->cashMemos()
            ->whereBetween('memo_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();
        // Only ITC-eligible memos feed the claim; all memos still count for the
        // memo tally below.
        $eligibleMemos = $cashMemos->where('itc_eligible', true);

        // Purchase bills: GST charged by the vendor, and GST you paid yourself
        // under reverse charge, are both creditable when the bill is eligible.
        $eligibleBills = $billsInPeriod->where('itc_eligible', true);

        $itc = [
            'igst' => round((float) $eligibleMemos->sum('total_igst') + (float) $eligibleBills->sum('total_igst') + $interExpenseGst, 2),
            'cgst' => round((float) $eligibleMemos->sum('total_cgst') + (float) $eligibleBills->sum('total_cgst') + ($intraExpenseGst / 2), 2),
            'sgst' => round((float) $eligibleMemos->sum('total_sgst') + (float) $eligibleBills->sum('total_sgst') + ($intraExpenseGst / 2), 2),
        ];
        $itc['total'] = round($itc['igst'] + $itc['cgst'] + $itc['sgst'], 2);
        // Of which: credit of the tax paid under reverse charge - GSTR-3B
        // reports it on its own row, 4(A)(3); the rest is 4(A)(5).
        $eligibleRcm = $eligibleBills->where('reverse_charge', true);
        $itcRcm = [
            'igst' => round((float) $eligibleRcm->sum('total_igst'), 2),
            'cgst' => round((float) $eligibleRcm->sum('total_cgst'), 2),
            'sgst' => round((float) $eligibleRcm->sum('total_sgst'), 2),
        ];
        $itcOther = [
            'igst' => round($itc['igst'] - $itcRcm['igst'], 2),
            'cgst' => round($itc['cgst'] - $itcRcm['cgst'], 2),
            'sgst' => round($itc['sgst'] - $itcRcm['sgst'], 2),
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
        $setOff = app(GstSetOff::class)->settle($liability, $itc);
        $netCash = $setOff['cash'];
        $itcCarried = $setOff['carried'];
        // Reverse-charge tax on inward supplies (3.1(d)) must be paid in cash -
        // input credit cannot be used against it (s.49(4) read with the GSTR-3B
        // instructions). Add it on top of the outward set-off.
        foreach (['igst', 'cgst', 'sgst'] as $head) {
            $netCash[$head] = round($netCash[$head] + $rcm_inward[$head], 2);
        }
        $netCash['total'] = round($netCash['igst'] + $netCash['cgst'] + $netCash['sgst'], 2);

        return [
            'itc_carried' => $itcCarried,
            'itc_used' => $setOff['used'],
            'itc_rcm' => $itcRcm,
            'itc_other' => $itcOther,
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
            'expenseCount' => $company->expenses()->whereNull('cash_memo_id')->whereBetween('entry_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            'cashMemoCount' => $cashMemos->count(),
        ];
        }
}
