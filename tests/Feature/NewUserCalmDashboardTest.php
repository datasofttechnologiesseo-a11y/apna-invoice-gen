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
 * Two separate rules, easy to confuse:
 *
 *  - Navigation is always complete. Every section is reachable from the first
 *    minute; the only restricted item is Admin, which is super-admin only.
 *  - Dashboard *content* is reduced while the account is empty, because a wall
 *    of zeroes and eight actions the user cannot take yet is not useful.
 */
class NewUserCalmDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function freshUser(): User
    {
        $user = User::factory()->create();
        Company::factory()->recycle($user)->create([
            'name' => '', 'state_id' => null, 'onboarded_at' => null,
        ]);

        return $user;
    }

    private function establishedUser(): User
    {
        $state = State::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'name' => 'Sharma Exports', 'state_id' => $state->id, 'onboarded_at' => now(),
        ]);
        $customer = Customer::factory()->recycle($user)->recycle($company)->create();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)
            ->create(['status' => 'final', 'finalized_at' => now()]);

        return $user;
    }

    public function test_a_brand_new_user_still_gets_the_whole_navigation(): void
    {
        $res = $this->actingAs($this->freshUser())->get('/dashboard');

        $res->assertOk();
        foreach (['products.index', 'companies.index', 'finance.index', 'customers.index', 'invoices.index'] as $route) {
            $res->assertSee(route($route));
        }
        $res->assertSee(route('referrals.index'));
        $res->assertSee(route('quotations.create'));
    }

    public function test_admin_is_hidden_from_a_normal_user(): void
    {
        $res = $this->actingAs($this->establishedUser())->get('/dashboard');

        $res->assertOk();
        $res->assertDontSee(route('admin.dashboard'));
    }

    public function test_admin_is_visible_to_a_super_admin(): void
    {
        $user = $this->establishedUser();
        $user->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($user)->get('/dashboard')->assertSee(route('admin.dashboard'));
    }

    public function test_empty_dashboard_hides_stats_the_user_has_no_data_for(): void
    {
        $res = $this->actingAs($this->freshUser())->get('/dashboard');

        $res->assertDontSee('Quick actions');
        $res->assertDontSee('Recent invoices');
        $res->assertDontSee('Reports & exports', false);
        // What they get instead: the numbered checklist and a route to a human.
        $res->assertSee('Step 1');
        $res->assertSee('Need a hand getting started?');
    }

    public function test_the_first_invoice_instruction_is_not_duplicated(): void
    {
        $res = $this->actingAs($this->freshUser())->get('/dashboard');

        // The checklist owns the instruction; the panel below must not repeat it.
        $this->assertSame(
            1,
            substr_count($res->getContent(), 'Make your first invoice'),
            'the "make your first invoice" instruction should appear exactly once'
        );
    }

    public function test_established_user_sees_the_full_dashboard(): void
    {
        $res = $this->actingAs($this->establishedUser())->get('/dashboard');

        $res->assertOk();
        $res->assertSee('Quick actions');
        $res->assertSee('Recent invoices');
        $res->assertSee('Reports & exports', false);
    }
}
