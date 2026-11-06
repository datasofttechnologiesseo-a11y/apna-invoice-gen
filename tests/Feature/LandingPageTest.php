<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page and the ads landing page (/start) sign visitors up on the
 * page itself, with the same form as /register.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_sign_up_on_the_home_page(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('register') . '"', $html);
        foreach (['name', 'email', 'phone', 'password', 'terms_accepted'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $html, "home sign-up form has no {$field}");
        }
        $this->assertStringContainsString('Create free account', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_the_home_page_keeps_its_faq_and_calculator(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="faq"', false)
            ->assertSee('gst-calculator', false);
    }

    public function test_the_ads_page_signs_up_on_the_page_and_stays_focused(): void
    {
        $html = $this->get('/start')->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('register') . '"', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex', $html);
        $this->assertStringContainsString('<link rel="canonical" href="' . rtrim(config('app.url'), '/') . '/">', $html);
        // No site navigation leading away from sign-up.
        $this->assertStringNotContainsString(route('pages.features'), $html);
        $this->assertStringNotContainsString(route('blog.index'), $html);
        $this->assertStringContainsString(route('login'), $html);
    }

    public function test_the_headline_matches_the_ad_group(): void
    {
        $this->get('/start?for=wholesale')->assertOk()->assertSee('Free GST billing for wholesalers and distributors');
        $this->get('/start?for=ca')->assertOk()->assertSee('with GSTR-1 ready for you');
        $this->get('/start?for=shop')->assertOk()->assertSee('Free billing software for shops, with GST');
        // Anything else falls back to the default, never echoes the input.
        $this->get('/start?for=%3Cscript%3E')->assertOk()
            ->assertSee('Free GST billing software for Indian businesses')
            ->assertDontSee('<script>alert', false);
    }

    public function test_a_failed_sign_up_on_the_ads_page_comes_back_to_it(): void
    {
        $this->get('/start?for=shop');
        $this->from('/start?for=shop')->post(route('register'), ['name' => 'Ravi'])
            ->assertRedirect('/start?for=shop')
            ->assertSessionHasErrors(['email', 'phone', 'password', 'terms_accepted']);

        $this->get('/start?for=shop')->assertOk()->assertSee('data-has-errors="1"', false);
    }

    public function test_a_sign_up_from_the_ads_page_goes_on_to_the_email_code(): void
    {
        $this->get('/start?gclid=Cj0abc&utm_campaign=gst-pune');
        $this->from('/start')->post(route('register'), [
            'name' => 'Ravi Kumar', 'phone' => '9876543210', 'email' => 'ravi@example.in',
            'password' => 'Str0ng-pass-123', 'terms_accepted' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('register.verify'));
    }

    public function test_a_sign_up_that_leaves_out_the_captcha_is_refused(): void
    {
        config(['captcha.secret_key' => 'test-secret', 'captcha.site_key' => 'test-site-key']);
        $this->from('/start')->post(route('register'), [
            'name' => 'Bot', 'phone' => '9876543210', 'email' => 'bot@example.in',
            'password' => 'Str0ng-pass-123', 'terms_accepted' => true,
        ])->assertRedirect('/start')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.in']);
    }

    public function test_without_turnstile_keys_on_the_server_people_can_still_log_in_and_sign_up(): void
    {
        // A live server with no captcha keys shows no captcha widget, so no
        // answer is ever sent. That must never lock people out. (November
        // 2026: login refused every user, with the message hidden because
        // it belongs to the widget that was not on the page.)
        $missing = [[null, null], ['only-secret', null], [null, 'only-site-key']];

        // The rule as production runs it: it passes, and logs the gap.
        \Illuminate\Support\Facades\Log::spy();
        $this->app->detectEnvironment(fn () => 'production');
        foreach ($missing as [$secret, $site]) {
            config(['captcha.secret_key' => $secret, 'captcha.site_key' => $site]);
            $failed = false;
            (new \App\Rules\TurnstileValid('1.2.3.4'))->validate('cf-turnstile-response', null, function () use (&$failed) { $failed = true; });
            $this->assertFalse($failed, 'missing captcha keys must not block the form');
        }
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->times(3);
        $this->app->detectEnvironment(fn () => 'testing');

        // And a real login goes through each time.
        foreach ($missing as [$secret, $site]) {
            config(['captcha.secret_key' => $secret, 'captcha.site_key' => $site]);
            $user = User::factory()->create(['password' => bcrypt('Str0ng-pass-123')]);
            $this->post(route('login'), ['email' => $user->email, 'password' => 'Str0ng-pass-123'])
                ->assertSessionHasNoErrors();
            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }

        $this->from('/start')->post(route('register'), [
            'name' => 'Ravi Kumar', 'phone' => '9876543210', 'email' => 'ravi.nokeys@example.in',
            'password' => 'Str0ng-pass-123', 'terms_accepted' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('register.verify'));
    }

    public function test_with_turnstile_keys_set_login_needs_the_captcha(): void
    {
        config(['captcha.secret_key' => 'test-secret', 'captcha.site_key' => 'test-site-key']);
        $user = User::factory()->create(['password' => bcrypt('Str0ng-pass-123')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Str0ng-pass-123'])
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_signed_in_visitors_see_the_dashboard_link_instead_of_the_form(): void
    {
        $user = User::factory()->create();
        $user->ensureCompany();

        foreach (['/', '/start'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString(route('dashboard'), $html);
            $this->assertStringNotContainsString('action="' . route('register') . '"', $html);
        }
    }

    public function test_the_ads_page_answers_questions_and_compares_with_the_bill_book(): void
    {
        $this->get('/start')->assertOk()
            ->assertSee('Before you sign up')
            ->assertSee('Does it file my GST return?')
            ->assertSee('From the bill book and Excel to Apna Invoice')
            ->assertSee('Made for how India bills')
            ->assertSee('Billing, GST, stock and money, in one app');
        // The home page keeps its own FAQ and does not repeat the short one.
        $this->get('/')->assertOk()->assertDontSee('Before you sign up');
    }

    public function test_the_ad_group_opens_its_own_business_tab(): void
    {
        $this->get('/start?for=wholesale')->assertOk()->assertSee("x-data=\"{ tab: 'wholesale' }\"", false);
        $this->get('/start')->assertOk()->assertSee("x-data=\"{ tab: 'shop' }\"", false);
    }

    public function test_the_ads_page_is_not_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/start<', false);
    }
}
