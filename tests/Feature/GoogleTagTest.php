<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Google tag (GA4 + Google Ads AW-18551697749) on every page, the
 * sign-up conversion, and where a new account came from.
 */
class GoogleTagTest extends TestCase
{
    use RefreshDatabase;

    private function enable(array $extra = []): void
    {
        config(['services.google_tags' => array_merge([
            'enabled' => true, 'ga4' => 'G-8RLQRM0KMV', 'ads' => 'AW-18551697749', 'ads_signup_label' => null,
        ], $extra)]);
    }

    private function signUp(string $email = 'meera@example.in'): \Illuminate\Testing\TestResponse
    {
        $res = $this->post(route('register'), [
            'name' => 'Meera Joshi', 'phone' => '9876543210', 'email' => $email,
            'password' => 'Str0ng-pass-123', 'terms_accepted' => true,
        ])->assertRedirect(route('register.verify'));

        return $this->post(route('register.verify.store'), ['code' => $res->getSession()->get('otp_dev_code')]);
    }

    public function test_every_kind_of_page_loads_the_tag_for_analytics_and_ads(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $user->ensureCompany();

        foreach (['/', '/features', '/pricing', '/blog', route('login')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('googletagmanager.com/gtag/js?id=G-8RLQRM0KMV', false)
                ->assertSee('["G-8RLQRM0KMV","AW-18551697749"]', false);
        }
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('AW-18551697749', false);
    }

    public function test_the_tag_loads_once_per_page(): void
    {
        $this->enable();
        $html = $this->get('/pricing')->getContent();

        $this->assertSame(1, substr_count($html, 'googletagmanager.com/gtag/js'));
    }

    public function test_ads_cookies_wait_for_marketing_consent(): void
    {
        $this->enable();

        $this->get('/')->assertSee("gtag('consent', 'default', c)", false)
            ->assertSee("ad_storage: ads", false)
            ->assertSee("addEventListener('cookie-choice-made'", false);
    }

    public function test_not_loaded_when_switched_off_or_while_impersonating(): void
    {
        $this->enable(['enabled' => false]);
        $this->get('/')->assertDontSee('AW-18551697749', false);

        $this->enable();
        $user = User::factory()->create();
        $user->ensureCompany();
        $this->actingAs($user)->withSession(['impersonator_id' => 1])->get(route('dashboard'))->assertDontSee('AW-18551697749', false);
    }

    public function test_a_new_account_reports_one_sign_up(): void
    {
        $this->enable(['ads_signup_label' => 'AbCdEf123']);

        $this->signUp();
        $first = $this->followingRedirects()->get(route('onboarding.index'))->getContent();
        $this->assertStringContainsString("gtag('event', 'sign_up', { method: \"email\" })", $first);
        $this->assertStringContainsString('"AW-18551697749\/AbCdEf123"', $first);

        // Once only.
        $this->get(route('onboarding.index'))->assertDontSee("'sign_up'", false);
    }

    public function test_without_a_label_only_the_ga4_event_is_sent(): void
    {
        $this->enable();

        $this->signUp();
        $html = $this->followingRedirects()->get(route('onboarding.index'))->getContent();
        $this->assertStringContainsString("'sign_up'", $html);
        $this->assertStringContainsString('})(null);', $html);
    }

    public function test_the_ad_click_and_campaign_are_saved_on_the_new_account(): void
    {
        $this->get('/start?gclid=Cj0KCQabc&utm_source=google&utm_medium=cpc&utm_campaign=gst-billing-pune&for=shop')->assertOk();
        // A later visit with other tags does not replace the first.
        $this->get('/pricing?utm_source=newsletter');

        $this->signUp('ravi@example.in');

        $a = User::where('email', 'ravi@example.in')->firstOrFail()->signup_attribution;
        $this->assertSame('Cj0KCQabc', $a['gclid']);
        $this->assertSame('gst-billing-pune', $a['utm_campaign']);
        $this->assertSame('/start', $a['landing']);
    }

    public function test_a_sign_up_without_tags_has_no_attribution(): void
    {
        $this->signUp('plain@example.in');

        $this->assertNull(User::where('email', 'plain@example.in')->firstOrFail()->signup_attribution);
    }

    public function test_the_security_policy_allows_google_ads(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://www.googleadservices.com', $csp);
        $this->assertStringContainsString('https://googleads.g.doubleclick.net', $csp);
        $this->assertStringContainsString('https://td.doubleclick.net', $csp);
    }
}
