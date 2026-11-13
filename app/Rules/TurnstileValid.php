<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates a Cloudflare Turnstile response token against the siteverify
 * endpoint. Passes silently if captcha is not configured (dev / tests).
 */
class TurnstileValid implements ValidationRule
{
    /**
     * Run even when the field is missing or empty. Laravel skips an ordinary
     * rule for an absent field, so a bot that simply left the captcha field
     * out of its request would have got through.
     */
    public bool $implicit = true;

    public function __construct(private readonly ?string $ip = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('captcha.secret_key');

        // The widget is on the form only when the site key is set (see
        // components/turnstile), and a token can be checked only with the
        // secret. With either key missing there is no captcha to answer, so
        // the request goes through: refusing it would lock every user out of
        // login, sign-up and password reset (it did, in November 2026, on a
        // server without the keys). In production the gap is logged so it is
        // seen and fixed.
        if (empty($secret) || empty(config('captcha.site_key'))) {
            if (app()->environment('production')) {
                Log::error('Turnstile is not configured (TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are both needed). Forms are accepted without a captcha.');
            }
            return;
        }

        if (empty($value) || ! is_string($value)) {
            $fail(__('Captcha challenge was not completed. Please try again.'));
            return;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('captcha.timeout', 5))
                ->post(config('captcha.verify_url'), [
                    'secret'   => $secret,
                    'response' => $value,
                    'remoteip' => $this->ip ?? request()->ip(),
                ]);

            $body = $response->json();

            if (! ($body['success'] ?? false)) {
                Log::warning('Turnstile verification failed', [
                    'errors' => $body['error-codes'] ?? [],
                    'ip'     => $this->ip ?? request()->ip(),
                ]);
                $fail(__('Captcha verification failed. Please refresh and try again.'));
            }
        } catch (\Throwable $e) {
            // Fail-closed: if Cloudflare is unreachable, don't let auth through.
            Log::error('Turnstile verification error: ' . $e->getMessage());
            $fail(__('Could not verify captcha. Please try again in a moment.'));
        }
    }
}
