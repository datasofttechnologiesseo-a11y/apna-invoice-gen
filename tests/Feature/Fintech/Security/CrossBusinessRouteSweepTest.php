<?php

namespace Tests\Feature\Fintech\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\Feature\Fintech\Security\Concerns\SweepsRecordRoutes;
use Tests\TestCase;

/**
 * IDOR sweep driven by the router itself: every route in routes/web.php and
 * routes/api.php that names a record in its URL is called by business A with
 * business B's record. Each must answer 403 or 404 and leave every table
 * exactly as it was; a GET must not carry any of B's SECRET-B markers.
 *
 * The list is generated from Route::getRoutes(), so a route added tomorrow
 * is swept tomorrow. A URL parameter this test does not know how to fill
 * fails the test instead of being skipped silently.
 */
class CrossBusinessRouteSweepTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;
    use SweepsRecordRoutes;

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->a = $this->buildBusiness('A');
        $this->b = $this->buildBusiness('B');
        $this->seedPlatformRecords();
    }

    public function test_every_web_route_refuses_another_businesss_record_and_changes_nothing(): void
    {
        $this->sweep(api: false);
    }

    public function test_every_api_route_refuses_another_businesss_record_and_changes_nothing(): void
    {
        $this->sweep(api: true);
    }

    /**
     * Control: the same GET URLs open for B's own owner, so A's 403/404 above
     * is the ownership check at work and not a broken fixture or URL.
     */
    public function test_the_swept_record_pages_open_for_their_own_owner(): void
    {
        $bad = [];
        foreach ([false, true] as $api) {
            foreach ($this->recordRoutes($api) as $route) {
                $missing = [];
                $url = $this->urlFor($this->b, $route, $missing);
                $name = (string) $route->getName();
                // Signed links and the super-admin panel are refused to any
                // plain signed-in owner by design.
                if ($url === null || $this->verb($route) !== 'GET'
                    || preg_match('/^(invoices\.public|quotations\.public|verification\.verify|admin\.)/', $name)) {
                    continue;
                }
                app('cache')->store()->flush();
                if ($api) {
                    Sanctum::actingAs($this->b['owner']->fresh());
                    $status = $this->getJson($url)->getStatusCode();
                } else {
                    $status = $this->actingAs($this->b['owner']->fresh())->get($url)->getStatusCode();
                }
                if ($status !== 200) {
                    $bad[] = "GET {$url} [{$name}] answered {$status} to its own owner";
                }
            }
        }
        $this->assertSame([], $bad, implode("\n", $bad));
    }

    private function sweep(bool $api): void
    {
        $failures = [];
        $missing = [];
        $swept = 0;

        foreach ($this->recordRoutes($api) as $route) {
            $url = $this->urlFor($this->b, $route, $missing);
            if ($url === null) {
                continue;
            }
            $verb = $this->verb($route);
            // Route throttles share one per-user counter, so a 429 here would
            // only mean earlier sweep calls used the budget up, and would hide
            // whether the ownership check works. Start each call fresh.
            app('cache')->store()->flush();
            $before = $this->snapshot();

            if ($api) {
                Sanctum::actingAs($this->a['owner']->fresh());
                $res = $this->json($verb, $url, $verb === 'GET' ? [] : $this->sweepPayload($this->b));
            } else {
                $this->actingAs($this->a['owner']->fresh());
                $res = $verb === 'GET' ? $this->get($url) : $this->call($verb, $url, $this->sweepPayload($this->b));
            }
            $swept++;

            $status = $res->getStatusCode();
            $label = "{$verb} {$url} [" . ($route->getName() ?? $route->getActionName()) . ']';
            if (! in_array($status, [403, 404], true)) {
                $where = $res->isRedirect() ? ' -> ' . $res->headers->get('Location') : '';
                $errors = session('errors') ? ' errors: ' . json_encode(session('errors')->getMessages()) : '';
                $failures[] = "{$label} answered {$status}{$where}{$errors}";
            }
            $changed = $this->changedTables($before, $this->snapshot());
            if ($changed) {
                $failures[] = "{$label} changed: " . implode(', ', $changed);
            }
            $body = (string) $res->getContent();
            foreach (['SECRET-CUSTOMER-B', 'SECRET-FIRM-B', 'SECRET-ACCT-B', 'SECRET-VENDOR-B', 'SECRET-SELLER-B', 'SECRET-RENT-B'] as $marker) {
                if (str_contains($body, $marker)) {
                    $failures[] = "{$label} response contains {$marker}";
                }
            }
            // A's own session state must not have been pointed at B's business.
            $this->assertSame($this->a['company']->id, (int) $this->a['owner']->fresh()->active_company_id, "{$label} switched A into B's business");
        }

        $this->assertSame([], $missing, "URL parameters this sweep cannot fill (add them to targetFor):\n" . implode("\n", $missing));
        $this->assertSame([], $failures, "Cross-business access not refused:\n" . implode("\n", $failures));
        // Guard against the sweep silently shrinking (e.g. a filter bug).
        $this->assertGreaterThan($api ? 25 : 60, $swept, "only {$swept} routes swept");
    }
}
