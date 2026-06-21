<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // A DPDP-erased account must never be usable again, even if someone
        // somehow knows the scrubbed credentials.
        if ($request->user()?->erased_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->withErrors(['email' => 'This account has been closed.']);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $company = $user->ensureCompany();
        $intended = $request->session()->pull('url.intended');

        if ($intended) {
            return redirect($intended);
        }

        // Everyone lands on the dashboard. The dashboard shows a setup checklist
        // for incomplete profiles, but never forces the wizard — users can dive
        // straight into creating an invoice and fill business details later.
        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
