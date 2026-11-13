<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Services\TeamManager;
use App\Support\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The owner's team in the mobile app: the same rules as the web team page
 * (TeamManager). Only the business's own owner manages its team - every
 * member role is refused team.* by CompanyMember::RULES, and these routes
 * carry the web route names, so ActAsTeamMember applies the same rule. A
 * member may always leave a business they joined (team.leave).
 */
class TeamController extends Controller
{
    public function __construct(private readonly TeamManager $team) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->ownedCompany($request);

        return response()->json([
            'data' => $this->team->members($company)->map(fn ($m) => $this->member($m))->values(),
            'meta' => ['company_id' => $company->id, 'roles' => self::roles()],
        ]);
    }

    /** Invite by email with a role. 201 with the invitation; email_sent says whether the mail went out. */
    public function invite(Request $request): JsonResponse
    {
        $company = $this->ownedCompany($request);
        [$member, $sent] = $this->team->invite($company, $request->user(), $request->only('email', 'role'));

        return response()->json([
            'data' => $this->member($member->setRelation('user', null)),
            'meta' => [
                'email_sent' => $sent,
                'message' => ($sent ? "Invitation emailed to {$member->email}." : "Invitation created for {$member->email}.")
                    .' The invite link can also be shared.',
            ],
        ], 201);
    }

    /** Send a pending invitation again (once every five minutes per invitation). */
    public function resend(Request $request, CompanyMember $member): JsonResponse
    {
        $company = $this->ownedCompany($request);
        abort_unless($member->company_id === $company->id, 404);

        $result = $this->team->resend($company, $member, $request->user());
        $message = TeamManager::resendMessage($member, $result);

        return response()->json([
            'status' => $result['status'],
            'message' => $message,
            'retry_after_minutes' => $result['minutes'] ?? null,
        ], match ($result['status']) {
            'throttled' => 429,
            'accepted' => 409,
            default => 200,
        });
    }

    public function update(Request $request, CompanyMember $member): JsonResponse
    {
        $member = $this->team->changeRole($this->ownedCompany($request), $member, $request->only('role'));

        return response()->json([
            'data' => $this->member($member->load('user')),
            'message' => "{$member->email} is now {$member->roleLabel()}.",
        ]);
    }

    public function destroy(Request $request, CompanyMember $member): JsonResponse
    {
        $this->team->remove($this->ownedCompany($request), $member);

        return response()->json(['message' => "{$member->email} no longer has access."]);
    }

    /** A member leaves a business they were invited into; their own business becomes active. */
    public function leave(Request $request, CompanyMember $member): JsonResponse
    {
        $user = Team::realUser();
        $name = $member->company?->name;
        $this->team->leave($user, $member);

        return response()->json([
            'message' => "You have left {$name}.",
            'active_company_id' => $user->fresh()->active_company_id,
        ]);
    }

    /** @return array<int, array{key: string, label: string, help: string}> */
    public static function roles(): array
    {
        return collect(CompanyMember::ROLES)
            ->map(fn ($r, $key) => ['key' => $key, 'label' => $r['label'], 'help' => $r['help']])
            ->values()->all();
    }

    private function member(CompanyMember $m): array
    {
        return [
            'id' => $m->id,
            'email' => $m->email,
            'role' => $m->role,
            'role_label' => $m->roleLabel(),
            'status' => $m->isAccepted() ? 'active' : 'pending',
            'accepted_at' => $m->accepted_at?->toIso8601String(),
            'invited_at' => $m->created_at?->toIso8601String(),
            'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name, 'email' => $m->user->email] : null,
            // The link the owner can share on WhatsApp until it is accepted.
            'invite_url' => $m->isAccepted() ? null : route('team.join', $m->token),
        ];
    }

    /** Only the business's own owner manages its team. */
    private function ownedCompany(Request $request): Company
    {
        abort_if(Team::isMember(), 403, 'Only the owner of this business can manage the team.');

        return $request->user()->ensureCompany();
    }
}
