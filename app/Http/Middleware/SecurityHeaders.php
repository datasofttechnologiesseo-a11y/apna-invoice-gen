<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response security headers.
 *
 * Two things constrain what is achievable here and both are deliberate:
 *
 *  1. /gst-calculator/embed exists to be iframed by third-party sites, so it
 *     must NOT inherit the framing restriction the rest of the app gets.
 *  2. The app uses Alpine (which evaluates expressions, so it needs
 *     'unsafe-eval'), 18 inline <script> blocks and ~400 inline style
 *     attributes. A nonce-based CSP would need all of that refactored first.
 *
 * So the CSP here restricts ORIGINS - an injected <script src> pointing at an
 * attacker's domain is blocked - but it cannot stop inline execution. That is
 * a real reduction in blast radius, not full XSS protection, and it should not
 * be described as more than that.
 */
class SecurityHeaders
{
    /** Routes that third parties are meant to embed. */
    private const EMBEDDABLE = ['gst-calculator/embed'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only decorate real documents. Leaving JSON, PDFs and file downloads
        // alone keeps Content-Disposition and streaming behaviour intact.
        $type = (string) $response->headers->get('Content-Type');
        if ($type !== '' && ! str_contains($type, 'text/html')) {
            return $response;
        }

        $embeddable = in_array(trim($request->path(), '/'), self::EMBEDDABLE, true);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );

        if (! $embeddable) {
            // X-Frame-Options for older browsers; frame-ancestors below is the
            // one modern browsers actually honour.
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // HSTS only over a real TLS connection. Sending it over plain HTTP is
        // ignored anyway, and sending it from a local dev server would pin the
        // developer's browser to https://localhost.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $csp = [
            "default-src 'self'",
            // 'unsafe-eval' is Alpine; 'unsafe-inline' is the inline blocks.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            // Customers upload their own logo and signature, and invoices embed
            // them as data URIs for the PDF renderer.
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com",
            "frame-src https://challenges.cloudflare.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            $embeddable ? 'frame-ancestors *' : "frame-ancestors 'self'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        return $response;
    }
}
