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
 * The steps between signing up and sending the first invoice, with the
 * friction a review of the flow found taken out: the customer added in setup
 * comes pre-selected, setup ends on one clear next step, WhatsApp asks for a
 * missing mobile in place, a future date is flagged on the field, there is
 * one way to add a customer on the form, and sign-up has one password box.
 */
class FirstInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $mh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create(['state_id' => $this->mh->id, 'onboarded_at' => now()]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function customer(array $attrs = []): Customer
    {
        return Customer::factory()->recycle($this->user)->recycle($this->company)->create(['state_id' => $this->mh->id] + $attrs);
    }

    private function selectedCustomer(string $html): string
    {
        preg_match('/customerId: "([^"]*)"/', $html, $m);

        return $m[1] ?? '';
    }

    // ── Customer pre-selected ─────────────────────────────────────────────

    public function test_the_only_customer_is_pre_selected(): void
    {
        $only = $this->customer();

        $html = $this->actingAs($this->user)->get(route('invoices.create'))->assertOk()->getContent();
        $this->assertSame((string) $only->id, $this->selectedCustomer($html));
    }

    public function test_a_customer_named_in_the_link_is_pre_selected(): void
    {
        $this->customer(['name' => 'Asha Traders']);
        $second = $this->customer(['name' => 'Bharat Steels']);

        $html = $this->actingAs($this->user)->get(route('invoices.create', ['customer_id' => $second->id]))->getContent();
        $this->assertSame((string) $second->id, $this->selectedCustomer($html));

        // With several customers and none named, nothing is chosen for the user.
        $html = $this->actingAs($this->user)->get(route('invoices.create'))->getContent();
        $this->assertSame('', $this->selectedCustomer($html));
    }

    public function test_another_businesss_customer_in_the_link_is_ignored(): void
    {
        $this->customer();
        $this->customer();
        $stranger = User::factory()->create();
        $theirs = Customer::factory()->recycle($stranger)->recycle(Company::factory()->recycle($stranger)->create())->create();

        $html = $this->actingAs($this->user)->get(route('invoices.create', ['customer_id' => $theirs->id]))->getContent();
        $this->assertSame('', $this->selectedCustomer($html));
    }

    // ── End of setup ──────────────────────────────────────────────────────

    public function test_setup_ends_with_one_button_to_the_first_invoice_for_that_customer(): void
    {
        $customer = $this->customer(['name' => 'Patil Stores']);

        $this->actingAs($this->user)->get(route('onboarding.done'))->assertOk()
            ->assertSee('Setup complete')
            ->assertSee(route('invoices.create', ['customer_id' => $customer->id]), false)
            ->assertDontSee('Add a customer first');
    }

    public function test_setup_without_a_customer_does_not_claim_to_be_complete(): void
    {
        $this->actingAs($this->user)->get(route('onboarding.done'))->assertOk()
            ->assertDontSee('Setup complete')
            ->assertSee('Business details saved')
            ->assertSee('Create your first invoice')
            ->assertSee('Add a customer first');
    }

    // ── One way to add a customer ─────────────────────────────────────────

    public function test_with_no_customers_the_form_has_one_add_customer_button(): void
    {
        $html = $this->actingAs($this->user)->get(route('invoices.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('No customers yet.', $html);
        $this->assertStringContainsString('Type the customer name to add them', $html);
        $this->assertStringContainsString('title="Add a new customer">+ New</button>', $html);
    }

    // ── Future date ──────────────────────────────────────────────────────

    public function test_the_date_field_flags_a_date_after_today(): void
    {
        $this->actingAs($this->user)->get(route('invoices.create'))->assertOk()
            ->assertSee('x-show="date > today"', false)
            ->assertSee('After today. It can be saved as a draft and issued on that date.');
    }

    // ── WhatsApp without a mobile ────────────────────────────────────────

    private function issuedFor(Customer $customer): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(15)->toDateString(),
            'items' => [['description' => 'Service', 'hsn_sac' => '998314', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $inv = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasNoErrors();

        return $inv->fresh();
    }

    public function test_whatsapp_asks_for_the_mobile_on_the_invoice_and_then_works(): void
    {
        $customer = $this->customer(['name' => 'Kulkarni Infotech', 'phone' => null]);
        $inv = $this->issuedFor($customer);

        $this->actingAs($this->user)->get(route('invoices.show', $inv))->assertOk()
            ->assertSee(route('invoices.customer-phone', $inv), false)
            ->assertDontSee('Send on WhatsApp to', false);

        $this->actingAs($this->user)->post(route('invoices.customer-phone', $inv), ['customer_phone' => '98765 43210'])
            ->assertRedirect(route('invoices.show', $inv))
            ->assertSessionHas('status', 'Mobile saved. Send the invoice on WhatsApp now.');

        $this->assertSame('9876543210', $customer->fresh()->phone);
        $this->actingAs($this->user)->get(route('invoices.show', $inv))->assertSee('https://wa.me/919876543210', false);
    }

    public function test_a_wrong_mobile_is_refused_and_nothing_changes(): void
    {
        $customer = $this->customer(['phone' => null]);
        $inv = $this->issuedFor($customer);

        $this->actingAs($this->user)->post(route('invoices.customer-phone', $inv), ['customer_phone' => '12345'])
            ->assertSessionHasErrors('customer_phone');
        $this->assertNull($customer->fresh()->phone);
    }

    public function test_only_the_owner_can_save_a_mobile_from_the_invoice(): void
    {
        $inv = $this->issuedFor($this->customer(['phone' => null]));
        $other = User::factory()->create();

        $this->actingAs($other)->post(route('invoices.customer-phone', $inv), ['customer_phone' => '9876543210'])->assertForbidden();
    }

    // ── Sign-up ──────────────────────────────────────────────────────────

    public function test_sign_up_has_one_password_box(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertDontSee('name="password_confirmation"', false)
            ->assertDontSee('Confirm password');

        $this->post(route('register'), [
            'name' => 'Meera Joshi', 'phone' => '9876543210', 'email' => 'meera@example.in',
            'password' => 'Str0ng-pass-123', 'terms_accepted' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('register.verify'));
    }
}
