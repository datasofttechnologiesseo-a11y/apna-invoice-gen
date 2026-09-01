<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FinanceController as WebFinance;
use App\Http\Controllers\InvoiceController as WebInvoice;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * JSON finance reports for the mobile app — P&L, receivables aging, GSTR-3B and
 * a GSTR-1 summary. The on-screen figures are computed here (mirroring the web
 * FinanceController exactly); the heavy CSV/PDF file exports are delegated to
 * the existing web controllers so there's a single source of truth for the file
 * formats a CA receives.
 */
class FinanceController extends Controller
{
    // ---- On-screen JSON reports -------------------------------------------

    /** Profit & Loss summary for a period. Mirrors web FinanceController::index. */
    public function pnl(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();
        [$start, $end, $label, $key] = $this->resolvePeriod($request);

        $invoicesInPeriod = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$start, $end]);

        $revenue = [
            'taxable' => (float) (clone $invoicesInPeriod)->sum('subtotal'),
            'gst_collected' => (float) (clone $invoicesInPeriod)->sum('total_tax'),
            'grand_total' => (float) (clone $invoicesInPeriod)->sum('grand_total'),
            'received' => (float) (clone $invoicesInPeriod)->sum('paid_amount'),
            'outstanding' => (float) $company->invoices()
                ->whereIn('status', ['final', 'partially_paid'])
                ->sum('balance'),
        ];

        $expensesInPeriod = $company->expenses()->whereBetween('entry_date', [$start, $end]);
        $expense = [
            'taxable' => (float) (clone $expensesInPeriod)->sum('amount'),
            'gst_itc' => (float) (clone $expensesInPeriod)->sum('gst_amount'),
        ];
        $expense['cash_out'] = $expense['taxable'] + $expense['gst_itc'];

        $netProfit = $revenue['taxable'] - $expense['taxable'];
        $margin = $revenue['taxable'] > 0 ? ($netProfit / $revenue['taxable']) * 100 : 0;
        $cashInHand = $revenue['received'] - $expense['cash_out'];
        $gstPayable = $revenue['gst_collected'] - $expense['gst_itc'];

        $byCategory = (clone $expensesInPeriod)
            ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($expense) {
                $cfg = config('expense_categories.' . $row->category, ['label' => ucfirst($row->category), 'color' => '#6b7280']);
                return [
                    'category' => $row->category,
                    'label' => $cfg['label'],
                    'color' => $cfg['color'],
                    'total' => (float) $row->total,
                    'count' => (int) $row->count,
                    'share' => $expense['taxable'] > 0 ? (float) $row->total / $expense['taxable'] * 100 : 0,
                ];
            })->values();

        return response()->json([
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => $label, 'key' => $key],
            'revenue' => $revenue,
            'expense' => $expense,
            'net_profit' => round($netProfit, 2),
            'margin' => round($margin, 2),
            'cash_in_hand' => round($cashInHand, 2),
            'gst_payable' => round($gstPayable, 2),
            'by_category' => $byCategory,
            'trend' => $this->monthlyTrend($company, $end),
        ]);
    }

    /** Receivables aging. Mirrors web FinanceController::aging. */
    public function aging(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();
        $today = now()->startOfDay();

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        $rows = $this->bucketAging($invoices, $today);

        $byCustomer = $rows->groupBy('customer_id')->map(function ($group) {
            $first = $group->first();
            return [
                'name' => $first['customer_name'],
                'gstin' => $first['customer_gstin'],
                'invoice_count' => $group->count(),
                'oldest_days' => $group->max('days_overdue'),
                'total' => round($group->sum('balance'), 2),
                'current' => round($group->where('bucket', 'current')->sum('balance'), 2),
                'b30_60' => round($group->where('bucket', '30-60')->sum('balance'), 2),
                'b60_90' => round($group->where('bucket', '60-90')->sum('balance'), 2),
                'b90_plus' => round($group->where('bucket', '90+')->sum('balance'), 2),
            ];
        })->sortByDesc('total')->values();

        $summary = [
            'customers' => $byCustomer->count(),
            'invoices' => $rows->count(),
            'total' => round($rows->sum('balance'), 2),
            'current' => round($rows->where('bucket', 'current')->sum('balance'), 2),
            'b30_60' => round($rows->where('bucket', '30-60')->sum('balance'), 2),
            'b60_90' => round($rows->where('bucket', '60-90')->sum('balance'), 2),
            'b90_plus' => round($rows->where('bucket', '90+')->sum('balance'), 2),
        ];

        return response()->json([
            'as_on' => $today->toDateString(),
            'summary' => $summary,
            'by_customer' => $byCustomer,
        ]);
    }

    /** GSTR-3B summary. Mirrors web FinanceController::gstr3b. */
    public function gstr3b(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();
        [$start, $end, $label] = $this->resolveGstr3bPeriod($request);

        $data = $this->buildGstr3b($company, $start, $end);

        return response()->json(array_merge($data, [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => $label, 'month' => $start->format('Y-m')],
        ]));
    }

    /** GSTR-1 outward-supply summary (B2B vs B2C). The full row-level CSV is the export. */
    public function gstr1(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('customer:id,name,gstin')
            ->get();

        $summarise = function ($group) {
            return [
                'count' => $group->count(),
                'taxable' => round((float) $group->sum('subtotal'), 2),
                'cgst' => round((float) $group->sum('total_cgst'), 2),
                'sgst' => round((float) $group->sum('total_sgst'), 2),
                'igst' => round((float) $group->sum('total_igst'), 2),
                'total' => round((float) $group->sum('grand_total'), 2),
            ];
        };

        $b2b = $invoices->filter(fn ($i) => ! empty($i->customer?->gstin));
        $b2c = $invoices->reject(fn ($i) => ! empty($i->customer?->gstin));

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'b2b' => $summarise($b2b),
            'b2c' => $summarise($b2c),
            'all' => $summarise($invoices),
        ]);
    }

    // ---- File exports (delegated to the web controllers) ------------------

    public function agingCsv(Request $request)
    {
        return app(WebFinance::class)->agingCsv($request);
    }

    public function agingPdf(Request $request)
    {
        return app(WebFinance::class)->agingPdf($request);
    }

    public function gstr3bCsv(Request $request)
    {
        return app(WebFinance::class)->gstr3bCsv($request);
    }

    public function gstr3bPdf(Request $request)
    {
        return app(WebFinance::class)->gstr3bPdf($request);
    }

    public function expensesCsv(Request $request)
    {
        return app(WebFinance::class)->expensesCsv($request);
    }

    public function expensesPdf(Request $request)
    {
        return app(WebFinance::class)->expensesPdf($request);
    }

    public function gstr1Csv(Request $request)
    {
        return app(WebInvoice::class)->gstr1Csv($request);
    }

    // ---- Shared compute helpers (copied from the web controller) ----------

    private function bucketAging($invoices, Carbon $today)
    {
        return $invoices->map(function ($inv) use ($today) {
            $base = $inv->due_date ?? $inv->invoice_date ?? $today;
            $days = (int) max(0, $today->diffInDays($base, false) * -1);
            if ($days <= 30) {
                $bucket = 'current';
            } elseif ($days <= 60) {
                $bucket = '30-60';
            } elseif ($days <= 90) {
                $bucket = '60-90';
            } else {
                $bucket = '90+';
            }
            return [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date,
                'due_date' => $inv->due_date,
                'customer_id' => $inv->customer_id,
                'customer_name' => $inv->customer?->name ?? '—',
                'customer_gstin' => $inv->customer?->gstin,
                'grand_total' => (float) $inv->grand_total,
                'paid_amount' => (float) $inv->paid_amount,
                'balance' => (float) $inv->balance,
                'days_overdue' => $days,
                'bucket' => $bucket,
            ];
        });
    }

    private function buildGstr3b(Company $company, Carbon $from, Carbon $to): array
    {
        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $regular = $invoices->where('reverse_charge', false);
        $rcm = $invoices->where('reverse_charge', true);

        $outward = [
            'taxable' => round((float) $regular->sum('subtotal'), 2),
            'igst' => round((float) $regular->sum('total_igst'), 2),
            'cgst' => round((float) $regular->sum('total_cgst'), 2),
            'sgst' => round((float) $regular->sum('total_sgst'), 2),
        ];

        $rcm_outward = [
            'taxable' => round((float) $rcm->sum('subtotal'), 2),
            'igst' => 0.0,
            'cgst' => 0.0,
            'sgst' => 0.0,
        ];

        $expensesInPeriod = $company->expenses()
            ->whereBetween('entry_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['gst_amount', 'is_interstate']);

        $intraExpenseGst = (float) $expensesInPeriod->where('is_interstate', false)->sum('gst_amount');
        $interExpenseGst = (float) $expensesInPeriod->where('is_interstate', true)->sum('gst_amount');

        $cashMemos = $company->cashMemos()
            ->whereBetween('memo_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $itc = [
            'igst' => round((float) $cashMemos->sum('total_igst') + $interExpenseGst, 2),
            'cgst' => round((float) $cashMemos->sum('total_cgst') + ($intraExpenseGst / 2), 2),
            'sgst' => round((float) $cashMemos->sum('total_sgst') + ($intraExpenseGst / 2), 2),
        ];
        $itc['total'] = round($itc['igst'] + $itc['cgst'] + $itc['sgst'], 2);

        $netCash = [
            'igst' => round(max(0, $outward['igst'] - $itc['igst']), 2),
            'cgst' => round(max(0, $outward['cgst'] - $itc['cgst']), 2),
            'sgst' => round(max(0, $outward['sgst'] - $itc['sgst']), 2),
        ];
        $netCash['total'] = round($netCash['igst'] + $netCash['cgst'] + $netCash['sgst'], 2);

        return [
            'outward' => $outward,
            'rcm_outward' => $rcm_outward,
            'itc' => $itc,
            'net_cash' => $netCash,
            'invoice_count' => $invoices->count(),
            'expense_count' => $company->expenses()->whereBetween('entry_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            'cash_memo_count' => $cashMemos->count(),
        ];
    }

    private function monthlyTrend(Company $company, Carbon $pivot): array
    {
        $start = $pivot->copy()->subMonths(11)->startOfMonth();

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->where('invoice_date', '>=', $start)
            ->get(['invoice_date', 'subtotal']);

        $expenses = $company->expenses()
            ->where('entry_date', '>=', $start)
            ->get(['entry_date', 'amount']);

        $revByMonth = $invoices->groupBy(fn ($i) => $i->invoice_date->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('subtotal'));
        $expByMonth = $expenses->groupBy(fn ($e) => $e->entry_date->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $ym = $month->format('Y-m');
            $out[] = [
                'label' => $month->format('M y'),
                'ym' => $ym,
                'revenue' => $revByMonth[$ym] ?? 0.0,
                'expenses' => $expByMonth[$ym] ?? 0.0,
            ];
        }

        return $out;
    }

    private function resolveGstr3bPeriod(Request $request): array
    {
        $month = $request->input('month');
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = Carbon::parse($month . '-01')->startOfMonth();
        } else {
            $start = now()->subMonthNoOverflow()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();

        return [$start, $end, $start->format('F Y')];
    }

    private function resolvePeriod(Request $request): array
    {
        $key = $request->query('period', 'this_month');
        $now = Carbon::now();

        $fyStart = $now->copy()->month >= 4
            ? Carbon::create($now->year, 4, 1)->startOfDay()
            : Carbon::create($now->year - 1, 4, 1)->startOfDay();
        $fyEnd = $fyStart->copy()->addYear()->subDay()->endOfDay();

        $monthsFromApr = ($now->month + 12 - 4) % 12;
        $qIdx = intdiv($monthsFromApr, 3);
        $qStart = $fyStart->copy()->addMonths($qIdx * 3);
        $qEnd = $qStart->copy()->addMonths(3)->subDay()->endOfDay();
        $qLabel = ['Q1 (Apr–Jun)', 'Q2 (Jul–Sep)', 'Q3 (Oct–Dec)', 'Q4 (Jan–Mar)'][$qIdx];

        return match ($key) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today · ' . $now->format('d M Y'), $key],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'Yesterday', $key],
            'this_week' => [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY), 'This week', $key],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(Carbon::MONDAY), $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY), 'Last week', $key],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'Last month · ' . $now->copy()->subMonthNoOverflow()->format('M Y'), $key],
            'this_quarter' => [$qStart, $qEnd, 'This quarter · ' . $qLabel . ' ' . $qStart->format('Y'), $key],
            'this_fy' => [$fyStart, $fyEnd, 'This FY · ' . $fyStart->format('Y') . '–' . $fyEnd->format('y'), $key],
            'last_fy' => [$fyStart->copy()->subYear(), $fyEnd->copy()->subYear(), 'Last FY', $key],
            'ytd' => [$fyStart, $now->copy()->endOfDay(), 'FY to date', $key],
            'custom' => [
                Carbon::parse($request->query('from', $now->copy()->startOfMonth()->toDateString()))->startOfDay(),
                Carbon::parse($request->query('to', $now->toDateString()))->endOfDay(),
                'Custom period',
                $key,
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'This month · ' . $now->format('M Y'), 'this_month'],
        };
    }
}
