<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceListResource;
use App\Services\DashboardSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Home-screen summary for the mobile app: headline KPIs, recent invoices and
 * everything the web dashboard shows (charts, receivables, GST calendar,
 * action items). The figures come from App\Services\DashboardSummary, the
 * same service the web dashboard uses, so the two never disagree. Where the
 * web links to a page, the app gets a target: {screen, params}.
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
            ->whereDate('invoice_date', '>=', $fyStart->toDateString())
            ->sum('grand_total');

        $revenueThisMonth = (float) $company->invoices()
            ->whereIn('status', $issued)
            ->whereYear('invoice_date', $now->year)
            ->whereMonth('invoice_date', $now->month)
            ->sum('grand_total');

        $summary = DashboardSummary::for($company);
        $chart = $summary->chartSeries();
        $thisMonthSpend = end($chart['purchases']['monthly']);
        $date = fn ($d) => $d?->toDateString();
        $gst = $summary->gstCalendar();

        $recent = $company->invoices()
            ->with('customer:id,name')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'company' => ['id' => $company->id, 'name' => $company->name, 'gstin' => $company->gstin],
                'kpis' => [
                    'outstanding' => round($outstanding, 2),
                    'overdue' => round($overdue, 2),
                    'revenue_fy' => round($revenueFy, 2),
                    'revenue_this_month' => round($revenueThisMonth, 2),
                    'collected_this_month' => round((float) $summary->stats()['received_this_month'], 2),
                    'expenses_this_month' => round((float) ($thisMonthSpend['a'] ?? 0), 2),
                    'today_issued' => $summary->todayIssued(),
                    'today_collected' => round($summary->todayCollected(), 2),
                ],
                'counts' => [
                    'invoices' => $company->invoices()->count(),
                    'drafts' => $company->invoices()->where('status', 'draft')->count(),
                    'customers' => $company->customers()->count(),
                    'products' => $company->products()->where('is_active', true)->count(),
                    'quotations_awaiting' => $company->quotations()->where('status', 'sent')->count(),
                ],
                'status_breakdown' => $summary->statusBreakdown(),
                'sales' => $this->series($chart['sales'], 'billed', 'collected'),
                'purchases' => $this->series($chart['purchases'], 'spend', 'gst'),
                'spend_by_category' => array_map(fn (array $s) => [
                    'key' => $s['key'],
                    'label' => $s['label'],
                    'amount' => round($s['amount'], 2),
                    'color' => $s['color'],
                ], $summary->spendByCategory()),
                'aging' => $summary->aging(),
                'top_debtors' => $summary->whoOwes()->map(fn ($c) => [
                    'customer_id' => $c->id,
                    'name' => $c->name,
                    'amount' => round((float) $c->owed, 2),
                    'oldest_due_date' => $c->oldest_due ? substr((string) $c->oldest_due, 0, 10) : null,
                    'overdue_days' => (int) $c->overdue_days,
                ])->values()->all(),
                'gst' => $gst ? [
                    'label' => $gst['label'],
                    'month_query' => $gst['query'],
                    'gstr1_due' => $date($gst['gstr1_due']),
                    'gstr3b_due' => $date($gst['gstr3b_due']),
                    'gstr2b_uploaded' => (bool) $gst['gstr2b_uploaded'],
                    'cash' => round($gst['cash'], 2),
                    'invoices' => $gst['invoices'],
                ] : null,
                'action_items' => $this->actionItems($summary->actionItems()),
                'recent_invoices' => InvoiceListResource::collection($recent),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION); // amounts stay floats (3500.0) for the app
    }

    /** Chart bars with the web's a/b renamed for the app. */
    private function series(array $spans, string $a, string $b): array
    {
        return array_map(fn (array $bars) => array_map(fn (array $bar) => [
            'label' => $bar['label'],
            'full' => $bar['full'],
            'from' => $bar['from'],
            'to' => $bar['to'],
            $a => round($bar['a'], 2),
            $b => round($bar['b'], 2),
        ], $bars), $spans);
    }

    /**
     * The web dashboard's action items, with an app target in place of the
     * web link. An item whose page the app does not have (bank matching) is
     * left out rather than shown with nowhere to go.
     */
    private function actionItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $target = $this->target($item['link']['route'], $item['link']['params']);
            if ($target === null) {
                continue;
            }
            $out[] = [
                'tone' => in_array($item['tone'], ['danger', 'accent'], true) ? $item['tone'] : 'info',
                'icon' => $item['icon'],
                'title' => $item['title'],
                'sub' => $item['sub'],
                'cta' => $item['cta'],
                'target' => $target,
            ];
        }

        return $out;
    }

    /** @return array{screen: string, params: object}|null */
    private function target(string $route, array $params): ?array
    {
        $to = fn (string $screen, array $p = []) => ['screen' => $screen, 'params' => (object) $p];

        return match ($route) {
            // The aging report: every unpaid issued invoice.
            'finance.aging' => $to('invoices', ['status' => 'outstanding']),
            'invoices.index' => $to('invoices', $params),
            'reports.show' => ($params['report'] ?? null) === 'stock-summary' ? $to('items', ['stock' => 'low']) : null,
            'finance.gstr1' => $to('gstr1', $params),
            'finance.gstr2b' => $to('gstr2b', $params),
            'finance.gstr3b' => $to('gstr3b', $params),
            'finance.expenses' => $to('expenses', $params),
            default => null,
        };
    }
}
