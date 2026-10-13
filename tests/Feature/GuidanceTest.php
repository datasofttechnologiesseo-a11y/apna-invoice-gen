<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nobody should feel lost: the top search goes to any page or action, every
 * page has a help button for that page, and a new person gets a short tour.
 */
class GuidanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $state = State::factory()->create(['gst_code' => '27']);
        $this->owner = User::factory()->create(['guide_completed_at' => now()]);
        $this->company = Company::factory()->create(['user_id' => $this->owner->id, 'state_id' => $state->id, 'onboarded_at' => now()]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
    }

    public function test_the_top_search_offers_pages_and_actions(): void
    {
        $html = $this->actingAs($this->owner)->get(route('invoices.index'))->assertOk()->getContent();

        $this->assertStringContainsString('commandSearch(', $html);
        $destinations = collect($this->destinations($html))->keyBy('label');
        foreach (['GSTR-1', 'Bank reconciliation', 'New customer', 'Receivables aging', 'Take the tour'] as $label) {
            $this->assertTrue($destinations->has($label), $label);
        }
        $this->assertSame(route('finance.gstr1'), $destinations['GSTR-1']['url']);
    }

    /** The destinations list Blade embeds as commandSearch(JSON.parse('...'), ...). */
    private function destinations(string $html): array
    {
        $this->assertSame(1, preg_match("/commandSearch\(JSON\.parse\('(.*?)'\)/s", $html, $m), 'destinations embedded');

        return json_decode(json_decode('"' . $m[1] . '"'), true);
    }

    public function test_billing_staff_are_only_offered_what_their_role_opens(): void
    {
        $staff = User::factory()->create(['guide_completed_at' => now()]);
        CompanyMember::create([
            'company_id' => $this->company->id, 'invited_by' => $this->owner->id, 'user_id' => $staff->id,
            'email' => $staff->email, 'role' => 'staff', 'token' => str_repeat('a', 40), 'accepted_at' => now(),
        ]);
        $staff->forceFill(['active_company_id' => $this->company->id])->save();

        $html = $this->actingAs($staff)->get(route('invoices.index'))->assertOk()->getContent();
        $labels = array_column($this->destinations($html), 'label');

        $this->assertContains('New invoice', $labels);
        $this->assertContains('New customer', $labels);
        $this->assertNotContains('GSTR-1', $labels);
        $this->assertNotContains('Purchase invoices', $labels);
        $this->assertNotContains('Bank reconciliation', $labels);
    }

    public function test_each_page_has_a_help_button_for_that_page(): void
    {
        $this->actingAs($this->owner)->get(route('bank.index'))->assertOk()
            ->assertSee('href="' . route('help') . '#bank"', false)
            ->assertSee('aria-label="Help with this page"', false);
        $this->actingAs($this->owner)->get(route('finance.gstr3b'))->assertOk()
            ->assertSee('href="' . route('help') . '#gst"', false);
    }

    public function test_a_new_person_gets_the_tour_once(): void
    {
        $new = User::factory()->create(['guide_completed_at' => null]);
        $company = Company::factory()->create(['user_id' => $new->id, 'state_id' => $this->company->state_id, 'onboarded_at' => now()]);
        $new->forceFill(['active_company_id' => $company->id])->save();

        $this->actingAs($new)->get(route('dashboard'))->assertOk()->assertSee('appTour(', false);

        $this->actingAs($new)->post(route('guide.complete'))->assertNoContent();
        $this->assertNotNull($new->fresh()->guide_completed_at);

        $this->actingAs($new->fresh())->get(route('dashboard'))->assertOk()->assertDontSee('appTour(', false);
    }

    public function test_people_who_already_use_the_app_are_not_interrupted_but_can_take_it(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertDontSee('appTour(', false);
        $this->actingAs($this->owner)->get(route('dashboard', ['tour' => 1]))->assertOk()->assertSee('appTour(', false);
        $this->actingAs($this->owner)->get(route('help'))->assertOk()->assertSee('Take the tour');
    }
}
