<?php

namespace Tests\Feature;

use App\Mail\TeamInviteMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Team members work in the owner's business under a role: the owner keeps
 * every right, members see and do only what their role allows, and what
 * they enter belongs to the business.
 */
class TeamMembersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->owner = User::factory()->create(['name' => 'Owner Arbaz', 'email' => 'owner@shop.in']);
        $this->company = Company::factory()->create(['user_id' => $this->owner->id, 'state_id' => $mh->id, 'name' => 'Arbaz Traders',
            'gstin' => '27ABCDE1234F1Z5', 'invoice_prefix' => 'INV', 'invoice_counter' => 0]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id, 'state_id' => $mh->id]);
    }

    /** Invite, then sign in as the invitee and accept. */
    private function join(string $role, string $email = 'ca@firm.in'): User
    {
        Mail::fake();
        $this->actingAs($this->owner)->post(route('team.invite'), ['email' => $email, 'role' => $role])->assertSessionHasNoErrors();
        $member = User::factory()->create(['name' => 'Member ' . ucfirst($role), 'email' => $email]);
        Company::factory()->create(['user_id' => $member->id, 'name' => 'Member Own Firm']);
        $token = CompanyMember::where('email', $email)->value('token');

        $this->actingAs($member)->get(route('team.join', $token))->assertOk()->assertSee('Accept and open Arbaz Traders');
        $this->actingAs($member)->post(route('team.accept', $token))->assertRedirect(route('dashboard'));

        return $member->fresh();
    }

    public function test_the_invitation_is_emailed_with_a_link(): void
    {
        Mail::fake();
        $this->actingAs($this->owner)->post(route('team.invite'), ['email' => 'Staff@Shop.in', 'role' => 'staff'])->assertSessionHasNoErrors();
        $m = CompanyMember::firstOrFail();
        $this->assertSame('staff@shop.in', $m->email);
        Mail::assertSent(TeamInviteMail::class, fn ($mail) => $mail->hasTo('staff@shop.in'));
        $this->actingAs($this->owner)->get(route('team.index'))->assertOk()->assertSee('Invitation pending')->assertSee(route('team.join', $m->token));
    }

    public function test_an_accountant_works_in_the_owners_books(): void
    {
        $member = $this->join('accountant');
        $this->assertSame($this->company->id, (int) $member->active_company_id);

        $this->actingAs($member)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Audit fee', 'hsn_sac' => '998221', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame($this->owner->id, $invoice->user_id, 'the business owns what the member enters');
        $this->assertSame($this->company->id, $invoice->company_id);

        $this->actingAs($member)->post(route('invoices.finalize', $invoice))->assertRedirect();
        $this->assertSame('INV-0001', $invoice->fresh()->invoice_number);
        $this->actingAs($this->owner)->get(route('invoices.index'))->assertOk()->assertSee('INV-0001');

        // Purchases, GST and reports are part of the role...
        $this->actingAs($member)->get(route('purchases.index'))->assertOk();
        $this->actingAs($member)->get(route('finance.gstr3b'))->assertOk();
        // ...the team and company settings are not.
        $this->actingAs($member)->get(route('team.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($member)->get(route('company.edit'))->assertRedirect(route('dashboard'));
        $this->actingAs($member)->get(route('backup.index'))->assertRedirect(route('dashboard'));

        $entry = AuditLog::where('action', 'invoice.finalized')->firstOrFail();
        $this->assertStringContainsString('by Member Accountant (Accountant / CA)', $entry->summary);

        $this->assertSame($this->company->id, (int) $this->owner->fresh()->active_company_id, "the owner's own choice is never touched");
    }

    public function test_billing_staff_can_bill_but_not_see_the_accounts(): void
    {
        $member = $this->join('staff', 'staff@shop.in');

        $this->actingAs($member)->get(route('invoices.create'))->assertOk();
        // The money overview is not for billing staff: they land on the invoices.
        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('invoices.index'));
        $this->actingAs($member)->get(route('purchases.index'))->assertRedirect(route('invoices.index'));
        $this->actingAs($member)->get(route('finance.index'))->assertRedirect(route('invoices.index'));
        $this->actingAs($member)->get(route('finance.gstr1'))->assertRedirect(route('invoices.index'));

        $page = $this->actingAs($member)->get(route('invoices.index'))->assertOk();
        $page->assertSee('as <strong>Billing staff</strong>', false);
        $page->assertDontSee('Purchase invoices');
        $page->assertDontSee('GSTR-3B');

        // No cancelling or deleting issued invoices.
        $this->actingAs($member)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Item', 'hsn_sac' => '998221', 'quantity' => 1, 'rate' => 100, 'gst_rate' => 18]],
        ]);
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($member)->post(route('invoices.finalize', $invoice));
        $this->actingAs($member)->from(route('invoices.show', $invoice))
            ->post(route('invoices.cancel', $invoice), ['cancellation_reason' => 'Trying to cancel'])->assertRedirect(route('invoices.index'));
        $this->assertNotSame('cancelled', $invoice->fresh()->status);
    }

    public function test_a_member_keeps_their_own_profile(): void
    {
        $member = $this->join('admin', 'admin@shop.in');
        $this->actingAs($member)->get(route('profile.edit'))->assertOk()->assertSee('admin@shop.in')->assertDontSee('owner@shop.in');
    }

    public function test_removing_a_member_ends_their_access(): void
    {
        $member = $this->join('accountant');
        $this->actingAs($this->owner)->delete(route('team.destroy', CompanyMember::firstOrFail()))->assertRedirect();

        $this->actingAs($member)->get(route('invoices.index'))->assertOk()->assertDontSee('Arbaz Traders');
        $this->assertNotSame($this->company->id, (int) $member->fresh()->active_company_id);
    }

    public function test_an_invitation_cannot_be_used_by_someone_else(): void
    {
        Mail::fake();
        $this->actingAs($this->owner)->post(route('team.invite'), ['email' => 'ca@firm.in', 'role' => 'admin']);
        $token = CompanyMember::value('token');
        $intruder = User::factory()->create(['email' => 'other@else.in']);

        $this->actingAs($intruder)->get(route('team.join', $token))->assertOk()->assertSee('This invitation is for');
        $this->actingAs($intruder)->post(route('team.accept', $token));
        $this->assertNull(CompanyMember::firstOrFail()->accepted_at);

        // Pointing active_company_id at the business by hand gets nowhere either.
        $intruder->forceFill(['active_company_id' => $this->company->id])->save();
        $this->actingAs($intruder)->get(route('invoices.index'))->assertOk()->assertDontSee('Arbaz Traders');
    }

    public function test_members_cannot_manage_the_team_and_owners_cannot_invite_themselves(): void
    {
        $this->actingAs($this->owner)->post(route('team.invite'), ['email' => 'owner@shop.in', 'role' => 'admin'])->assertSessionHasErrors('email');
        $member = $this->join('admin', 'admin@shop.in');
        $this->actingAs($member)->post(route('team.invite'), ['email' => 'x@y.in', 'role' => 'admin'])->assertStatus(403);
    }

    public function test_the_mobile_app_follows_the_same_roles(): void
    {
        $member = $this->join('staff', 'staff@shop.in');
        \Laravel\Sanctum\Sanctum::actingAs($member);

        // Billing works in the owner's business...
        $this->postJson('/api/invoices', [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Item', 'hsn_sac' => '998221', 'quantity' => 1, 'rate' => 100, 'gst_rate' => 18]],
        ])->assertCreated();
        $this->assertSame($this->owner->id, Invoice::latest('id')->first()->user_id);
        \Laravel\Sanctum\Sanctum::actingAs($member->fresh());
        $this->getJson('/api/invoices')->assertOk()->assertJsonCount(1, 'data');
        \Laravel\Sanctum\Sanctum::actingAs($member->fresh());
        $this->getJson('/api/customers')->assertOk();

        // ...the accounts and cancelling are not part of the role.
        $as = fn () => \Laravel\Sanctum\Sanctum::actingAs($member->fresh()); // a fresh token request each time
        $as();
        $this->getJson('/api/finance/pnl')->assertForbidden();
        $as();
        $this->getJson('/api/dashboard')->assertForbidden();
        $invoice = Invoice::latest('id')->first();
        $as();
        $this->postJson("/api/invoices/{$invoice->id}/cancel", ['cancellation_reason' => 'Not allowed to'])->assertForbidden();

        // Their own profile and the firm list are theirs.
        $as();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'staff@shop.in');
        $as();
        $this->getJson('/api/companies')->assertOk()->assertJsonPath('meta.memberships.0.name', 'Arbaz Traders');
    }

    public function test_a_member_cannot_reach_the_owners_other_businesses(): void
    {
        // The owner has a second business the member was not invited into.
        $other = Company::factory()->create(['user_id' => $this->owner->id, 'state_id' => $this->company->state_id, 'name' => 'Arbaz Exports']);
        $otherCustomer = Customer::factory()->create(['user_id' => $this->owner->id, 'company_id' => $other->id, 'state_id' => $this->company->state_id]);
        $otherInvoice = Invoice::factory()->create(['user_id' => $this->owner->id, 'company_id' => $other->id, 'customer_id' => $otherCustomer->id, 'status' => 'draft']);

        $member = $this->join('admin', 'admin@shop.in');
        $this->actingAs($member)->get(route('invoices.show', $otherInvoice))->assertForbidden();
        $this->actingAs($member)->get(route('customers.edit', $otherCustomer))->assertForbidden();
        $this->actingAs($member)->delete(route('invoices.destroy', $otherInvoice))->assertForbidden();
        $this->actingAs($member)->get(route('companies.edit', $other))->assertForbidden();
        $this->assertNotNull($otherInvoice->fresh(), 'nothing in the other business was touched');

        \Laravel\Sanctum\Sanctum::actingAs($member->fresh());
        $this->getJson("/api/invoices/{$otherInvoice->id}")->assertForbidden();

        // Their own business's records still open.
        $own = Invoice::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'status' => 'draft']);
        $this->actingAs($member)->get(route('invoices.show', $own))->assertOk();
    }

    public function test_the_firm_switcher_lists_joined_businesses_with_the_role(): void
    {
        $member = $this->join('accountant');
        $this->actingAs($member)->get(route('companies.index'))->assertOk()
            ->assertSee('As a team member')->assertSee('Arbaz Traders')->assertSee('Your role: Accountant / CA');

        $own = Company::where('user_id', $member->id)->firstOrFail();
        $this->actingAs($member)->post(route('companies.switch', $own));
        $this->assertSame($own->id, (int) $member->fresh()->active_company_id);
        $this->actingAs($member)->post(route('companies.switch', $this->company))->assertRedirect(route('dashboard'));
        $this->assertSame($this->company->id, (int) $member->fresh()->active_company_id);
    }
}
