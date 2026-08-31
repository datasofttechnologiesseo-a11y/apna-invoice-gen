<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ships-safe smoke test: every authenticated screen renders, and the paths a
 * new user actually walks (sign up -> verify -> set up -> first invoice ->
 * issue -> PDF) complete end to end.
 */
class PreflightSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_app_screen_renders(): void
    {
        $state = State::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'name' => 'Sharma Exports', 'state_id' => $state->id, 'onboarded_at' => now(),
        ]);
        $customer = Customer::factory()->recycle($user)->recycle($company)->create();
        $invoice = Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)
            ->create(['status' => 'final', 'finalized_at' => now()]);

        $screens = [
            '/dashboard', '/invoices', '/invoices/create', '/customers', '/customers/create',
            '/products', '/products/create', '/companies', '/companies/create',
            '/finance', '/finance/expenses', '/finance/expenses/create', '/finance/aging',
            '/finance/gstr3b', '/finance/cash-memos', '/finance/cash-memos/create',
            '/quotations', '/quotations/create', '/settings', '/backup', '/help', '/refer',
            '/setup/business', '/setup/customer',
            '/invoices/'.$invoice->id, '/customers/'.$customer->id.'/ledger',
        ];

        foreach ($screens as $uri) {
            $res = $this->actingAs($user)->get($uri);
            $this->assertContains(
                $res->getStatusCode(),
                [200, 302],
                "screen {$uri} returned {$res->getStatusCode()}"
            );
        }
    }

    public function test_a_new_user_can_go_from_signup_to_an_issued_invoice(): void
    {
        $state = State::factory()->create(['gst_code' => '27']);

        // 1. Register — no account exists until the emailed code is confirmed.
        $this->post('/register', [
            'name' => 'Ramesh Kumar',
            'phone' => '9876500001',
            'email' => 'ramesh@example.test',
            'password' => 'preflight-pass-1',
            'password_confirmation' => 'preflight-pass-1',
            'terms_accepted' => '1',
        ])->assertRedirect(route('register.verify'));

        $this->assertNull(User::where('email', 'ramesh@example.test')->first());

        // 2. Read the flashed code before any further request consumes it.
        $code = session('otp_dev_code');
        $this->assertNotEmpty($code, 'a verification code should have been issued');
        $this->get(route('register.verify'))->assertOk();
        $this->post(route('register.verify.store'), ['code' => $code])
            ->assertRedirect(route('onboarding.index'));

        $user = User::where('email', 'ramesh@example.test')->firstOrFail();

        // 3. Setup with only the two required fields.
        $this->actingAs($user)->post(route('onboarding.business.save'), [
            'name' => 'Kumar Traders',
            'state_id' => $state->id,
            'country' => 'India',
            'default_currency' => 'INR',
        ])->assertRedirect(route('onboarding.customer'));

        // 4. First customer.
        $this->actingAs($user)->post(route('onboarding.customer.save'), [
            'name' => 'Verma Enterprises',
            'state_id' => $state->id,
            'country' => 'India',
        ])->assertRedirect(route('onboarding.done'));

        $customer = $user->customers()->firstOrFail();

        // 5. First invoice, then issue it.
        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [[
                'description' => 'Consulting', 'hsn_sac' => '998311',
                'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18,
            ]],
        ])->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertEqualsWithDelta(11800.0, (float) $invoice->grand_total, 0.01);

        $this->actingAs($user)->post(route('invoices.finalize', $invoice));

        $invoice->refresh();
        $this->assertSame('final', $invoice->status);
        $this->assertNotNull($invoice->finalized_at);
        $this->assertNotEmpty($invoice->invoice_number);

        // 6. The PDF the customer actually receives.
        $pdf = $this->actingAs($user)->get(route('invoices.pdf', $invoice));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_both_manuals_download_as_valid_pdfs(): void
    {
        foreach (['pages.manuals.handbook', 'pages.manuals.quick-start'] as $route) {
            $res = $this->get(route($route));
            $res->assertOk();
            $this->assertStringStartsWith('%PDF', $res->getContent(), $route);
        }
    }
}
