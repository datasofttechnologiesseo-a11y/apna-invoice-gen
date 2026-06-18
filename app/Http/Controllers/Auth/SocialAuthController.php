<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Sign up / sign in with Google (OAuth via Socialite).
 *
 * New Google users are still routed through mobile-OTP verification before the
 * account is created, so the "mobile verified at sign-up" guarantee holds for
 * every account regardless of how they registered. Returning users (matched by
 * google_id or email) log straight in.
 */
class SocialAuthController extends Controller
{
    private const PENDING_KEY = 'pending_registration';

    /** Begin the OAuth handshake. */
    public function redirect(string $provider): SymfonyRedirect|RedirectResponse
    {
        abort_unless($provider === 'google' && $this->configured(), 404);

        return Socialite::driver('google')->redirect();
    }

    /** Handle the provider callback. */
    public function callback(string $provider, \Illuminate\Http\Request $request): RedirectResponse
    {
        abort_unless($provider === 'google' && $this->configured(), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Google sign-in could not be completed. Please try again or use email.']);
        }

        $email = strtolower((string) $googleUser->getEmail());
        if ($email === '') {
            return redirect()->route('register')
                ->withErrors(['email' => 'Google did not share an email address, so we can\'t create your account.']);
        }

        // Returning user — match on google_id first, then on a pre-existing
        // email account. Erased (depersonalised) accounts are never matched, so
        // a DPDP-erased account can't be re-opened via Google.
        $existing = User::where('google_id', $googleUser->getId())->whereNull('erased_at')->first()
            ?? User::where('email', $email)->whereNull('erased_at')->first();

        if ($existing) {
            if (! $existing->google_id) {
                // Google has verified ownership of this email address, so it's
                // safe to link it to the existing account and mark it verified.
                $existing->forceFill([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                ])->save();
            }

            Auth::login($existing, remember: true);

            return redirect()->intended(route('dashboard', absolute: false));
        }

        // New user — stash the verified Google profile and collect a mobile
        // number (with OTP) before the account is actually created.
        $request->session()->put(self::PENDING_KEY, [
            'name' => $googleUser->getName() ?: 'New user',
            'email' => $email,
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'password' => null,            // OAuth account, no password
            'email_verified' => true,      // Google has verified the email
            'marketing_opt_in' => false,
            'referral_code' => strtoupper(trim((string) session('referral_code', ''))),
        ]);

        return redirect()->route('register.mobile');
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }
}
