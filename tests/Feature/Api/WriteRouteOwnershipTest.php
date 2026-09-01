<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The six API write routes had no test of any kind. The interesting question
 * is not "does store work" but "can one user reach another user's rows" -
 * these endpoints take an id straight from the URL, so a missing ownership
 * check is a data breach rather than a bug.
 */
class WriteRouteOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function userWithCompany(): array
    {
        // The GSTIN rule cross-checks the leading two digits against the
        // state's GST code, so the fixture has to agree with itself.
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'state_id' => $state->id, 'onboarded_at' => now(),
        ]);
        return [$user, $company];
    }

    public function test_a_user_can_create_and_update_their_own_customer(): void
    {
        [$user, $company] = $this->userWithCompany();
        $state = State::first();

        $create = $this->actingAs($user)->postJson('/api/customers', [
            'name' => 'Kirana Store', 'state_id' => $state->id,
        ]);
        $create->assertSuccessful();

        $customer = Customer::where('name', 'Kirana Store')->firstOrFail();
        $this->assertSame($user->id, $customer->user_id);
        $this->assertSame($company->id, $customer->company_id);

        $this->actingAs($user)
            ->putJson("/api/customers/{$customer->id}", ['name' => 'Kirana Store Ltd', 'state_id' => $state->id])
            ->assertSuccessful();

        $this->assertSame('Kirana Store Ltd', $customer->fresh()->name);
    }

    public function test_a_user_cannot_read_write_or_delete_another_users_customer(): void
    {
        [$owner] = $this->userWithCompany();
        [$intruder] = $this->userWithCompany();
        $state = State::first();

        $customer = Customer::factory()->recycle($owner)->create(['name' => 'Private Client']);

        $this->actingAs($intruder)
            ->putJson("/api/customers/{$customer->id}", ['name' => 'Hijacked', 'state_id' => $state->id])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertForbidden();

        $this->assertSame('Private Client', $customer->fresh()->name);
        $this->assertNotNull($customer->fresh());
    }

    public function test_a_user_cannot_write_or_delete_another_users_product(): void
    {
        [$owner] = $this->userWithCompany();
        [$intruder] = $this->userWithCompany();

        $product = Product::create([
            'user_id' => $owner->id,
            'company_id' => $owner->companies()->first()->id,
            'name' => 'Consulting hour',
            'kind' => 'service',
            'hsn_sac' => '998314',
            'unit' => 'HRS',
            'rate' => 1500,
            'gst_rate' => 18,
        ]);

        $this->actingAs($intruder)
            ->putJson("/api/products/{$product->id}", ['name' => 'Hijacked', 'rate' => 1])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->deleteJson("/api/products/{$product->id}")
            ->assertForbidden();

        $this->assertSame('Consulting hour', $product->fresh()->name);
    }

    public function test_the_api_write_routes_reject_anonymous_callers(): void
    {
        [$owner] = $this->userWithCompany();
        $customer = Customer::factory()->recycle($owner)->create();

        $this->postJson('/api/customers', ['name' => 'X'])->assertUnauthorized();
        $this->putJson("/api/customers/{$customer->id}", ['name' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/customers/{$customer->id}")->assertUnauthorized();
        $this->postJson('/api/products', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_validation_rejects_an_empty_customer(): void
    {
        [$user] = $this->userWithCompany();

        $this->actingAs($user)->postJson('/api/customers', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_a_gstin_is_stored_upper_case_through_the_api(): void
    {
        // The web forms cast this; the API is a second door onto the same data
        // and must not be able to write a lower-case GSTIN.
        [$user] = $this->userWithCompany();
        $state = State::first();

        $this->actingAs($user)->postJson('/api/customers', [
            'name' => 'Case Test', 'state_id' => $state->id, 'gstin' => '27aaact2727q1zw',
        ])->assertSuccessful();

        $this->assertSame('27AAACT2727Q1ZW', Customer::where('name', 'Case Test')->first()->gstin);
    }
}
