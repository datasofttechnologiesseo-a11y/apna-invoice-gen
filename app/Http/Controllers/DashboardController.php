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
            'paid_this_month' => (clone $invoices)->where('currency', $currency)
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
                'tone' => 'red',
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
                'tone' => 'amber',
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
                'tone' => 'blue',
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
            'greeting', 'firstName', 'festival', 'todayIssued', 'todayCollected',
            'actionItems', 'isFirstRun', 'showReviewInvite'
        ));
    }
}
