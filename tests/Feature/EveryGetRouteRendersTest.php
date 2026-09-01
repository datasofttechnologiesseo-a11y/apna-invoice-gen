<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Walks the router itself rather than a hand-written list of screens.
 *
 * The hand-written list in PreflightSmokeTest drifts: it had no admin routes
 * at all, so a view that referenced a missing array key rendered a 500 in
 * production and nothing failed. Enumerating Route::getRoutes() means every
 * screen added from here on is covered without anyone remembering to add it.
 *
 * This asserts only "does not blow up" - 200/302/403/404 are all acceptable
 * outcomes for a GET. A 500 is not.
 */
class EveryGetRouteRendersTest extends TestCase
{
    use RefreshDatabase;

    /** GET routes that legitimately mutate state or stream binaries. */
    private const SKIP = ['logout', 'api/backup/download', 'backup/download'];

    public function test_no_get_route_returns_a_server_error(): void
    {
        $state = State::factory()->create(['gst_code' => '27']);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $company = Company::factory()->recycle($admin)->create([
            'state_id' => $state->id, 'onboarded_at' => now(),
        ]);
        $customer = Customer::factory()->recycle($admin)->recycle($company)->create();
        $invoice = Invoice::factory()->recycle($admin)->recycle($company)->recycle($customer)
            ->create(['status' => 'final', 'finalized_at' => now()]);

        // Values for the common route parameters, so parameterised screens
        // are exercised too rather than skipped.
        $bind = [
            'user' => $admin->id, 'company' => $company->id, 'customer' => $customer->id,
            'invoice' => $invoice->id, 'id' => $invoice->id,
        ];

        $checked = 0;
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = $route->uri();
            if (in_array($uri, self::SKIP, true) || str_starts_with($uri, '_')) {
                continue;
            }

            // Fill what we can; skip anything still holding a parameter.
            $filled = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($bind) {
                return $bind[$m[1]] ?? '__SKIP__';
            }, $uri);
            if (str_contains($filled, '__SKIP__')) {
                continue;
            }

            $res = $this->actingAs($admin)->get('/'.ltrim($filled, '/'));
            $checked++;

            if ($res->getStatusCode() >= 500) {
                $failures[] = "/{$filled} -> {$res->getStatusCode()}";
            }
        }

        $this->assertGreaterThan(40, $checked, 'expected the router to expose many GET screens');
        $this->assertSame([], $failures, "server errors:\n".implode("\n", $failures));
    }
}
