<?php

namespace App\Http\Controllers;

use App\Services\ReviewPrompt;
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
}
