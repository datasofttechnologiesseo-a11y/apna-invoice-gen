<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditNote;
use Illuminate\Support\Carbon;

/**
 * The P&L headline figures for a period - one computation for the web
 * dashboard and the mobile API, which had drifted apart (the API counted
 * credit notes as sales and blocked credit as claimable).
 *
 *  - Revenue: issued invoices in the period less credit notes dated in it (s.34).
 *  - Expense: every expense row; GST that cannot be claimed (s.17(5)) is cost.
 *  - Cash out: money actually paid - a purchase invoice counts by its
 *    payments in the period, not in full on the day it was entered.
 *  - Net GST: output tax plus reverse-charge tax on purchases, less input
 *    credit. Same direction as GSTR-3B; the return itself applies set-off.
 */
class ProfitAndLoss
{
    public function summary(Company $company, Carbon $from, Carbon $to): array
    {
        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$from, $to]);

        $notes = CreditNote::where('company_id', $company->id)
            ->whereBetween('credit_note_date', [$from, $to]);
        $cnTaxable = (float) (clone $notes)->sum('taxable_value');
        $cnTax = (float) (clone $notes)->sum('total_cgst') + (float) (clone $notes)->sum('total_sgst') + (float) (clone $notes)->sum('total_igst');

        $receipts = \App\Models\Payment::where('company_id', $company->id)
            ->whereDate('received_at', '>=', $from->toDateString())
            ->whereDate('received_at', '<=', $to->toDateString())
            ->get(['amount', 'tds_amount']);
        // TDS the customer deducted is credit with the income-tax department,
        // not money in the bank.
        $receivedInBank = round((float) $receipts->sum('amount') - (float) $receipts->sum('tds_amount'), 2);

        $revenue = [
            'taxable' => round((float) (clone $invoices)->sum('subtotal') - $cnTaxable, 2),
            'gst_collected' => round((float) (clone $invoices)->sum('total_tax') - $cnTax, 2),
            'grand_total' => round((float) (clone $invoices)->sum('grand_total') - (float) (clone $notes)->sum('amount'), 2),
            // Money received in the period, by the date it came in (not the
            // payments of invoices dated in the period, which mixed bases with
            // cash out). Gross of TDS, as the receipts register shows it.
            'received' => round((float) $receipts->sum('amount'), 2),
            'outstanding' => round((float) $company->invoices()->whereIn('status', ['final', 'partially_paid'])->sum('balance'), 2),
        ];

        $expenses = $company->expenses()->whereBetween('entry_date', [$from, $to]);
        $gstClaimable = (float) (clone $expenses)->where('itc_eligible', true)->sum('gst_amount');
        $gstBlocked = (float) (clone $expenses)->where('itc_eligible', false)->sum('gst_amount');

        // Cash out: expenses entered directly are paid when entered; a cash
        // memo is paid at its total (round-off included); a purchase invoice
        // is paid when its payments say so. Date bounds by whole days, so the
        // last day of the period counts on every database.
        $direct = (clone $expenses)->whereNull('purchase_bill_id')->whereNull('cash_memo_id');
        $memosPaid = (float) $company->cashMemos()
            ->whereDate('memo_date', '>=', $from->toDateString())
            ->whereDate('memo_date', '<=', $to->toDateString())
            ->sum('grand_total');
        $billPayments = (float) \App\Models\PurchasePayment::where('company_id', $company->id)
            ->whereDate('paid_on', '>=', $from->toDateString())
            ->whereDate('paid_on', '<=', $to->toDateString())
            ->sum('amount');

        $expense = [
            'taxable' => round((float) (clone $expenses)->sum('amount') + $gstBlocked, 2),
            'gst_itc' => round($gstClaimable, 2),
            'cash_out' => round((float) (clone $direct)->sum('amount') + (float) (clone $direct)->sum('gst_amount') + $memosPaid + $billPayments, 2),
        ];

        // Tax you pay yourself under reverse charge on purchases (3.1(d)).
        $rcm = $company->purchaseBills()->where('reverse_charge', true)->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(booked_on, bill_date)'), [$from, $to]);
        $rcmTax = (float) (clone $rcm)->sum('total_igst') + (float) (clone $rcm)->sum('total_cgst') + (float) (clone $rcm)->sum('total_sgst');

        $netProfit = $revenue['taxable'] - $expense['taxable'];

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'netProfit' => round($netProfit, 2),
            'margin' => $revenue['taxable'] > 0 ? round(($netProfit / $revenue['taxable']) * 100, 2) : 0,
            'cashInHand' => round($receivedInBank - $expense['cash_out'], 2),
            'gstPayable' => round($revenue['gst_collected'] + $rcmTax - $expense['gst_itc'], 2),
            'rcmTax' => round($rcmTax, 2),
        ];
    }
}
