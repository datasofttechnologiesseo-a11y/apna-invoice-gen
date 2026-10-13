<?php

namespace Tests\Feature\Audit\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * A filter sent as a list (?search[]=x, ?from[]=x, ?ref[]=x - one edited
 * URL) reaches string-typed code unvalidated and the page answers 500
 * instead of ignoring it or answering 422. Swept over every GET route
 * without a record in its URL, signed in and signed out.
 */
class ArrayQuerySweepTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    private const QUERY = ['search' => ['x'], 'q' => ['x'], 'status' => ['x'], 'from' => ['x'], 'to' => ['x'], 'month' => ['x'],
        'period' => ['x'], 'category' => ['x'], 'scope' => ['x'], 'account' => ['x'], 'kind' => ['x'], 'method' => ['x'],
        'vendor' => ['x'], 'customer' => ['x'], 'template' => ['x'], 'per_page' => ['x'], 'ref' => ['x'], 'type' => ['x']];

    public function test_a_list_valued_filter_never_crashes_a_page(): void
    {
        $this->freezeClock();
        $a = $this->buildBusiness('A');
        $q = '?' . http_build_query(self::QUERY);
        $bad = [];

        foreach (collect(Route::getRoutes()->getRoutes()) as $route) {
            /** @var RouteDef $route */
            if (! in_array('GET', $route->methods(), true) || $route->parameterNames() !== []
                || in_array($route->uri(), ['logout', 'backup/download', 'api/backup/download'], true)) {
                continue;
            }
            $signedIn = collect($route->gatherMiddleware())->contains(fn ($m) => $m === 'auth' || str_starts_with((string) $m, 'auth:'));
            app('cache')->store()->flush();
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            if ($signedIn && str_starts_with($route->uri(), 'api/')) {
                Sanctum::actingAs($a['owner']->fresh());
                $res = $this->getJson('/' . $route->uri() . $q);
            } elseif ($signedIn) {
                $res = $this->actingAs($a['owner']->fresh())->get('/' . $route->uri() . $q);
            } else {
                $res = $this->get('/' . $route->uri() . $q);
            }
            if ($res->getStatusCode() >= 500) {
                $bad[] = ($signedIn ? '' : '[signed out] ') . '/' . $route->uri() . ' -> ' . $res->getStatusCode() . ': '
                    . substr((string) $res->exception?->getMessage(), 0, 90);
            }
        }

        $this->assertSame([], $bad, count($bad) . " pages crash on a list-valued filter:\n" . implode("\n", $bad));
    }
}
