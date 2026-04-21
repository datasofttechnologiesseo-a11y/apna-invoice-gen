<?php

/**
 * Cloudflare Turnstile — free, privacy-respecting captcha. Required on the
 * public auth endpoints in production to block bot sign-ups / credential
 * stuffing / password-reset floods.
 *
 * Get keys at https://dash.cloudflare.com/?to=/:account/turnstile
 * Set TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY in your .env.
 *
 * When both keys are blank, captcha is a silent no-op (useful for local dev,
 * tests, and staging).
 */

return [

    'provider' => 'turnstile',

    'site_key'   => env('TURNSTILE_SITE_KEY'),
    'secret_key' => env('TURNSTILE_SECRET_KEY'),

    // Turnstile siteverify endpoint
    'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',

    // Timeout (seconds) for the server-side verification call
    'timeout' => 5,

];
