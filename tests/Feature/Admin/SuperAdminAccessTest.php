<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_regular_user_gets_403_on_admin_dashboard(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($user)->get('/admin')->assertStatus(403);
    }

    public function test_regular_user_gets_403_on_admin_users_list(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($user)->get('/admin/users')->assertStatus(403);
    }

    public function test_super_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Admin Console');
    }

    public function test_super_admin_can_view_users_list(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/admin/users');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_view_user_detail(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.show', $target));
        $response->assertStatus(200);
        $response->assertSee($target->name);
    }

    public function test_make_super_admin_command_promotes_user(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'is_super_admin' => false]);

        $this->artisan('app:make-super-admin', ['email' => 'owner@example.com'])
            ->assertExitCode(0);

        $this->assertTrue($user->fresh()->isSuperAdmin());
    }

    public function test_make_super_admin_command_revokes_with_flag(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com', 'is_super_admin' => true]);

        $this->artisan('app:make-super-admin', ['email' => 'boss@example.com', '--revoke' => true])
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->isSuperAdmin());
    }

    public function test_make_super_admin_command_errors_on_missing_user(): void
    {
        $this->artisan('app:make-super-admin', ['email' => 'nobody@example.com'])
            ->assertExitCode(1);
    }

    public function test_super_admin_can_access_invoices_list(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->get(route('admin.invoices'))->assertStatus(200);
    }

    public function test_super_admin_can_access_companies_list(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->get(route('admin.companies'))->assertStatus(200);
    }

    public function test_super_admin_can_access_customers_list(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->get(route('admin.customers'))->assertStatus(200);
    }

    public function test_super_admin_can_impersonate_regular_user(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $target = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $target))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);
        $this->assertSame($admin->id, session('impersonator_id'));
    }

    public function test_cannot_impersonate_another_super_admin(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $otherAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $otherAdmin))
            ->assertStatus(403);
    }

    public function test_stop_impersonation_returns_to_original_admin(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $target = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($admin)->post(route('admin.users.impersonate', $target));
        $this->post(route('admin.impersonation.stop'))->assertRedirect(route('admin.users'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_regular_user_cannot_impersonate(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.users.impersonate', $target))
            ->assertStatus(403);
    }
}
