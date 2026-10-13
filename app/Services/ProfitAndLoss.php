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

        $revenue = [
            'taxable' => round((float) (clone $invoices)->sum('subtotal') - $cnTaxable, 2),
            'gst_collected' => round((float) (clone $invoices)->sum('total_tax') - $cnTax, 2),
            'grand_total' => round((float) (clone $invoices)->sum('grand_total') - (float) (clone $notes)->sum('amount'), 2),
            'received' => round((float) (clone $invoices)->sum('paid_amount'), 2),
            'outstanding' => round((float) $company->invoices()->whereIn('status', ['final', 'partially_paid'])->sum('balance'), 2),
        ];

        $expenses = $company->expenses()->whereBetween('entry_date', [$from, $to]);
        $gstClaimable = (float) (clone $expenses)->where('itc_eligible', true)->sum('gst_amount');
        $gstBlocked = (float) (clone $expenses)->where('itc_eligible', false)->sum('gst_amount');

        // Cash out: expenses entered directly (and cash memos) are paid when
        // entered; a purchase invoice is paid when its payments say so.
        $direct = (clone $expenses)->whereNull('purchase_bill_id');
        $billPayments = (float) \App\Models\PurchasePayment::where('company_id', $company->id)
            ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $expense = [
            'taxable' => round((float) (clone $expenses)->sum('amount') + $gstBlocked, 2),
            'gst_itc' => round($gstClaimable, 2),
            'cash_out' => round((float) (clone $direct)->sum('amount') + (float) (clone $direct)->sum('gst_amount') + $billPayments, 2),
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
            'cashInHand' => round($revenue['received'] - $expense['cash_out'], 2),
            'gstPayable' => round($revenue['gst_collected'] + $rcmTax - $expense['gst_itc'], 2),
            'rcmTax' => round($rcmTax, 2),
        ];
    }
}
