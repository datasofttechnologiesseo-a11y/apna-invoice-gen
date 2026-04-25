<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserConsent;
use App\Rules\TurnstileValid;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        // Remember an inbound referral code so it survives the POST round-trip.
        // Normalised to uppercase to match the stored format.
        if ($code = $request->query('ref')) {
            $code = strtoupper(trim((string) $code));
            if (User::where('referral_code', $code)->exists()) {
                session(['referral_code' => $code]);
            }
        }

        return view('auth.register', [
            'referralCode' => session('referral_code'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_accepted' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'cf-turnstile-response' => ['nullable', 'string', new TurnstileValid($request->ip())],
        ], [
            'terms_accepted.accepted' => 'Please accept the Terms of Service and Privacy Policy to continue.',
        ]);

        // Look up the referrer before creating the user so the link is atomic.
        $referrer = null;
        $code = strtoupper(trim((string) ($request->input('referral_code') ?? session('referral_code', ''))));
        if ($code !== '') {
            $referrer = User::where('referral_code', $code)->first();
        }

        // Wrap user + consent + referral in a single transaction so we never
        // end up with an orphaned user if one of the follow-up inserts fails.
        $user = DB::transaction(function () use ($request, $referrer, $code) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // DPDP §6 audit trail — timestamp, version, IP, UA for each consent.
            // `marketing_opt_in` is recorded even when absent (as refused) so
            // withdrawal history is complete.
            UserConsent::record($user->id, 'terms', true, 'signup', $request);
            UserConsent::record($user->id, 'privacy', true, 'signup', $request);
            UserConsent::record(
                $user->id, 'marketing', $request->boolean('marketing_opt_in'), 'signup', $request,
            );

            if ($referrer) {
                // referred_by_user_id is guarded; set server-side only.
                $user->forceFill(['referred_by_user_id' => $referrer->id])->save();

                Referral::create([
                    'referrer_user_id' => $referrer->id,
                    'referee_user_id' => $user->id,
                    'referral_code' => $code,
                    'reward_status' => 'pending',
                    'signed_up_at' => now(),
                ]);
            }

            return $user;
        });

        if ($referrer) {
            session()->forget('referral_code');
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('onboarding.index', absolute: false));
    }
}
