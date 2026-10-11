<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use App\Services\ReviewPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Google review invite appears on the dashboard once the user has issued
 * an invoice - never on the invoice screen itself, whose job is to get the
 * bill sent. Put off, it returns a week later; once the user says the review
 * is given, it never returns.
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
            'showing it must be recorded so the snooze clock starts');
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

    public function test_put_off_it_waits_a_week_and_then_asks_again(): void
    {
        $this->issueInvoice();

        $this->actingAs($this->user)->get(route('dashboard'))->assertSee(self::POSTER, false);
        // Same day, next visit: left alone. A reminder on every page load is a nag.
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);

        $this->travel(ReviewPrompt::SNOOZE_DAYS - 1)->days();
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);

        $this->travel(2)->days();
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee(self::POSTER, false);
    }

    public function test_someone_asked_long_ago_who_never_reviewed_is_asked_again(): void
    {
        // Users from before this change were shown it once and then dropped.
        // They have issued bills, so they are owed the same weekly ask.
        $this->user->forceFill(['review_prompt_shown_at' => now()->subMonth()])->save();
        $this->issueInvoice();

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee(self::POSTER, false);
    }

    public function test_once_the_review_is_given_it_never_returns(): void
    {
        $this->issueInvoice();
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee(self::POSTER, false);

        // "Write a review" / "I've already reviewed" both hit this.
        $this->actingAs($this->user)->post(route('review-invite.given'))->assertNoContent();
        $this->assertNotNull($this->user->fresh()->review_given_at);

        $this->travel(1)->year();
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);
        $this->issueInvoice();
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);
    }

    public function test_marking_the_review_given_requires_a_signed_in_user(): void
    {
        $this->post(route('review-invite.given'))->assertRedirect(route('login'));
    }

    public function test_the_review_link_points_at_the_real_listing(): void
    {
        $this->issueInvoice();

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('g.page/r/CTvUSP6MtrMaEBM/review', false)
            ->assertSee('Write a review')
            ->assertSee("I've already reviewed", false);
    }

    public function test_the_button_is_hidden_when_no_review_url_is_configured(): void
    {
        config(['seo.contact.google_review_url' => null]);
        $this->issueInvoice();

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee(self::POSTER, false)      // poster and its QR still show
            ->assertDontSee('Write a review')
            ->assertSee("I've already reviewed", false); // still a way to retire it
    }
}
