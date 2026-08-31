<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The setup form shows only business name + state + GSTIN by default; every
 * other field sits behind a collapsed panel. These cover the cases that panel
 * creates: submitting without ever opening it, and the GST-registered path
 * where the hidden address fields become mandatory.
 */
class OnboardingBusinessSetupTest extends TestCase
{
    use RefreshDatabase;

    private function freshUser(): User
    {
        $user = User::factory()->create();
        Company::factory()->recycle($user)->create([
            'name' => '', 'state_id' => null, 'onboarded_at' => null,
        ]);

        return $user;
    }

    public function test_minimal_submission_with_only_name_and_state_succeeds(): void
    {
        $user = $this->freshUser();
        $state = State::factory()->create();

        $response = $this->actingAs($user)->post(route('onboarding.business.save'), [
            'name' => 'Chai Point Traders',
            'state_id' => $state->id,
            'country' => 'India',
            'default_currency' => 'INR',
        ]);

        $response->assertRedirect(route('onboarding.customer'));
        $company = $user->fresh()->ensureCompany();
        $this->assertSame('Chai Point Traders', $company->name);
        $this->assertSame($state->id, $company->state_id);
    }

    public function test_blank_invoice_prefix_falls_back_to_a_default(): void
    {
        $user = $this->freshUser();
        $state = State::factory()->create();

        $this->actingAs($user)->post(route('onboarding.business.save'), [
            'name' => 'Chai Point Traders',
            'state_id' => $state->id,
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => '',
        ])->assertRedirect(route('onboarding.customer'));

        $company = $user->fresh()->ensureCompany();
        $this->assertNotEmpty($company->invoice_prefix);
        $this->assertStringContainsString($company->invoice_prefix, $company->invoice_number_format);
    }

    public function test_gstin_still_requires_an_address(): void
    {
        $user = $this->freshUser();
        $state = State::factory()->create(['gst_code' => 27]);

        $this->actingAs($user)->post(route('onboarding.business.save'), [
            'name' => 'Chai Point Traders',
            'state_id' => $state->id,
            'gstin' => '27AAACT2727Q1ZW',
            'country' => 'India',
            'default_currency' => 'INR',
        ])->assertSessionHasErrors('address_line1');
    }
}
