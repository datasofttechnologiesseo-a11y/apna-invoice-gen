<?php

namespace Tests\Feature\Api;

use App\Mail\TeamInviteMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The team screens in the app follow the web team page: only the owner
 * manages the team, every member role is refused, and a member can leave.
 */
class ApiTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $state = State::factory()->create(['gst_code' => '27']);
        $this->owner = User::factory()->create(['email' => 'owner@shop.in']);
        $this->company = Company::factory()->create(['user_id' => $this->owner->id, 'state_id' => $state->id, 'name' => 'Arbaz Traders', 'onboarded_at' => now()]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
    }

    /** A member who has accepted, working in the owner's business. */
    private function member(string $role, string $email = 'member@firm.in'): User
    {
        $user = User::factory()->create(['email' => $email]);
        Company::factory()->create(['user_id' => $user->id, 'name' => 'Member Own Firm']);
        $this->company->members()->create(['email' => $email, 'role' => $role, 'token' => CompanyMember::newToken(),
            'invited_by' => $this->owner->id, 'user_id' => $user->id, 'accepted_at' => now()]);
        $user->forceFill(['active_company_id' => $this->company->id])->save();

        return $user->fresh();
    }

    public function test_the_owner_invites_lists_changes_and_removes(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/team', ['email' => ' CA@Firm.in ', 'role' => 'accountant'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'ca@firm.in')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('meta.email_sent', true);
        Mail::assertSent(TeamInviteMail::class, fn ($m) => $m->hasTo('ca@firm.in'));
        $member = CompanyMember::where('email', 'ca@firm.in')->firstOrFail();
        $this->assertTrue(AuditLog::where('action', 'team.invited')->exists());

        $this->getJson('/api/team')->assertOk()
            ->assertJsonPath('data.0.email', 'ca@firm.in')
            ->assertJsonPath('data.0.invite_url', route('team.join', $member->token))
            ->assertJsonCount(3, 'meta.roles');

        // The same rules as the web form.
        $this->postJson('/api/team', ['email' => 'ca@firm.in', 'role' => 'admin'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/team', ['email' => 'owner@shop.in', 'role' => 'admin'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/team', ['email' => 'x@y.in', 'role' => 'owner'])->assertStatus(422)->assertJsonValidationErrors('role');

        $this->patchJson("/api/team/{$member->id}", ['role' => 'staff'])->assertOk()->assertJsonPath('data.role', 'staff');
        $this->assertSame('staff', $member->fresh()->role);

        $this->deleteJson("/api/team/{$member->id}")->assertOk();
        $this->assertNull(CompanyMember::find($member->id));
    }

    public function test_a_pending_invitation_can_be_resent_but_not_in_a_burst(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/team', ['email' => 'ca@firm.in', 'role' => 'accountant'])->assertCreated();
        $member = CompanyMember::firstOrFail();
        RateLimiter::clear('team-invite-resend:'.$member->id);

        $this->postJson("/api/team/{$member->id}/resend")->assertOk()->assertJsonPath('status', 'sent');
        Mail::assertSent(TeamInviteMail::class, 2);
        $this->postJson("/api/team/{$member->id}/resend")->assertStatus(429)->assertJsonPath('status', 'throttled');
        Mail::assertSent(TeamInviteMail::class, 2);
    }

    public function test_another_owners_member_cannot_be_touched(): void
    {
        $other = User::factory()->create();
        $otherCo = Company::factory()->create(['user_id' => $other->id]);
        $foreign = $otherCo->members()->create(['email' => 'x@y.in', 'role' => 'admin', 'token' => CompanyMember::newToken(), 'invited_by' => $other->id]);

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/team/{$foreign->id}", ['role' => 'staff'])->assertForbidden();
        $this->deleteJson("/api/team/{$foreign->id}")->assertForbidden();
        $this->postJson("/api/team/{$foreign->id}/resend")->assertNotFound();
        $this->assertSame('admin', $foreign->fresh()->role);
    }

    public function test_no_member_role_manages_the_team(): void
    {
        foreach (['admin', 'accountant', 'staff'] as $i => $role) {
            $member = $this->member($role, "m{$i}@firm.in");
            Sanctum::actingAs($member);
            $this->getJson('/api/team')->assertForbidden();
            Sanctum::actingAs($member->fresh());
            $this->postJson('/api/team', ['email' => "new{$i}@firm.in", 'role' => 'admin'])->assertForbidden();
        }
        $this->assertSame(3, CompanyMember::count());
    }

    public function test_a_member_can_leave_and_lands_in_their_own_business(): void
    {
        $member = $this->member('staff');
        $membership = CompanyMember::where('user_id', $member->id)->firstOrFail();

        Sanctum::actingAs($member);
        $this->getJson('/api/companies')->assertOk()->assertJsonPath('meta.memberships.0.member_id', $membership->id);

        Sanctum::actingAs($member->fresh());
        $own = Company::where('user_id', $member->id)->firstOrFail();
        $this->postJson("/api/team/leave/{$membership->id}")->assertOk()->assertJsonPath('active_company_id', $own->id);
        $this->assertNull(CompanyMember::find($membership->id));

        // Nobody else can make someone leave.
        $other = $this->member('admin', 'admin@firm.in');
        $otherMembership = CompanyMember::where('user_id', $other->id)->firstOrFail();
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/team/leave/{$otherMembership->id}")->assertForbidden();
        $this->assertNotNull($otherMembership->fresh());
    }

    public function test_team_routes_refuse_an_anonymous_caller(): void
    {
        $this->getJson('/api/team')->assertUnauthorized();
        $this->postJson('/api/team', ['email' => 'a@b.in', 'role' => 'admin'])->assertUnauthorized();
    }
}
