<?php

namespace App\Services;

use App\Models\User;

/**
 * Decides when to invite a user to leave a Google review.
 *
 * Two moments, for new and existing users alike, until the review is given:
 *  - at registration: the first dashboard a user sees, and the first time an
 *    existing user who has never been asked opens the dashboard;
 *  - on every alternate invoice: right after issuing their 2nd, 4th, 6th…
 *    invoice.
 * Once they click "Write a review" or "I've already reviewed", it never
 * appears again.
 */
class ReviewPrompt
{
    /** First dashboard visit of anyone who has never been asked. */
    public function shouldPromptOnDashboard(User $user): bool
    {
        // A team member is not the account holder asked for the review.
        if (\App\Support\Team::isMember()) {
            return false;
        }

        return $user->review_given_at === null && $user->review_prompt_shown_at === null;
    }

    /** Right after issuing an invoice, when it is an even-numbered one. */
    public function shouldPromptAfterIssue(User $user, int $issuedCount): bool
    {
        if (\App\Support\Team::isMember()) {
            return false;
        }

        return $user->review_given_at === null && $issuedCount >= 2 && $issuedCount % 2 === 0;
    }

    /** Record that the invite was shown. */
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
