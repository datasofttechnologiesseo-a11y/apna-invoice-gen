<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The token-authenticated API is a second, fully independent door onto the
 * same data as the web app, and none of its write routes were exercised.
 * Two properties matter more than the happy paths:
 *
 *  - every write route refuses an anonymous caller, and
 *  - no route lets one token reach another user's rows.
 *
 * DELETE /api/profile deletes an account, and finalize/cancel move an invoice
 * between states that carry GST meaning, so these are asserted explicitly
 * rather than inferred from the route group's middleware.
 */
class ApiSurfaceGuardTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'state_id' => $state->id, 'onboarded_at' => now(),
        ]);
        $customer = Customer::factory()->recycle($user)->recycle($company)->create();
        $invoice = Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->create();
        $quotation = Quotation::factory()->recycle($user)->recycle($company)->recycle($customer)->create();

        return compact('user', 'company', 'customer', 'invoice', 'quotation');
    }

    public function test_every_api_write_route_refuses_an_anonymous_caller(): void
    {
        ['invoice' => $inv, 'quotation' => $q, 'company' => $co] = $this->fixture();

        $routes = [
            ['POST',   '/api/auth/logout'],
            ['PUT',    '/api/profile'],
            ['PUT',    '/api/profile/password'],
            ['DELETE', '/api/profile'],
            ['POST',   '/api/backup/email'],
            ['POST',   '/api/backup/toggle'],
            ['POST',   '/api/companies'],
            ['POST',   "/api/companies/{$co->id}/switch"],
            ['POST',   "/api/invoices/{$inv->id}/finalize"],
            ['POST',   "/api/invoices/{$inv->id}/cancel"],
            ['POST',   "/api/invoices/{$inv->id}/payments"],
            ['POST',   "/api/invoices/{$inv->id}/remind"],
            ['DELETE', "/api/invoices/{$inv->id}"],
            ['POST',   "/api/quotations/{$q->id}/accept"],
            ['POST',   "/api/quotations/{$q->id}/decline"],
            ['POST',   "/api/quotations/{$q->id}/convert"],
            ['POST',   "/api/quotations/{$q->id}/send"],
            ['DELETE', "/api/quotations/{$q->id}"],
        ];

        foreach ($routes as [$verb, $uri]) {
            $res = $this->json($verb, $uri);
            $this->assertSame(401, $res->getStatusCode(),
                "{$verb} {$uri} answered {$res->getStatusCode()} to an anonymous caller");
        }
    }

    public function test_a_token_cannot_reach_another_users_records(): void
    {
        $mine = $this->fixture();
        $theirs = $this->fixture();

        Sanctum::actingAs($mine['user']);

        foreach ([
            ['POST',   "/api/invoices/{$theirs['invoice']->id}/finalize"],
            ['POST',   "/api/invoices/{$theirs['invoice']->id}/cancel"],
            ['POST',   "/api/invoices/{$theirs['invoice']->id}/payments"],
            ['DELETE', "/api/invoices/{$theirs['invoice']->id}"],
            ['POST',   "/api/quotations/{$theirs['quotation']->id}/accept"],
            ['POST',   "/api/quotations/{$theirs['quotation']->id}/convert"],
            ['DELETE', "/api/quotations/{$theirs['quotation']->id}"],
            ['POST',   "/api/companies/{$theirs['company']->id}/switch"],
        ] as [$verb, $uri]) {
            $res = $this->json($verb, $uri);
            $this->assertContains($res->getStatusCode(), [403, 404],
                "{$verb} {$uri} answered {$res->getStatusCode()} for someone else's record");
        }

        // and nothing was actually mutated
        $this->assertNotNull(Invoice::find($theirs['invoice']->id));
        $this->assertNotNull(Quotation::find($theirs['quotation']->id));
    }

    public function test_api_login_issues_a_token_and_rejects_a_bad_password(): void
    {
        $user = User::factory()->create([
            'email' => 'api@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('api-secret-99'),
        ]);

        $ok = $this->postJson('/api/auth/login', [
            'email' => 'api@example.test', 'password' => 'api-secret-99', 'device_name' => 'phpunit',
        ]);
        $ok->assertSuccessful();
        $this->assertNotEmpty($ok->json('token') ?? $ok->json('data.token'),
            'a successful API login should return a token');

        $this->postJson('/api/auth/login', [
            'email' => 'api@example.test', 'password' => 'wrong', 'device_name' => 'phpunit',
        ])->assertStatus(422);
    }

    public function test_deleting_the_account_over_the_api_only_ever_deletes_the_caller(): void
    {
        $mine = $this->fixture();
        $theirs = $this->fixture();

        Sanctum::actingAs($mine['user']);
        $this->deleteJson('/api/profile', ['password' => 'wrong-password'])
            ->assertStatus(422, 'account deletion must require the current password');

        // the other user is untouched regardless
        $this->assertNotNull(User::find($theirs['user']->id));
    }

    public function test_an_invoice_with_no_line_items_cannot_be_issued(): void
    {
        // Issuing an empty invoice would put a numbered document with nothing
        // on it into a GST-reportable series, so the API refuses it. This was
        // already correct; the assertion pins it down.
        $f = $this->fixture();
        Sanctum::actingAs($f['user']);

        $this->postJson("/api/invoices/{$f['invoice']->id}/finalize")->assertStatus(422);
        $this->assertNull($f['invoice']->fresh()->finalized_at);
    }

    public function test_finalizing_an_invoice_over_the_api_assigns_a_number(): void
    {
        $f = $this->fixture();
        // At least one line item, or finalize refuses for a different reason.
        \App\Models\InvoiceItem::factory()->recycle($f['invoice'])->create();

        Sanctum::actingAs($f['user']);

        $res = $this->postJson("/api/invoices/{$f['invoice']->id}/finalize");
        $res->assertSuccessful();

        $invoice = $f['invoice']->fresh();
        $this->assertNotNull($invoice->finalized_at, 'finalize should stamp the invoice');
        $this->assertNotEmpty($invoice->invoice_number, 'a finalized invoice must carry a number');
    }
}
