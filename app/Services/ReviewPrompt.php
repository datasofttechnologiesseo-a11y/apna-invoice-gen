<?php

namespace App\Services;

use App\Models\User;

/**
 * Decides whether to invite a user to leave a Google review.
 *
 * The invite is shown exactly once, immediately after the user issues their
 * first invoice. That is the moment the product has just proved itself, and
 * asking once means the request never becomes nagging.
 */
class ReviewPrompt
{
    /**
     * Should this user be invited, having just issued their
     * {@see $issuedCount}th invoice?
     */
    public function shouldPrompt(User $user, int $issuedCount): bool
    {
        return $issuedCount === 1
            && $user->review_prompt_shown_at === null;
    }

    /** Record that the invite was shown. It is never shown again. */
    public function markShown(User $user): void
    {
        $user->forceFill(['review_prompt_shown_at' => now()])->save();
    }
}
