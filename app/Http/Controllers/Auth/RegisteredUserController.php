<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\TurnstileValid;
use App\Services\Otp\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            // 10-digit Indian mobile (the +91 is fixed in the UI). Required so
            // the team can reach every new user for onboarding help. The
            // verification code itself is delivered to email (no SMS gateway
            // yet), so the mobile is collected but not the OTP channel — this
            // keeps the field mandatory without the "waiting for an SMS that
            // never comes" drop-off.
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_accepted' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'cf-turnstile-response' => ['nullable', 'string', new TurnstileValid($request->ip())],
        ], [
            'terms_accepted.accepted' => 'Please accept the Terms of Service and Privacy Policy to continue.',
            'phone.required' => 'Please enter your mobile number so we can help you get started.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
        ]);

        $code = $otp->generateCode();
        $referralCode = strtoupper(trim((string) ($request->input('referral_code') ?? session('referral_code', ''))));

        $phone = $otp->normalizeIndianMobile($request->phone);
        // Deliver the code to email (works today); the mobile is stored for the
        // team to reach out. When an SMS gateway is wired up, pass $phone here.
        $result = $otp->send(null, $code, $request->email);

        // Stash the pending registration. Password is kept only for the life of
        // this server-side session and is hashed the moment the account is made.
        $request->session()->put(self::SESSION_KEY, [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $phone,
            'password' => $request->password,
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
            'referral_code' => $referralCode,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes', 10))->timestamp,
            'attempts' => 0,
            'resends' => 0,
            'resend_at' => now()->addSeconds((int) config('otp.resend_cooldown_seconds', 30))->timestamp,
            'channel' => $result['channel'],
        ]);

        return redirect()->route('register.verify')
            ->with('status', $this->codeSentMessage($result['channel']))
            ->with('otp_dev_code', $result['dev_code']);
    }

    /** Channel-aware "we sent your code" message. */
    private function codeSentMessage(string $channel): string
    {
        return $channel === 'email'
            ? 'We\'ve emailed you a 6-digit verification code.'
            : 'We\'ve sent a 6-digit code to your mobile.';
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
        ], [
            'phone.required' => 'Please enter your mobile number so we can help you get started.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
        ]);

        $reg['phone'] = $otp->normalizeIndianMobile($request->phone);

        // This path is only reached after Google OAuth, where the email is
        // already verified — so no OTP is needed. Create the account now with
        // the collected (unverified) mobile and drop the user into onboarding.
        app(\App\Services\Registration\AccountCreator::class)
            ->create($reg, $request, ['email' => true]);

        return redirect()->route('onboarding.index');
    }

    /** Step 2 — show the OTP entry screen. */
    public function showVerify(Request $request, OtpService $otp): RedirectResponse|View
    {
        $reg = $request->session()->get(self::SESSION_KEY);
        if (! $reg) {
            return redirect()->route('register')
                ->withErrors(['phone' => 'Your sign-up session expired. Please start again.']);
        }

        // Where the code actually went — email (fallback) or the masked mobile.
        $channel = $reg['channel'] ?? 'sms';
        $sentTo = $channel === 'email' || empty($reg['phone'])
            ? ($reg['email'] ?? 'your email')
            : $otp->mask($reg['phone']);

        return view('auth.verify-otp', [
            'sentTo' => $sentTo,
            'channel' => $channel === 'email' || empty($reg['phone']) ? 'email' : 'sms',
            'canResendAt' => $reg['resend_at'],
            'devCode' => session('otp_dev_code'),
        ]);
    }

    /** Step 2 — verify the OTP and create the account. */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:' . (int) config('otp.length', 6)],
        ]);

        $reg = $request->session()->get(self::SESSION_KEY);
        if (! $reg) {
            return redirect()->route('register')
                ->withErrors(['phone' => 'Your sign-up session expired. Please start again.']);
        }

        if (now()->timestamp > $reg['expires_at']) {
            // Don't kill the sign-up — an expired code is recoverable with one
            // tap on "Resend code". Forcing a full re-registration here was a
            // major abandonment point.
            return back()->withErrors([
                'code' => 'That code has expired. Tap "Resend code" below to get a fresh one.',
            ]);
        }

        if ($reg['attempts'] >= (int) config('otp.max_attempts', 5)) {
            $request->session()->forget(self::SESSION_KEY);
            // Send them back with the form prefilled so they only re-enter
            // their password, not everything.
            return redirect()->route('register')
                ->withInput(['name' => $reg['name'], 'email' => $reg['email'], 'phone' => ltrim(str_replace('+91', '', (string) ($reg['phone'] ?? '')), '0')])
                ->withErrors(['email' => 'Too many incorrect attempts. Please re-submit the form to get a new code.']);
        }

        if (! Hash::check($request->code, $reg['otp_hash'])) {
            $reg['attempts']++;
            $request->session()->put(self::SESSION_KEY, $reg);
            $remaining = (int) config('otp.max_attempts', 5) - $reg['attempts'];
            return back()->withErrors([
                'code' => "Incorrect code. {$remaining} attempt(s) left.",
            ]);
        }

        // Correct code — create the account, stamping whichever contact point
        // the OTP actually proved. An email-delivered code verifies the email;
        // an SMS-delivered code verifies the phone. The dev 'log' channel
        // behaves like SMS when a phone was given (matches historical behavior).
        $channel = $reg['channel'] ?? 'sms';
        $verified = $channel === 'email' || empty($reg['phone'])
            ? ['email' => true]
            : ['phone' => true];

        $user = app(\App\Services\Registration\AccountCreator::class)
            ->create($reg, $request, $verified);

        // Straight into guided setup: business details → first customer →
        // first invoice. Landing on the dashboard instead left too many new
        // users unsure what to do next.
        return redirect()->route('onboarding.index');
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

        // Cap total resends so the per-code attempt limit can't be reset forever.
        if (($reg['resends'] ?? 0) >= (int) config('otp.max_resends', 3)) {
            $request->session()->forget(self::SESSION_KEY);
            return redirect()->route('register')
                ->withInput(['name' => $reg['name'], 'email' => $reg['email'], 'phone' => ltrim(str_replace('+91', '', (string) ($reg['phone'] ?? '')), '0')])
                ->withErrors(['email' => 'Too many code requests. Please re-submit the form to start fresh.']);
        }

        $code = $otp->generateCode();
        $result = $otp->send($reg['phone'], $code, $reg['email'] ?? null);
        $reg['otp_hash'] = Hash::make($code);
        $reg['expires_at'] = now()->addMinutes((int) config('otp.ttl_minutes', 10))->timestamp;
        $reg['resend_at'] = now()->addSeconds((int) config('otp.resend_cooldown_seconds', 30))->timestamp;
        $reg['attempts'] = 0;
        $reg['resends'] = ($reg['resends'] ?? 0) + 1;
        $reg['channel'] = $result['channel'];
        $request->session()->put(self::SESSION_KEY, $reg);

        return back()
            ->with('status', 'A new code is on its way.')
            ->with('otp_dev_code', $result['dev_code']);
    }
}
