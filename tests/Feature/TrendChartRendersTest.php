<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Revenue vs Expenses bars on Finance · P&L.
 *
 * This chart shipped drawing nothing. The data was always right - the bug
 * was one CSS class: `items-end` on the outer flex row shrink-wrapped each
 * month column to its label, so the bar row inside had no definite height
 * and every percentage bar resolved to 0px. A page-loads test would have
 * passed the whole time, which is why this one asserts both halves: that
 * the heights come from the figures, and that the container still gives the
 * columns a height to be a percentage of.
 */
class TrendChartRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pnl_trend_chart_draws_bars_scaled_to_the_months_figures(): void
    {
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create(['state_id' => $state->id, 'onboarded_at' => now()]);
        $user->switchCompany($company);
        $customer = Customer::factory()->recycle($user)->recycle($company)->create(['state_id' => $state->id]);

        // Two months, the older one at half the revenue of the newer.
        foreach ([['back' => 0, 'rev' => 20000, 'exp' => 5000], ['back' => 1, 'rev' => 10000, 'exp' => 2500]] as $m) {
            $date = now()->subMonths($m['back'])->startOfMonth()->addDays(3)->toDateString();
            Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
                ->create(['invoice_date' => $date, 'subtotal' => $m['rev'], 'grand_total' => $m['rev'] * 1.18]);
            Expense::factory()->recycle($user)->recycle($company)
                ->create(['entry_date' => $date, 'amount' => $m['exp']]);
        }

        $html = $this->actingAs($user)->get(route('finance.index'))->assertOk()->getContent();

        // The column has to stretch to the container's height, or the bars
        // below have nothing to be a percentage of and all render at zero.
        $this->assertStringContainsString('flex items-stretch gap-1 h-40', $html);
        $this->assertStringNotContainsString('flex items-end gap-1 h-40', $html);

        preg_match_all('/style="height: (\d+)%"/', $html, $matches);
        $heights = array_map('intval', $matches[1]);

        // 24 bars: revenue + expenses for each of the 12 months.
        $this->assertCount(24, $heights);
        // The biggest month sets the scale, and half the revenue is half the bar.
        $this->assertSame(100, max($heights));
        $this->assertContains(50, $heights);
    }
}
