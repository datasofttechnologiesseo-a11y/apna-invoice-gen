<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Post;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Two untested areas that fail badly rather than loudly:
 *
 *  - Product CRUD is the master data every invoice line pulls from.
 *  - The admin write routes had no test of their guard at all. A missing
 *    super-admin check on a destroy route is not a bug report, it is an
 *    incident, so the guard is asserted per verb rather than assumed from the
 *    route group.
 */
class ProductAndAdminGuardTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        Company::factory()->recycle($user)->create(['state_id' => $state->id, 'onboarded_at' => now()]);

        return $user;
    }

    private function productFor(User $user, string $name = 'Consulting hour'): Product
    {
        return Product::create([
            'user_id' => $user->id,
            'company_id' => $user->companies()->first()->id,
            'name' => $name, 'kind' => 'service', 'hsn_sac' => '998314',
            'unit' => 'HRS', 'rate' => 1500, 'gst_rate' => 18,
        ]);
    }

    public function test_a_user_can_create_edit_and_delete_their_own_product(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Website design', 'kind' => 'service', 'hsn_sac' => '998314',
            'unit' => 'NOS', 'rate' => 25000, 'gst_rate' => 18,
        ])->assertRedirect();

        $product = Product::where('name', 'Website design')->firstOrFail();
        $this->assertSame($user->id, $product->user_id);

        $this->actingAs($user)->put(route('products.update', $product), [
            'name' => 'Website design (retainer)', 'kind' => 'service', 'hsn_sac' => '998314',
            'unit' => 'NOS', 'rate' => 30000, 'gst_rate' => 18,
        ])->assertRedirect();
        $this->assertSame('Website design (retainer)', $product->fresh()->name);

        $this->actingAs($user)->delete(route('products.destroy', $product))->assertRedirect();
        $this->assertNull(Product::find($product->id));
    }

    public function test_a_user_cannot_edit_or_delete_another_users_product(): void
    {
        $owner = $this->owner();
        $intruder = $this->owner();
        $product = $this->productFor($owner);

        $this->actingAs($intruder)->put(route('products.update', $product), [
            'name' => 'Hijacked', 'kind' => 'service', 'hsn_sac' => '998314',
            'unit' => 'HRS', 'rate' => 1, 'gst_rate' => 18,
        ])->assertForbidden();

        $this->actingAs($intruder)->delete(route('products.destroy', $product))->assertForbidden();

        $this->assertSame('Consulting hour', $product->fresh()->name);
    }

    public function test_product_search_only_returns_the_callers_own_rows(): void
    {
        $owner = $this->owner();
        $intruder = $this->owner();
        $this->productFor($owner, 'Secret Retainer');

        $res = $this->actingAs($intruder)->getJson(route('products.search', ['q' => 'Secret']));

        $res->assertOk();
        $this->assertStringNotContainsString('Secret Retainer', $res->getContent());
    }

    #[DataProvider('adminWriteRoutes')]
    public function test_admin_write_routes_reject_a_normal_user(string $verb, string $uri): void
    {
        $user = $this->owner();
        Post::factory()->create(['id' => 1]);

        $res = $this->actingAs($user)->call($verb, $uri);

        $this->assertContains($res->getStatusCode(), [403, 404],
            "{$verb} {$uri} let a non-admin through with {$res->getStatusCode()}");
    }

    public static function adminWriteRoutes(): array
    {
        return [
            'create post'  => ['POST',   '/admin/blog'],
            'update post'  => ['PUT',    '/admin/blog/1'],
            'delete post'  => ['DELETE', '/admin/blog/1'],
            'toggle post'  => ['POST',   '/admin/blog/1/toggle'],
            'admin home'   => ['GET',    '/admin'],
            'admin users'  => ['GET',    '/admin/users'],
        ];
    }

    public function test_admin_write_routes_reject_anonymous_callers(): void
    {
        foreach ([['POST', '/admin/blog'], ['DELETE', '/admin/blog/1'], ['GET', '/admin']] as [$verb, $uri]) {
            $res = $this->call($verb, $uri);
            $this->assertContains($res->getStatusCode(), [302, 401, 403, 404],
                "{$verb} {$uri} responded {$res->getStatusCode()} to an anonymous caller");
        }
    }

    public function test_a_super_admin_can_reach_the_admin_area(): void
    {
        // The guard has to reject the right people without locking out the
        // right ones, otherwise the test above would pass on a broken route.
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }
}
