<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The result of an action ("5 lines read, 2 matched", "Books are locked")
 * reaches the user on every page: the layout shows it when the page itself
 * doesn't, and never twice.
 */
class FlashMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $this->user->id, 'state_id' => $state->id, 'onboarded_at' => now()]);
        $this->user->forceFill(['active_company_id' => $company->id])->save();
    }

    public function test_pages_without_their_own_message_box_still_show_the_result(): void
    {
        foreach (['bank.index', 'finance.gstr2b', 'finance.gstr1', 'finance.gstr3b', 'team.index'] as $route) {
            $html = $this->actingAs($this->user)->withSession(['status' => "Done on {$route}: 5 lines read."])
                ->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, "Done on {$route}: 5 lines read."), $route);
        }
    }

    public function test_errors_show_too_and_stay_until_dismissed(): void
    {
        $this->actingAs($this->user)->withSession(['error' => "Books are locked until 30 Sep 2026."])
            ->get(route('bank.index'))->assertOk()
            ->assertSee('Books are locked until 30 Sep 2026.')
            ->assertSee('role="alert"', false);
    }

    public function test_pages_with_their_own_message_box_show_it_once(): void
    {
        // invoices.index places <x-flash />; backup.index draws its own box.
        foreach (['invoices.index', 'backup.index'] as $route) {
            $html = $this->actingAs($this->user)->withSession(['status' => "Saved on {$route}."])
                ->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, "Saved on {$route}."), $route);
        }
    }

    public function test_internal_status_codes_are_not_shown_as_messages(): void
    {
        $this->actingAs($this->user)->withSession(['status' => 'profile-updated'])
            ->get(route('bank.index'))->assertOk()->assertDontSee('profile-updated');
    }
}
