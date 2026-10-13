<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's month card, and the chrome wrapped around every signed-in
 * page.
 *
 * The card is the one an owner glances at and believes without checking, and
 * it was showing a collections figure under an "Invoiced this month" heading -
 * a month of trading read as a month of missing sales.
 */
class DashboardAndAppChromeTest extends TestCase
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

    public function test_invoiced_this_month_shows_what_was_invoiced_not_what_was_collected(): void
    {
        [$user, $company, $customer] = $this->fixture();
        $thisMonth = now()->startOfMonth()->addDay();

        // Issued for 1,18,000, of which 20,000 has come in.
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()->create([
            'invoice_date' => $thisMonth->toDateString(), 'finalized_at' => $thisMonth,
            'grand_total' => 118000, 'paid_amount' => 20000, 'balance' => 98000,
            'status' => 'partially_paid',
        ]);
        // A draft is not invoiced, and a cancelled bill is not owed.
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create([
            'invoice_date' => $thisMonth->toDateString(), 'grand_total' => 50000, 'status' => 'draft',
        ]);
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()->create([
            'invoice_date' => $thisMonth->toDateString(), 'finalized_at' => $thisMonth,
            'grand_total' => 70000, 'status' => 'cancelled',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // The headline is the invoiced total, with the collected figure as the
        // supporting line - not the other way round.
        $response->assertSee('title="₹1,18,000"', false);
        // The collected part is a share (the next tile is the rupees received).
        $response->assertSee('₹20,000 of it received', false);
        $response->assertSee('17% of it collected', false);   // 20,000 of 1,18,000
    }

    public function test_the_month_card_links_to_that_months_invoices(): void
    {
        // The KPI cards only exist past first run - a dashboard with no
        // invoices deliberately shows the setup checklist instead.
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['finalized_at' => now()->subDays(3)]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee(
            route('invoices.index', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ])
            // escaped, because Blade writes the query separator as &amp;
        );
    }

    public function test_the_referral_email_link_is_encoded_for_a_mailto_not_a_form_body(): void
    {
        // urlencode() turns every space into "+", which RFC 6068 reads as a
        // literal plus, so the invite opened as "Try+Apna+Invoice".
        [$user] = $this->fixture();

        $html = $this->actingAs($user)->get(route('referrals.index'))->assertOk()->getContent();

        $this->assertStringContainsString('mailto:?subject=Try%20Apna%20Invoice', $html);
        $this->assertStringNotContainsString('mailto:?subject=Try+Apna+Invoice', $html);
    }

    public function test_signed_in_pages_carry_the_quiet_footer_not_the_marketing_one(): void
    {
        // The "minimal" footer was the full dark-green marketing block -
        // public nav links, social icons and badges under every invoice list.
        [$user] = $this->fixture();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Made in India')
            ->assertDontSee('GST Ready')
            ->assertSee('Datasoft Technologies');
    }

    public function test_the_key_figures_are_labelled_by_what_they_actually_count(): void
    {
        // Payments received is "Received this month" and invoices raised are
        // "Sales this month". Calling either "Revenue" put a second meaning of
        // the word on the same screen. The 30-day payments line was dropped:
        // it repeated the received figure for a slightly different window.
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['finalized_at' => now()->subDays(3)]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Sales this month')
            ->assertSee('Received this month')
            ->assertSee('Outstanding receivables')
            ->assertDontSee('Revenue · last 30 days', false);
    }
}
