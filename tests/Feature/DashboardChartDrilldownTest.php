<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Where the dashboard charts send you, and what they cost to draw.
 *
 * Every bar is a link into a filtered list, so the charts are only as good as
 * the screens on the other end. And the panel added four charts and two rings
 * to a page that already ran a lot of queries, which is worth a number rather
 * than a hope.
 */
class DashboardChartDrilldownTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create(['state_id' => $state->id, 'onboarded_at' => now()]);
        $user->switchCompany($company);

        return $user;
    }

    public function test_a_purchase_bar_opens_the_expenses_it_represents(): void
    {
        $user = $this->owner();
        $company = $user->companies()->first();
        $month = now()->startOfMonth();

        Expense::factory()->recycle($user)->recycle($company)->create([
            'category' => 'rent', 'description' => 'Shop rent for the month',
            'entry_date' => $month->copy()->addDays(3)->toDateString(),
            'amount' => 20000, 'gst_amount' => 3600,
        ]);
        Expense::factory()->recycle($user)->recycle($company)->create([
            'category' => 'fuel', 'description' => 'Diesel last quarter',
            'entry_date' => $month->copy()->subMonths(4)->toDateString(),
            'amount' => 5000, 'gst_amount' => 0,
        ]);

        $row = collect($this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->viewData('chartSeries')['purchases']['monthly'])
            ->firstWhere('full', $month->format('F Y'));

        // Follow the link the bar actually carries, not one rebuilt here.
        $this->actingAs($user)->get($row['url'])
            ->assertOk()
            ->assertSee('Shop rent for the month')
            ->assertDontSee('Diesel last quarter');
    }

    public function test_a_hand_edited_date_range_widens_the_list_rather_than_erroring(): void
    {
        $user = $this->owner();
        $company = $user->companies()->first();
        Expense::factory()->recycle($user)->recycle($company)->create([
            'category' => 'rent', 'description' => 'Still listed',
            'entry_date' => now()->toDateString(), 'amount' => 1000, 'gst_amount' => 0,
        ]);

        // These arrive from links, bookmarks and hand-edited URLs. The invoice
        // list already refuses to 500 on a mistyped date; the expense list is
        // reachable the same way and now that the dashboard bars link into it
        // with ?period=custom, so is this.
        $this->actingAs($user)
            ->get(route('finance.expenses', ['period' => 'custom', 'from' => 'not-a-date', 'to' => '??']))
            ->assertOk()
            ->assertSee('Still listed');
    }

    public function test_the_dashboard_does_not_run_a_query_per_bar(): void
    {
        $user = $this->owner();
        $company = $user->companies()->first();

        // Thirty months of spend across many categories: if any of the panel's
        // work were per-bucket or per-row, this is where it would show.
        foreach (range(0, 29) as $back) {
            Expense::factory()->recycle($user)->recycle($company)->create([
                'category' => ['rent', 'fuel', 'travel', 'software'][$back % 4],
                'entry_date' => now()->startOfMonth()->subMonths($back)->toDateString(),
                'amount' => 1000 + $back, 'gst_amount' => 100,
            ]);
        }

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Not a performance target, a shape check: four bar charts and two
        // rings off a fixed handful of grouped queries. If someone reintroduces
        // a query per month or per category this jumps by dozens.
        $this->assertLessThan(60, $count, "the dashboard ran {$count} queries");
    }
}
