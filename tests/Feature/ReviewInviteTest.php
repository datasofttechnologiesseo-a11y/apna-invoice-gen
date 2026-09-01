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
 * The Google review invite appears once, on the first dashboard visit after
 * the user has issued an invoice - never on the invoice screen itself, whose
 * job is to get the bill sent.
 */
class ReviewInviteTest extends TestCase
{
    use RefreshDatabase;

    private const POSTER = 'brand/review-invite.png';

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $state = State::factory()->create(['gst_code' => '27']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'state_id' => $state->id,
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $state->id]);
    }

    private function issueInvoice(): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [[
                'description' => 'Consulting', 'hsn_sac' => '998311',
                'quantity' => 1, 'rate' => 5000, 'gst_rate' => 18,
            ]],
        ])->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice));

        return $invoice->fresh();
    }

    public function test_it_never_covers_the_invoice_screen(): void
    {
        $invoice = $this->issueInvoice();

        // That page congratulates the user and carries the share buttons.
        // A modal on top of it would block the job it is celebrating.
        $this->actingAs($this->user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(self::POSTER, false);
    }

    public function test_it_appears_on_the_dashboard_after_the_first_invoice(): void
    {
        $this->issueInvoice();

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(self::POSTER, false);

        $this->assertNotNull($this->user->fresh()->review_prompt_shown_at,
            'showing it must be recorded so it cannot repeat');
    }

    public function test_it_is_not_shown_before_any_invoice_is_issued(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(self::POSTER, false);

        $this->assertNull($this->user->fresh()->review_prompt_shown_at);
    }

    public function test_a_draft_alone_does_not_trigger_it(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'description' => 'Consulting', 'hsn_sac' => '998311',
                'quantity' => 1, 'rate' => 5000, 'gst_rate' => 18,
            ]],
        ]);

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertDontSee(self::POSTER, false);
    }

    public function test_it_is_shown_only_once(): void
    {
        $this->issueInvoice();

        $this->actingAs($this->user)->get(route('dashboard'))->assertSee(self::POSTER, false);
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);
    }

    public function test_someone_already_asked_is_never_asked_again(): void
    {
        $this->user->forceFill(['review_prompt_shown_at' => now()->subMonth()])->save();
        $this->issueInvoice();

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertDontSee(self::POSTER, false);
    }

    public function test_the_review_link_points_at_the_real_listing(): void
    {
        $this->issueInvoice();

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('g.page/r/CTvUSP6MtrMaEBM/review', false)
            ->assertSee('Write a review');
    }

    public function test_the_button_is_hidden_when_no_review_url_is_configured(): void
    {
        config(['seo.contact.google_review_url' => null]);
        $this->issueInvoice();

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee(self::POSTER, false)      // poster and its QR still show
            ->assertDontSee('Write a review');
    }
}
