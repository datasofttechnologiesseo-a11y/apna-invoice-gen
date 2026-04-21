<?php

/**
 * Payment follow-up reminder configuration.
 *
 * The scheduler (`php artisan schedule:run` at 08:00 IST) runs the
 * `invoices:send-reminders` command, which walks every overdue invoice and
 * decides whether to send based on days-past-due and what's already been sent.
 */

return [

    // Feature toggle — set REMINDERS_ENABLED=false to pause all automation.
    'enabled' => env('REMINDERS_ENABLED', true),

    // Time of day (in app timezone) when reminders should go out. Morning in
    // India is polite and hits working-hour inboxes.
    'send_hour' => env('REMINDERS_SEND_HOUR', 8),

    // Days-past-due thresholds at which a reminder is automatically triggered.
    // A reminder is only sent once per threshold, so 7-day and 15-day can both fire
    // on distinct days without duplicating.
    'thresholds' => [
        0,    // Day the invoice is due
        3,    // 3 days late
        7,    // 1 week late
        15,   // 2 weeks late
        30,   // 1 month late
    ],

    // Channels available for reminders. Email is real; SMS and WhatsApp are
    // pluggable — drivers live in App\Services\Reminders\*. When a driver has
    // no credentials configured, it's a silent no-op.
    'channels' => [
        'email'    => ['enabled' => true],
        'whatsapp' => ['enabled' => false], // enable once you have a BSP account
        'sms'      => ['enabled' => false], // enable once DLT-registered
    ],

];
