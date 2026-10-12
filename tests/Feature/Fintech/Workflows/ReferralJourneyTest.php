<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The referral offer, end to end through the real sign-up: a friend who
 * signs up with your link and then ISSUES their first invoice earns you
 * lifetime free access. A sign-up or a draft earns nothing, the reward is
 * given once, and nobody can earn it from their own invoices.
 *
 * (tests/Feature/ReferralRewardTest.php builds the referral rows by hand;
 * this file goes through /register?ref=... and the OTP step.)
 */
class ReferralJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private const FRIEND_EMAIL = 'rahul@mehtaagencies.example';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        Mail::fake();
    }

    /** Back to plain web requests after an API call (Sanctum switches the default guard). */
    private function webGuard(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    /** Guest sign-up through the form and the OTP step. Returns the new user. */
    private function signUp(string $name, string $email, string $phone, array $extra = []): User
    {
        $res = $this->post('/register', array_merge([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Friend#2026-pass',
            'password_confirmation' => 'Friend#2026-pass',
            'terms_accepted' => '1',
        ], $extra));
        $res->assertRedirect(route('register.verify'));
        $code = (string) $res->getSession()->get('otp_dev_code');

        $this->post(route('register.verify.store'), ['code' => $code])
            ->assertRedirect(route('onboarding.index'));

        $user = User::where('email', $email)->firstOrFail();
        $this->assertAuthenticatedAs($user);

        return $user;
    }

    /** Guided setup for an unregistered (no GSTIN) shop in Maharashtra, plus one customer. */
    private function onboard(User $user, string $business, string $customer): Customer
    {
        $this->actingAs($user)->get(route('onboarding.index'))->assertRedirect(route('onboarding.business'));
        $this->actingAs($user)->post(route('onboarding.business.save'), [
            'name' => $business,
            'state_id' => $this->state('27')->id,
            'address_line1' => '7 Station Road',
            'city' => 'Nashik',
            'country' => 'India',
            'default_currency' => 'INR',
        ])->assertSessionHasNoErrors()->assertRedirect(route('onboarding.customer'));
        $this->actingAs($user)->post(route('onboarding.customer.save'), [
            'name' => $customer,
            'state_id' => $this->state('27')->id,
            'country' => 'India',
        ])->assertSessionHasNoErrors()->assertRedirect(route('onboarding.done'));
        $this->actingAs($user)->get(route('onboarding.done'))->assertOk();

        return Customer::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_a_friend_who_signs_up_with_the_link_and_issues_a_first_invoice_earns_the_referrer_lifetime_free_access(): void
    {
        $aBiz = $this->makeBusiness([], ['name' => 'Arbaz Khan', 'email' => 'arbaz@khantraders.example']);
        $a = $this->ownerOf($aBiz);
        $this->assertNull($a->referral_code, 'a code is made on first visit, not before');

        // ── A opens "Refer a friend" and gets a code and a link ────────────
        $page = $this->actingAs($a)->get(route('referrals.index'))->assertOk();
        $code = (string) $a->fresh()->referral_code;
        $this->assertMatchesRegularExpression('/^AI-[A-Z0-9]{4}$/', $code);
        $link = url('/register?ref=' . $code);
        $page->assertSee($code)->assertSee($link)->assertSee('No referrals yet.');

        // The code is stable: a second visit (and the app) returns the same one.
        $this->actingAs($a)->get(route('referrals.index'))->assertOk();
        $this->assertSame($code, $a->fresh()->referral_code);
        $this->apiAs($a)->getJson('/api/referrals')->assertOk()
            ->assertJsonPath('code', $code)
            ->assertJsonPath('share_url', $link)
            ->assertJsonPath('stats.total', 0)
            ->assertJsonPath('lifetime_free', false)
            ->assertJsonPath('lifetime_free_since', null);
        $this->webGuard();

        $this->actingAs($a)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();

        // ── B opens the link (typed in lower case) ────────────────────────
        $this->get('/register?ref=' . strtolower($code))->assertOk()
            ->assertSee('Referral code applied')
            ->assertSee($code);
        $this->assertSame($code, session('referral_code'));

        // ...and signs up through the OTP step (the form carries the code).
        $b = $this->signUp('Rahul Mehta', self::FRIEND_EMAIL, '9123456780', ['referral_code' => $code]);

        $this->assertSame((int) $a->id, (int) $b->referred_by_user_id);
        $this->assertSame(1, Referral::count());
        $referral = Referral::firstOrFail();
        $this->assertSame((int) $a->id, (int) $referral->referrer_user_id);
        $this->assertSame((int) $b->id, (int) $referral->referee_user_id);
        $this->assertSame($code, $referral->referral_code);
        $this->assertSame('pending', $referral->reward_status);
        $this->assertSame('2026-10-12 11:00:00', $referral->signed_up_at->format('Y-m-d H:i:s'));
        $this->assertNull($referral->rewarded_at);
        $this->assertNull(session('referral_code'), 'the remembered code is used once');
        $this->assertNull($a->fresh()->lifetime_free_at, 'a sign-up alone earns nothing');

        // A sees the friend, waiting.
        $text = $this->flatten($this->actingAs($a)->get(route('referrals.index'))->assertOk()->getContent());
        $this->assertStringContainsString('Sign-ups 1 Waiting for first invoice 1 Rewarded 0', $text);
        $this->assertStringContainsString('Rahul Mehta Joined 12 Oct 2026 Waiting for first invoice', $text);
        $this->assertStringNotContainsString('You have lifetime free access', $text);

        // ── B sets up and saves a draft: still nothing ────────────────────
        $customer = $this->onboard($b, 'Mehta Agencies', 'Joshi Kirana');
        $draft = $this->webDraft($b, $customer);
        $this->assertSame('draft', $draft->status);
        // 1 x 1,000 @ 18% intra-state = 1,180.
        $this->assertMoney(1180, $draft->grand_total);
        $this->assertNull($a->fresh()->lifetime_free_at, 'a draft is not an issued invoice');
        $this->assertSame('pending', $referral->fresh()->reward_status);

        // ── B issues the first invoice: A earns lifetime free access ──────
        $issued = $this->webIssue($b, $draft);
        $this->assertSame('INV/2026-27/0001', $issued->invoice_number);
        $this->assertSame((int) $b->id, (int) $issued->user_id);

        $a->refresh();
        $this->assertNotNull($a->lifetime_free_at);
        $this->assertSame('2026-10-12 11:00:00', $a->lifetime_free_at->format('Y-m-d H:i:s'));
        $this->assertTrue($a->hasLifetimeFreeAccess());
        $referral->refresh();
        $this->assertSame('rewarded', $referral->reward_status);
        $this->assertSame('2026-10-12 11:00:00', $referral->rewarded_at->format('Y-m-d H:i:s'));
        $this->assertNull($b->fresh()->lifetime_free_at, 'the friend is not rewarded for being referred');

        // ── Three days later, a second invoice changes nothing ────────────
        $this->travel(3)->days();
        $second = $this->webIssued($b, $customer);
        $this->assertSame('INV/2026-27/0002', $second->invoice_number);
        $this->assertSame('2026-10-12 11:00:00', $a->fresh()->lifetime_free_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-12 11:00:00', $referral->fresh()->rewarded_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, Referral::count());
        $this->assertSame(1, User::whereNotNull('lifetime_free_at')->count());

        // ── A's page and the app show the reward ──────────────────────────
        $text = $this->flatten($this->actingAs($a)->get(route('referrals.index'))->assertOk()->getContent());
        $this->assertStringContainsString('You have lifetime free access to Apna Invoice.', $text);
        $this->assertStringContainsString('Earned on 12 Oct 2026, when Rahul Mehta issued their first invoice.', $text);
        $this->assertStringContainsString('Sign-ups 1 Waiting for first invoice 0 Rewarded 1', $text);

        $this->apiAs($a)->getJson('/api/referrals')->assertOk()
            ->assertJsonPath('lifetime_free', true)
            ->assertJsonPath('lifetime_free_since', '2026-10-12')
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.pending', 0)
            ->assertJsonPath('stats.rewarded', 1)
            ->assertJsonPath('referrals.0.name', 'Rahul Mehta')
            ->assertJsonPath('referrals.0.reward_status', 'rewarded');
    }

    public function test_an_unknown_referral_code_is_ignored(): void
    {
        $aBiz = $this->makeBusiness();
        $a = $this->ownerOf($aBiz);
        $a->forceFill(['referral_code' => 'AI-K7Q2'])->save();

        // An unknown code on the link is not remembered or shown.
        $this->get('/register?ref=AI-K7Q3')->assertOk()->assertDontSee('Referral code applied');
        $this->assertNull(session('referral_code'));

        // Nor does a code typed into the hidden field by hand do anything.
        $b = $this->signUp('Sunita Rao', 'sunita@raoprints.example', '9988776655', ['referral_code' => 'ai-k7q3']);
        $this->assertNull($b->referred_by_user_id);
        $this->assertSame(0, Referral::count());

        // Her first invoice rewards nobody.
        $customer = $this->onboard($b, 'Rao Prints', 'Desai Book Depot');
        $this->webIssued($b, $customer);
        $this->assertSame(0, User::whereNotNull('lifetime_free_at')->count());
        $this->assertNull($a->fresh()->lifetime_free_at);
    }

    public function test_a_referrer_cannot_use_their_own_link_or_earn_from_their_own_invoices(): void
    {
        $aBiz = $this->makeBusiness([], ['name' => 'Arbaz Khan']);
        $a = $this->ownerOf($aBiz);
        $this->actingAs($a)->get(route('referrals.index'))->assertOk();
        $code = (string) $a->fresh()->referral_code;

        // Signed in, the sign-up page (and its ?ref=) is closed to them.
        $this->actingAs($a)->get('/register?ref=' . $code)->assertRedirect(route('dashboard'));
        $this->assertNull(session('referral_code'));

        // A's own invoices, in the first business and in a second one, earn A nothing.
        $this->webIssued($a, $this->customerOf($aBiz));
        $second = Company::factory()->create([
            'user_id' => $a->id, 'name' => 'Khan Exports', 'state_id' => $this->state('27')->id,
            'gstin' => '27AAACT2727Q1ZW', 'invoice_prefix' => 'KE', 'onboarded_at' => now(),
        ]);
        $this->actingAs($a)->from(route('dashboard'))->post(route('companies.switch', $second))
            ->assertSessionHas('status', 'Switched to Khan Exports.');
        $this->assertSame((int) $second->id, (int) $a->fresh()->active_company_id);
        $inSecond = $this->webIssued($a->fresh(), $this->customerOf($second));
        $this->assertSame((int) $second->id, (int) $inSecond->company_id);

        $this->assertNull($a->fresh()->lifetime_free_at);
        $this->assertSame(0, Referral::count());

        // A malformed referral row naming A as their own referee (legacy data,
        // a manual insert) is never rewarded either.
        Referral::create([
            'referrer_user_id' => $a->id, 'referee_user_id' => $a->id, 'referral_code' => $code,
            'reward_status' => 'pending', 'signed_up_at' => now(),
        ]);
        $this->webIssued($a->fresh(), $this->customerOf($second, '27', ['name' => 'Bhosale Mart']));
        $this->assertNull($a->fresh()->lifetime_free_at);
        $this->assertSame('pending', Referral::firstOrFail()->reward_status);
    }

    public function test_a_referred_friend_billing_inside_the_referrers_business_does_not_count(): void
    {
        // A refers B. A then invites B as billing staff. Invoices B raises in
        // A's business are A's own sales (they carry A's numbering and A's
        // account), so they must not earn A the reward. B's first invoice in
        // B's own business does.
        $aBiz = $this->makeBusiness([], ['name' => 'Arbaz Khan']);
        $a = $this->ownerOf($aBiz);
        $this->actingAs($a)->get(route('referrals.index'))->assertOk();
        $code = (string) $a->fresh()->referral_code;
        $aCustomer = $this->customerOf($aBiz);
        $this->actingAs($a)->post(route('logout'));

        $this->get('/register?ref=' . $code)->assertOk();
        $b = $this->signUp('Rahul Mehta', self::FRIEND_EMAIL, '9123456780', ['referral_code' => $code]);
        $bCustomer = $this->onboard($b, 'Mehta Agencies', 'Joshi Kirana');
        $bBiz = Company::where('user_id', $b->id)->firstOrFail();

        CompanyMember::create([
            'company_id' => $aBiz->id, 'user_id' => $b->id, 'email' => self::FRIEND_EMAIL,
            'role' => 'staff', 'token' => CompanyMember::newToken(), 'invited_by' => $a->id, 'accepted_at' => now(),
        ]);
        $this->actingAs($b)->post(route('companies.switch', $aBiz))->assertRedirect(route('dashboard'));
        $this->assertSame((int) $aBiz->id, (int) $b->fresh()->active_company_id);

        $inA = $this->webIssued($b->fresh(), $aCustomer);
        $this->assertSame((int) $aBiz->id, (int) $inA->company_id);
        $this->assertSame((int) $a->id, (int) $inA->user_id, "a member's invoice is the owner's");
        $this->assertSame('INV-0001', $inA->invoice_number, "A's own series");
        $this->assertNull($a->fresh()->lifetime_free_at, "billing in the referrer's own business is not the friend's first invoice");
        $this->assertSame('pending', Referral::where('referee_user_id', $b->id)->value('reward_status'));

        // Back in B's own business, B's first invoice earns A the reward.
        $this->actingAs($b->fresh())->post(route('companies.switch', $bBiz));
        $this->assertSame((int) $bBiz->id, (int) $b->fresh()->active_company_id);
        $own = $this->webIssued($b->fresh(), $bCustomer);
        $this->assertSame((int) $b->id, (int) $own->user_id);
        $this->assertSame('INV/2026-27/0001', $own->invoice_number);
        $this->assertNotNull($a->fresh()->lifetime_free_at);
        $this->assertSame('rewarded', Referral::where('referee_user_id', $b->id)->value('reward_status'));
    }

    public function test_the_referral_page_and_api_need_a_signed_in_user(): void
    {
        $this->get(route('referrals.index'))->assertRedirect(route('login'));
        $this->getJson('/api/referrals')->assertUnauthorized();
        $this->assertSame(0, User::whereNotNull('referral_code')->count());
    }
}
