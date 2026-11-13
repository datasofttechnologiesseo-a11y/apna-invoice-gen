<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummary;
use App\Services\ReviewPrompt;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The web dashboard. Every number comes from App\Services\DashboardSummary
 * (shared with the mobile app's Home screen); this controller adds what only
 * the web page has - the greeting, the festival line, the review invite - and
 * turns the summary's links into web URLs.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $currency = $company->default_currency;
        $summary = DashboardSummary::for($company);

        // Today's micro-stats — drive the "you've done X today" line in the
        // dashboard header.
        $todayIssued = $summary->todayIssued();
        $todayCollected = $summary->todayCollected();

        // Personalised, time-aware greeting. Indian SMEs respond to warmth —
        // not "Welcome back, USER". Honorific "ji" is universally polite
        // across regions and ages without being culture-specific.
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour < 5 => 'Working late',
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            $hour < 21 => 'Good evening',
            default => 'Good night',
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

        $stats = $summary->stats();
        $recent = $company->invoices()->with('customer')->latest('id')->take(8)->get();

        $setup = $summary->setup();
        $setupComplete = ! in_array(false, $setup, true);
        $setupProgress = round((array_sum($setup) / count($setup)) * 100);

        $receivables = $summary->receivables();
        $whoOwes = $summary->whoOwes();
        $isFirstRun = $summary->isFirstRun();
        $gstCalendar = $summary->gstCalendar();

        // Each slice opens the expense list over the ring's own window, or it
        // would open a list that does not add up to the slice just clicked.
        $window = $summary->spendWindow();
        $spendByCategory = array_map(fn (array $slice) => [
            'label' => $slice['label'],
            'amount' => $slice['amount'],
            'color' => $slice['color'],
            'href' => route('finance.expenses', ($slice['key'] === 'other' ? [] : ['category' => $slice['key']])
                + ['period' => 'custom', 'from' => $window['from'], 'to' => $window['to']]),
        ], $summary->spendByCategory());

        // Every bar drills into the period it represents.
        $chartSeries = [];
        foreach ($summary->chartSeries() as $view => $spans) {
            foreach ($spans as $span => $bars) {
                $chartSeries[$view][$span] = array_map(function (array $bar) use ($view) {
                    $range = ['from' => $bar['from'], 'to' => $bar['to']];

                    return $bar + ['url' => $view === 'sales'
                        ? route('invoices.index', $range)
                        : route('finance.expenses', $range + ['period' => 'custom'])];
                }, $bars);
            }
        }

        $actionItems = array_map(function (array $item) {
            $link = $item['link'];
            unset($item['link']);

            return $item + ['href' => route($link['route'], $link['params'])];
        }, $summary->actionItems());

        // Review invite, dashboard moment: the first dashboard of each day
        // until the review is given (a new user's very first one included).
        // The other moment - every alternate invoice - is in finalize().
        $reviewPrompt = app(ReviewPrompt::class);
        $showReviewInvite = $reviewPrompt->shouldPromptOnDashboard($user);
        if ($showReviewInvite) {
            $reviewPrompt->markShown($user);
        }

        return view('dashboard', compact(
            'gstCalendar',
            'stats', 'recent', 'currency', 'company', 'setup', 'setupComplete',
            'setupProgress',
            'receivables', 'spendByCategory', 'chartSeries',
            'greeting', 'firstName', 'festival', 'todayIssued', 'todayCollected',
            'actionItems', 'isFirstRun', 'showReviewInvite', 'whoOwes'
        ));
    }
}
