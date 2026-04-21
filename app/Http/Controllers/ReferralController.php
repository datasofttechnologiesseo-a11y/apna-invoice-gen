<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $code = $user->ensureReferralCode();

        $referrals = $user->referralsMade()
            ->with('referee:id,name,email,created_at')
            ->latest('signed_up_at')
            ->get();

        $stats = [
            'total'     => $referrals->count(),
            'pending'   => $referrals->where('reward_status', 'pending')->count(),
            'rewarded'  => $referrals->where('reward_status', 'rewarded')->count(),
        ];

        $shareUrl = url('/register?ref=' . $code);
        $shareText = "I've been using Apna Invoice for GST-compliant billing — it's free and actually works. Sign up with my code: {$code}\n\n{$shareUrl}";

        return view('referrals.index', [
            'code' => $code,
            'referrals' => $referrals,
            'stats' => $stats,
            'shareUrl' => $shareUrl,
            'shareText' => $shareText,
            'waShare' => 'https://wa.me/?text=' . rawurlencode($shareText),
        ]);
    }
}
