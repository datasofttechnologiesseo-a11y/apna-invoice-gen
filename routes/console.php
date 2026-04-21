<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Payment reminder automation. Runs once a day at the hour configured in
// config/reminders.php. Uses withoutOverlapping to be safe on misconfigured
// schedulers (e.g. the cron firing twice).
Schedule::command('invoices:send-reminders')
    ->dailyAt(str_pad((string) config('reminders.send_hour', 8), 2, '0', STR_PAD_LEFT) . ':00')
    ->withoutOverlapping()
    ->onOneServer();

// Weekly user data backup emails. Sunday morning — inbox arrival time that
// doesn't compete with Monday business emails and gives the user time to
// download before the week starts.
Schedule::command('backups:send-weekly')
    ->weeklyOn(0, '07:00') // 0 = Sunday
    ->withoutOverlapping()
    ->onOneServer();

// Note: database sessions are garbage-collected automatically via the session
// lottery in config/session.php (default 2/100 requests), so we don't schedule
// an explicit prune command. If session volume ever becomes an issue, add
// `delete from sessions where last_activity < (unix_timestamp() - 172800)`
// as a daily DB job.
