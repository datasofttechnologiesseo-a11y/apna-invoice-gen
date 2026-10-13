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
 * The Google review invite: on the first dashboard after registration, and
 * after every second invoice issued - for new and existing users alike -
 * until the user says the review is given; then never again.
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

    public function test_a_new_user_is_asked_on_their_first_dashboard_before_any_invoice(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee(self::POSTER, false);
        $this->assertNotNull($this->user->fresh()->review_prompt_shown_at);

        // Once is enough for that moment; the next one is the 2nd invoice.
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);
    }

    public function test_an_existing_user_never_asked_before_is_asked_on_the_dashboard(): void
    {
        $this->issueInvoice();
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee(self::POSTER, false);
    }

    public function test_it_comes_back_after_every_second_invoice(): void
    {
        $this->user->forceFill(['review_prompt_shown_at' => now()->subMonth()])->save();

        $seen = [];
        foreach (range(1, 5) as $n) {
            $this->issueInvoice();
            $seen[$n] = (bool) session('review_invite');
        }

        $this->assertSame([1 => false, 2 => true, 3 => false, 4 => true, 5 => false], $seen);
    }

    public function test_the_issued_invoice_page_shows_it_after_an_even_invoice(): void
    {
        $this->user->forceFill(['review_prompt_shown_at' => now()->subMonth()])->save();
        $this->issueInvoice();
        $second = $this->issueInvoice();

        $this->actingAs($this->user)->withSession(['review_invite' => true])
            ->get(route('invoices.show', $second))->assertOk()->assertSee(self::POSTER, false);
        $this->actingAs($this->user)
            ->get(route('invoices.show', $second))->assertOk()->assertDontSee(self::POSTER, false);
    }

    public function test_once_the_review_is_given_it_never_returns(): void
    {
        $this->actingAs($this->user)->post(route('review-invite.given'))->assertNoContent();
        $this->assertNotNull($this->user->fresh()->review_given_at);

        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);
        foreach (range(1, 4) as $n) {
            $this->issueInvoice();
            $this->assertFalse((bool) session('review_invite'), "asked again after invoice {$n}");
        }
    }

    public function test_marking_the_review_given_requires_a_signed_in_user(): void
    {
        $this->post(route('review-invite.given'))->assertRedirect(route('login'));
    }

    public function test_the_review_link_points_at_the_real_listing(): void
    {

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('g.page/r/CTvUSP6MtrMaEBM/review', false)
            ->assertSee('Write a review')
            ->assertSee("I've already reviewed", false);
    }

    public function test_the_poster_carries_a_qr_code_made_for_this_account(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee(self::POSTER, false)
            ->assertSee('left: 12.5%; top: 41.5%', false)   // laid over the printed code
            ->assertSee('<svg', false);
    }

    public function test_scanning_the_qr_records_the_review_and_opens_google(): void
    {
        // Scanned on a phone that is not signed in.
        $url = \Illuminate\Support\Facades\URL::signedRoute('review-invite.scan', ['user' => $this->user->id]);
        $this->get($url)->assertRedirect('https://g.page/r/CTvUSP6MtrMaEBM/review');
        $this->assertNotNull($this->user->fresh()->review_given_at);

        // Never asked again: not on the dashboard, not after any invoice.
        // (Signed back in on the computer: a fresh request reads the account anew.)
        $this->user = $this->user->fresh();
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee(self::POSTER, false);
        foreach (range(1, 4) as $n) {
            $this->issueInvoice();
            $this->assertFalse((bool) session('review_invite'), "asked again after invoice {$n}");
        }
    }

    public function test_scanning_twice_keeps_the_first_date_and_still_opens_google(): void
    {
        $this->user->forceFill(['review_given_at' => now()->subDays(3)])->save();
        $first = $this->user->fresh()->review_given_at->toDateTimeString();

        $url = \Illuminate\Support\Facades\URL::signedRoute('review-invite.scan', ['user' => $this->user->id]);
        $this->get($url)->assertRedirect('https://g.page/r/CTvUSP6MtrMaEBM/review');
        $this->assertSame($first, $this->user->fresh()->review_given_at->toDateTimeString());
    }

    public function test_a_scan_link_that_was_not_signed_by_the_app_records_nothing(): void
    {
        $other = User::factory()->create();

        // Someone edits the account number in a real link, or makes one up.
        $url = \Illuminate\Support\Facades\URL::signedRoute('review-invite.scan', ['user' => $this->user->id]);
        $this->get(str_replace('/review/scan/' . $this->user->id, '/review/scan/' . $other->id, $url))->assertForbidden();
        $this->get('/review/scan/' . $other->id)->assertForbidden();

        $this->assertNull($other->fresh()->review_given_at);
        $this->assertNull($this->user->fresh()->review_given_at);
    }

    public function test_without_a_review_the_invite_returns_on_every_alternate_invoice(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'));   // the first-dashboard invite, closed with "Not now"

        $asked = [];
        foreach (range(1, 6) as $n) {
            $this->issueInvoice();
            $asked[$n] = (bool) session('review_invite');
        }
        $this->assertSame([1 => false, 2 => true, 3 => false, 4 => true, 5 => false, 6 => true], $asked);
    }

    public function test_the_button_is_hidden_when_no_review_url_is_configured(): void
    {
        config(['seo.contact.google_review_url' => null]);

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee(self::POSTER, false)      // poster and its QR still show
            ->assertDontSee('Write a review')
            ->assertSee("I've already reviewed", false); // still a way to retire it
    }
}
