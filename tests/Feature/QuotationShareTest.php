<?php

namespace Tests\Feature;

use App\Http\Controllers\QuotationShareController;
use App\Mail\QuotationMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuotationShareTest extends TestCase
{
    use RefreshDatabase;

    private function setupOwner(array $companyOverrides = []): array
    {
        $user = User::factory()->create();
        $companyState = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $customerState = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);

        $company = Company::factory()->create(array_merge([
            'user_id' => $user->id,
            'state_id' => $companyState->id,
            'quote_prefix' => 'QT',
            'quote_counter' => 0,
        ], $companyOverrides));

        $user->forceFill(['active_company_id' => $company->id])->save();

        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'state_id' => $customerState->id,
        ]);

        return ['user' => $user, 'company' => $company, 'customer' => $customer];
    }

    public function test_owner_can_email_a_quotation_and_pdf_is_attached(): void
    {
        Mail::fake();
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)->post(route('quotations.share.email', $q), [
            'to' => 'customer@example.com',
            'subject' => 'Your quotation',
            'body' => 'Please review the attached quote.',
        ])->assertRedirect();

        Mail::assertSent(QuotationMail::class, fn (QuotationMail $m) => $m->hasTo('customer@example.com'));
    }

    public function test_emailing_a_draft_auto_assigns_number_and_marks_sent(): void
    {
        Mail::fake();
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertSame('draft', $q->status);
        $this->assertNull($q->quote_number);

        $this->actingAs($user)->post(route('quotations.share.email', $q), [
            'to' => 'customer@example.com',
            'subject' => 'Your quote',
            'body' => 'Attached.',
        ])->assertRedirect();

        $q->refresh();
        $this->assertSame('sent', $q->status);
        $this->assertSame('QT-0001', $q->quote_number);
    }

    public function test_email_requires_valid_recipient(): void
    {
        Mail::fake();
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)->post(route('quotations.share.email', $q), [
            'to' => 'not-an-email',
            'subject' => 'x',
            'body' => 'x',
        ])->assertSessionHasErrors('to');

        Mail::assertNothingSent();
    }

    public function test_cannot_email_a_converted_quotation(): void
    {
        Mail::fake();
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status' => 'converted',
        ]);

        $this->actingAs($user)->post(route('quotations.share.email', $q), [
            'to' => 'customer@example.com',
            'subject' => 'x',
            'body' => 'x',
        ])->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_cannot_email_a_declined_quotation(): void
    {
        Mail::fake();
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status' => 'declined',
        ]);

        $this->actingAs($user)->post(route('quotations.share.email', $q), [
            'to' => 'customer@example.com',
            'subject' => 'x',
            'body' => 'x',
        ])->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_user_cannot_email_another_users_quotation(): void
    {
        Mail::fake();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobQuote = Quotation::factory()->recycle($bob)->sent()->create();

        $this->actingAs($alice)->post(route('quotations.share.email', $bobQuote), [
            'to' => 'x@example.com',
            'subject' => 'x',
            'body' => 'x',
        ])->assertStatus(403);

        Mail::assertNothingSent();
    }

    public function test_public_link_returns_signed_url_for_owner(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($user)->get(route('quotations.share.link', $q));
        $response->assertOk();
        $payload = $response->json();
        $this->assertArrayHasKey('url', $payload);
        $this->assertStringContainsString('/q/', $payload['url']);
        $this->assertStringContainsString('signature=', $payload['url']);
    }

    public function test_public_view_shows_html_landing_page_with_valid_signed_url(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $url = QuotationShareController::makePublicUrl($q);
        $this->get($url)
            ->assertOk()
            ->assertSee('Download PDF', false)
            ->assertSee('noindex', false);
    }

    public function test_public_pdf_subroute_streams_pdf_with_noindex_header(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $pdfUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'quotations.public.pdf',
            ['quotation' => $q->id],
            now()->addDays(30)
        );
        $response = $this->get($pdfUrl);
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function test_unsigned_public_url_is_rejected(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        // No signature in the URL — Laravel's `signed` middleware should reject it.
        $this->get('/q/' . $q->id)->assertStatus(403);
    }

    public function test_declined_quotations_kill_their_public_link(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status' => 'declined',
        ]);

        $url = QuotationShareController::makePublicUrl($q);
        $this->get($url)->assertStatus(410);
    }

    public function test_user_cannot_get_public_link_for_another_users_quotation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobQuote = Quotation::factory()->recycle($bob)->create();

        $this->actingAs($alice)->get(route('quotations.share.link', $bobQuote))
            ->assertStatus(403);
    }
}
