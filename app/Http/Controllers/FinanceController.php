<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        [$periodStart, $periodEnd, $periodLabel, $periodKey] = $this->resolvePeriod($request);
        $view = in_array($request->query('view'), ['accrual', 'cash', 'gst'], true)
            ? $request->query('view')
            : 'accrual';

        // ── Revenue comes from finalized invoices ──
        $invoicesInPeriod = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$periodStart, $periodEnd]);

        $revenue = [
            'taxable' => (float) (clone $invoicesInPeriod)->sum('subtotal'),
            'gst_collected' => (float) (clone $invoicesInPeriod)->sum('total_tax'),
            'grand_total' => (float) (clone $invoicesInPeriod)->sum('grand_total'),
            'received' => (float) (clone $invoicesInPeriod)->sum('paid_amount'),
            'outstanding' => (float) $company->invoices()
                ->whereIn('status', ['final', 'partially_paid'])
                ->sum('balance'),
        ];

        // ── Expenses from the ledger ──
        $expensesInPeriod = $company->expenses()
            ->whereBetween('entry_date', [$periodStart, $periodEnd]);

        $expense = [
            'taxable' => (float) (clone $expensesInPeriod)->sum('amount'),
            'gst_itc' => (float) (clone $expensesInPeriod)->sum('gst_amount'),
        ];
        $expense['cash_out'] = $expense['taxable'] + $expense['gst_itc'];

        // ── Core P&L numbers ──
        $netProfit = $revenue['taxable'] - $expense['taxable'];
        $margin = $revenue['taxable'] > 0 ? ($netProfit / $revenue['taxable']) * 100 : 0;
        $cashInHand = $revenue['received'] - $expense['cash_out'];
        $gstPayable = $revenue['gst_collected'] - $expense['gst_itc'];

        // ── 12-month trend ──
        $trend = $this->monthlyTrend($company, $periodEnd);

        // ── Expenses by category ──
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
                    'count' => $row->count,
                    'share' => $expense['taxable'] > 0 ? (float) $row->total / $expense['taxable'] * 100 : 0,
                ];
            });

        // ── Top 10 expenses ──
        $topExpenses = (clone $expensesInPeriod)
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        return view('finance.index', compact(
            'company', 'periodStart', 'periodEnd', 'periodLabel', 'periodKey', 'view',
            'revenue', 'expense', 'netProfit', 'margin', 'cashInHand', 'gstPayable',
            'trend', 'byCategory', 'topExpenses'
        ));
    }

    public function expenses(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        $expenses = $company->expenses()
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('description', 'like', "%{$s}%")->orWhere('vendor_name', 'like', "%{$s}%");
            }))
            ->when($request->from, fn ($q, $d) => $q->where('entry_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('entry_date', '<=', $d))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('finance.expenses', compact('company', 'expenses'));
    }

    public function createExpense(Request $request): View
    {
        $expense = new Expense(['entry_date' => now()->toDateString(), 'payment_method' => 'bank']);
        return view('finance.entry-form', compact('expense'));
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validated($request);

        $expense = $company->expenses()->create(array_merge($data, ['user_id' => $user->id]));

        return redirect()->route('finance.expenses')
            ->with('status', "Expense of ₹" . number_format($expense->amount, 2) . " added.");
    }

    public function editExpense(Request $request, Expense $expense): View
    {
        abort_unless($expense->user_id === $request->user()->id, 403);
        return view('finance.entry-form', compact('expense'));
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 403);
        $expense->update($this->validated($request));

        return redirect()->route('finance.expenses')->with('status', 'Expense updated.');
    }

    public function destroyExpense(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 403);
        $expense->delete();

        return redirect()->route('finance.expenses')->with('status', 'Expense deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(config('expense_categories')))],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,bank,upi,card,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // gst_amount is validated as nullable but its DB column is NOT NULL default 0.
        // Coerce empty / null to 0 so Laravel doesn't insert a literal NULL that
        // bypasses the column default and triggers a 1048 integrity violation.
        $data['gst_amount'] = $data['gst_amount'] ?? 0;

        return $data;
    }

    /**
     * Resolve a period from the request: ?period=this_month|last_month|this_quarter|this_fy|last_fy|ytd|custom
     * Custom uses ?from=&to=.
     */
    private function resolvePeriod(Request $request): array
    {
        $key = $request->query('period', 'this_month');
        $now = Carbon::now();

        // Indian financial year runs Apr 1 → Mar 31
        $fyStart = $now->copy()->month >= 4
            ? Carbon::create($now->year, 4, 1)->startOfDay()
            : Carbon::create($now->year - 1, 4, 1)->startOfDay();
        $fyEnd = $fyStart->copy()->addYear()->subDay()->endOfDay();

        return match ($key) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'Last month · ' . $now->copy()->subMonthNoOverflow()->format('M Y'),
                $key,
            ],
            'this_quarter' => [
                $now->copy()->firstOfQuarter(),
                $now->copy()->lastOfQuarter()->endOfDay(),
                'This quarter · Q' . $now->quarter . ' ' . $now->year,
                $key,
            ],
            'this_fy' => [
                $fyStart,
                $fyEnd,
                'This financial year · FY ' . $fyStart->format('y') . '–' . $fyEnd->format('y'),
                $key,
            ],
            'last_fy' => [
                $fyStart->copy()->subYear(),
                $fyEnd->copy()->subYear(),
                'Last financial year · FY ' . $fyStart->copy()->subYear()->format('y') . '–' . $fyEnd->copy()->subYear()->format('y'),
                $key,
            ],
            'ytd' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfDay(),
                'Year to date · ' . $now->format('Y'),
                $key,
            ],
            'custom' => [
                Carbon::parse($request->query('from', $now->copy()->startOfMonth()->toDateString()))->startOfDay(),
                Carbon::parse($request->query('to', $now->toDateString()))->endOfDay(),
                'Custom period',
                $key,
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                'This month · ' . $now->format('M Y'),
                'this_month',
            ],
        };
    }

    /**
     * Last 12 months of revenue (invoice subtotal) vs expenses (expense amount).
     * Grouped in PHP so the query is portable (MySQL + SQLite alike).
     */
    private function monthlyTrend($company, Carbon $pivot): array
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
}
