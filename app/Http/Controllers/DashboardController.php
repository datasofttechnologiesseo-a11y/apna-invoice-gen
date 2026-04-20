<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $currency = $company->default_currency;

        $invoices = $company->invoices();

        $stats = [
            'total' => (clone $invoices)->count(),
            'drafts' => (clone $invoices)->where('status', 'draft')->count(),
            'outstanding' => (clone $invoices)->where('currency', $currency)
                ->whereIn('status', ['final', 'partially_paid'])->sum('balance'),
            'paid_this_month' => (clone $invoices)->where('currency', $currency)
                ->whereBetween('invoice_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('paid_amount'),
        ];

        $recent = (clone $invoices)->with('customer')->latest('id')->take(10)->get();

        // This month's P&L snapshot
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $monthIncome = (float) (clone $invoices)->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$start, $end])->sum('subtotal');
        $monthExpense = (float) $company->expenses()->whereBetween('entry_date', [$start, $end])->sum('amount');
        $pnl = [
            'income' => $monthIncome,
            'expense' => $monthExpense,
            'profit' => $monthIncome - $monthExpense,
        ];

        $setup = [
            'business' => $company->isBusinessComplete(),
            'customer' => $company->customers()->exists(),
            'first_invoice' => (clone $invoices)->exists(),
        ];
        $setupComplete = ! in_array(false, $setup, true);
        $setupProgress = round((array_sum($setup) / count($setup)) * 100);

        return view('dashboard', compact('stats', 'recent', 'currency', 'company', 'setup', 'setupComplete', 'setupProgress', 'pnl'));
    }
}
