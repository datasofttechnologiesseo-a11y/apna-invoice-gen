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

        return self::SUCCESS;
    }
}
