<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Referral;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The referral offer: refer a friend, and when they issue their first invoice
 * you get lifetime free access. A sign-up alone, or a draft, does not count.
 */
class ReferralRewardTest extends TestCase
{
    use RefreshDatabase;

    private User $referrer;
    private User $friend;
    private Invoice $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->referrer = User::factory()->create(['name' => 'Arbaz']);
        $this->referrer->ensureReferralCode();

        $this->friend = User::factory()->create(['name' => 'Rahul Traders', 'referred_by_user_id' => $this->referrer->id]);
        Referral::create([
            'referrer_user_id' => $this->referrer->id, 'referee_user_id' => $this->friend->id,
            'referral_code' => $this->referrer->referral_code, 'reward_status' => 'pending', 'signed_up_at' => now(),
        ]);

        $company = Company::factory()->create(['user_id' => $this->friend->id, 'state_id' => $state->id, 'invoice_prefix' => 'INV', 'invoice_counter' => 0, 'onboarded_at' => now()]);
        $this->friend->forceFill(['active_company_id' => $company->id])->save();
        $customer = Customer::factory()->create(['user_id' => $this->friend->id, 'company_id' => $company->id, 'state_id' => $state->id]);

        $this->actingAs($this->friend)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $this->draft = Invoice::latest('id')->firstOrFail();
    }

    public function test_a_sign_up_or_a_draft_does_not_earn_the_reward(): void
    {
        $this->assertSame('pending', Referral::first()->reward_status);
        $this->assertNull($this->referrer->fresh()->lifetime_free_at);
    }

    public function test_the_friends_first_issued_invoice_earns_lifetime_free_access_once(): void
    {
        $this->actingAs($this->friend)->post(route('invoices.finalize', $this->draft))->assertSessionHasNoErrors();

        $referral = Referral::first();
        $this->assertSame('rewarded', $referral->reward_status);
        $this->assertNotNull($referral->rewarded_at);
        $earned = $this->referrer->fresh()->lifetime_free_at;
        $this->assertNotNull($earned);
        $this->assertTrue($this->referrer->fresh()->hasLifetimeFreeAccess());

        // A second invoice changes nothing.
        $this->travel(2)->days();
        $second = $this->draft->replicate(['invoice_number', 'finalized_at']);
        $second->status = 'draft';
        $second->save();
        $second->update(['finalized_at' => now(), 'status' => 'final']);
        $this->assertEquals($earned->toDateTimeString(), $this->referrer->fresh()->lifetime_free_at->toDateTimeString());
    }

    public function test_the_refer_page_and_the_app_show_the_offer_and_the_reward(): void
    {
        $this->actingAs($this->referrer)->get(route('referrals.index'))->assertOk()
            ->assertSee('lifetime free access', false)
            ->assertSee('Waiting for first invoice')
            ->assertDontSee('You have lifetime free access to Apna Invoice.');

        $this->actingAs($this->friend)->post(route('invoices.finalize', $this->draft))->assertSessionHasNoErrors();

        $this->actingAs($this->referrer->fresh())->get(route('referrals.index'))->assertOk()
            ->assertSee('You have lifetime free access to Apna Invoice.')
            ->assertSee('when Rahul Traders issued their first invoice', false)
            ->assertSee('Rewarded');

        Sanctum::actingAs($this->referrer->fresh());
        $this->getJson('/api/referrals')->assertOk()
            ->assertJsonPath('lifetime_free', true)
            ->assertJsonPath('stats.rewarded', 1);
    }

    public function test_someone_who_was_not_referred_earns_nothing_for_anyone(): void
    {
        $loner = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $loner->id, 'status' => 'draft', 'finalized_at' => null]);
        $invoice->update(['finalized_at' => now(), 'status' => 'final']);

        $this->assertSame(0, User::whereNotNull('lifetime_free_at')->count());
    }
}
