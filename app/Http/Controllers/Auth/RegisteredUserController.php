<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserConsent;
use App\Rules\TurnstileValid;
use App\Services\Otp\OtpService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registration is a two-step, mobile-verified flow (Zoho-style):
 *   1. store()  — collect details, send an OTP to the mobile, stash the
 *                 pending registration in the session. NO account yet.
 *   2. verify() — confirm the OTP, then actually create the account with
 *                 phone_verified_at set.
 *
 * The pending registration lives only in the server-side session and is
 * discarded once verified or when it expires, so an unverified number never
 * creates a user row.
 */
class RegisteredUserController extends Controller
{
    private const SESSION_KEY = 'pending_registration';

    public function create(Request $request): View
    {
        // Remember an inbound referral code so it survives the POST round-trip.
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
     * Step 1 — validate details and send a mobile OTP. Creates no account.
     *
     * @throws ValidationException
     */
    public function store(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // 10-digit Indian mobile (the +91 is fixed in the UI). Leading 6-9.
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_accepted' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'cf-turnstile-response' => ['nullable', 'string', new TurnstileValid($request->ip())],
        ], [
            'terms_accepted.accepted' => 'Please accept the Terms of Service and Privacy Policy to continue.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
        ]);

        $code = $otp->generateCode();
        $referralCode = strtoupper(trim((string) ($request->input('referral_code') ?? session('referral_code', ''))));

        // Stash the pending registration. Password is kept only for the life of
        // this server-side session and is hashed the moment the account is made.
        $request->session()->put(self::SESSION_KEY, [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $otp->normalizeIndianMobile($request->phone),
            'password' => $request->password,
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
            'referral_code' => $referralCode,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes', 10))->timestamp,
            'attempts' => 0,
            'resend_at' => now()->addSeconds((int) config('otp.resend_cooldown_seconds', 30))->timestamp,
        ]);

        $result = $otp->send($otp->normalizeIndianMobile($request->phone), $code);

        return redirect()->route('register.verify')
            ->with('status', 'We\'ve sent a 6-digit code to your mobile.')
            ->with('otp_dev_code', $result['dev_code']);
    }

    /**
     * Mobile-entry step for Google sign-ups. Google gives us name + email but
     * no phone, so we collect the mobile here before sending the OTP, keeping
     * the "every account is mobile-verified" guarantee.
     */
    public function showMobile(Request $request): RedirectResponse|View
    {
        $reg = $request->session()->get(self::SESSION_KEY);
        if (! $reg) {
            return redirect()->route('register')
                ->withErrors(['phone' => 'Your sign-up session expired. Please start again.']);
        }

        return view('auth.register-mobile', ['name' => $reg['name'] ?? null]);
    }

    public function storeMobile(Request $request, OtpService $otp): RedirectResponse
    {
        $reg = $request->session()->get(self::SESSION_KEY);
        if (! $reg) {
            return redirect()->route('register')
                ->withErrors(['phone' => 'Your sign-up session expired. Please start again.']);
        }

        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
        ], ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.']);

        $code = $otp->generateCode();
        $reg['phone'] = $otp->normalizeIndianMobile($request->phone);
        $reg['otp_hash'] = Hash::make($code);
        $reg['expires_at'] = now()->addMinutes((int) config('otp.ttl_minutes', 10))->timestamp;
        $reg['attempts'] = 0;
        $reg['resend_at'] = now()->addSeconds((int) config('otp.resend_cooldown_seconds', 30))->timestamp;
        $request->session()->put(self::SESSION_KEY, $reg);

        $result = $otp->send($reg['phone'], $code);

        return redirect()->route('register.verify')
            ->with('status', 'We\'ve sent a 6-digit code to your mobile.')
            ->with('otp_dev_code', $result['dev_code']);
    }

    /** Step 2 — show the OTP entry screen. */
    public function showVerify(Request $request, OtpService $otp): RedirectResponse|View
    {
        $reg = $request->session()->get(self::SESSION_KEY);
        if (! $reg) {
            return redirect()->route('register')
                ->withErrors(['phone' => 'Your sign-up session expired. Please start again.']);
        }

        return view('auth.verify-otp', [
            'maskedPhone' => $otp->mask($reg['phone']),
            'canResendAt' => $reg['resend_at'],
            'devCode' => session('otp_dev_code'),
        ]);
    }

    /** Step 2 — verify the OTP and create the account. */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{4,8}$/'],
        ]);

        $reg = $request->session()->get(self::SESSION_KEY);
        if (! $reg) {
            return redirect()->route('register')
                ->withErrors(['phone' => 'Your sign-up session expired. Please start again.']);
        }

        if (now()->timestamp > $reg['expires_at']) {
            $request->session()->forget(self::SESSION_KEY);
            return redirect()->route('register')
                ->withErrors(['phone' => 'The code expired. Please sign up again.']);
        }

        if ($reg['attempts'] >= (int) config('otp.max_attempts', 5)) {
            $request->session()->forget(self::SESSION_KEY);
            return redirect()->route('register')
                ->withErrors(['phone' => 'Too many incorrect attempts. Please sign up again.']);
        }

        if (! Hash::check($request->code, $reg['otp_hash'])) {
            $reg['attempts']++;
            $request->session()->put(self::SESSION_KEY, $reg);
            $remaining = (int) config('otp.max_attempts', 5) - $reg['attempts'];
            return back()->withErrors([
                'code' => "Incorrect code. {$remaining} attempt(s) left.",
            ]);
        }

        // Correct code — create the verified account.
        $referrer = $reg['referral_code'] !== ''
            ? User::where('referral_code', $reg['referral_code'])->first()
            : null;

        $user = DB::transaction(function () use ($reg, $referrer, $request) {
            $user = User::create([
                'name' => $reg['name'],
                'email' => $reg['email'],
                'phone' => $reg['phone'],
                // OAuth (Google) accounts have no password.
                'password' => isset($reg['password']) && $reg['password'] !== null
                    ? Hash::make($reg['password'])
                    : null,
            ]);

            $forced = ['phone_verified_at' => now()];
            if (! empty($reg['google_id'])) {
                $forced['google_id'] = $reg['google_id'];
                $forced['avatar'] = $reg['avatar'] ?? null;
            }
            // Google has already verified the email address.
            if (! empty($reg['email_verified'])) {
                $forced['email_verified_at'] = now();
            }
            $user->forceFill($forced)->save();

            UserConsent::record($user->id, 'terms', true, 'signup', $request);
            UserConsent::record($user->id, 'privacy', true, 'signup', $request);
            UserConsent::record($user->id, 'marketing', $reg['marketing_opt_in'], 'signup', $request);

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

        $request->session()->forget(self::SESSION_KEY);
        session()->forget('referral_code');

        event(new Registered($user));
        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /** Re-send the OTP, respecting a cooldown. */
    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $reg = $request->session()->get(self::SESSION_KEY);
        if (! $reg) {
            return redirect()->route('register')
                ->withErrors(['phone' => 'Your sign-up session expired. Please start again.']);
        }

        if (now()->timestamp < $reg['resend_at']) {
            $wait = $reg['resend_at'] - now()->timestamp;
            return back()->withErrors(['code' => "Please wait {$wait}s before requesting a new code."]);
        }

        $code = $otp->generateCode();
        $reg['otp_hash'] = Hash::make($code);
        $reg['expires_at'] = now()->addMinutes((int) config('otp.ttl_minutes', 10))->timestamp;
        $reg['resend_at'] = now()->addSeconds((int) config('otp.resend_cooldown_seconds', 30))->timestamp;
        $reg['attempts'] = 0;
        $request->session()->put(self::SESSION_KEY, $reg);

        $result = $otp->send($reg['phone'], $code);

        return back()
            ->with('status', 'A new code is on its way.')
            ->with('otp_dev_code', $result['dev_code']);
    }
}
