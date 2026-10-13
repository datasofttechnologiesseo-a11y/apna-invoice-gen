<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReviewPrompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReviewInviteController extends Controller
{
    /**
     * Called from the review pop-up when the user clicks "Write a review" or
     * "I've already reviewed". Either way the invite has done its job and is
     * never shown to this user again.
     */
    public function given(Request $request, ReviewPrompt $prompt): Response
    {
        $prompt->markGiven($request->user());

        return response()->noContent();
    }

    /**
     * The invite's QR code, scanned on a phone. The signed link names the
     * account, so scanning retires the invite for good (as clicking "Write
     * a review" does) and then opens the Google review page.
     */
    public function scanned(int $user, ReviewPrompt $prompt): RedirectResponse
    {
        $account = User::find($user);
        if ($account && $account->review_given_at === null) {
            $prompt->markGiven($account);
        }

        return redirect()->away((string) config('seo.contact.google_review_url'));
    }
}
