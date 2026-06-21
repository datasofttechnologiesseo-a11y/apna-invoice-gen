<?php

/**
 * Single source of truth for DPDP / legal contact details.
 *
 * The Digital Personal Data Protection Act, 2023 (and the IT Rules) require a
 * data fiduciary to publish a Grievance Officer and to be reachable for data
 * principal requests. Keep these values here so the privacy policy, the
 * in-app Data & Privacy Center, and the footer all read from one place.
 *
 * Override per-environment via the matching env vars if the contacts differ
 * between staging and production.
 */
return [
    // The data fiduciary (the legal operator of the Service).
    'operator' => env('LEGAL_OPERATOR', 'Datasoft Technologies'),

    // Grievance Officer — must be a real, monitored mailbox.
    'grievance' => [
        'name' => env('LEGAL_GRIEVANCE_NAME', 'Grievance Officer'),
        'email' => env('LEGAL_GRIEVANCE_EMAIL', 'grievance@datasofttechnologies.com'),
    ],

    // Where data principals send access / correction / erasure requests.
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL', 'privacy@datasofttechnologies.com'),

    // Internal contact notified when a personal-data breach is logged.
    'breach_email' => env('LEGAL_BREACH_EMAIL', 'security@datasofttechnologies.com'),

    // Statutory retention for GST invoice records (months). Section 36 of the
    // CGST Act requires books/records be kept 72 months from the due date of
    // the annual return. Erasure depersonalises but retains within this window.
    'gst_retention_months' => (int) env('LEGAL_GST_RETENTION_MONTHS', 72),

    // DPDP timelines we hold ourselves to (days / hours), surfaced in the UI.
    'request_response_days' => 30,
    'breach_report_hours' => 72,
];
