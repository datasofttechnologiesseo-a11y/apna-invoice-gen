<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super-admin email allowlist
    |--------------------------------------------------------------------------
    |
    | Admin access is gated on BOTH the `is_super_admin` database flag AND
    | membership of this allowlist. This is defence in depth: if the flag is
    | ever flipped on some other account (by accident, a bad seeder, or an
    | attacker who reaches the DB), that account STILL cannot open /admin
    | unless its email is listed here.
    |
    | Set SUPER_ADMIN_EMAILS in the environment as a comma-separated list to
    | change who qualifies — no code deploy needed. Defaults to the single
    | authorised operator. An empty allowlist falls back to the flag alone
    | (used by the test suite; do NOT leave it empty in production).
    |
    */

    'super_admin_emails' => array_values(array_filter(array_map(
        fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('SUPER_ADMIN_EMAILS', 'brijesh@datasofttechnologies.com'))
    ))),

];
