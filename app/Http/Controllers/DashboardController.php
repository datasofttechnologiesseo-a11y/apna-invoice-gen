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
        $payments = $company->payments();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        // Today's micro-stats — drive the "you've done X today" line in the
        // dashboard header. Keeps users engaged with a sense of momentum.
        // A cancelled invoice was issued, but it is not a sale: not counted.
        $todayIssued = (clone $invoices)->whereNotNull('finalized_at')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('finalized_at', [$todayStart, $todayEnd])->count();
        $todayCollected = (float) (clone $payments)
            ->whereBetween('received_at', [$todayStart, $todayEnd])->sum('amount');

        // Personalised, time-aware greeting. Indian SMEs respond to warmth —
        // not "Welcome back, USER". Honorific "ji" is universally polite
        // across regions and ages without being culture-specific.
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour < 5            => 'Working late',
            $hour < 12           => 'Good morning',
            $hour < 17           => 'Good afternoon',
            $hour < 21           => 'Good evening',
            default              => 'Good night',
        };
        $firstName = preg_split('/\s+/', trim($user->name ?? ''))[0] ?? '';

        // Fixed-date festivals and national days, as one quiet line under the
        // date. Variable-date festivals (Diwali, Holi, Eid) need a year
        // lookup; the 2026 dates are listed here.
        $today = now()->format('m-d');
        $festivals2026 = [
            '01-01' => 'Happy New Year',
            '01-14' => 'Happy Makar Sankranti and Pongal',
            '01-26' => 'Happy Republic Day',
            '03-04' => 'Happy Holi',
            '04-14' => 'Happy Baisakhi and Tamil New Year',
            '05-01' => 'Happy Labour Day',
            '08-15' => 'Happy Independence Day',
            '10-02' => 'Gandhi Jayanti',
            '10-20' => 'Shubh Dhanteras',
            '10-22' => 'Shubh Diwali',
            '11-14' => "Children's Day",
            '12-25' => 'Merry Christmas',
        ];
        $festival = $festivals2026[$today] ?? null;

        $stats = [
            'total' => (clone $invoices)->count(),
            'drafts' => (clone $invoices)->where('status', 'draft')->count(),
            // "Bills issued" — count of invoices that have actually been finalised (have a statutory number).
            'issued' => (clone $invoices)->whereNotNull('finalized_at')->count(),
            'issued_this_month' => (clone $invoices)->whereNotNull('finalized_at')
                ->whereBetween('finalized_at', [$monthStart, $monthEnd])->count(),
            'outstanding' => (clone $invoices)->where('currency', $currency)
                ->whereIn('status', ['final', 'partially_paid'])->sum('balance'),
            // Lifetime payments received (sum of receipts), and this-month subset.
            'received_total' => (clone $payments)->sum('amount'),
            'received_this_month' => (clone $payments)
                ->whereBetween('received_at', [$monthStart, $monthEnd])->sum('amount'),
            'receipts_issued' => (clone $payments)->count(),
            // Two halves of one card: what this month's bills add up to, and
            // how much of it has come in. Both exclude drafts (not issued) and
            // cancelled bills (not owed), so the pair is comparable - a
            // collection figure against an invoiced figure counted differently
            // is a ratio that means nothing.
            'invoiced_this_month' => (clone $invoices)->where('currency', $currency)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                ->sum('grand_total'),
            'paid_this_month' => (clone $invoices)->where('currency', $currency)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                ->sum('paid_amount'),
            // Credit notes raised against this month's invoices. The tile shows
            // sales net of them: the same basis as the collection ring, where
            // collected + due + overdue = billed less credit notes.
            'credited_this_month' => (clone $invoices)->where('currency', $currency)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                ->sum('credited_amount'),
            'received_count_this_month' => (clone $payments)
                ->whereBetween('received_at', [$monthStart, $monthEnd])->count(),
        ];

        // The key-figure row. Each figure is the total of the report its tile
        // opens, so the dashboard and the report never disagree:
        //   outstanding = Receivables aging (issued invoices, unpaid balance)
        //   payables    = Payables aging (purchase invoices with a balance)
        $todayDate = now()->toDateString();
        $stats['outstanding_overdue'] = (float) (clone $invoices)->where('currency', $currency)
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->whereNotNull('due_date')->where('due_date', '<', $todayDate)
            ->sum('balance');
        $payablesQ = $company->purchaseBills()->where('balance', '>', 0);
        $stats['payables'] = (float) (clone $payablesQ)->sum('balance');
        $stats['payables_count'] = (clone $payablesQ)->count();
        $stats['payables_overdue'] = (float) (clone $payablesQ)
            ->whereNotNull('due_date')->where('due_date', '<', $todayDate)->sum('balance');

        $recent = (clone $invoices)->with('customer')->latest('id')->take(8)->get();

        // "Needs attention": what the owner should act on, most urgent first.
        // Overdue invoices, then the GST returns falling due (added below,
        // once the return calendar is known), then stock and old drafts.
        // Computed from indexed columns; no extra tables or jobs.
        $overdueQ = (clone $invoices)
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $todayDate);
        $overdueCount  = (clone $overdueQ)->count();
        $overdueAmount = (float) (clone $overdueQ)->sum('balance');

        $staleDrafts = (clone $invoices)
            ->where('status', 'draft')
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        $actionItems = [];
        if ($overdueCount > 0) {
            $actionItems[] = [
                'tone' => 'danger',
                'icon' => 'overdue',
                'title' => $overdueCount === 1
                    ? '1 invoice is overdue'
                    : "{$overdueCount} invoices are overdue",
                'sub'   => '₹' . rupees($overdueAmount) . ' past the due date',
                'cta'   => 'View',
                'href'  => route('finance.aging'),
            ];
        }
        // Items at or below their low-stock level (only for items that track
        // stock and have a level set - nothing shows for anyone else). An
        // archived item is no longer sold, so it is nothing to reorder.
        $lowStock = $company->products()->where('is_active', true)->where('track_stock', true)->whereNotNull('low_stock_level')
            ->withSum('stockMovements', 'quantity')->get()
            ->filter(fn ($p) => $p->stockOnHand() <= (float) $p->low_stock_level);
        $laterItems = [];
        if ($lowStock->isNotEmpty()) {
            $laterItems[] = [
                'tone' => 'accent',
                'icon' => 'stock',
                'title' => $lowStock->count() === 1
                    ? $lowStock->first()->name . ' is low on stock'
                    : $lowStock->count() . ' items are low on stock',
                'sub'   => 'At or below the reorder level',
                'cta'   => 'View',
                'href'  => route('reports.show', ['report' => 'stock-summary', 'status' => 'low']),
            ];
        }
        if ($staleDrafts > 0) {
            $laterItems[] = [
                'tone' => 'accent',
                'icon' => 'draft',
                'title' => $staleDrafts === 1
                    ? '1 draft older than a week'
                    : "{$staleDrafts} drafts older than a week",
                'sub'   => 'Issue or delete them',
                'cta'   => 'View',
                'href'  => route('invoices.index', ['status' => 'draft']),
            ];
        }

        $setup = [
            'business' => $company->isBusinessComplete(),
            'customer' => $company->customers()->exists(),
            'first_invoice' => (clone $invoices)->exists(),
        ];
        $setupComplete = ! in_array(false, $setup, true);
        $setupProgress = round((array_sum($setup) / count($setup)) * 100);

        // Where the money stands right now — the sales ring.
        //
        // Three buckets that are mutually exclusive and, together, account for
        // every rupee this company has billed and not written off:
        //   collected — banked against issued bills
        //   due       — owed, due date has not passed
        //   overdue   — owed, and late
        // Drafts and cancelled bills are excluded from all three, the same way
        // the "invoiced this month" card excludes them: a draft was never
        // issued and a cancelled bill is not owed, so counting either would
        // make the ring add up to money that does not exist.
        //
        // This is a snapshot of today, not of the selected period. The period
        // control on the dashboard drives the bars only, and both rings say in
        // their subtitle what window they cover, so nothing silently disagrees.
        $issuedQ = (clone $invoices)->where('currency', $currency)
            ->whereNotIn('status', ['draft', 'cancelled']);
        $owedQ = (clone $issuedQ)->where('balance', '>', 0);
        $owed = (float) (clone $owedQ)->sum('balance');
        $owedOverdue = (float) (clone $owedQ)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->sum('balance');

        $receivables = [
            // Deliberately the invoice-side figure (paid_amount), not a sum of
            // the payments table. The three slices have to add up to exactly
            // what was billed or the ring is a lie; the payments table is dated
            // by receipt and would not reconcile against it. The bars below do
            // use payments, because there the date is the point.
            'collected' => (float) (clone $issuedQ)->sum('paid_amount'),
            // A bill with no due date is unpaid but not late. max() also keeps
            // the subtraction off a negative if the two sums ever disagree.
            'due' => max(0, $owed - $owedOverdue),
            'overdue' => $owedOverdue,
        ];

        // Where the money went — the purchases ring. Categories, labels and
        // colours all come from config/expense_categories.php, the same source
        // the Finance screen's breakdown uses, so a category cannot be one
        // colour here and another there.
        $spendWindowStart = now()->startOfMonth()->subMonths(11);
        $spendRows = $company->expenses()
            ->where('entry_date', '>=', $spendWindowStart->toDateString())
            ->selectRaw('category, SUM(amount + gst_amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // Six named slices plus an "Everything else" catch-all. Twenty legend
        // rows would be a list, not a chart.
        $spendByCategory = $spendRows->take(6)->map(fn ($row) => [
            'label' => config('expense_categories.' . $row->category . '.label', ucfirst($row->category)),
            'amount' => (float) $row->total,
            'color' => config('expense_categories.' . $row->category . '.color', '#6b7280'),
            // Same window as the ring, or the slice would open a this-month
            // list that does not add up to the slice the user just clicked.
            'href' => route('finance.expenses', [
                'category' => $row->category,
                'period' => 'custom',
                'from' => $spendWindowStart->toDateString(),
                'to' => now()->toDateString(),
            ]),
        ])->values()->all();

        $spendRest = (float) $spendRows->skip(6)->sum('total');
        if ($spendRest > 0) {
            $spendByCategory[] = [
                'label' => 'Everything else',
                'amount' => $spendRest,
                'color' => '#cbd5e1',
                'href' => route('finance.expenses', [
                    'period' => 'custom',
                    'from' => $spendWindowStart->toDateString(),
                    'to' => now()->toDateString(),
                ]),
            ];
        }

        // ── The bars, in four flavours: sales and purchases, by month and by
        // financial year. All four come off three grouped queries over one
        // spine of months, rather than a query per bucket, so switching between
        // them on the dashboard is instant and costs nothing extra.
        //
        // SUBSTR on the date is the one month-bucket expression that reads the
        // same on MySQL (production) and SQLite (tests); DATE_FORMAT and
        // strftime each exist on only one.
        [$fyStartYear] = \App\Models\Company::financialYearFor(now());
        $fyCount = 3;
        $spineStart = \Illuminate\Support\Carbon::create($fyStartYear - ($fyCount - 1), 4, 1)->startOfDay();

        $byMonth = function ($query, string $dateColumn, array $sums) use ($spineStart): array {
            // $dateColumn and $sums are literals from the calls below, never
            // request input - nothing here is interpolating anything a user typed.
            $select = ["SUBSTR({$dateColumn}, 1, 7) as ym"];
            foreach ($sums as $alias => $expr) {
                $select[] = "SUM({$expr}) as {$alias}";
            }

            $out = [];
            $rows = $query->where($dateColumn, '>=', $spineStart->toDateString())
                ->selectRaw(implode(', ', $select))
                ->groupBy('ym')
                ->get();

            foreach ($rows as $row) {
                foreach (array_keys($sums) as $alias) {
                    $out[$row->ym][$alias] = (float) $row->{$alias};
                }
            }

            return $out;
        };

        $invoicedMonths = $byMonth(
            (clone $invoices)->where('currency', $currency)->whereNotIn('status', ['draft', 'cancelled']),
            'invoice_date',
            // Net of credit notes, like the sales tile and the ring.
            ['total' => 'grand_total - credited_amount']
        );
        $collectedMonths = $byMonth((clone $payments), 'received_at', ['total' => 'amount']);
        $spendMonths = $byMonth(
            $company->expenses(),
            'entry_date',
            ['total' => 'amount + gst_amount', 'gst' => 'gst_amount']
        );

        $pick = fn (array $rows, string $ym, string $key) => (float) ($rows[$ym][$key] ?? 0);

        // startOfMonth() before subMonths() matters: subtracting a month from
        // the 31st overflows (31 Mar − 1 month = 3 Mar in PHP), which would
        // drop a month from the chart and draw another one twice.
        $thisMonth = now()->startOfMonth();

        $salesMonthly = [];
        $purchaseMonthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $thisMonth->copy()->subMonths($i);
            $ym = $m->format('Y-m');
            $window = ['from' => $m->toDateString(), 'to' => $m->copy()->endOfMonth()->toDateString()];

            $salesMonthly[] = $window + [
                'label' => $m->format('M'),
                'full' => $m->format('F Y'),
                'a' => $pick($invoicedMonths, $ym, 'total'),
                'b' => $pick($collectedMonths, $ym, 'total'),
                'url' => route('invoices.index', $window),
            ];
            $purchaseMonthly[] = $window + [
                'label' => $m->format('M'),
                'full' => $m->format('F Y'),
                'a' => $pick($spendMonths, $ym, 'total'),
                'b' => $pick($spendMonths, $ym, 'gst'),
                'url' => route('finance.expenses', $window + ['period' => 'custom']),
            ];
        }

        $salesYearly = [];
        $purchaseYearly = [];
        for ($i = $fyCount - 1; $i >= 0; $i--) {
            $fy = $fyStartYear - $i;
            $from = \Illuminate\Support\Carbon::create($fy, 4, 1)->startOfDay();
            $totals = ['inv' => 0.0, 'col' => 0.0, 'spend' => 0.0, 'gst' => 0.0];

            for ($k = 0; $k < 12; $k++) {
                $ym = $from->copy()->addMonths($k)->format('Y-m');
                $totals['inv'] += $pick($invoicedMonths, $ym, 'total');
                $totals['col'] += $pick($collectedMonths, $ym, 'total');
                $totals['spend'] += $pick($spendMonths, $ym, 'total');
                $totals['gst'] += $pick($spendMonths, $ym, 'gst');
            }

            // Indian FY runs 1 Apr → 31 Mar, so it is named for both years.
            $window = [
                'from' => $from->toDateString(),
                'to' => $from->copy()->addYear()->subDay()->toDateString(),
                'label' => 'FY ' . substr((string) $fy, 2) . '-' . substr((string) ($fy + 1), 2),
                'full' => 'FY ' . $fy . '-' . ($fy + 1),
            ];

            $range = ['from' => $window['from'], 'to' => $window['to']];
            $salesYearly[] = $window + [
                'a' => $totals['inv'], 'b' => $totals['col'],
                'url' => route('invoices.index', $range),
            ];
            $purchaseYearly[] = $window + [
                'a' => $totals['spend'], 'b' => $totals['gst'],
                'url' => route('finance.expenses', $range + ['period' => 'custom']),
            ];
        }

        $chartSeries = [
            'sales' => ['monthly' => $salesMonthly, 'yearly' => $salesYearly],
            'purchases' => ['monthly' => $purchaseMonthly, 'yearly' => $purchaseYearly],
        ];

        // First run — the account has produced nothing yet, so every KPI, the
        // P&L and the revenue trend would read ₹0. A wall of zeroes tells a new
        // user nothing and makes the product feel empty, so the view swaps them
        // for a single "make your first invoice" panel until there is data.
        $isFirstRun = $stats['total'] === 0
            && $stats['receipts_issued'] === 0
            && ! $company->expenses()->exists();

        // Review invite, "registration" moment: the first dashboard a new user
        // sees, and the first visit of an existing user never asked before.
        // The other moment - every alternate invoice - is in finalize().
        $reviewPrompt = app(\App\Services\ReviewPrompt::class);
        $showReviewInvite = $reviewPrompt->shouldPromptOnDashboard($user);
        if ($showReviewInvite) {
            $reviewPrompt->markShown($user);
        }

        // This month's GST at a glance (registered businesses with invoices):
        // last month's returns, in the order they fall due.
        $gstCalendar = null;
        if ($company->gstin && ! $isFirstRun) {
            $period = \App\Support\ReturnPeriod::for(now()->subMonthNoOverflow());
            $g3b = app(\App\Services\Gstr3bBuilder::class)->build($company, $period->start, $period->end);
            $twoB = \App\Models\Gstr2bEntry::where('company_id', $company->id)->where('period', $period->month())->exists();
            $gstCalendar = [
                'label' => $period->label(),
                'query' => $period->query(),
                'gstr1_due' => $period->gstr1Due(),
                'gstr2b_from' => $period->end->copy()->addDay()->day(14),
                'gstr2b_uploaded' => $twoB,
                'gstr3b_due' => $period->gstr3bDue($company->state?->gst_code),
                'cash' => (float) $g3b['netCash']['total'],
                'invoices' => (int) $g3b['invoiceCount'],
            ];

            // A return joins the list a week before it is due and stays for a
            // few days after. We cannot see the portal, so after that the GST
            // card still shows the date, without nagging.
            $startOfToday = now()->startOfDay();
            $daysTo = fn ($date) => (int) $startOfToday->diffInDays($date->copy()->startOfDay(), false);
            $dueLine = fn (int $days, $date): string => match (true) {
                $days < 0 => 'Was due ' . $date->format('d M') . '. File it if you have not yet.',
                $days === 0 => 'Due today',
                $days === 1 => 'Due tomorrow, ' . $date->format('d M'),
                default => 'Due ' . $date->format('d M') . ', in ' . $days . ' days',
            };
            $label = $gstCalendar['label'];
            $d1 = $daysTo($gstCalendar['gstr1_due']);
            if ($d1 <= 7 && $d1 >= -5) {
                $actionItems[] = [
                    'tone' => $d1 <= 0 ? 'danger' : 'accent',
                    'icon' => 'gst',
                    'title' => "GSTR-1 for {$label}",
                    'sub' => $dueLine($d1, $gstCalendar['gstr1_due']),
                    'cta' => 'Open',
                    'href' => route('finance.gstr1', $gstCalendar['query']),
                ];
            }
            $d3 = $daysTo($gstCalendar['gstr3b_due']);
            if (! $gstCalendar['gstr2b_uploaded'] && now()->gte($gstCalendar['gstr2b_from']) && $d3 >= 0) {
                $actionItems[] = [
                    'tone' => 'accent',
                    'icon' => 'gst',
                    'title' => "Upload GSTR-2B for {$label}",
                    'sub' => 'Match supplier invoices before you file GSTR-3B',
                    'cta' => 'Upload',
                    'href' => route('finance.gstr2b', ['month' => $gstCalendar['query']['month']]),
                ];
            }
            if ($d3 <= 7 && $d3 >= -5) {
                $actionItems[] = [
                    'tone' => $d3 <= 0 ? 'danger' : 'accent',
                    'icon' => 'gst',
                    'title' => "GSTR-3B for {$label}"
                        . ($gstCalendar['cash'] > 0 ? ' · ₹' . rupees($gstCalendar['cash']) . ' to pay' : ''),
                    'sub' => $dueLine($d3, $gstCalendar['gstr3b_due']),
                    'cta' => 'Open',
                    'href' => route('finance.gstr3b', $gstCalendar['query']),
                ];
            }
        }
        $actionItems = array_merge($actionItems, $laterItems);

        return view('dashboard', compact(
            'gstCalendar',
            'stats', 'recent', 'currency', 'company', 'setup', 'setupComplete',
            'setupProgress',
            'receivables', 'spendByCategory', 'chartSeries',
            'greeting', 'firstName', 'festival', 'todayIssued', 'todayCollected',
            'actionItems', 'isFirstRun', 'showReviewInvite'
        ));
    }
}
