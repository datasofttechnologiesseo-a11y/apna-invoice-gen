<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Install Apna Invoice" on phones: offered on the public site and sign-in
 * pages, where people arrive from a search, and on the dashboard; kept off
 * app screens that have their own bottom bars. The site must stay
 * installable: a manifest that names the app and its icons.
 */
class InstallAppPromptTest extends TestCase
{
    use RefreshDatabase;

    private const BAR = 'aria-label="Install Apna Invoice"';

    public function test_the_home_page_and_sign_in_pages_offer_install(): void
    {
        $this->get('/')->assertOk()->assertSee(self::BAR, false)->assertSee('beforeinstallprompt', false);
        $this->get(route('login'))->assertOk()->assertSee(self::BAR, false);
        $this->get(route('register'))->assertOk()->assertSee(self::BAR, false);
    }

    public function test_public_content_pages_offer_install(): void
    {
        $this->get(route('pages.contact'))->assertOk()->assertSee(self::BAR, false);
    }

    public function test_inside_the_app_only_the_dashboard_offers_it(): void
    {
        $user = User::factory()->create();
        $user->ensureCompany();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee(self::BAR, false);
        $this->actingAs($user)->get(route('invoices.create'))->assertOk()->assertDontSee(self::BAR, false);
    }

    public function test_the_site_stays_installable(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['short_name']);
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertFileExists(public_path('service-worker.js'));
        $this->get('/')->assertSee('<link rel="manifest" href="/manifest.json">', false);
    }
}
