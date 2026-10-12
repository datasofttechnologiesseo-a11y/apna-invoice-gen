<?php

namespace App\Http\Controllers;

use App\Mail\TeamInviteMail;
use App\Models\AuditLog;
use App\Models\CompanyMember;
use App\Support\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

        return redirect()->route('dashboard')->with('status', "Invitation accepted. You now have access to {$member->company->name} as {$member->roleLabel()}.");
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
