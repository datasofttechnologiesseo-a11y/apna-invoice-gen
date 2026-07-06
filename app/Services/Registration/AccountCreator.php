<?php

namespace App\Services\Registration;

use App\Models\Referral;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a user account from a pending-registration payload (the session
 * blob built during sign-up). Shared by the OTP verification step and the
 * instant Google sign-up path so both produce identical accounts: consents
 * recorded, referral linked, welcome email queued, user logged in.
 */
class AccountCreator
{
    /**
     * @param array $reg  The pending_registration session payload.
     * @param array $verified  Which contact points were verified at sign-up,
     *                         e.g. ['email' => true, 'phone' => false].
     */
    public function create(array $reg, Request $request, array $verified = []): User
    {
        $referrer = ($reg['referral_code'] ?? '') !== ''
            ? User::where('referral_code', $reg['referral_code'])->first()
            : null;

        $user = DB::transaction(function () use ($reg, $referrer, $request, $verified) {
            $user = User::create([
                'name' => $reg['name'],
                'email' => $reg['email'],
                'phone' => $reg['phone'] ?? null,
                // OAuth (Google) accounts have no password.
                'password' => isset($reg['password']) && $reg['password'] !== null
                    ? Hash::make($reg['password'])
                    : null,
            ]);

            $forced = [];
            if (! empty($verified['phone']) && ! empty($reg['phone'])) {
                $forced['phone_verified_at'] = now();
            }
            // Google-verified email, or the OTP was delivered to the email.
            if (! empty($reg['email_verified']) || ! empty($verified['email'])) {
                $forced['email_verified_at'] = now();
            }
            if (! empty($reg['google_id'])) {
                $forced['google_id'] = $reg['google_id'];
                $forced['avatar'] = $reg['avatar'] ?? null;
            }
            if ($forced !== []) {
                $user->forceFill($forced)->save();
            }

            UserConsent::record($user->id, 'terms', true, 'signup', $request);
            UserConsent::record($user->id, 'privacy', true, 'signup', $request);
            UserConsent::record($user->id, 'marketing', (bool) ($reg['marketing_opt_in'] ?? false), 'signup', $request);

            if ($referrer) {
                $user->forceFill(['referred_by_user_id' => $referrer->id])->save();

                Referral::create([
                    'referrer_user_id' => $referrer->id,
                    'referee_user_id' => $user->id,
                    'referral_code' => $reg['referral_code'],
                    'reward_status' => 'pending',
                    'signed_up_at' => now(),
                ]);
            }

            return $user;
        });

        $request->session()->forget('pending_registration');
        session()->forget('referral_code');

        event(new Registered($user));
        Auth::login($user);

        // Kick off the activation sequence with a welcome email. Failures here
        // must never block sign-up, so OnboardingMailer swallows its own errors.
        app(\App\Services\Onboarding\OnboardingMailer::class)->send($user, 'welcome');

        return $user;
    }
}
