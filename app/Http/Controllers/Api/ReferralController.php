<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API for the referral programme. Mirrors the web ReferralController:
 * returns the user's code, ready-to-share text/links and their referral stats.
 */
class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = $user->ensureReferralCode();

        $referrals = $user->referralsMade()
            ->with('referee:id,name,email,created_at')
            ->latest('signed_up_at')
            ->get();

        $stats = [
            'total' => $referrals->count(),
            'pending' => $referrals->where('reward_status', 'pending')->count(),
            'rewarded' => $referrals->where('reward_status', 'rewarded')->count(),
        ];

        $shareUrl = url('/register?ref=' . $code);
        $shareText = "I use Apna Invoice for GST billing. It is free to use. Sign up with my code: {$code}\n\n{$shareUrl}";

        return response()->json([
            'code' => $code,
            'share_url' => $shareUrl,
            'share_text' => $shareText,
            'wa_share' => 'https://wa.me/?text=' . rawurlencode($shareText),
            // The offer: a referred friend who issues their first invoice earns
            // the referrer lifetime free access.
            'offer' => 'Refer a friend and get lifetime free access. It counts when they issue their first invoice.',
            'lifetime_free' => $user->hasLifetimeFreeAccess(),
            'lifetime_free_since' => optional($user->lifetime_free_at)->toDateString(),
            'stats' => $stats,
            'referrals' => $referrals->map(fn ($r) => [
                'name' => $r->referee?->name,
                'email' => $r->referee?->email,
                'signed_up_at' => optional($r->signed_up_at)->toDateString(),
                'reward_status' => $r->reward_status,
            ])->values(),
        ]);
    }
}
