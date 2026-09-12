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
        $todayIssued = (clone $invoices)->whereNotNull('finalized_at')
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

        // Fixed-date festivals + national days. Keep it small and tasteful —
        // a single emoji + short label. Variable-date festivals (Diwali, Holi,
        // Eid) need a year lookup; for 2026 we hard-code the known dates.
        $today = now()->format('m-d');
        $festivals2026 = [
            '01-01' => ['name' => 'Happy New Year', 'emoji' => '🎊'],
            '01-14' => ['name' => 'Happy Makar Sankranti / Pongal', 'emoji' => '🌾'],
            '01-26' => ['name' => 'Happy Republic Day', 'emoji' => '🇮🇳'],
            '03-04' => ['name' => 'Happy Holi', 'emoji' => '🎨'],     // 2026
            '04-14' => ['name' => 'Happy Baisakhi / Tamil New Year', 'emoji' => '🌻'],
            '05-01' => ['name' => 'Happy Labour Day', 'emoji' => '👷'],
            '08-15' => ['name' => 'Happy Independence Day', 'emoji' => '🇮🇳'],
            '10-02' => ['name' => 'Gandhi Jayanti', 'emoji' => '🕊️'],
            '10-20' => ['name' => 'Shubh Dhanteras', 'emoji' => '💰'],   // 2026
            '10-22' => ['name' => 'Shubh Diwali', 'emoji' => '🪔'],      // 2026
            '11-14' => ['name' => "Children's Day", 'emoji' => '🧒'],
            '12-25' => ['name' => 'Merry Christmas', 'emoji' => '🎄'],
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

        // Smart Action Items — the "what should I do today?" widget. Each entry
        // is something the user can act on in one click. Computed cheap from
        // existing indexed columns; no separate tables or jobs.
        $today = now()->toDateString();
        $overdueQ = (clone $invoices)
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today);
        $overdueCount  = (clone $overdueQ)->count();
        $overdueAmount = (float) (clone $overdueQ)->sum('balance');

        $staleDraftsQ = (clone $invoices)
            ->where('status', 'draft')
            ->where('created_at', '<', now()->subDays(7));
        $staleDrafts  = (clone $staleDraftsQ)->count();

        // Invoices issued but never sent (no email, no reminder, no payment).
        // Cheap heuristic: finalised > 1 day ago, balance owed, no reminders sent.
        $unsentRecent = (clone $invoices)
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->where('finalized_at', '<', now()->subDay())
            ->whereDoesntHave('reminders')
            ->count();

        $actionItems = [];
        if ($overdueCount > 0) {
            $actionItems[] = [
                'tone' => 'danger',
                'icon' => 'overdue',
                'title' => $overdueCount === 1
                    ? '1 invoice is overdue'
                    : "{$overdueCount} invoices are overdue",
                'sub'   => '₹' . number_format($overdueAmount, 2, '.', ',') . ' awaiting payment',
                'cta'   => 'Send reminders',
                'href'  => route('invoices.index', ['status' => 'outstanding']),
            ];
        }
        if ($staleDrafts > 0) {
            $actionItems[] = [
                'tone' => 'accent',
                'icon' => 'draft',
                'title' => $staleDrafts === 1
                    ? '1 draft older than a week'
                    : "{$staleDrafts} drafts older than a week",
                'sub'   => 'Issue them or delete to keep things tidy',
                'cta'   => 'Review drafts',
                'href'  => route('invoices.index', ['status' => 'draft']),
            ];
        }
        if ($unsentRecent > 0) {
            $actionItems[] = [
                'tone' => 'brand',
                'icon' => 'send',
                'title' => $unsentRecent === 1
                    ? '1 invoice not shared yet'
                    : "{$unsentRecent} invoices not shared yet",
                'sub'   => 'Send on WhatsApp or email so they get paid faster',
                'cta'   => 'See invoices',
                'href'  => route('invoices.index', ['status' => 'outstanding']),
            ];
        }

        $setup = [
            'business' => $company->isBusinessComplete(),
            'customer' => $company->customers()->exists(),
            'first_invoice' => (clone $invoices)->exists(),
        ];
        $setupComplete = ! in_array(false, $setup, true);
        $setupProgress = round((array_sum($setup) / count($setup)) * 100);

        // 30-day daily revenue trend — payments received per day. Drives the
        // sparkline on the dashboard. One row per day so the chart shows real
        // gaps (zero days) instead of misleadingly straight-lining over them.
        $trendStart = now()->subDays(29)->startOfDay();
        $trendEnd = now()->endOfDay();
        $rows = (clone $payments)
            ->selectRaw('DATE(received_at) as d, SUM(amount) as total')
            ->whereBetween('received_at', [$trendStart, $trendEnd])
            ->groupBy('d')
            ->pluck('total', 'd');

        $trend30 = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $trend30[] = [
                'date' => $day,
                'label' => \Illuminate\Support\Carbon::parse($day)->format('d M'),
                'amount' => (float) ($rows[$day] ?? 0),
            ];
        }

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
            ['total' => 'grand_total']
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

        // Ask for a Google review once, on the first dashboard visit after the
        // user has actually issued an invoice. By now the bill has been sent
        // and the product has proved itself, and nothing here is blocked by
        // the modal the way the invoice screen's share buttons would be.
        $reviewPrompt = app(\App\Services\ReviewPrompt::class);
        $showReviewInvite = $reviewPrompt->shouldPrompt($user, $stats['issued']);
        if ($showReviewInvite) {
            $reviewPrompt->markShown($user);
        }

        return view('dashboard', compact(
            'stats', 'recent', 'currency', 'company', 'setup', 'setupComplete',
            'setupProgress', 'pnl', 'trend30',
            'receivables', 'spendByCategory', 'chartSeries',
            'greeting', 'firstName', 'festival', 'todayIssued', 'todayCollected',
            'actionItems', 'isFirstRun', 'showReviewInvite'
        ));
    }
}
