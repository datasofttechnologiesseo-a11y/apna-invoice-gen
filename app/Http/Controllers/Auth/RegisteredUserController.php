<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Rules\TurnstileValid;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'cf-turnstile-response' => ['nullable', 'string', new TurnstileValid($request->ip())],
        ]);

        // Look up the referrer before creating the user so the link is atomic.
        $referrer = null;
        $code = strtoupper(trim((string) ($request->input('referral_code') ?? session('referral_code', ''))));
        if ($code !== '') {
            $referrer = User::where('referral_code', $code)->first();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // referred_by_user_id is intentionally guarded — set server-side only.
        if ($referrer) {
            $user->forceFill(['referred_by_user_id' => $referrer->id])->save();
        }

        if ($referrer) {
            Referral::create([
                'referrer_user_id' => $referrer->id,
                'referee_user_id' => $user->id,
                'referral_code' => $code,
                'reward_status' => 'pending',
                'signed_up_at' => now(),
            ]);
            session()->forget('referral_code');
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('onboarding.index', absolute: false));
    }
}
