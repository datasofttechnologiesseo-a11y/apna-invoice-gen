<?php

namespace App\Services;

use App\Models\User;

/**
 * Decides whether to invite a user to leave a Google review.
 *
 * The invite first appears once the user has issued a bill - the moment the
 * product has just proved itself. If they put it off, it comes back after a
 * week, and keeps doing so until they say the review is done; from then on
 * it is never shown again. A week between asks is the line between a
 * reminder and a nag.
 */
class ReviewPrompt
{
    /** Days to wait after a dismissed invite before asking again. */
    public const SNOOZE_DAYS = 7;

    /**
     * Should this user be invited, having issued {@see $issuedCount}
     * invoices so far?
     */
    public function shouldPrompt(User $user, int $issuedCount): bool
    {
        if ($issuedCount < 1 || $user->review_given_at !== null) {
            return false;
        }

        $lastShown = $user->review_prompt_shown_at;

        return $lastShown === null || $lastShown->lte(now()->subDays(self::SNOOZE_DAYS));
    }

    /** Record that the invite was shown, which starts the snooze clock. */
    public function markShown(User $user): void
    {
        $user->forceFill(['review_prompt_shown_at' => now()])->save();
    }

    /** The user has left (or gone off to leave) the review. Retire the invite. */
    public function markGiven(User $user): void
    {
        $user->forceFill(['review_given_at' => now()])->save();
    }
}
