<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'app:make-super-admin
                            {email : The email of the user to promote}
                            {--revoke : Revoke super-admin access instead of granting it}';

    protected $description = 'Promote (or demote with --revoke) a user to super admin';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_super_admin' => $grant])->save();

        $this->info($grant
            ? "✓ {$user->name} ({$email}) is now a super admin."
            : "✓ {$user->name} ({$email}) is no longer a super admin.");

        // The flag alone isn't enough — access also needs the email allowlist.
        // Warn loudly if we just granted a flag that the allowlist will ignore.
        if ($grant) {
            $allow = config('admin.super_admin_emails', []);
            if ($allow !== [] && ! in_array(strtolower($email), $allow, true)) {
                $this->warn("Note: {$email} is NOT in SUPER_ADMIN_EMAILS, so admin access stays BLOCKED.");
                $this->line('Add it to the SUPER_ADMIN_EMAILS env var to actually grant access.');
            }
        }

        return self::SUCCESS;
    }
}
