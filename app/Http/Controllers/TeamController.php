<?php

namespace App\Http\Controllers;

use App\Models\CompanyMember;
use App\Services\TeamManager;
use App\Support\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * The owner's team: invite people by email (or a link to share on
 * WhatsApp), give each a role, change it, remove them. The invited person
 * accepts with their own Apna Invoice account.
 */
class TeamController extends Controller
{
    public function __construct(private readonly TeamManager $team) {}

    public function index(Request $request): View
    {
        $company = $this->ownedCompany($request);

        return view('team.index', [
            'company' => $company,
            'members' => $this->team->members($company),
            'roles' => CompanyMember::ROLES,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $company = $this->ownedCompany($request);

        // "Resend invite" on a pending member posts here with its id.
        if ($request->filled('resend')) {
            return $this->resend($request, $company);
        }

        [$member, $sent] = $this->team->invite($company, $request->user(), $request->only('email', 'role'));

        return back()->with('status', ($sent ? "Invitation emailed to {$member->email}." : "Invitation created for {$member->email}.")
            . ' The invite link below can also be shared.');
    }

    /**
     * Send the same invitation again to someone who has not joined yet (at
     * most once every five minutes per invitation; the route is throttled too).
     */
    private function resend(Request $request, $company): RedirectResponse
    {
        $member = $company->members()->whereKey((int) $request->input('resend'))->first();
        abort_unless($member, 404);

        $result = $this->team->resend($company, $member, $request->user());
        $flash = ['accepted' => 'info', 'throttled' => 'warning', 'sent' => 'status', 'failed' => 'error'][$result['status']];

        return back()->with($flash, TeamManager::resendMessage($member, $result));
    }

    public function update(Request $request, CompanyMember $member): RedirectResponse
    {
        $this->team->changeRole($this->ownedCompany($request), $member, $request->only('role'));

        return back()->with('status', "{$member->email} is now {$member->roleLabel()}.");
    }

    public function destroy(Request $request, CompanyMember $member): RedirectResponse
    {
        $this->team->remove($this->ownedCompany($request), $member);

        return back()->with('status', "{$member->email} no longer has access.");
    }

    /** The invite link: who invited, which business, which role. */
    public function join(Request $request, string $token): View|RedirectResponse
    {
        $member = CompanyMember::where('token', $token)->with('company')->first();
        if (! $member || ! $member->company) {
            return redirect()->route('dashboard')->with('error', 'This invitation is no longer valid. Ask the owner to invite you again.');
        }
        $user = Team::realUser();

        return view('team.join', [
            'member' => $member,
            'user' => $user,
            'emailMatches' => $user && mb_strtolower($user->email) === mb_strtolower($member->email),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $member = CompanyMember::where('token', $token)->with('company')->firstOrFail();
        $user = Team::realUser();
        if (mb_strtolower($user->email) !== mb_strtolower($member->email)) {
            return back()->with('error', "This invitation is for {$member->email}. Sign in with that account to accept it.");
        }
        // The email on an account can be changed on the profile page without
        // proof, so a matching address alone does not show the invitation
        // reached its owner. Only a confirmed address accepts it.
        if (! $user->hasVerifiedEmail()) {
            return $this->confirmEmailFirst($request, $user, $member);
        }
        if ((int) $member->company->user_id === (int) $user->id) {
            return redirect()->route('dashboard');
        }
        $member->update(['user_id' => $user->id, 'accepted_at' => $member->accepted_at ?? now()]);
        $user->switchCompany($member->company);

        // The first page their role opens (billing staff have no dashboard).
        return redirect()->route(Team::homeRoute($member))->with('status', "Invitation accepted. You now have access to {$member->company->name} as {$member->roleLabel()}.");
    }

    /**
     * Mail a confirmation link (at most once every five minutes) and bring the
     * person back to this invitation once they have opened it.
     */
    private function confirmEmailFirst(Request $request, \App\Models\User $user, CompanyMember $member): RedirectResponse
    {
        $key = 'team-accept-verify:' . $user->id;
        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 300);
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $request->session()->put('url.intended', route('team.join', $member->token));

        return back()->with('error', "Confirm your email address first. Open the link we sent to {$member->email}, then accept the invitation again.");
    }

    /** A member leaves a business they were invited into. */
    public function leave(Request $request, CompanyMember $member): RedirectResponse
    {
        $this->team->leave(Team::realUser(), $member);

        return redirect()->route('companies.index')->with('status', "You have left {$member->company?->name}.");
    }

    /** Only the business's own owner manages its team. */
    private function ownedCompany(Request $request)
    {
        abort_if(Team::isMember(), 403, 'Only the owner of this business can manage the team.');

        return $request->user()->ensureCompany();
    }
}
