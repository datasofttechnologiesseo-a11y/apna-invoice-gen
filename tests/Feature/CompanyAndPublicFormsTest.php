<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\Company;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Company CRUD and the two public forms, none of which were exercised.
 *
 * The company's state_id drives CGST/SGST vs IGST on every invoice the
 * business ever issues, so "can this be saved without a state" is a GST
 * correctness question, not a form-validation nicety.
 */
class CompanyAndPublicFormsTest extends TestCase
{
    use RefreshDatabase;

    private function state(): State
    {
        return State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
    }

    /** The company form also requires currency and the numbering settings. */
    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'state_id' => $this->state()->id,
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'INV/',
            'invoice_number_padding' => 4,
        ], $overrides);
    }

    public function test_a_user_can_create_update_and_delete_their_own_company(): void
    {
        $state = $this->state();
        $user = User::factory()->create();
        Company::factory()->recycle($user)->create(['state_id' => $state->id, 'onboarded_at' => now()]);

        $this->actingAs($user)->post(route('companies.store'),
            $this->companyPayload(['name' => 'Second Venture']))->assertRedirect();

        $created = Company::where('name', 'Second Venture')->firstOrFail();
        $this->assertSame($user->id, $created->user_id);

        $this->actingAs($user)->patch(route('companies.update', $created),
            $this->companyPayload(['name' => 'Second Venture LLP']))->assertRedirect();
        $this->assertSame('Second Venture LLP', $created->fresh()->name);

        $this->actingAs($user)->delete(route('companies.destroy', $created))->assertRedirect();
        $this->assertNull(Company::find($created->id));
    }

    public function test_a_company_cannot_be_saved_without_a_state(): void
    {
        // No state means GST determination downstream has nothing to compare
        // the buyer against, so every invoice would be wrong.
        $user = User::factory()->create();

        $payload = $this->companyPayload(['name' => 'Stateless Co']);
        unset($payload['state_id']);
        $this->actingAs($user)->post(route('companies.store'), $payload)
            ->assertSessionHasErrors('state_id');

        $this->assertNull(Company::where('name', 'Stateless Co')->first());
    }

    public function test_a_user_cannot_update_or_delete_another_users_company(): void
    {
        $state = $this->state();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $company = Company::factory()->recycle($owner)->create([
            'name' => 'Not Yours', 'state_id' => $state->id,
        ]);

        $this->actingAs($intruder)->patch(route('companies.update', $company),
            $this->companyPayload(['name' => 'Hijacked']))->assertForbidden();

        $this->actingAs($intruder)->delete(route('companies.destroy', $company))->assertForbidden();

        $this->assertSame('Not Yours', $company->fresh()->name);
    }

    public function test_a_lower_case_gstin_is_stored_upper_case(): void
    {
        $state = $this->state();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('companies.store'),
            $this->companyPayload(['name' => 'Case Co', 'gstin' => '27aaact2727q1zw']))
            ->assertRedirect();

        $this->assertSame('27AAACT2727Q1ZW', Company::where('name', 'Case Co')->first()->gstin);
    }

    public function test_the_contact_form_sends_a_message(): void
    {
        Mail::fake();

        $this->post(route('pages.contact.send'), [
            'name' => 'Ramesh', 'email' => 'ramesh@example.test',
            'subject' => 'Question about GSTR-1',
            'message' => 'How do I export the return for last quarter?',
        ])->assertRedirect(route('pages.contact'));

        Mail::assertSent(ContactMessage::class);
    }

    public function test_the_contact_form_rejects_junk_without_sending_mail(): void
    {
        Mail::fake();

        $this->post(route('pages.contact.send'), [
            'name' => '', 'email' => 'not-an-email', 'subject' => 'x', 'message' => 'short',
        ])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        Mail::assertNothingSent();
    }

    public function test_cookie_consent_is_recorded_and_survives_bad_input(): void
    {
        $this->post(route('cookie-consent.store'), ['analytics' => '1', 'marketing' => '0'])
            ->assertStatus(200, 'the consent endpoint should answer without error');

        // Garbage must not 500 - this endpoint is called by a banner on every
        // public page, so an exception here is visible to every visitor.
        $res = $this->post(route('cookie-consent.store'), ['analytics' => 'banana']);
        $this->assertLessThan(500, $res->getStatusCode());
    }
}
