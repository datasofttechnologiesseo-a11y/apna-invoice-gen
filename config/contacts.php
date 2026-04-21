<?php

/**
 * Role-based contact email addresses shown across the marketing / legal
 * pages. Centralised here so changing the domain or consolidating inboxes
 * later is a one-file edit.
 *
 * Override per-env with CONTACT_EMAIL_* entries in .env if needed.
 */

$domain = env('CONTACT_EMAIL_DOMAIN', 'datasofttechnologies.com');

return [
    'sales'     => env('CONTACT_EMAIL_SALES',     "sales@{$domain}"),
    'support'   => env('CONTACT_EMAIL_SUPPORT',   "support@{$domain}"),
    'hello'     => env('CONTACT_EMAIL_HELLO',     "hello@{$domain}"),
    'careers'   => env('CONTACT_EMAIL_CAREERS',   "careers@{$domain}"),
    'press'     => env('CONTACT_EMAIL_PRESS',     "press@{$domain}"),
    'partners'  => env('CONTACT_EMAIL_PARTNERS',  "partners@{$domain}"),
    // Required by Indian DPDP Act / privacy-policy compliance.
    'privacy'   => env('CONTACT_EMAIL_PRIVACY',   "privacy@{$domain}"),
    'grievance' => env('CONTACT_EMAIL_GRIEVANCE', "grievance@{$domain}"),
    'legal'     => env('CONTACT_EMAIL_LEGAL',     "legal@{$domain}"),
    'billing'   => env('CONTACT_EMAIL_BILLING',   "billing@{$domain}"),
    'security'  => env('CONTACT_EMAIL_SECURITY',  "security@{$domain}"),
];
