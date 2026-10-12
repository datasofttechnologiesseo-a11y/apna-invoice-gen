<?php

namespace App\Http\Controllers;

use App\Mail\TeamInviteMail;
use App\Models\AuditLog;
use App\Models\CompanyMember;
use App\Support\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The owner's team: invite people by email (or a link to share on
 * WhatsApp), give each a role, change it, remove them. The invited person
 * accepts with their own Apna Invoice account.
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->ownedCompany($request);

        return view('team.index', [
            'company' => $company,
            'members' => $company->members()->with('user')->orderByRaw('accepted_at IS NULL')->orderBy('email')->get(),
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

        // Emails are stored lower case, so the duplicate check compares lower
        // case too ("CA@x.in" after "ca@x.in" is the same person).
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255',
                Rule::unique('company_members', 'email')->where(fn ($q) => $q->where('company_id', $company->id))],
            'role' => ['required', Rule::in(array_keys(CompanyMember::ROLES))],
        ], ['email.unique' => 'This person is already invited to this business.']);
        $email = mb_strtolower(trim($data['email']));
        if ($email === mb_strtolower($request->user()->email)) {
            return back()->withErrors(['email' => 'This is your own email. You already own this business.'])->withInput();
        }

        $member = $company->members()->create([
            'email' => $email, 'role' => $data['role'], 'token' => CompanyMember::newToken(), 'invited_by' => $request->user()->id,
        ]);
        try {
            Mail::to($email)->send(new TeamInviteMail($member->setRelation('company', $company), $request->user()->name));
            $sent = true;
        } catch (\Throwable) {
            $sent = false;
        }
        AuditLog::record('team.invited', "Invited {$email} as {$member->roleLabel()}", null);

        return back()->with('status', ($sent ? "Invitation emailed to {$email}." : "Invitation created for {$email}.")
            . ' The invite link below can also be shared.');
    }

    /**
     * Send the same invitation again to someone who has not joined yet. Once
     * every five minutes per invitation, so a double tap or an impatient
     * owner cannot flood the person's inbox (the route is throttled too).
     */
    private function resend(Request $request, $company): RedirectResponse
    {
        $member = $company->members()->whereKey((int) $request->input('resend'))->first();
        abort_unless($member, 404);
        if ($member->isAccepted()) {
            return back()->with('info', "{$member->email} has already joined.");
        }

        $key = 'team-invite-resend:' . $member->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            return back()->with('warning', "The invitation to {$member->email} was sent a moment ago. You can send it again in {$minutes} " . \Illuminate\Support\Str::plural('minute', $minutes) . '.');
        }
        RateLimiter::hit($key, 300);

        try {
            Mail::to($member->email)->send(new TeamInviteMail($member->setRelation('company', $company), $request->user()->name));
            $sent = true;
        } catch (\Throwable) {
            $sent = false;
        }
        AuditLog::record('team.invite_resent', "Invitation sent again to {$member->email}", null);

        return $sent
            ? back()->with('status', "Invitation emailed again to {$member->email}.")
            : back()->with('error', "The email to {$member->email} could not be sent. Share the invite link instead.");
    }

    public function update(Request $request, CompanyMember $member): RedirectResponse
    {
        $company = $this->ownedCompany($request);
        abort_unless($member->company_id === $company->id, 403);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(CompanyMember::ROLES))]]);
        $member->update(['role' => $data['role']]);
        AuditLog::record('team.role_changed', "{$member->email} is now {$member->roleLabel()}", null);

        return back()->with('status', "{$member->email} is now {$member->roleLabel()}.");
    }

    public function destroy(Request $request, CompanyMember $member): RedirectResponse
    {
        $company = $this->ownedCompany($request);
        abort_unless($member->company_id === $company->id, 403);
        $member->delete();
        AuditLog::record('team.removed', "Removed {$member->email} from the team", null);

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
        if ((int) $member->company->user_id === (int) $user->id) {
            return redirect()->route('dashboard');
        }
        $member->update(['user_id' => $user->id, 'accepted_at' => $member->accepted_at ?? now()]);
        $user->switchCompany($member->company);

        // The first page their role opens (billing staff have no dashboard).
        return redirect()->route(Team::homeRoute($member))->with('status', "Invitation accepted. You now have access to {$member->company->name} as {$member->roleLabel()}.");
    }

    /** A member leaves a business they were invited into. */
    public function leave(Request $request, CompanyMember $member): RedirectResponse
    {
        $user = Team::realUser();
        abort_unless((int) $member->user_id === (int) $user->id, 403);
        $member->delete();
        $user->forceFill(['active_company_id' => $user->companies()->orderBy('id')->value('id')])->save();

        return redirect()->route('companies.index')->with('status', "You have left {$member->company?->name}.");
    }

    /** Only the business's own owner manages its team. */
    private function ownedCompany(Request $request)
    {
        abort_if(Team::isMember(), 403, 'Only the owner of this business can manage the team.');

        return $request->user()->ensureCompany();
    }
}
