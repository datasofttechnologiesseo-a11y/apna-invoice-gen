<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\UserConsent;
use App\Services\AccountErasureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Audit-trail viewer for the current user's company. Shows who did what, when.
     */
    public function activity(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        $logs = \App\Models\AuditLog::where('company_id', $company->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('profile.activity', compact('logs', 'company'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Data & Privacy Center — the data principal's self-service hub for the
     * DPDP rights: view consent history, withdraw marketing consent, export
     * (right to access) and erase (right to erasure).
     */
    public function privacy(Request $request): View
    {
        $user = $request->user();

        return view('profile.privacy', [
            'user' => $user,
            'marketingConsent' => $user->marketingConsentGiven(),
            'consents' => $user->consents()->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    /**
     * Grant or withdraw marketing consent. Each change is appended to the
     * immutable consent log (never an in-place edit) so withdrawal history is
     * complete and provable under DPDP §6.
     */
    public function updateMarketingConsent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'marketing_opt_in' => ['required', 'boolean'],
        ]);

        UserConsent::record(
            $request->user()->id,
            'marketing',
            (bool) $data['marketing_opt_in'],
            'privacy_center',
            $request,
        );

        return back()->with('status', $data['marketing_opt_in']
            ? 'You\'ll now receive product and offer emails.'
            : 'You\'ve been opted out of marketing emails.');
    }

    /**
     * Delete (erase) the user's account.
     *
     * Erasure is delegated to AccountErasureService, which either fully deletes
     * the account or — when GST records must legally be retained — depersonalises
     * it. Either way the login is destroyed, so we log out and invalidate the
     * session here.
     */
    public function destroy(Request $request, AccountErasureService $eraser): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $result = $eraser->erase($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $result['mode'] === 'deleted'
            ? 'Your account and all personal data have been permanently deleted.'
            : 'Your account has been closed and your personal data erased. Your GST invoice records are retained only as long as Indian tax law requires, then automatically purged.';

        return Redirect::to('/')->with('status', $message);
    }
}
