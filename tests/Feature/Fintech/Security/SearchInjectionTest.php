<?php

namespace Tests\Feature\Fintech\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * Search boxes: SQL metacharacters must neither error nor widen the query,
 * LIKE wildcards typed by the user must be taken literally, and no search
 * ever reaches another business's rows.
 */
class SearchInjectionTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    private const INJECTIONS = ["' OR '1'='1", "') OR 1=1 --", '" OR ""="', "1; DROP TABLE invoices; --", '\\', "\\'", "%' --", "' UNION SELECT password FROM users --"];

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->a = $this->buildBusiness('A');
        $this->b = $this->buildBusiness('B');
    }

    private function webSearches(string $term): array
    {
        return [
            route('invoices.index', ['search' => $term]), route('customers.index', ['search' => $term]),
            route('products.index', ['search' => $term]), route('quotations.index', ['search' => $term]),
            route('purchases.index', ['search' => $term]), route('vendors.index', ['search' => $term]),
            route('finance.expenses', ['search' => $term]), route('finance.cash-memos.index', ['search' => $term]),
        ];
    }

    /** @return array<string, int> API list => number of rows returned */
    private function apiCounts(string $term): array
    {
        $out = [];
        foreach (['/api/invoices', '/api/customers', '/api/products', '/api/expenses', '/api/cash-memos'] as $uri) {
            Sanctum::actingAs($this->a['owner']->fresh());
            $res = $this->getJson($uri . '?' . http_build_query(['search' => $term]));
            $res->assertOk();
            $out[$uri] = count($res->json('data') ?? []);
        }
        foreach (['/api/products/search', ] as $uri) {
            Sanctum::actingAs($this->a['owner']->fresh());
            $res = $this->getJson($uri . '?' . http_build_query(['q' => $term]))->assertOk();
            $json = $res->json();
            $out[$uri] = count($json['data'] ?? $json);
        }
        $res = $this->actingAs($this->a['owner']->fresh())->getJson(route('search.suggest', ['q' => $term, 'scope' => 'invoices']))->assertOk();
        $out['search.suggest'] = collect($res->json('groups'))->sum(fn ($g) => count($g['items']));
        $res = $this->actingAs($this->a['owner']->fresh())->getJson(route('products.search', ['q' => $term]))->assertOk();
        $json = $res->json();
        $out['products.search'] = count($json['data'] ?? $json['results'] ?? $json);

        return $out;
    }

    public function test_sql_metacharacters_neither_error_nor_return_rows(): void
    {
        $before = $this->snapshot();
        foreach (self::INJECTIONS as $term) {
            foreach ($this->webSearches($term) as $url) {
                $this->actingAs($this->a['owner']->fresh())->get($url)->assertOk();
            }
            foreach ($this->apiCounts($term) as $where => $n) {
                $this->assertSame(0, $n, "{$where} returned {$n} rows for " . json_encode($term));
            }
        }
        $this->assertSame([], $this->changedTables($before, $this->snapshot()));
    }

    public function test_search_never_reaches_another_business(): void
    {
        foreach (['SECRET-CUSTOMER-B', 'SECRET-ITEM-B', 'SECRET-RENT-B', 'SECRET-SELLER-B', 'buyer.b@example.test'] as $term) {
            foreach ($this->apiCounts($term) as $where => $n) {
                $this->assertSame(0, $n, "{$where} found B's {$term}");
            }
            foreach ($this->webSearches($term) as $url) {
                $html = $this->actingAs($this->a['owner']->fresh())->get($url)->assertOk()->getContent();
                $this->assertFalse(str_contains($html, 'SECRET-FIRM-B') || str_contains($html, 'SECRET-ACCT-B'), "{$url} shows B");
            }
        }
    }

    /**
     * "%" and "_" are LIKE wildcards. Typed into a search box they should
     * match those characters, not everything: today the term goes into
     * LIKE '%{term}%' unescaped, so "%" lists every row and "_" every row
     * with at least one character. Not a leak (the query is still scoped to
     * the business), but the search answers a question nobody asked.
     */
    public function test_like_wildcards_typed_by_the_user_are_taken_literally(): void
    {
        foreach (['%', '_', '%%', '__'] as $term) {
            $over = array_filter($this->apiCounts($term));
            $this->assertSame([], $over, 'search for ' . json_encode($term) . ' matched rows that do not contain it: ' . json_encode($over));
        }
    }
}
