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
 * The bell's "new activity" dot, which previously had no read state.
 *
 * It was derived entirely from time windows - payments in the last 7 days,
 * invoices issued in the last 24 h - so opening the dropdown changed nothing
 * and the dot sat on the bell for a week. QA reported it as a defect; it was
 * working as written, and what was written had no way to be finished with.
 *
 * The overdue badge is deliberately excluded from that: an invoice that is
 * still overdue after you have looked at it has not become old news.
 */
class NotificationBellTest extends TestCase
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

    public function test_activity_lights_the_dot_and_opening_the_bell_puts_it_out(): void
    {
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['finalized_at' => now()->subHour()]);

        // x-show="unseen" is only ever rendered on the activity dot.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('x-show="unseen"', false);

        $this->actingAs($user)->post(route('notifications.seen'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($user->fresh()->notifications_seen_at);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('x-show="unseen"', false);
    }

    public function test_activity_newer_than_the_last_look_lights_the_dot_again(): void
    {
        [$user, $company, $customer] = $this->fixture();
        $user->forceFill(['notifications_seen_at' => now()->subHours(2)])->save();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('x-show="unseen"', false);

        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['finalized_at' => now()->subMinutes(5)]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('x-show="unseen"', false);
    }

    public function test_marking_notifications_seen_does_not_silence_an_overdue_invoice(): void
    {
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()->create([
            'finalized_at' => now()->subDays(40),
            'due_date' => now()->subDays(10)->toDateString(),
            'balance' => 11800,
        ]);

        $user->forceFill(['notifications_seen_at' => now()])->save();

        // The red badge counts what is still owed, not what is unread.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('1 need attention');
    }

    public function test_the_endpoint_is_closed_to_guests(): void
    {
        $this->post(route('notifications.seen'))->assertRedirect(route('login'));
    }
}
