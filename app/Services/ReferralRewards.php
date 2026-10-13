<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The referral offer: refer a friend, and when they issue their first invoice
 * you get lifetime free access to Apna Invoice.
 *
 * A sign-up alone does not count (anyone can make an account); a friend who
 * issues a real invoice does. One such friend is enough, and the reward is
 * given once: later referrals are still marked rewarded but the date the
 * access was earned does not move.
 */
class ReferralRewards
{
    public function invoiceIssuedBy(int $refereeUserId): void
    {
        DB::transaction(function () use ($refereeUserId) {
            $referral = Referral::where('referee_user_id', $refereeUserId)
                ->where('reward_status', 'pending')
                ->lockForUpdate()
                ->first();
            if (! $referral || (int) $referral->referrer_user_id === $refereeUserId) {
                return;
            }

            $referral->update(['reward_status' => 'rewarded', 'rewarded_at' => now()]);
            $granted = User::whereKey($referral->referrer_user_id)
                ->whereNull('lifetime_free_at')
                ->update(['lifetime_free_at' => now()]);

            Log::info('Referral rewarded', [
                'referral_id' => $referral->id,
                'referrer_user_id' => $referral->referrer_user_id,
                'lifetime_free_granted' => (bool) $granted,
            ]);
        });
    }
}
