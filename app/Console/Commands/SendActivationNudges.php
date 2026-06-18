<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Onboarding\OnboardingMailer;
use Illuminate\Console\Command;

/**
 * Daily activation drip: nudges users who signed up but haven't created their
 * first invoice yet. Each stage (day1/day3/day7) fires once, in a short window
 * after sign-up, and only while the user still has zero invoices. Creating an
 * invoice removes them from every future nudge.
 */
class SendActivationNudges extends Command
{
    protected $signature = 'emails:send-activation
                            {--dry : List who would be emailed without sending}';

    protected $description = 'Send day-1/3/7 activation nudges to users who have not created an invoice yet';

    public function handle(OnboardingMailer $mailer): int
    {
        if (! config('onboarding.enabled', true)) {
            $this->info('Onboarding emails are disabled (ONBOARDING_EMAILS_ENABLED=false).');
            return self::SUCCESS;
        }

        // Only the timed nudges here; "welcome" is sent at sign-up.
        $stages = collect(config('onboarding.stages', []))
            ->filter(fn ($cfg, $stage) => $stage !== 'welcome' && ($cfg['after_days'] ?? 0) > 0);

        $sent = 0;

        foreach ($stages as $stage => $cfg) {
            $days = (int) $cfg['after_days'];

            // Window: registered between `days` and `days + 2` ago. The upper
            // bound tolerates a missed daily run; the lower bound stops us from
            // back-blasting users who signed up long before this feature existed.
            $upper = now()->subDays($days);
            $lower = now()->subDays($days + 2);

            $users = User::query()
                ->whereNull('erased_at')
                ->whereBetween('created_at', [$lower, $upper])
                ->whereDoesntHave('invoices')
                ->whereNotIn('id', function ($q) use ($stage) {
                    $q->select('user_id')->from('onboarding_emails')->where('stage', $stage);
                })
                ->get();

            $this->info("Stage {$stage}: {$users->count()} candidate(s).");

            foreach ($users as $user) {
                if ($this->option('dry')) {
                    $this->line("  [dry] {$stage} -> {$user->email} (#{$user->id})");
                    continue;
                }

                if ($mailer->send($user, $stage)) {
                    $sent++;
                    $this->line("  sent {$stage} -> {$user->email}");
                }
            }
        }

        $this->info("Done. Sent {$sent} nudge(s).");

        return self::SUCCESS;
    }
}
