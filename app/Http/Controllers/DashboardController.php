<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $user->ensureCompany();

        $invoices = $user->invoices();

        $stats = [
            'total' => (clone $invoices)->count(),
            'drafts' => (clone $invoices)->where('status', 'draft')->count(),
            'outstanding' => (clone $invoices)->whereIn('status', ['final', 'partially_paid'])->sum('balance'),
            'paid_this_month' => (clone $invoices)->whereBetween('invoice_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('paid_amount'),
        ];

        $recent = (clone $invoices)->with('customer')->latest('id')->take(10)->get();

        return view('dashboard', compact('stats', 'recent'));
    }
}
