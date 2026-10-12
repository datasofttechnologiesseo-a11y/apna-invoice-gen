<?php

namespace Tests\Feature\Fintech\Security\Concerns;

use App\Models\DataBreach;
use App\Models\Post;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Route;

/**
 * Router-driven sweep helpers: list every route whose URL names a record,
 * fill each URL parameter with a record of the TARGET business (the one the
 * caller must not reach), and send a payload valid enough that a refusal can
 * only come from the ownership check.
 */
trait SweepsRecordRoutes
{
    /**
     * Parameters that are not a business record, so there is no "B's one" to
     * try. Each is covered elsewhere or is public by design.
     */
    protected static function sweepNotARecord(): array
    {
        return [
            'template',  // built-in invoice templates, identical for everyone
            'type',      // import type (customers/products/vendors), scoped to the caller's own business
            'report',    // report key from config/reports.php, always the caller's own business
            'format',    // export format
            'slug',      // public blog post
            'provider',  // OAuth provider name (guest only)
            'token',     // team invite / password reset tokens: see TeamRolesTest and AuthAbuseTest
            'path',      // Laravel's private-disk file route (storage.local): see UploadSafetyTest
        ];
    }

    protected function seedPlatformRecords(): void
    {
        Post::factory()->create(['status' => 'published']);
        DataBreach::create(['title' => 'Seeded', 'description' => 'x', 'severity' => 'low', 'status' => 'open',
            'discovered_at' => now()->subDay(), 'affected_users' => 0]);
    }

    /** The target business's record for a URL parameter, chosen so the action WOULD succeed for its owner. */
    protected function targetFor(array $b, string $param, RouteDef $route): mixed
    {
        $name = (string) $route->getName();
        $uri = $route->uri();
        $verb = $this->verb($route);
        $isDraftAction = in_array($name, ['invoices.edit', 'invoices.update', 'invoices.destroy', 'invoices.finalize'], true)
            || (str_starts_with($uri, 'api/invoices/{invoice}') && preg_match('#^api/invoices/\{invoice\}(/finalize)?$#', $uri));

        return match ($param) {
            'invoice' => $isDraftAction ? $b['invoiceDraft']->id : $b['invoiceOpen']->id,
            'payment' => $b['payment']->id,
            'creditNote' => $b['creditNote']->id,
            'quotation' => match (true) {
                str_ends_with($uri, '/convert') => $b['quoteAccepted']->id,
                str_ends_with($uri, '/send'), str_ends_with($uri, '/edit'),
                str_ends_with($uri, '{quotation}') && in_array($verb, ['PUT', 'PATCH', 'DELETE'], true) => $b['quoteDraft']->id,
                default => $b['quoteSent']->id,   // accept, decline, show, pdf, share
            },
            'customer' => str_contains($name, 'destroy') || $uri === 'api/customers/{customer}' ? $b['customerUnused']->id : $b['customer']->id,
            'product' => str_contains($name, 'destroy') ? $b['productUnused']->id : $b['product']->id,
            'expense' => $b['expense']->id,
            'cashMemo' => $b['cashMemo']->id,
            'purchaseBill' => $b['purchaseBill']->id,
            'purchasePayment' => $b['purchasePayment']->id,
            'vendor' => str_contains($name, 'destroy') ? $b['vendorUnused']->id : $b['vendor']->id,
            'line' => $b['bankLine']->id,
            'statement' => $b['statement']->id,
            'company' => $b['company']->id,
            'member' => $b['memberPending']->id,
            'user', 'id' => $b['owner']->id,
            'hash' => sha1($b['owner']->email),
            'post' => Post::first()->id,
            'breach' => DataBreach::first()->id,
            default => null,
        };
    }

    /**
     * A payload that passes the usual validation for the route, so a refusal
     * can only come from the ownership check and never from a missing field.
     * Every id in it is B's, the strongest version of the attempt.
     */
    protected function sweepPayload(array $b): array
    {

        return [
            'customer_id' => $b['customer']->id, 'vendor_id' => $b['vendor']->id,
            'invoice_date' => '2026-09-25', 'quote_date' => '2026-09-25', 'bill_date' => '2026-09-25',
            'bill_number' => 'X-99', 'memo_date' => '2026-09-25', 'seller_name' => 'X', 'payment_mode' => 'cash',
            'items' => [['description' => 'X', 'hsn_sac' => '8471', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10, 'gst_rate' => 18]],
            'amount' => 1, 'method' => 'cash', 'received_at' => '2026-09-25', 'paid_on' => '2026-09-25',
            'credit_note_date' => '2026-09-25', 'reason' => 'rate_correction', 'cancellation_reason' => 'Testing access',
            'decline_reason' => 'x', 'to' => 'x@example.test', 'subject' => 'x', 'body' => 'x',
            'name' => 'Renamed by A', 'state_id' => $b['company']->state_id, 'country' => 'India', 'default_currency' => 'INR',
            'invoice_prefix' => 'INV', 'invoice_number_padding' => 4, 'kind' => 'goods', 'unit' => 'NOS', 'gst_rate' => 18,
            'entry_date' => '2026-09-25', 'category' => 'rent', 'description' => 'X',
            'direction' => 'out', 'quantity' => 1, 'moved_on' => '2026-09-25',
            'role' => 'admin', 'candidate' => 'new-expense', 'password' => 'password',
        ];
    }

    protected function urlFor(array $b, RouteDef $route, array &$missing): ?string
    {
        $uri = $route->uri();
        $unknown = false;
        $path = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($b, $route, $uri, &$missing, &$unknown) {
            $value = $this->targetFor($b, $m[1], $route);
            if ($value === null) {
                $missing[] = "{$m[1]} in /{$uri}";
                $unknown = true;

                return 'x';
            }

            return (string) $value;
        }, $uri);

        return $unknown ? null : '/' . ltrim($path, '/');
    }

    /** @return list<RouteDef> routes whose URL names a record */
    protected function recordRoutes(bool $api): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDef $r) => str_starts_with($r->uri(), 'api/') === $api)
            ->filter(fn (RouteDef $r) => $r->parameterNames() !== [])
            ->reject(fn (RouteDef $r) => array_intersect($r->parameterNames(), self::sweepNotARecord()) !== [])
            ->values()->all();
    }

    protected function verb(RouteDef $route): string
    {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));

        return in_array('PUT', $methods, true) ? 'PUT' : $methods[0];
    }
}
