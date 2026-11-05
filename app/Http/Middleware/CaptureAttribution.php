<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers where a visitor came from: the Google Ads click id and the UTM
 * tags of the first link that brought them in this session, plus the page
 * they landed on. AccountCreator saves it on the new account, so sign-ups can
 * be traced back to the ad or campaign that brought them. First touch wins:
 * later visits in the same session do not overwrite it.
 */
class CaptureAttribution
{
    public const KEYS = ['gclid', 'gbraid', 'wbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->user() && ! $request->session()->has('attribution')) {
            $tags = array_filter(
                array_map(fn ($v) => is_string($v) ? mb_substr(trim($v), 0, 200) : null, $request->only(self::KEYS)),
                fn ($v) => $v !== null && $v !== ''
            );
            if ($tags) {
                $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
                $request->session()->put('attribution', $tags + [
                    'landing' => mb_substr('/' . ltrim($request->path(), '/'), 0, 200),
                    'referrer' => $referrer ? mb_substr($referrer, 0, 120) : null,
                    'at' => now()->toIso8601String(),
                ]);
            }
        }

        return $next($request);
    }
}
