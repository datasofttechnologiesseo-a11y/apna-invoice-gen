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
        $cutoff = self::retentionCutoff(now(), (int) config('legal.gst_retention_months', 72));

        // Depersonalised shells with no issued invoice still inside the retention window.
        $users = User::query()
            ->whereNotNull('erased_at')
            ->whereDoesntHave('invoices', fn ($q) => $q->whereNotNull('finalized_at')->whereDate('invoice_date', '>=', $cutoff))
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

    /**
     * The first invoice date whose records must still be kept on $now.
     * Section 36 CGST: 72 months from the due date of the annual return for
     * the year, i.e. from 31 December after the financial year ends. An
     * invoice of 12 Oct 2026 (FY 2026-27, annual return due 31 Dec 2027) is
     * kept until 31 Dec 2033. Counting from the issue date purged it in 2032.
     */
    public static function retentionCutoff(\Carbon\CarbonInterface $now, int $months): \Illuminate\Support\Carbon
    {
        $keptUntil = fn (int $fyStart) => \Illuminate\Support\Carbon::create($fyStart + 1, 12, 31)->endOfDay()->addMonthsNoOverflow($months);
        $fy = (int) $now->format('Y');
        while ($keptUntil($fy - 1)->greaterThanOrEqualTo($now)) {
            $fy--;
        }

        return \Illuminate\Support\Carbon::create($fy, 4, 1)->startOfDay();
    }
}
