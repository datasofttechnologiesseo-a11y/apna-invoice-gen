<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\TurnstileValid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'cf-turnstile-response' => ['nullable', 'string', new TurnstileValid($request->ip())],
        ]);

        // Google-only accounts (no password set) sign in with Google. Issuing a
        // password-reset would let whoever controls the inbox bypass Google as
        // the sole sign-in factor, so we steer them back to "Sign in with Google".
        $user = \App\Models\User::where('email', $request->email)->first();
        if ($user && $user->google_id && $user->password === null) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This account uses Google sign-in. Please use "Sign in with Google".']);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
