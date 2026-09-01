<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The embed exception is the fragile part here: /gst-calculator/embed exists
 * to be iframed by third-party sites, so anything that tightens framing
 * globally silently breaks a published integration. These assertions exist so
 * that breakage shows up as a failing test rather than as a partner's blank
 * iframe.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_responses_carry_the_baseline_headers(): void
    {
        $res = $this->get('/');

        $res->assertOk();
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString('camera=()', $res->headers->get('Permissions-Policy'));
    }

    public function test_csp_allows_what_the_app_actually_loads(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        // Alpine evaluates expressions, so removing 'unsafe-eval' takes every
        // interactive screen down. Fonts and Turnstile are third-party origins
        // the app genuinely depends on.
        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString('https://fonts.bunny.net', $csp);
        $this->assertStringContainsString('https://challenges.cloudflare.com', $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_the_embeddable_calculator_stays_embeddable(): void
    {
        $res = $this->get('/gst-calculator/embed');

        $res->assertOk();
        $res->assertHeaderMissing('X-Frame-Options');
        $this->assertStringContainsString(
            'frame-ancestors *',
            $res->headers->get('Content-Security-Policy'),
            'third-party sites must still be able to iframe the calculator'
        );
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $secure = $this->get('https://localhost/');
        $this->assertStringContainsString(
            'max-age=31536000',
            (string) $secure->headers->get('Strict-Transport-Security')
        );
    }

    public function test_json_endpoints_are_left_alone(): void
    {
        // Decorating non-HTML responses risks interfering with downloads and
        // streamed PDFs, so the middleware skips them.
        $this->get('/up')->assertOk();
        $res = $this->getJson('/api/states');
        $this->assertNull($res->headers->get('Content-Security-Policy'));
    }
}
