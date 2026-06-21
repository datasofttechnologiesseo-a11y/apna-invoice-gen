<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountErasureService;
use Illuminate\Console\Command;

/**
 * Completes DPDP erasure for accounts that were depersonalised because GST
 * records had to be retained. Once every retained invoice has aged past the
 * statutory window, the de-identified shell (and its now-stale books) is
 * hard-deleted, so we keep personal data no longer than the law requires.
 */
class PurgeErasedAccounts extends Command
{
    protected $signature = 'accounts:purge-erased {--dry : List candidates without deleting}';

    protected $description = 'Hard-delete depersonalised accounts whose retained GST records have aged out';

    public function handle(AccountErasureService $eraser): int
    {
        $cutoff = now()->subMonths(config('legal.gst_retention_months', 72));

        // Depersonalised shells with no invoice still inside the retention window.
        $users = User::query()
            ->whereNotNull('erased_at')
            ->whereDoesntHave('invoices', fn ($q) => $q->where('finalized_at', '>=', $cutoff))
            ->get();

        $this->info("Found {$users->count()} erased account(s) past retention.");

        foreach ($users as $user) {
            if ($this->option('dry')) {
                $this->line("  [dry] user #{$user->id} (erased {$user->erased_at->toDateString()})");
                continue;
            }

            $eraser->purge($user);
            $this->line("  ✓ purged user #{$user->id}");
        }

        return self::SUCCESS;
    }
}
