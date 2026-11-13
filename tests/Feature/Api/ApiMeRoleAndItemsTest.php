<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The app hides what a role cannot open (from /me), and the item form takes the
 * same stock fields and low-stock filter as the web.
 */
class ApiMeRoleAndItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $state = State::factory()->create(['gst_code' => '27']);
        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $this->owner->id, 'state_id' => $state->id, 'onboarded_at' => now()]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
    }

    public function test_me_reports_the_owner_and_member_roles(): void
    {
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('role', 'owner')
            ->assertJsonPath('can.reports', true)
            ->assertJsonPath('can.team', true);

        $staff = User::factory()->create(['email' => 'staff@shop.in']);
        Company::factory()->create(['user_id' => $staff->id]);
        $this->company->members()->create(['email' => 'staff@shop.in', 'role' => 'staff', 'token' => CompanyMember::newToken(),
            'invited_by' => $this->owner->id, 'user_id' => $staff->id, 'accepted_at' => now()]);
        $staff->forceFill(['active_company_id' => $this->company->id])->save();

        Sanctum::actingAs($staff->fresh());
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('role', 'staff')
            ->assertJsonPath('can.reports', false)
            ->assertJsonPath('can.purchases', false)
            ->assertJsonPath('can.invoices_delete', false)
            ->assertJsonPath('can.team', false);
    }

    public function test_items_take_stock_fields_and_filter_low_stock(): void
    {
        Sanctum::actingAs($this->owner);
        $base = ['kind' => 'goods', 'unit' => 'NOS', 'rate' => 100, 'gst_rate' => 18];

        $low = $this->postJson('/api/products', $base + ['name' => 'Bulb', 'track_stock' => true, 'opening_stock' => 3, 'low_stock_level' => 5])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.track_stock', true)
            ->assertJsonPath('data.stock', 3)
            ->json('data.id');
        $this->postJson('/api/products', $base + ['name' => 'Fan', 'track_stock' => true, 'opening_stock' => 50, 'low_stock_level' => 5])->assertCreated();
        // services never track stock
        $this->postJson('/api/products', ['kind' => 'service', 'unit' => 'OTH', 'rate' => 500, 'gst_rate' => 18, 'name' => 'Repair', 'track_stock' => true])
            ->assertCreated()->assertJsonPath('data.track_stock', false);

        $this->getJson('/api/products?stock=low')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $low);

        $this->getJson('/api/units')->assertOk()->assertJsonStructure(['data' => [['code']]]);
        $this->assertSame(1, Product::where('name', 'Bulb')->where('is_active', true)->count());
    }
}
