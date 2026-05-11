<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_current_users_customers(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceCustomer = Customer::factory()->recycle($alice)->create(['name' => 'Alice Co']);
        $bobCustomer = Customer::factory()->recycle($bob)->create(['name' => 'Bob Co']);

        $response = $this->actingAs($alice)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('Alice Co');
        $response->assertDontSee('Bob Co');
    }

    public function test_user_can_create_their_own_customer(): void
    {
        $user = User::factory()->create();
        $state = State::factory()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'Acme Traders',
            'state_id' => $state->id,
            'country' => 'India',
            'email' => 'billing@acme.example',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', ['name' => 'Acme Traders', 'user_id' => $user->id]);
    }

    public function test_user_cannot_mass_assign_user_id_to_spoof_ownership(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $state = State::factory()->create();

        $this->actingAs($alice)->post(route('customers.store'), [
            'name' => 'Hijack Attempt',
            'user_id' => $bob->id,
            'state_id' => $state->id,
            'country' => 'India',
        ]);

        // Even with user_id in request, the customer belongs to the authenticated user.
        $this->assertDatabaseHas('customers', ['name' => 'Hijack Attempt', 'user_id' => $alice->id]);
        $this->assertDatabaseMissing('customers', ['name' => 'Hijack Attempt', 'user_id' => $bob->id]);
    }

    public function test_user_cannot_edit_another_users_customer(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobCustomer = Customer::factory()->recycle($bob)->create();

        $response = $this->actingAs($alice)->get(route('customers.edit', $bobCustomer));

        $response->assertStatus(403);
    }

    public function test_user_cannot_update_another_users_customer(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobCustomer = Customer::factory()->recycle($bob)->create(['name' => 'Bob Original']);

        $this->actingAs($alice)->patch(route('customers.update', $bobCustomer), [
            'name' => 'Hijacked',
            'state_id' => $bobCustomer->state_id,
            'country' => 'India',
        ])->assertStatus(403);

        $this->assertDatabaseHas('customers', ['id' => $bobCustomer->id, 'name' => 'Bob Original']);
    }

    public function test_user_cannot_delete_another_users_customer(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobCustomer = Customer::factory()->recycle($bob)->create();

        $this->actingAs($alice)->delete(route('customers.destroy', $bobCustomer))
            ->assertStatus(403);

        $this->assertDatabaseHas('customers', ['id' => $bobCustomer->id]);
    }

    public function test_gstin_validation_rejects_malformed(): void
    {
        $user = User::factory()->create();
        $state = State::factory()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'Test',
            'gstin' => 'INVALID',
            'state_id' => $state->id,
            'country' => 'India',
        ]);

        $response->assertSessionHasErrors('gstin');
    }

    public function test_state_id_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'Missing state',
            'country' => 'India',
        ])->assertSessionHasErrors('state_id');
    }

    public function test_unauthenticated_user_cannot_access_customers(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->post(route('customers.store'), [])->assertRedirect(route('login'));
    }

    /* ──────────────────────────────────────────────────────────────────
     * Inline "Quick add customer" modal — JSON endpoint used from the
     * invoice / quotation forms so the user doesn't lose typed work.
     * ────────────────────────────────────────────────────────────────── */

    public function test_quick_create_returns_201_and_attaches_to_active_company(): void
    {
        $user = User::factory()->create();
        $company = \App\Models\Company::factory()->recycle($user)->create();
        $user->switchCompany($company);
        $state = State::factory()->create();

        $response = $this->actingAs($user)->postJson(route('customers.quick-create'), [
            'name' => 'Acme Quick LLP',
            'state_id' => $state->id,
            'phone' => '+91 99999 88888',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Acme Quick LLP',
                'state_id' => $state->id,
                'state_name' => $state->name,
                'has_gstin' => false,
            ])
            ->assertJsonStructure(['id', 'name', 'state_id', 'state_name', 'state_gst_code', 'phone', 'email', 'gstin', 'has_gstin', 'message']);

        $this->assertDatabaseHas('customers', [
            'name' => 'Acme Quick LLP',
            'user_id' => $user->id,
            'company_id' => $company->id,
            'state_id' => $state->id,
            'country' => 'India',
        ]);
    }

    public function test_quick_create_validates_required_name_and_state(): void
    {
        $user = User::factory()->create();
        \App\Models\Company::factory()->recycle($user)->create();

        $this->actingAs($user)->postJson(route('customers.quick-create'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'state_id']);
    }

    public function test_quick_create_rejects_invalid_email(): void
    {
        $user = User::factory()->create();
        \App\Models\Company::factory()->recycle($user)->create();
        $state = State::factory()->create();

        $this->actingAs($user)->postJson(route('customers.quick-create'), [
            'name' => 'Test', 'state_id' => $state->id, 'email' => 'not-an-email',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_quick_create_requires_authentication(): void
    {
        $state = State::factory()->create();
        $this->postJson(route('customers.quick-create'), [
            'name' => 'Anon', 'state_id' => $state->id,
        ])->assertStatus(401);
    }
}
