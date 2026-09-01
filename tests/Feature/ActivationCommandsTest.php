<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivationCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function activatedUser(): User
    {
        $state = State::factory()->create();
        $user = User::factory()->create(['created_at' => now()->subDays(30)]);
        $company = Company::factory()->recycle($user)->create([
            'state_id' => $state->id, 'onboarded_at' => now(),
        ]);
        $customer = Customer::factory()->recycle($user)->recycle($company)->create();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)
            ->create(['status' => 'final', 'finalized_at' => now()]);

        return $user;
    }

    private function stalledUser(int $daysAgo = 30): User
    {
        $user = User::factory()->create(['created_at' => now()->subDays($daysAgo)]);
        Company::factory()->recycle($user)->create(['name' => '', 'state_id' => null]);

        return $user;
    }

    public function test_report_runs_and_shows_the_funnel(): void
    {
        $this->activatedUser();
        $this->stalledUser();

        $this->artisan('activation:report')
            ->expectsOutputToContain('ACTIVATION FUNNEL')
            ->expectsOutputToContain('Activation rate')
            ->assertSuccessful();
    }

    public function test_report_flags_accounts_the_sequence_cannot_reach(): void
    {
        $this->stalledUser(60);

        $this->artisan('activation:report')
            ->expectsOutputToContain('OUT OF REACH')
            ->assertSuccessful();
    }

    public function test_report_is_read_only(): void
    {
        $this->stalledUser(60);
        $before = DB::table('onboarding_emails')->count();

        $this->artisan('activation:report --stalled')->assertSuccessful();

        $this->assertSame($before, DB::table('onboarding_emails')->count(),
            'the report must never send or record anything');
    }

    public function test_catch_up_sends_nothing_without_the_send_flag(): void
    {
        $this->stalledUser(60);

        $this->artisan('activation:catch-up')
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('onboarding_emails')->where('stage', 'catchup')->count());
    }

    public function test_catch_up_skips_anyone_inside_the_normal_nudge_window(): void
    {
        // Two days old — the scheduled sequence can still reach this person.
        $this->stalledUser(2);

        $this->artisan('activation:catch-up')
            ->expectsOutputToContain('Nobody is waiting')
            ->assertSuccessful();
    }

    public function test_catch_up_skips_users_who_already_made_an_invoice(): void
    {
        $this->activatedUser();

        $this->artisan('activation:catch-up')
            ->expectsOutputToContain('Nobody is waiting')
            ->assertSuccessful();
    }

    public function test_catch_up_never_contacts_the_same_person_twice(): void
    {
        $user = $this->stalledUser(60);
        DB::table('onboarding_emails')->insert([
            'user_id' => $user->id, 'stage' => 'catchup', 'sent_at' => now(),
        ]);

        $this->artisan('activation:catch-up')
            ->expectsOutputToContain('Nobody is waiting')
            ->assertSuccessful();
    }

    public function test_catch_up_rejects_an_unknown_stage(): void
    {
        $this->stalledUser(60);

        $this->artisan('activation:catch-up --stage=nonsense')->assertFailed();
    }
}
