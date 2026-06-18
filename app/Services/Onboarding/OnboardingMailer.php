<?php

namespace App\Services\Onboarding;

use App\Mail\OnboardingEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends a single activation/onboarding email and records it, so each stage
 * fires at most once per user. Safe to call repeatedly.
 */
class OnboardingMailer
{
    /**
     * Send the given stage to the user if eligible and not already sent.
     * Returns true only if an email actually went out.
     */
    public function send(User $user, string $stage): bool
    {
        if (! config('onboarding.enabled', true)) {
            return false;
        }

        if (! array_key_exists($stage, (array) config('onboarding.stages'))) {
            return false;
        }

        if (! $this->isMailable($user)) {
            return false;
        }

        // Already sent this stage? (unique index is the real guard; this avoids
        // the exception in the common case.)
        $already = DB::table('onboarding_emails')
            ->where('user_id', $user->id)
            ->where('stage', $stage)
            ->exists();
        if ($already) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new OnboardingEmail($user, $stage));

            DB::table('onboarding_emails')->insert([
                'user_id' => $user->id,
                'stage' => $stage,
                'sent_at' => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Onboarding email failed', [
                'user_id' => $user->id,
                'stage' => $stage,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Skip erased accounts and the non-routable placeholder addresses used by
     * the DPDP erasure flow.
     */
    private function isMailable(User $user): bool
    {
        if ($user->erased_at !== null) {
            return false;
        }

        return filled($user->email) && ! Str::endsWith($user->email, '@erased.invalid');
    }
}
