<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Sign up / sign in with Google (OAuth via Socialite).
 *
 * New Google users get their email verified by Google, then hand over a mobile
 * number (mandatory, so the team can reach every new user) — that step creates
 * the account immediately with no OTP, since the email is already proven.
 * Returning users (matched by google_id or email) log straight in. An email
 * account whose address was never proven is handed to the Google owner with
 * everyone else signed out of it (see signOutEveryoneElse).
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
                ->withErrors(['email' => 'Google sign-in could not be completed. Try again or sign up with email.']);
        }

        $email = strtolower((string) $googleUser->getEmail());
        if ($email === '') {
            return redirect()->route('register')
                ->withErrors(['email' => 'Google did not share an email address, so the account cannot be created. Sign up with email instead.']);
        }

        // Returning user — match on google_id first, then on a pre-existing
        // email account. Erased (depersonalised) accounts are never matched, so
        // a DPDP-erased account can't be re-opened via Google.
        $existing = User::where('google_id', $googleUser->getId())->whereNull('erased_at')->first()
            ?? User::where('email', $email)->whereNull('erased_at')->first();

        if ($existing) {
            // Matched on the email alone, and nobody ever proved that email:
            // the app sign-up asks for no code and a profile email change is
            // unconfirmed, so the account may have been opened by someone else
            // with this address. Google has now proven who owns it, so whoever
            // made the account loses every way back in (their own Google link
            // included) before the owner starts using it.
            $tookOver = (string) $existing->google_id !== (string) $googleUser->getId()
                && $existing->email_verified_at === null;
            if ($tookOver) {
                $this->signOutEveryoneElse($existing, $request);
                $existing->forceFill(['google_id' => null]);
            }

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

            $response = redirect()->intended(route('dashboard', absolute: false));

            return $tookOver
                ? $response->with('info', 'Signed in with Google. The old password for this account no longer works. To set a new one, use Forgot password on the sign-in page.')
                : $response;
        }

        // New user — stash the Google-verified profile and collect a mobile
        // number before creating the account, so every new user is reachable.
        // Google has already verified the email, so no OTP step is needed: the
        // mobile screen creates the account directly on submit (one field, no
        // "waiting for an SMS" gate).
        $request->session()->put(self::PENDING_KEY, [
            // Only this callback sets 'via' => 'google'. The mobile step
            // creates the account without a code, so it accepts nothing else:
            // the email sign-up form fills the same session key.
            'via' => 'google',
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

    /**
     * End every way into $user that does not come from this Google sign-in:
     * app tokens, the password, "remember me" cookies and other browser
     * sessions. Auth::logoutOtherDevices() needs the current password, which
     * the owner does not have here, so each one is closed directly.
     */
    private function signOutEveryoneElse(User $user, \Illuminate\Http\Request $request): void
    {
        $user->tokens()->delete();

        // A random password nobody knows; the owner can set one with
        // Forgot password, which mails the link to the proven address.
        $user->forceFill([
            'password' => Hash::make(Str::random(64)),
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }
}
