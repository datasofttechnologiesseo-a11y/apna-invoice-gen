<?php

namespace App\Services;

use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Gstr2bEntry;
use App\Models\Invoice;
use App\Support\ReturnPeriod;
use App\Support\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every number on the dashboard, for the web page and the mobile app's Home
 * screen alike, so the two can never disagree.
 *
 * Nothing here knows about web URLs. Where a figure opens something (an action
 * item, a chart bar, a spend slice) it carries a "link": the web route name and
 * its parameters. The web controller turns that into an href, the API turns it
 * into an app screen. Role visibility (Team::can) is checked here, so a member
 * sees the same thing on both.
 *
 * Each section is computed once, on first use.
 */
class DashboardSummary
{
    /** Issued and owed: what "outstanding" means everywhere on the dashboard. */
    private const OPEN = ['final', 'partially_paid'];

    private array $memo = [];

    public function __construct(public readonly Company $company) {}

    public static function for(Company $company): self
    {
        return new self($company);
    }

    public function currency(): ?string
    {
        return $this->company->default_currency;
    }

    private function remember(string $key, callable $build): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $build();
        }

        return $this->memo[$key];
    }

    private function invoices()
    {
        return $this->company->invoices();
    }

    private function payments()
    {
        return $this->company->payments();
    }

    /** Invoices finalised today. A cancelled invoice was issued, but it is not a sale. */
    public function todayIssued(): int
    {
        return $this->remember('todayIssued', fn () => $this->invoices()->whereNotNull('finalized_at')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('finalized_at', [now()->startOfDay(), now()->endOfDay()])->count());
    }

    public function todayCollected(): float
    {
        return $this->remember('todayCollected', fn () => (float) $this->payments()
            ->whereBetween('received_at', [now()->startOfDay(), now()->endOfDay()])->sum('amount'));
    }

    public function stats(): array
    {
        return $this->remember('stats', function () {
            $currency = $this->currency();
            $invoices = $this->invoices();
            $payments = $this->payments();
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();

            $stats = [
                'total' => (clone $invoices)->count(),
                'drafts' => (clone $invoices)->where('status', 'draft')->count(),
                // "Bills issued" — invoices that have actually been finalised (have a statutory number).
                'issued' => (clone $invoices)->whereNotNull('finalized_at')->count(),
                'issued_this_month' => (clone $invoices)->whereNotNull('finalized_at')
                    ->whereBetween('finalized_at', [$monthStart, $monthEnd])->count(),
                'outstanding' => (clone $invoices)->where('currency', $currency)
                    ->whereIn('status', self::OPEN)->sum('balance'),
                // Lifetime payments received (sum of receipts), and this-month subset.
                'received_total' => (clone $payments)->sum('amount'),
                'received_this_month' => (clone $payments)
                    ->whereBetween('received_at', [$monthStart, $monthEnd])->sum('amount'),
                'receipts_issued' => (clone $payments)->count(),
                // Two halves of one card: what this month's bills add up to, and
                // how much of it has come in. Both exclude drafts (not issued) and
                // cancelled bills (not owed), so the pair is comparable.
                'invoiced_this_month' => (clone $invoices)->where('currency', $currency)
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                    ->sum('grand_total'),
                'paid_this_month' => (clone $invoices)->where('currency', $currency)
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                    ->sum('paid_amount'),
                // Credit notes raised against this month's invoices: the tile
                // shows sales net of them, the same basis as the collection ring.
                'credited_this_month' => (clone $invoices)->where('currency', $currency)
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                    ->sum('credited_amount'),
                'received_count_this_month' => (clone $payments)
                    ->whereBetween('received_at', [$monthStart, $monthEnd])->count(),
            ];

            // The key-figure row. Each figure is the total of the report its
            // tile opens, so the dashboard and the report never disagree.
            $todayDate = now()->toDateString();
            $stats['outstanding_overdue'] = (float) (clone $invoices)->where('currency', $currency)
                ->whereIn('status', self::OPEN)
                ->where('balance', '>', 0)
                ->whereNotNull('due_date')->where('due_date', '<', $todayDate)
                ->sum('balance');
            $payablesQ = $this->company->purchaseBills()->where('balance', '>', 0);
            $stats['payables'] = (float) (clone $payablesQ)->sum('balance');
            $stats['payables_count'] = (clone $payablesQ)->count();
            $stats['payables_overdue'] = (float) (clone $payablesQ)
                ->whereNotNull('due_date')->where('due_date', '<', $todayDate)->sum('balance');

            return $stats;
        });
    }

    public function setup(): array
    {
        return $this->remember('setup', fn () => [
            'business' => $this->company->isBusinessComplete(),
            'customer' => $this->company->customers()->exists(),
            'first_invoice' => $this->invoices()->exists(),
        ]);
    }

    /**
     * First run: the account has produced nothing yet, so every KPI would read
     * ₹0. The web view swaps them for a "make your first invoice" panel.
     */
    public function isFirstRun(): bool
    {
        return $this->remember('isFirstRun', fn () => $this->stats()['total'] === 0
            && $this->stats()['receipts_issued'] === 0
            && ! $this->company->expenses()->exists());
    }

    /**
     * Where the money stands right now — the sales ring. Three mutually
     * exclusive buckets that together account for every rupee billed and not
     * written off. Drafts and cancelled bills are in none of them. The
     * collected slice is the invoice-side figure (paid_amount), so the three
     * add up to exactly what was billed.
     */
    public function receivables(): array
    {
        return $this->remember('receivables', function () {
            $issuedQ = $this->invoices()->where('currency', $this->currency())
                ->whereNotIn('status', ['draft', 'cancelled']);
            $owedQ = (clone $issuedQ)->where('balance', '>', 0);
            $owed = (float) (clone $owedQ)->sum('balance');
            $owedOverdue = (float) (clone $owedQ)
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->sum('balance');

            return [
                'collected' => (float) (clone $issuedQ)->sum('paid_amount'),
                // A bill with no due date is unpaid but not late.
                'due' => max(0, $owed - $owedOverdue),
                'overdue' => $owedOverdue,
            ];
        });
    }

    /** The spend ring covers the last twelve months, this one included. */
    public function spendWindow(): array
    {
        return ['from' => now()->startOfMonth()->subMonths(11)->toDateString(), 'to' => now()->toDateString()];
    }

    /**
     * Where the money went — the purchases ring. Six named categories plus an
     * "Everything else" catch-all (key "other"). Labels and colours come from
     * config/expense_categories.php, as on the Finance screen.
     *
     * @return array<int, array{key: string, label: string, amount: float, color: string}>
     */
    public function spendByCategory(): array
    {
        return $this->remember('spendByCategory', function () {
            $rows = $this->company->expenses()
                ->whereDate('entry_date', '>=', $this->spendWindow()['from'])
                ->selectRaw('category, SUM(amount + gst_amount) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get();

            $slices = $rows->take(6)->map(fn ($row) => [
                'key' => (string) $row->category,
                'label' => config('expense_categories.'.$row->category.'.label', ucfirst((string) $row->category)),
                'amount' => (float) $row->total,
                'color' => config('expense_categories.'.$row->category.'.color', '#6b7280'),
            ])->values()->all();

            $rest = (float) $rows->skip(6)->sum('total');
            if ($rest > 0) {
                $slices[] = ['key' => 'other', 'label' => 'Everything else', 'amount' => $rest, 'color' => '#cbd5e1'];
            }

            return $slices;
        });
    }

    /**
     * The bars: sales (a = billed net of credit notes, b = collected) and
     * purchases (a = spend incl. GST, b = the GST inside it), by month (last
     * six) and by Indian financial year (last three). Three grouped queries
     * over one spine of months.
     *
     * @return array{sales: array{monthly: array, yearly: array}, purchases: array{monthly: array, yearly: array}}
     */
    public function chartSeries(): array
    {
        return $this->remember('chartSeries', function () {
            [$fyStartYear] = Company::financialYearFor(now());
            $fyCount = 3;
            $spineStart = Carbon::create($fyStartYear - ($fyCount - 1), 4, 1)->startOfDay();

            // SUBSTR on the date is the one month-bucket expression that reads
            // the same on MySQL (production) and SQLite (tests).
            $byMonth = function ($query, string $dateColumn, array $sums) use ($spineStart): array {
                // $dateColumn and $sums are literals from the calls below, never request input.
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
                $this->invoices()->where('currency', $this->currency())->whereNotIn('status', ['draft', 'cancelled']),
                'invoice_date',
                // Net of credit notes, like the sales tile and the ring.
                ['total' => 'grand_total - credited_amount']
            );
            $collectedMonths = $byMonth($this->payments(), 'received_at', ['total' => 'amount']);
            $spendMonths = $byMonth(
                $this->company->expenses(),
                'entry_date',
                ['total' => 'amount + gst_amount', 'gst' => 'gst_amount']
            );

            $pick = fn (array $rows, string $ym, string $key) => (float) ($rows[$ym][$key] ?? 0);

            // startOfMonth() before subMonths(): subtracting a month from the
            // 31st overflows (31 Mar − 1 month = 3 Mar).
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
                ];
                $purchaseMonthly[] = $window + [
                    'label' => $m->format('M'),
                    'full' => $m->format('F Y'),
                    'a' => $pick($spendMonths, $ym, 'total'),
                    'b' => $pick($spendMonths, $ym, 'gst'),
                ];
            }

            $salesYearly = [];
            $purchaseYearly = [];
            for ($i = $fyCount - 1; $i >= 0; $i--) {
                $fy = $fyStartYear - $i;
                $from = Carbon::create($fy, 4, 1)->startOfDay();
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
                    'label' => 'FY '.substr((string) $fy, 2).'-'.substr((string) ($fy + 1), 2),
                    'full' => 'FY '.$fy.'-'.($fy + 1),
                ];

                $salesYearly[] = $window + ['a' => $totals['inv'], 'b' => $totals['col']];
                $purchaseYearly[] = $window + ['a' => $totals['spend'], 'b' => $totals['gst']];
            }

            return [
                'sales' => ['monthly' => $salesMonthly, 'yearly' => $salesYearly],
                'purchases' => ['monthly' => $purchaseMonthly, 'yearly' => $purchaseYearly],
            ];
        });
    }

    /**
     * This month's GST at a glance (registered businesses with activity):
     * last month's returns, in the order they fall due. Dates are Carbon.
     */
    public function gstCalendar(): ?array
    {
        return $this->remember('gstCalendar', function () {
            if (! $this->company->gstin || $this->isFirstRun()) {
                return null;
            }
            $period = ReturnPeriod::for(now()->subMonthNoOverflow());
            $g3b = app(Gstr3bBuilder::class)->build($this->company, $period->start, $period->end);

            return [
                'label' => $period->label(),
                'query' => $period->query(),
                'gstr1_due' => $period->gstr1Due(),
                'gstr2b_from' => $period->end->copy()->addDay()->day(14),
                'gstr2b_uploaded' => Gstr2bEntry::where('company_id', $this->company->id)->where('period', $period->month())->exists(),
                'gstr3b_due' => $period->gstr3bDue($this->company->state?->gst_code),
                'cash' => (float) $g3b['netCash']['total'],
                'invoices' => (int) $g3b['invoiceCount'],
            ];
        });
    }

    /**
     * "Needs attention", most urgent first: overdue invoices, the GST returns
     * falling due, then bank lines to match, low stock and old drafts.
     *
     * Each item carries a "link" ['route' => web route name, 'params' => [...]]
     * instead of a URL.
     *
     * @return array<int, array{tone: string, icon: string, title: string, sub: string, cta: string, link: array{route: string, params: array}}>
     */
    public function actionItems(): array
    {
        return $this->remember('actionItems', function () {
            $todayDate = now()->toDateString();
            $items = [];

            $overdueQ = $this->invoices()
                ->whereIn('status', self::OPEN)
                ->where('balance', '>', 0)
                ->whereNotNull('due_date')
                ->where('due_date', '<', $todayDate);
            $overdueCount = (clone $overdueQ)->count();
            $overdueAmount = (float) (clone $overdueQ)->sum('balance');
            if ($overdueCount > 0) {
                $items[] = [
                    'tone' => 'danger',
                    'icon' => 'overdue',
                    'title' => $overdueCount === 1 ? '1 invoice is overdue' : "{$overdueCount} invoices are overdue",
                    'sub' => '₹'.rupees($overdueAmount).' past the due date',
                    'cta' => 'View',
                    'link' => ['route' => 'finance.aging', 'params' => []],
                ];
            }

            $laterItems = [];
            // Items at or below their low-stock level (only for items that
            // track stock and have a level set). An archived item is not reordered.
            $lowStock = $this->company->products()->where('is_active', true)->where('track_stock', true)->whereNotNull('low_stock_level')
                ->withSum('stockMovements', 'quantity')->get()
                ->filter(fn ($p) => $p->stockOnHand() <= (float) $p->low_stock_level);
            if ($lowStock->isNotEmpty()) {
                $laterItems[] = [
                    'tone' => 'accent',
                    'icon' => 'stock',
                    'title' => $lowStock->count() === 1
                        ? $lowStock->first()->name.' is low on stock'
                        : $lowStock->count().' items are low on stock',
                    'sub' => 'At or below the reorder level',
                    'cta' => 'View',
                    'link' => ['route' => 'reports.show', 'params' => ['report' => 'stock-summary', 'status' => 'low']],
                ];
            }

            $staleDrafts = $this->invoices()
                ->where('status', 'draft')
                ->where('created_at', '<', now()->subDays(7))
                ->count();
            if ($staleDrafts > 0) {
                $laterItems[] = [
                    'tone' => 'accent',
                    'icon' => 'draft',
                    'title' => $staleDrafts === 1 ? '1 draft older than a week' : "{$staleDrafts} drafts older than a week",
                    'sub' => 'Issue or delete them',
                    'cta' => 'View',
                    'link' => ['route' => 'invoices.index', 'params' => ['status' => 'draft']],
                ];
            }

            // Bank statement lines not yet matched to anything in the books.
            if (Team::can('bank.index')) {
                $unmatched = BankStatementLine::where('company_id', $this->company->id)
                    ->where('status', 'unmatched')->count();
                if ($unmatched > 0) {
                    array_unshift($laterItems, [
                        'tone' => 'brand',
                        'icon' => 'bank',
                        'title' => $unmatched === 1 ? '1 bank line to review' : "{$unmatched} bank lines to review",
                        'sub' => 'Match them to invoices, bills or expenses',
                        'cta' => 'Review',
                        'link' => ['route' => 'bank.index', 'params' => []],
                    ]);
                }
            }

            // A return joins the list a week before it is due and stays for a
            // few days after. We cannot see the portal, so after that the GST
            // card still shows the date, without nagging.
            $gst = $this->gstCalendar();
            if ($gst) {
                $startOfToday = now()->startOfDay();
                $daysTo = fn ($date) => (int) $startOfToday->diffInDays($date->copy()->startOfDay(), false);
                $dueLine = fn (int $days, $date): string => match (true) {
                    $days < 0 => 'Was due '.$date->format('d M').'. File it if you have not yet.',
                    $days === 0 => 'Due today',
                    $days === 1 => 'Due tomorrow, '.$date->format('d M'),
                    default => 'Due '.$date->format('d M').', in '.$days.' days',
                };
                $label = $gst['label'];
                $d1 = $daysTo($gst['gstr1_due']);
                if ($d1 <= 7 && $d1 >= -5) {
                    $items[] = [
                        'tone' => $d1 <= 0 ? 'danger' : 'accent',
                        'icon' => 'gst',
                        'title' => "GSTR-1 for {$label}",
                        'sub' => $dueLine($d1, $gst['gstr1_due']),
                        'cta' => 'Open',
                        'link' => ['route' => 'finance.gstr1', 'params' => $gst['query']],
                    ];
                }
                $d3 = $daysTo($gst['gstr3b_due']);
                if (! $gst['gstr2b_uploaded'] && now()->gte($gst['gstr2b_from']) && $d3 >= 0) {
                    $items[] = [
                        'tone' => 'accent',
                        'icon' => 'gst',
                        'title' => "Upload GSTR-2B for {$label}",
                        'sub' => 'Match supplier invoices before you file GSTR-3B',
                        'cta' => 'Upload',
                        'link' => ['route' => 'finance.gstr2b', 'params' => ['month' => $gst['query']['month']]],
                    ];
                }
                if ($d3 <= 7 && $d3 >= -5) {
                    $items[] = [
                        'tone' => $d3 <= 0 ? 'danger' : 'accent',
                        'icon' => 'gst',
                        'title' => "GSTR-3B for {$label}"
                            .($gst['cash'] > 0 ? ' · ₹'.rupees($gst['cash']).' to pay' : ''),
                        'sub' => $dueLine($d3, $gst['gstr3b_due']),
                        'cta' => 'Open',
                        'link' => ['route' => 'finance.gstr3b', 'params' => $gst['query']],
                    ];
                }
            }

            return array_merge($items, $laterItems);
        });
    }

    /**
     * "Who owes you": the five customers with the largest unpaid balance on
     * issued invoices, in the same currency and on the same basis as the
     * Outstanding tile. Empty when the role cannot open customer ledgers.
     * Each customer carries owed, oldest_due (oldest open due date),
     * oldest_overdue_due, overdue_days and remind_url (WhatsApp, or null).
     */
    public function whoOwes(): Collection
    {
        return $this->remember('whoOwes', function () {
            if (! Team::can('customers.ledger')) {
                return collect();
            }
            $company = $this->company;
            $owing = fn ($q) => $q->where('company_id', $company->id)
                ->where('currency', $this->currency())
                ->whereIn('status', self::OPEN)
                ->where('balance', '>', 0);
            $indiaToday = Invoice::todayInIndia();

            return $company->customers()
                ->whereHas('invoices', $owing)
                ->withSum(['invoices as owed' => $owing], 'balance')
                ->withMin(['invoices as oldest_overdue_due' => fn ($q) => $owing($q)
                    ->whereNotNull('due_date')->where('due_date', '<', $indiaToday)], 'due_date')
                ->withMin(['invoices as oldest_due' => $owing], 'due_date')
                ->orderByDesc('owed')
                ->orderBy('name')
                ->take(5)
                ->get()
                ->each(function ($c) use ($company, $indiaToday) {
                    $c->overdue_days = $c->oldest_overdue_due
                        ? (int) Carbon::parse($c->oldest_overdue_due)->startOfDay()->diffInDays(Carbon::parse($indiaToday))
                        : 0;
                    $digits = Invoice::whatsAppNumber($c->phone);
                    $c->remind_url = $digits
                        ? 'https://wa.me/'.$digits.'?text='.rawurlencode(
                            "Namaste {$c->name}, this is a reminder from {$company->name} that ₹".rupees($c->owed)
                            .' is due on your account. Please arrange the payment at the earliest. Thank you.')
                        : null;
                });
        });
    }

    /**
     * Outstanding receivables by days past due (the due date, or the invoice
     * date when none was set; not yet due counts as 0-30), on the same basis
     * as the Outstanding tile. Buckets follow App\Services\Aging.
     *
     * @return array<int, array{bucket: string, label: string, amount: float, count: int}>
     */
    public function aging(): array
    {
        return $this->remember('aging', function () {
            $names = ['b0_30' => '0-30', 'b31_60' => '31-60', 'b61_90' => '61-90', 'b90' => '90+'];
            $out = [];
            foreach (Aging::BUCKETS as $key => $label) {
                $out[$key] = ['bucket' => $names[$key], 'label' => $label, 'amount' => 0.0, 'count' => 0];
            }
            $today = now()->startOfDay();
            $this->invoices()->where('currency', $this->currency())
                ->whereIn('status', self::OPEN)
                ->where('balance', '>', 0)
                ->get(['id', 'due_date', 'invoice_date', 'balance'])
                ->each(function ($inv) use (&$out, $today) {
                    $b = Aging::bucketFor(Aging::daysPastDue($inv->due_date ?? $inv->invoice_date, $today));
                    $out[$b]['amount'] += (float) $inv->balance;
                    $out[$b]['count']++;
                });

            return array_values(array_map(fn ($row) => array_merge($row, ['amount' => round($row['amount'], 2)]), $out));
        });
    }

    /**
     * This financial year's invoices (by invoice date, default currency) by
     * payment state. Overdue wins over paid-in-part / unpaid: an issued bill
     * with a balance and a due date in the past. A fully credited bill (balance
     * 0) counts as paid. Amounts are the bill totals.
     *
     * @return array<int, array{status: string, count: int, amount: float}>
     */
    public function statusBreakdown(): array
    {
        return $this->remember('statusBreakdown', function () {
            [$fyStartYear] = Company::financialYearFor(now());
            $today = now()->toDateString();
            $out = [];
            foreach (['paid', 'partially_paid', 'unpaid', 'overdue', 'draft', 'cancelled'] as $s) {
                $out[$s] = ['status' => $s, 'count' => 0, 'amount' => 0.0];
            }

            $this->invoices()->where('currency', $this->currency())
                ->whereDate('invoice_date', '>=', Carbon::create($fyStartYear, 4, 1)->toDateString())
                ->get(['id', 'status', 'balance', 'due_date', 'grand_total'])
                ->each(function ($inv) use (&$out, $today) {
                    $owed = in_array($inv->status, self::OPEN, true) && (float) $inv->balance > 0;
                    $state = match (true) {
                        $inv->status === 'draft' => 'draft',
                        $inv->status === 'cancelled' => 'cancelled',
                        $owed && $inv->due_date && $inv->due_date->toDateString() < $today => 'overdue',
                        ! $owed || $inv->status === 'paid' => 'paid',
                        $inv->status === 'partially_paid' => 'partially_paid',
                        default => 'unpaid',
                    };
                    $out[$state]['count']++;
                    $out[$state]['amount'] += (float) $inv->grand_total;
                });

            return array_values(array_map(fn ($row) => array_merge($row, ['amount' => round($row['amount'], 2)]), $out));
        });
    }
}
