<?php

namespace App\Console\Commands;

use App\Mail\BackupMail;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWeeklyBackups extends Command
{
    protected $signature = 'backups:send-weekly
                            {--user=* : Only send to specific user IDs (optional)}
                            {--dry : List recipients without sending}';

    protected $description = 'Send weekly data backup ZIPs to opted-in users via email';

    public function handle(BackupService $service): int
    {
        $query = User::query()
            ->where('auto_backup_enabled', true)
            ->whereNotNull('email_verified_at'); // only verified emails

        if ($ids = $this->option('user')) {
            $query->whereIn('id', $ids);
        }

        $users = $query->get();
        $this->info("Building backups for {$users->count()} user(s)…");

        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            if ($this->option('dry')) {
                $this->line("  [dry] {$user->email} (#{$user->id})");
                continue;
            }

            try {
                $path = $service->buildZipForUser($user);
                Mail::to($user->email)->send(new BackupMail($user, $path));
                $user->forceFill(['last_backup_sent_at' => now()])->save();
                @unlink($path);
                $sent++;
                $this->line("  ✓ {$user->email}");
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Weekly backup failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ {$user->email} — {$e->getMessage()}");
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}");
        return self::SUCCESS;
    }
}
