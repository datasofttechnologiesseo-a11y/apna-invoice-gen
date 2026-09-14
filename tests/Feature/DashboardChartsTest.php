<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The dashboard insights panel: two rings, four bar charts, and the two
 * switches that choose between them (Sales/Purchases and Monthly/Yearly).
 *
 * A tester asked for something more attractive than a line and a row of
 * numbers, and then for it to change with the option chosen. Attractive is not
 * testable; being right is, and a chart that is wrong is worse than no chart,
 * because a shape is believed faster than a number. So what is pinned here is
 * the arithmetic behind each combination:
 *
 *   - the sales ring's three slices are mutually exclusive and add up to
 *     exactly what was billed, with drafts and cancelled bills out of all three
 *   - the purchases ring rolls the long tail of categories into one slice
 *   - the bars put each period's money in that period, monthly and by Indian
 *     financial year, and six calls to subMonths() still produce six distinct
 *     months on the 31st
 */
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Company,2:Customer} */
    private function fixture(): array
    {
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create(['state_id' => $state->id, 'onboarded_at' => now()]);
        $user->switchCompany($company);
        $customer = Customer::factory()->recycle($user)->recycle($company)->create(['state_id' => $state->id]);

        return [$user, $company, $customer];
    }

    private function series(User $user, string $view, string $span): array
    {
        return $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->viewData('chartSeries')[$view][$span];
    }

    public function test_the_sales_ring_splits_collected_due_and_overdue(): void
    {
        [$user, $company, $customer] = $this->fixture();
        $make = fn (array $attrs) => Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create($attrs);

        // Part paid, not yet due.
        $make([
            'status' => 'partially_paid', 'invoice_number' => 'INV-0001', 'finalized_at' => now(),
            'grand_total' => 10000, 'paid_amount' => 4000, 'balance' => 6000,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);
        // Unpaid and late.
        $make([
            'status' => 'final', 'invoice_number' => 'INV-0002', 'finalized_at' => now(),
            'grand_total' => 5000, 'paid_amount' => 0, 'balance' => 5000,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
        // Neither of these is money: one was never issued, the other was voided.
        $make(['status' => 'draft', 'grand_total' => 99999, 'balance' => 99999]);
        $make([
            'status' => 'cancelled', 'invoice_number' => 'INV-0003', 'finalized_at' => now(),
            'grand_total' => 77777, 'balance' => 77777, 'cancelled_at' => now(),
            'due_date' => now()->subDays(30)->toDateString(),
        ]);

        $ring = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('receivables');

        $this->assertSame(4000.0, $ring['collected']);
        $this->assertSame(6000.0, $ring['due']);
        $this->assertSame(5000.0, $ring['overdue']);

        // The slices must account for the two issued bills and nothing else.
        $this->assertSame(15000.0, array_sum($ring));
    }

    public function test_a_bill_with_no_due_date_is_unpaid_but_not_late(): void
    {
        [$user, $company, $customer] = $this->fixture();

        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create([
            'status' => 'final', 'invoice_number' => 'INV-0010', 'finalized_at' => now(),
            'grand_total' => 3000, 'paid_amount' => 0, 'balance' => 3000, 'due_date' => null,
        ]);

        $ring = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('receivables');

        $this->assertSame(3000.0, $ring['due']);
        $this->assertSame(0.0, $ring['overdue']);
    }

    public function test_the_purchases_ring_keeps_six_categories_and_rolls_up_the_rest(): void
    {
        [$user, $company] = $this->fixture();

        // Eight categories, descending. Only six should survive as themselves.
        $categories = ['rent', 'salaries', 'travel', 'marketing', 'software', 'fuel', 'courier', 'food'];
        foreach ($categories as $i => $category) {
            Expense::factory()->recycle($user)->recycle($company)->create([
                'category' => $category,
                'entry_date' => now()->subMonths($i % 6)->toDateString(),
                'amount' => 10000 - ($i * 1000),
                'gst_amount' => 0,
            ]);
        }

        $ring = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('spendByCategory');

        $this->assertCount(7, $ring, 'six named slices plus one catch-all');
        $this->assertSame('Rent', $ring[0]['label']);
        $this->assertSame(config('expense_categories.rent.color'), $ring[0]['color']);

        $last = end($ring);
        $this->assertSame('Everything else', $last['label']);
        // The two smallest, 4,000 + 3,000.
        $this->assertSame(7000.0, $last['amount']);
    }

    public function test_the_purchases_ring_counts_gst_as_money_that_left(): void
    {
        [$user, $company] = $this->fixture();

        Expense::factory()->recycle($user)->recycle($company)->create([
            'category' => 'software', 'entry_date' => now()->toDateString(),
            'amount' => 10000, 'gst_amount' => 1800,
        ]);

        $ring = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('spendByCategory');

        // 11,800 actually left the bank, not 10,000 - the ring is about cash out.
        $this->assertSame(11800.0, $ring[0]['amount']);
    }

    public function test_the_sales_bars_put_each_months_money_in_its_own_month(): void
    {
        [$user, $company, $customer] = $this->fixture();
        $thisMonth = now()->startOfMonth();

        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create([
            'status' => 'final', 'invoice_number' => 'INV-A', 'finalized_at' => now(),
            'grand_total' => 8000, 'balance' => 8000,
            'invoice_date' => $thisMonth->copy()->toDateString(),
        ]);
        $twoBack = $thisMonth->copy()->subMonths(2);
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create([
            'status' => 'final', 'invoice_number' => 'INV-B', 'finalized_at' => $twoBack,
            'grand_total' => 2000, 'balance' => 2000,
            'invoice_date' => $twoBack->copy()->toDateString(),
        ]);
        // A draft in the current month, which is not billing.
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create([
            'status' => 'draft', 'grand_total' => 50000, 'balance' => 50000,
            'invoice_date' => $thisMonth->copy()->toDateString(),
        ]);

        Payment::create([
            'user_id' => $user->id, 'company_id' => $company->id,
            'invoice_id' => Invoice::where('invoice_number', 'INV-B')->value('id'),
            'receipt_number' => 'RCPT-0001', 'received_at' => $twoBack->copy()->addDay(),
            'amount' => 1500, 'method' => 'upi',
        ]);

        $months = collect($this->series($user, 'sales', 'monthly'))->keyBy('full');

        $this->assertSame(8000.0, $months[$thisMonth->format('F Y')]['a']);
        $this->assertSame(0.0, $months[$thisMonth->format('F Y')]['b']);
        $this->assertSame(2000.0, $months[$twoBack->format('F Y')]['a']);
        $this->assertSame(1500.0, $months[$twoBack->format('F Y')]['b']);
    }

    public function test_the_purchase_bars_separate_spend_from_the_gst_inside_it(): void
    {
        [$user, $company] = $this->fixture();
        $thisMonth = now()->startOfMonth();

        Expense::factory()->recycle($user)->recycle($company)->create([
            'category' => 'rent', 'entry_date' => $thisMonth->copy()->addDay()->toDateString(),
            'amount' => 20000, 'gst_amount' => 3600,
        ]);

        $row = collect($this->series($user, 'purchases', 'monthly'))->firstWhere('full', $thisMonth->format('F Y'));

        $this->assertSame(23600.0, $row['a'], 'spend is cash out, GST included');
        $this->assertSame(3600.0, $row['b'], 'the GST component is called out separately');
    }

    public function test_the_yearly_bars_bucket_by_indian_financial_year(): void
    {
        // Mid-FY 2026-27, which runs 1 Apr 2026 → 31 Mar 2027.
        $this->travelTo(Carbon::parse('2026-09-15 10:00:00'));
        [$user, $company, $customer] = $this->fixture();

        $bill = fn (string $date, float $amount, string $number) => Invoice::factory()
            ->recycle($user)->recycle($company)->recycle($customer)->create([
                'status' => 'final', 'invoice_number' => $number, 'finalized_at' => Carbon::parse($date),
                'invoice_date' => $date, 'grand_total' => $amount, 'balance' => $amount,
            ]);

        // Both sides of the 1 April boundary, plus one in the FY before.
        $bill('2026-03-31', 1000, 'INV-PREV-END');   // FY 2025-26
        $bill('2026-04-01', 2000, 'INV-THIS-START'); // FY 2026-27
        $bill('2026-09-10', 3000, 'INV-THIS-MID');   // FY 2026-27

        $years = collect($this->series($user, 'sales', 'yearly'))->keyBy('full');

        $this->assertSame(['FY 2024-2025', 'FY 2025-2026', 'FY 2026-2027'], $years->keys()->all());
        $this->assertSame(0.0, $years['FY 2024-2025']['a']);
        $this->assertSame(1000.0, $years['FY 2025-2026']['a'], '31 March belongs to the FY that is ending');
        $this->assertSame(5000.0, $years['FY 2026-2027']['a'], '1 April opens the new FY');
    }

    public function test_six_months_are_still_six_distinct_months_on_the_thirty_first(): void
    {
        // PHP subtracts a month from 31 March by landing on 3 March. Left
        // alone, that draws March twice and drops February off the chart.
        $this->travelTo(Carbon::parse('2026-03-31 11:00:00'));

        [$user] = $this->fixture();
        $months = $this->series($user, 'sales', 'monthly');

        $this->assertCount(6, $months);
        $this->assertSame(
            ['October 2025', 'November 2025', 'December 2025', 'January 2026', 'February 2026', 'March 2026'],
            array_column($months, 'full')
        );
    }

    public function test_every_bar_drills_into_the_period_it_represents(): void
    {
        [$user] = $this->fixture();

        foreach ($this->series($user, 'sales', 'monthly') as $row) {
            $this->assertSame(
                route('invoices.index', ['from' => $row['from'], 'to' => $row['to']]),
                $row['url']
            );
        }

        foreach ($this->series($user, 'purchases', 'yearly') as $row) {
            $this->assertStringContainsString('period=custom', $row['url']);
            $this->assertStringContainsString('from=' . $row['from'], $row['url']);
        }
    }

    public function test_the_panel_renders_both_switches_and_all_four_charts(): void
    {
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create([
            'status' => 'final', 'invoice_number' => 'INV-0100', 'finalized_at' => now(),
            'grand_total' => 1180, 'paid_amount' => 180, 'balance' => 1000,
        ]);
        Expense::factory()->recycle($user)->recycle($company)->create([
            'category' => 'rent', 'entry_date' => now()->toDateString(), 'amount' => 5000, 'gst_amount' => 900,
        ]);

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // Both switches.
        $page->assertSee("setView('purchases')", false);
        $page->assertSee("setSpan('yearly')", false);

        // Both rings.
        $page->assertSee('Where your money stands');
        $page->assertSee('Where your money went');
        $page->assertSee('pathLength="100"', false);

        // All four bar combinations are in the DOM, so switching needs no request.
        foreach (['sales-monthly', 'sales-yearly', 'purchases-monthly', 'purchases-yearly'] as $combination) {
            $page->assertSee("combo === '{$combination}'", false);
        }
    }

    public function test_no_bar_block_hides_a_dependency_behind_a_short_circuit(): void
    {
        // x-show="view === 'x' && span === 'y'" reads like the obvious way to
        // write this and quietly breaks: && short-circuits, so an effect whose
        // first half is false never reads the second, never registers it as a
        // dependency, and stops responding to that switch for good. Caught in
        // the browser, where the Yearly button moved the label and left the
        // bars alone. Every toggled block therefore tests one value, not two.
        // The panel only renders once the account has data - a first-run
        // dashboard swaps the whole thing for a "make your first invoice" panel.
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create([
            'status' => 'final', 'invoice_number' => 'INV-0300', 'finalized_at' => now(),
            'grand_total' => 1180, 'paid_amount' => 180, 'balance' => 1000,
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->content();

        $start = strpos($html, 'id="insights"');
        $this->assertNotFalse($start, 'the insights panel should be on the page');
        // Markup only - the script block below the panel talks about the bug
        // in prose and would match the pattern it is warning about.
        $insights = preg_replace('#<script[^>]*>.*?</script>#s', '', substr($html, $start));

        preg_match_all('/x-show="([^"]*(?:view|span|combo)[^"]*)"/', $insights, $matches);
        $this->assertNotEmpty($matches[1], 'the panel should have toggled blocks to check');

        foreach ($matches[1] as $expression) {
            $this->assertStringNotContainsString(
                '&&',
                $expression,
                "x-show=\"{$expression}\" reads two reactive values; the second will not be tracked"
            );
        }
    }

    public function test_the_sales_monthly_pair_survives_alpine_never_loading(): void
    {
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create([
            'status' => 'final', 'invoice_number' => 'INV-0200', 'finalized_at' => now(),
            'grand_total' => 1180, 'paid_amount' => 180, 'balance' => 1000,
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->content();

        // x-cloak is display:none until Alpine boots, so exactly one bar chart
        // and one ring must be left uncloaked or a JS failure blanks the panel.
        $this->assertStringContainsString('<div x-show="combo === \'sales-monthly\'">', $html);
        $this->assertStringContainsString('<div x-show="view === \'sales\'">', $html);
    }
}
