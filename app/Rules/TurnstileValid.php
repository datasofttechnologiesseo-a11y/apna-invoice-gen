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
    public function __construct(private readonly ?string $ip = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('captcha.secret_key');

        // Captcha not configured — dev/test/staging pass-through. In production
        // we refuse to silently disable bot protection: that would mean a
        // forgotten env var quietly leaves auth endpoints wide open.
        if (empty($secret)) {
            if (app()->environment('production')) {
                Log::error('Turnstile keys missing in production — refusing to accept form submission.');
                $fail(__('Captcha is misconfigured. Please contact support.'));
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
