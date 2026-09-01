<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Onboarding\OnboardingMailer;
use Illuminate\Console\Command;

/**
 * One-off re-engagement for accounts the nudge sequence can no longer reach.
 *
 * `emails:send-activation` only looks at users who registered between N and
 * N+2 days ago — a deliberate guard so a missed run can't back-blast the whole
 * database. The side effect is that anyone who signed up before the sequence
 * existed, or who slipped past the window, is permanently unreachable: they
 * never made an invoice and will never be emailed about it again.
 *
 * This command reaches exactly those people, once. It is deliberately awkward
 * to fire: dry-run by default, an explicit --send to actually deliver, and a
 * volume cap. Every send is recorded in onboarding_emails under its own stage,
 * so nobody can receive it twice.
 */
class ActivationCatchUp extends Command
{
    protected $signature = 'activation:catch-up
        {--send : actually send. Without this flag the command only reports}
        {--stage=day7 : which configured message to use}
        {--limit=200 : never send more than this many in one run}';

    protected $description = 'Email accounts that never made an invoice and are outside the nudge window (dry-run by default)';

    /** Recorded separately so a catch-up can never be confused with, or repeat, a scheduled nudge. */
    private const RECORD_STAGE = 'catchup';

    public function handle(OnboardingMailer $mailer): int
    {
        if (! config('onboarding.enabled', true)) {
            $this->warn('Onboarding emails are disabled (ONBOARDING_EMAILS_ENABLED=false). Nothing to do.');

            return self::SUCCESS;
        }

        $stage = (string) $this->option('stage');
        if (! config("onboarding.stages.{$stage}")) {
            $this->error("Unknown stage [{$stage}]. Available: ".implode(', ', array_keys(config('onboarding.stages', []))));

            return self::FAILURE;
        }

        $maxDays = (int) (collect(config('onboarding.stages', []))->max('after_days') ?: 7);
        $cutoff = now()->subDays($maxDays + 2);

        $users = User::query()
            ->whereNull('erased_at')
            ->where('is_super_admin', false)
            ->whereDoesntHave('invoices')
            ->where('created_at', '<', $cutoff)
            // Never twice.
            ->whereNotIn('id', fn ($q) => $q->select('user_id')
                ->from('onboarding_emails')->where('stage', self::RECORD_STAGE))
            ->orderBy('created_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($users->isEmpty()) {
            $this->info('Nobody is waiting — every out-of-window account has already been contacted.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%d account(s) registered before %s, never made an invoice, never caught up.',
            $users->count(), $cutoff->format('d M Y')));

        if (! $this->option('send')) {
            $this->newLine();
            $this->warn('DRY RUN — nothing sent. These would receive the "'.$stage.'" message:');
            foreach ($users as $u) {
                $this->line(sprintf('  #%-5d %-28s %-14s signed up %s',
                    $u->id, \Illuminate\Support\Str::limit($u->name, 26), $u->phone, $u->created_at->format('Y-m-d')));
            }
            $this->newLine();
            $this->line('Run again with --send to deliver.');

            return self::SUCCESS;
        }

        $sent = $failed = 0;
        foreach ($users as $user) {
            try {
                if ($mailer->send($user, $stage)) {
                    // Stamp under the catch-up stage, not the borrowed one, so
                    // this run is auditable and can never repeat.
                    \Illuminate\Support\Facades\DB::table('onboarding_emails')->insert([
                        'user_id' => $user->id,
                        'stage' => self::RECORD_STAGE,
                        'sent_at' => now(),
                    ]);
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  #{$user->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Sent {$sent}.".($failed ? " Failed {$failed}." : ''));

        return self::SUCCESS;
    }
}
