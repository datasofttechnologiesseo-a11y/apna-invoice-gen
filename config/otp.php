<?php

/**
 * Mobile OTP verification for registration.
 *
 * In India, transactional/OTP SMS requires DLT registration of the entity,
 * sender ID and message template with your telecom operator, plus an SMS
 * gateway (MSG91, Twilio, Fast2SMS, …). Until that's configured the 'log'
 * driver writes the OTP to the log so the flow is fully testable in dev.
 */
return [
    // Number of digits in the OTP.
    'length' => 6,

    // How long an OTP stays valid.
    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    // Wrong-guess attempts allowed before the code is invalidated.
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    // Seconds the user must wait before requesting a new code.
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 30),

    // Total new codes a user may request per sign-up attempt. Caps the overall
    // guess budget (max_resends x max_attempts) so the attempt limit can't be
    // reset indefinitely by repeatedly resending.
    'max_resends' => (int) env('OTP_MAX_RESENDS', 3),

    // OTP delivery driver:
    //   'log'   — dev: writes the code to the log (and exposes it in the UI).
    //   'email' — always email the code.
    //   (a real SMS gateway like 'msg91' can be added later).
    'driver' => env('OTP_SMS_DRIVER', 'log'),

    // Safety net: in PRODUCTION, if the driver is 'log' (i.e. no real SMS
    // gateway is wired up yet), deliver the code by email instead so sign-ups
    // never break while DLT/SMS is being set up.
    'email_fallback' => (bool) env('OTP_EMAIL_FALLBACK', true),

    // In non-production, surface the code in the UI so testing doesn't need a
    // live SMS gateway. NEVER enabled in production.
    'expose_in_dev' => (bool) env('OTP_EXPOSE_IN_DEV', true),
];
