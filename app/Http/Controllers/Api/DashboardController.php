<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Home-screen summary for the mobile app: headline KPIs + recent invoices.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();
        $now = now();
        $fyStart = ($now->month >= 4 ? $now->copy()->month(4) : $now->copy()->subYear()->month(4))
            ->startOfMonth()->startOfDay();

        $issued = ['final', 'partially_paid', 'paid'];

        $outstanding = (float) $company->invoices()
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->sum('balance');

        $overdue = (float) $company->invoices()
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $now->toDateString())
            ->sum('balance');

        $revenueFy = (float) $company->invoices()
            ->whereIn('status', $issued)
            ->where('invoice_date', '>=', $fyStart->toDateString())
            ->sum('grand_total');

        $revenueThisMonth = (float) $company->invoices()
            ->whereIn('status', $issued)
            ->whereYear('invoice_date', $now->year)
            ->whereMonth('invoice_date', $now->month)
            ->sum('grand_total');

        $recent = $company->invoices()
            ->with('customer:id,name')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'company' => ['id' => $company->id, 'name' => $company->name],
                'kpis' => [
                    'outstanding' => round($outstanding, 2),
                    'overdue' => round($overdue, 2),
                    'revenue_fy' => round($revenueFy, 2),
                    'revenue_this_month' => round($revenueThisMonth, 2),
                ],
                'counts' => [
                    'invoices' => $company->invoices()->count(),
                    'drafts' => $company->invoices()->where('status', 'draft')->count(),
                    'customers' => $company->customers()->count(),
                    'products' => $company->products()->where('is_active', true)->count(),
                    'quotations_awaiting' => $company->quotations()->where('status', 'sent')->count(),
                ],
                'recent_invoices' => InvoiceListResource::collection($recent),
            ],
        ]);
    }
}
