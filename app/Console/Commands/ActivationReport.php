<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Where does activation break?
 *
 * Reports the funnel from sign-up to a first issued invoice, plus the health of
 * the nudge sequence. Read-only: it sends nothing and changes nothing, so it is
 * safe to run on production at any time.
 */
class ActivationReport extends Command
{
    protected $signature = 'activation:report {--stalled : also list every account that never issued an invoice}';

    protected $description = 'Show the sign-up → first-invoice funnel and the health of the activation emails';

    public function handle(): int
    {
        $users = User::query()->whereNull('erased_at')->where('is_super_admin', false);
        $total = (clone $users)->count();

        if ($total === 0) {
            $this->warn('No accounts yet.');

            return self::SUCCESS;
        }

        // ---- funnel ----------------------------------------------------
        $stages = [
            'Registered' => (clone $users)->count(),
            'Business details saved' => (clone $users)->whereHas('companies', fn ($q) => $q
                ->whereNotNull('name')->where('name', '<>', '')->whereNotNull('state_id'))->count(),
            'Finished setup wizard' => (clone $users)->whereHas('companies', fn ($q) => $q
                ->whereNotNull('onboarded_at'))->count(),
            'Added a customer' => (clone $users)->whereHas('customers')->count(),
            'Started an invoice' => (clone $users)->whereHas('invoices')->count(),
            'Issued an invoice' => (clone $users)->whereHas('invoices', fn ($q) => $q
                ->whereNotNull('finalized_at'))->count(),
            'Issued 2 or more' => (clone $users)->whereHas('invoices', fn ($q) => $q
                ->whereNotNull('finalized_at'), '>=', 2)->count(),
        ];

        $this->newLine();
        $this->line('<options=bold>ACTIVATION FUNNEL</>');
        $this->line(str_repeat('-', 62));

        $previous = null;
        foreach ($stages as $label => $count) {
            $pct = round(100 * $count / $total, 1);
            // Stages are not strictly monotonic — a user can add a customer
            // without finishing the wizard — so only report an actual fall.
            $delta = $previous === null ? 0 : $previous - $count;
            $drop = $delta > 0 ? sprintf('  (-%d)', $delta) : '';
            $this->line(sprintf('  %-24s %5d   %5s%%%s', $label, $count, $pct, $drop));
            $previous = $count;
        }

        $activated = $stages['Issued an invoice'];
        $this->newLine();
        $this->line(sprintf('  <options=bold>Activation rate: %s%%</> (%d of %d issued a real invoice)',
            round(100 * $activated / $total, 1), $activated, $total));

        // ---- biggest drop ----------------------------------------------
        $labels = array_keys($stages);
        $worst = ['from' => null, 'to' => null, 'lost' => 0];
        for ($i = 1; $i < count($labels); $i++) {
            $lost = $stages[$labels[$i - 1]] - $stages[$labels[$i]];
            if ($lost > $worst['lost']) {
                $worst = ['from' => $labels[$i - 1], 'to' => $labels[$i], 'lost' => $lost];
            }
        }
        if ($worst['lost'] > 0) {
            $this->line(sprintf('  Biggest single drop: %d accounts between "%s" and "%s".',
                $worst['lost'], $worst['from'], $worst['to']));
        }

        // ---- nudge health ----------------------------------------------
        $this->newLine();
        $this->line('<options=bold>ACTIVATION EMAILS</>');
        $this->line(str_repeat('-', 62));

        if (! config('onboarding.enabled', true)) {
            $this->warn('  DISABLED — ONBOARDING_EMAILS_ENABLED is false. No nudges are being sent.');
        }

        $bystage = DB::table('onboarding_emails')
            ->select('stage', DB::raw('COUNT(*) as n'), DB::raw('MAX(sent_at) as last_sent'))
            ->groupBy('stage')->get()->keyBy('stage');

        foreach (array_keys(config('onboarding.stages', [])) as $stage) {
            $row = $bystage->get($stage);
            $this->line(sprintf('  %-10s %5d sent   last: %s',
                $stage, $row->n ?? 0, $row->last_sent ?? 'never'));
        }

        $lastAny = DB::table('onboarding_emails')->max('sent_at');
        if (! $lastAny) {
            $this->warn('  No activation email has EVER been recorded.');
            $this->warn('  Check that the server cron runs: * * * * * php artisan schedule:run');
        } elseif (\Illuminate\Support\Carbon::parse($lastAny)->lt(now()->subDays(3))) {
            $this->warn(sprintf('  Nothing sent since %s — the scheduler may not be running.', $lastAny));
            $this->warn('  Check the server cron: * * * * * php artisan schedule:run');
        }

        // ---- who the sequence can no longer reach ----------------------
        $maxDays = collect(config('onboarding.stages', []))->max('after_days') ?: 7;
        $unreachable = (clone $users)
            ->whereDoesntHave('invoices')
            ->where('created_at', '<', now()->subDays($maxDays + 2))
            ->count();

        if ($unreachable > 0) {
            $this->newLine();
            $this->line('<options=bold>OUT OF REACH</>');
            $this->line(str_repeat('-', 62));
            $this->warn(sprintf('  %d account(s) never made an invoice AND are older than the', $unreachable));
            $this->warn('  nudge window, so no automated email can reach them any more.');
            $this->line('  Send them a one-off with:  php artisan activation:catch-up');
        }

        // ---- stalled list ----------------------------------------------
        if ($this->option('stalled')) {
            $this->newLine();
            $this->line('<options=bold>ACCOUNTS THAT NEVER ISSUED AN INVOICE</>');
            $this->line(str_repeat('-', 62));

            $rows = (clone $users)
                ->whereDoesntHave('invoices', fn ($q) => $q->whereNotNull('finalized_at'))
                ->with('companies')
                ->orderByDesc('created_at')
                ->get()
                ->map(function (User $u) {
                    $company = $u->companies->first();
                    $stopped = match (true) {
                        ! $company => 'no company row',
                        blank($company->name) || ! $company->state_id => 'abandoned business form',
                        ! $company->onboarded_at => 'abandoned setup wizard',
                        ! $u->customers()->exists() => 'no customer added',
                        ! $u->invoices()->exists() => 'never opened the invoice form',
                        default => 'draft only, never issued',
                    };

                    return [$u->id, $u->name, $u->phone, (string) $u->created_at?->format('Y-m-d'), $stopped];
                });

            $this->table(['ID', 'Name', 'Mobile', 'Signed up', 'Stopped at'], $rows);
            $this->line(sprintf('  %d account(s). The mobile numbers are there so someone can call them.', $rows->count()));
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
