<?php

namespace App\Services;

use App\Mail\TeamInviteMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The owner's team, for the web page (TeamController) and the mobile app
 * (Api\TeamController): who is in it, inviting by email with a role,
 * re-sending an invitation, changing a role, removing someone and a member
 * leaving. The controllers only decide how the result is shown.
 */
class TeamManager
{
    /** Joined people first, then pending invitations, each by email. */
    public function members(Company $company): Collection
    {
        return $company->members()->with('user')->orderByRaw('accepted_at IS NULL')->orderBy('email')->get();
    }

    /**
     * Invite someone by email. Emails are stored lower case, so the duplicate
     * check compares lower case too ("CA@x.in" after "ca@x.in" is the same
     * person). Returns the invitation and whether the email went out.
     *
     * @return array{0: CompanyMember, 1: bool}
     *
     * @throws ValidationException
     */
    public function invite(Company $company, User $inviter, array $input): array
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }
        $data = Validator::make($input, [
            'email' => ['required', 'email', 'max:255',
                Rule::unique('company_members', 'email')->where(fn ($q) => $q->where('company_id', $company->id))],
            'role' => ['required', Rule::in(array_keys(CompanyMember::ROLES))],
        ], ['email.unique' => 'This person is already invited to this business.'])->validate();

        $email = mb_strtolower(trim($data['email']));
        if ($email === mb_strtolower($inviter->email)) {
            throw ValidationException::withMessages(['email' => 'This is your own email. You already own this business.']);
        }

        $member = $company->members()->create([
            'email' => $email, 'role' => $data['role'], 'token' => CompanyMember::newToken(), 'invited_by' => $inviter->id,
        ]);
        $sent = $this->mail($member, $company, $inviter);
        AuditLog::record('team.invited', "Invited {$email} as {$member->roleLabel()}", null);

        return [$member, $sent];
    }

    /**
     * Send the same invitation again to someone who has not joined yet. Once
     * every five minutes per invitation, so a double tap or an impatient
     * owner cannot flood the person's inbox.
     *
     * @return array{status: 'accepted'|'throttled'|'sent'|'failed', minutes?: int}
     */
    public function resend(Company $company, CompanyMember $member, User $inviter): array
    {
        if ($member->isAccepted()) {
            return ['status' => 'accepted'];
        }

        $key = 'team-invite-resend:'.$member->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return ['status' => 'throttled', 'minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60))];
        }
        RateLimiter::hit($key, 300);

        $sent = $this->mail($member, $company, $inviter);
        AuditLog::record('team.invite_resent', "Invitation sent again to {$member->email}", null);

        return ['status' => $sent ? 'sent' : 'failed'];
    }

    /** The human sentence for a resend() result. */
    public static function resendMessage(CompanyMember $member, array $result): string
    {
        return match ($result['status']) {
            'accepted' => "{$member->email} has already joined.",
            'throttled' => "The invitation to {$member->email} was sent a moment ago. You can send it again in {$result['minutes']} ".Str::plural('minute', $result['minutes']).'.',
            'sent' => "Invitation emailed again to {$member->email}.",
            default => "The email to {$member->email} could not be sent. Share the invite link instead.",
        };
    }

    /** @throws ValidationException */
    public function changeRole(Company $company, CompanyMember $member, array $input): CompanyMember
    {
        abort_unless($member->company_id === $company->id, 403);
        $data = Validator::make($input, ['role' => ['required', Rule::in(array_keys(CompanyMember::ROLES))]])->validate();
        $member->update(['role' => $data['role']]);
        AuditLog::record('team.role_changed', "{$member->email} is now {$member->roleLabel()}", null);

        return $member;
    }

    public function remove(Company $company, CompanyMember $member): void
    {
        abort_unless($member->company_id === $company->id, 403);
        $member->delete();
        AuditLog::record('team.removed', "Removed {$member->email} from the team", null);
    }

    /** A member leaves a business they were invited into, and lands back in their own. */
    public function leave(User $user, CompanyMember $member): void
    {
        abort_unless((int) $member->user_id === (int) $user->id, 403);
        $member->delete();
        $user->forceFill(['active_company_id' => $user->companies()->orderBy('id')->value('id')])->save();
    }

    private function mail(CompanyMember $member, Company $company, User $inviter): bool
    {
        try {
            Mail::to($member->email)->send(new TeamInviteMail($member->setRelation('company', $company), $inviter->name));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
