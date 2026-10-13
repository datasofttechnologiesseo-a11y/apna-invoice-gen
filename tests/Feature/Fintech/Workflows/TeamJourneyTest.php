<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Mail\TeamInviteMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A team member's whole life in someone else's business: invited by email,
 * signs up on their own, accepts, bills in the owner's books under a role,
 * gets promoted and demoted, and finally leaves or is removed.
 *
 * What is proven here and not in TeamMembersTest: the invite email and its
 * link, sign-up through the real OTP routes, the number series continuing
 * from the owner's, the customer and payment a member enters belonging to the
 * business, the full list of pages a billing clerk is turned away from (and
 * how), live role changes both ways on the web and the app, members trying
 * to change roles, a removal taking effect on the very next request, leaving,
 * dead invite links, and double / wrong-account acceptance.
 *
 * Today is Mon 12 Oct 2026 (FY 2026-27). Sharma Traders (Maharashtra, 27)
 * moved over mid-year: its counter stands at 41, so its next invoice is
 * INV-0042.
 */
class TeamJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;
    private Customer $patil;

    private const STAFF_DENIED = 'Your role in Sharma Traders (Billing staff) does not include this page. Ask the owner if you need it.';
    private const ACCOUNTANT_DENIED = 'Your role in Sharma Traders (Accountant / CA) does not include this page. Ask the owner if you need it.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        Mail::fake();

        $this->biz = $this->makeBusiness(
            ['invoice_counter' => 41, 'invoice_counter_fy' => 2026],
            ['name' => 'Arbaz Sharma', 'email' => 'arbaz@sharmatraders.in'],
        );
        $this->owner = $this->ownerOf($this->biz);
        $this->patil = $this->customerOf($this->biz);
    }

    // ------------------------------------------------------------- helpers

    private function invite(string $email, string $role): CompanyMember
    {
        $this->actingAs($this->owner)->from(route('team.index'))
            ->post(route('team.invite'), ['email' => $email, 'role' => $role])
            ->assertSessionHasNoErrors();

        return CompanyMember::where('company_id', $this->biz->id)->where('email', mb_strtolower($email))->firstOrFail();
    }

    /** Invite, then a separately signed-up person (with their own shop) accepts. */
    private function joinAs(string $role, string $email, string $name): User
    {
        $invite = $this->invite($email, $role);
        $member = User::factory()->create(['name' => $name, 'email' => $email]);
        $own = Company::factory()->create([
            'user_id' => $member->id, 'name' => $name . ' Own Shop', 'state_id' => $this->state('27')->id,
            'invoice_prefix' => 'OWN', 'invoice_counter' => 0, 'onboarded_at' => now(),
        ]);
        $member->forceFill(['active_company_id' => $own->id])->save();

        // Each role lands on a page it can open: billing staff have no dashboard.
        $this->actingAs($member)->post(route('team.accept', $invite->token))
            ->assertRedirect(route(CompanyMember::allows($role, 'dashboard') ? 'dashboard' : 'invoices.index'));

        return $member->fresh();
    }

    private function membershipOf(User $member): CompanyMember
    {
        return CompanyMember::where('company_id', $this->biz->id)->where('user_id', $member->id)->firstOrFail();
    }

    private function ownBusinessOf(User $user): Company
    {
        return Company::where('user_id', $user->id)->orderBy('id')->firstOrFail();
    }

    private function assertTurnedAway(TestResponse $response, string $fallback, string $message, string $what): void
    {
        $this->assertTrue($response->isRedirect(route($fallback)),
            "{$what}: expected a redirect to {$fallback}, got HTTP {$response->getStatusCode()} " . ($response->headers->get('Location') ?? ''));
        $response->assertSessionHas('error', $message);
    }

    // ------------------------------------------------------------- the journey

    public function test_an_invited_clerk_signs_up_joins_and_bills_in_the_owners_books(): void
    {
        // The owner issues their own invoice first: counter 41 -> INV-0042.
        // 1 x 1,000 @ 18% intra-state = 1,000 + CGST 90 + SGST 90 = 1,180.
        $ownerInvoice = $this->webIssued($this->owner, $this->patil);
        $this->assertSame('INV-0042', $ownerInvoice->invoice_number);

        // 1. The owner invites Ravi as billing staff. The address is stored
        //    in lower case whatever way it was typed.
        $this->actingAs($this->owner)->get(route('team.index'))->assertOk()->assertSee('No team members yet.');
        $this->actingAs($this->owner)->from(route('team.index'))
            ->post(route('team.invite'), ['email' => 'Ravi.Kumar@Example.in', 'role' => 'staff'])
            ->assertRedirect(route('team.index'))
            ->assertSessionHas('status', 'Invitation emailed to ravi.kumar@example.in. The invite link below can also be shared.');

        $invite = CompanyMember::where('company_id', $this->biz->id)->firstOrFail();
        $this->assertSame('ravi.kumar@example.in', $invite->email);
        $this->assertSame('staff', $invite->role);
        $this->assertNull($invite->user_id, 'nobody has accepted yet');
        $this->assertNull($invite->accepted_at);
        $this->assertSame($this->owner->id, (int) $invite->invited_by);
        $this->assertSame(48, strlen($invite->token), 'the invite link carries a long unguessable token');
        $joinUrl = route('team.join', $invite->token);

        // 2. The email goes to that address, names the business, the role and
        //    who invited, and carries the join link.
        Mail::assertSent(TeamInviteMail::class, 1);
        Mail::assertSent(TeamInviteMail::class, function (TeamInviteMail $mail) use ($joinUrl) {
            $html = $mail->render();

            return $mail->hasTo('ravi.kumar@example.in')
                && $mail->hasSubject('Arbaz Sharma invited you to Sharma Traders on Apna Invoice')
                && str_contains($html, $joinUrl)
                && str_contains($html, 'Billing staff');
        });
        $logged = AuditLog::where('action', 'team.invited')->firstOrFail();
        $this->assertSame('Invited ravi.kumar@example.in as Billing staff', $logged->summary);
        $this->assertSame($this->biz->id, (int) $logged->company_id);

        // 3. Ravi opens the link before he has an account: he is asked to
        //    sign in with the invited address, and accepting needs a login.
        $this->app['auth']->forgetGuards();
        $this->get($joinUrl)->assertOk()
            ->assertSee('Join Sharma Traders')
            ->assertSee('Log in with <strong>ravi.kumar@example.in</strong> to accept', false);
        $this->post(route('team.accept', $invite->token))->assertRedirect(route('login'));
        $this->assertNull($invite->fresh()->accepted_at);

        // 4. He signs up through the real two-step OTP registration.
        $step1 = $this->post('/register', [
            'name' => 'Ravi Kumar', 'phone' => '9876543210', 'email' => 'ravi.kumar@example.in',
            'password' => 'ravi-billing-2026', 'password_confirmation' => 'ravi-billing-2026', 'terms_accepted' => true,
        ]);
        $step1->assertRedirect(route('register.verify'));
        $code = $step1->getSession()->get('otp_dev_code');
        $this->assertNotEmpty($code);
        $this->post(route('register.verify.store'), ['code' => $code])->assertRedirect(route('onboarding.index'));

        $ravi = User::where('email', 'ravi.kumar@example.in')->firstOrFail();
        $this->assertNotNull($ravi->email_verified_at);
        // Opening the app gives him his own (placeholder) business first.
        $this->actingAs($ravi)->get(route('onboarding.index'))->assertRedirect(route('onboarding.business'));
        $ravisOwn = $this->ownBusinessOf($ravi);
        $this->assertSame((int) $ravisOwn->id, (int) $ravi->fresh()->active_company_id);

        // 5. Signed in, the link offers to accept; accepting opens Sharma Traders.
        $this->actingAs($ravi)->get($joinUrl)->assertOk()
            ->assertSee('You are invited as <strong>Billing staff</strong>', false)
            ->assertSee('Accept and open Sharma Traders');
        $this->actingAs($ravi)->post(route('team.accept', $invite->token))
            ->assertRedirect(route('invoices.index')) // billing staff have no dashboard
            ->assertSessionHas('status', 'Invitation accepted. You now have access to Sharma Traders as Billing staff.');

        $invite->refresh();
        $this->assertSame($ravi->id, (int) $invite->user_id);
        $this->assertSame('2026-10-12', $invite->accepted_at->toDateString());
        $this->assertSame($this->biz->id, (int) $ravi->fresh()->active_company_id);
        $this->assertSame($this->biz->id, (int) $this->owner->fresh()->active_company_id, "the owner's own choice is never touched");

        // 6. He adds a customer: it belongs to the business, not to him.
        $this->actingAs($ravi)->get(route('customers.create'))->assertOk();
        $this->actingAs($ravi)->post(route('customers.store'), [
            'name' => 'Deshmukh Hardware', 'gstin' => '27PQRST5678K1Z2', 'state_id' => $this->state('27')->id,
            'country' => 'India', 'city' => 'Nashik', 'email' => 'accounts@deshmukh.example', 'phone' => '9822000111',
        ])->assertRedirect(route('customers.index'))
            ->assertSessionHas('status', "Customer 'Deshmukh Hardware' added to Sharma Traders.");
        $deshmukh = Customer::where('name', 'Deshmukh Hardware')->firstOrFail();
        $this->assertSame($this->owner->id, (int) $deshmukh->user_id);
        $this->assertSame($this->biz->id, (int) $deshmukh->company_id);
        $this->assertSame(0, Customer::where('user_id', $ravi->id)->count(), 'nothing is filed under the clerk');

        // 7. He bills it. 2 x 1,250 = 2,500; 18% intra-state (27 -> 27):
        //    CGST 9% = 225, SGST 9% = 225, IGST 0; grand 2,950, round-off 0.
        $draft = $this->webDraft($ravi, $deshmukh, [$this->line(1250, 18, 2)]);
        $this->assertSame('draft', $draft->status);
        $this->assertSame($this->owner->id, (int) $draft->user_id);
        $this->assertSame($this->biz->id, (int) $draft->company_id);
        $this->assertMoney(2500, $draft->subtotal);
        $this->assertMoney(225, $draft->total_cgst);
        $this->assertMoney(225, $draft->total_sgst);
        $this->assertMoney(0, $draft->total_igst);
        $this->assertMoney(2950, $draft->grand_total);

        // 8. Issuing continues the owner's series: 0042 was the owner's, so 0043.
        $issued = $this->webIssue($ravi, $draft);
        $this->assertSame('INV-0043', $issued->invoice_number);
        $this->assertSame('final', $issued->status);
        $this->assertSame(43, (int) $this->biz->fresh()->invoice_counter);

        // 9. Records ₹1,000 by UPI: receipt RCPT-0001 (the business's first),
        //    balance 2,950 - 1,000 = 1,950, partly paid.
        $this->webPay($ravi, $issued, 1000, ['reference_number' => 'UPI-881'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Payment recorded. Receipt RCPT-0001 issued.');
        $payment = $this->latestPayment();
        $this->assertSame('RCPT-0001', $payment->receipt_number);
        $this->assertSame($this->owner->id, (int) $payment->user_id);
        $this->assertSame($this->biz->id, (int) $payment->company_id);
        $this->assertMoney(1000, $payment->amount);
        $issued->refresh();
        $this->assertMoney(1000, $issued->paid_amount);
        $this->assertMoney(1950, $issued->balance);
        $this->assertSame('partially_paid', $issued->status);

        // 10. The audit trail is the owner's, and names the clerk.
        $finalized = AuditLog::where('action', 'invoice.finalized')->where('subject_id', $issued->id)->firstOrFail();
        $this->assertStringContainsString('Invoice INV-0043 finalized', $finalized->summary);
        $this->assertStringEndsWith('by Ravi Kumar (Billing staff)', $finalized->summary);
        $this->assertSame($this->owner->id, (int) $finalized->user_id);
        $this->assertSame($this->biz->id, (int) $finalized->company_id);
        $paid = AuditLog::where('action', 'payment.recorded')->where('subject_id', $payment->id)->firstOrFail();
        $this->assertStringContainsString('Receipt RCPT-0001 · Invoice INV-0043', $paid->summary);
        $this->assertStringEndsWith('by Ravi Kumar (Billing staff)', $paid->summary);
        $ownersOwn = AuditLog::where('action', 'invoice.finalized')->where('subject_id', $ownerInvoice->id)->firstOrFail();
        $this->assertStringNotContainsString(' by ', $ownersOwn->summary, "the owner's own entries are not attributed to anyone else");

        // 11. The owner sees all of it in their own lists and log.
        $list = $this->actingAs($this->owner)->get(route('invoices.index'))->assertOk();
        $this->assertSame(['INV-0043', 'INV-0042'], collect($list->viewData('invoices')->items())->pluck('invoice_number')->all());
        $list->assertSee('Deshmukh Hardware');
        $this->actingAs($this->owner)->get(route('customers.index'))->assertOk()->assertSee('Deshmukh Hardware');
        $this->actingAs($this->owner)->get(route('profile.activity'))->assertOk()
            ->assertSee('by Ravi Kumar (Billing staff)');
        $this->actingAs($this->owner)->get(route('team.index'))->assertOk()
            ->assertSee('Ravi Kumar')->assertSee('Joined 12 Oct 2026');

        // 12. Ravi's own business stayed empty.
        $this->assertSame(0, Invoice::where('company_id', $ravisOwn->id)->count());
    }

    // ------------------------------------------------------------- what a clerk may not open

    public function test_billing_staff_is_turned_away_from_owner_only_pages_the_way_the_middleware_says(): void
    {
        $issued = $this->webIssued($this->owner, $this->patil); // INV-0042, 1,180
        $draft = $this->webDraft($this->owner, $this->patil);
        $staff = $this->joinAs('staff', 'clerk@sharmatraders.in', 'Sunita Clerk');

        // Every GET outside the billing role sends them to the invoice list
        // with the same explanation.
        foreach ([
            'dashboard' => route('dashboard'),
            'team.index' => route('team.index'),
            'company.edit' => route('company.edit'),
            'companies.edit' => route('companies.edit', $this->biz),
            'backup.index' => route('backup.index'),
            'purchases.index' => route('purchases.index'),
            'purchases.create' => route('purchases.create'),
            'finance.index' => route('finance.index'),
            'finance.expenses' => route('finance.expenses'),
            'finance.gstr1' => route('finance.gstr1'),
            'finance.gstr2b' => route('finance.gstr2b'),
            'finance.gstr3b' => route('finance.gstr3b'),
            'invoices.gstr1' => route('invoices.gstr1'),
            'bank.index' => route('bank.index'),
            'reports.index' => route('reports.index'),
        ] as $name => $url) {
            $this->assertTurnedAway($this->actingAs($staff)->get($url), 'invoices.index', self::STAFF_DENIED, $name);
        }

        // Cancelling: a form post with no page behind it is a plain 403...
        // (from() earlier left a Referer on every later request; clear it.)
        $this->flushHeaders();
        $this->actingAs($staff)->post(route('invoices.cancel', $issued), ['cancellation_reason' => 'Customer returned goods'])
            ->assertForbidden();
        // ...from the invoice page it goes back to the list with the message.
        $this->assertTurnedAway(
            $this->actingAs($staff)->from(route('invoices.show', $issued))
                ->post(route('invoices.cancel', $issued), ['cancellation_reason' => 'Customer returned goods']),
            'invoices.index', self::STAFF_DENIED, 'invoices.cancel');
        $this->assertSame('final', $issued->fresh()->status, 'the issued invoice was not cancelled');

        // Deleting a draft or a customer is not theirs either.
        $this->assertTurnedAway($this->actingAs($staff)->delete(route('invoices.destroy', $draft)), 'invoices.index', self::STAFF_DENIED, 'invoices.destroy');
        $this->assertNotNull(Invoice::find($draft->id), 'the draft is still there');
        $this->assertTurnedAway($this->actingAs($staff)->delete(route('customers.destroy', $this->patil)), 'invoices.index', self::STAFF_DENIED, 'customers.destroy');
        $this->assertNotNull(Customer::find($this->patil->id));

        // An XHR / JSON caller gets a 403, not a redirect.
        $this->actingAs($staff)->getJson(route('finance.index'))->assertForbidden();

        // What the role does include still opens.
        $this->actingAs($staff)->get(route('invoices.index'))->assertOk()->assertSee('INV-0042');
        $this->actingAs($staff)->get(route('invoices.show', $issued))->assertOk();
        $this->actingAs($staff)->get(route('customers.index'))->assertOk();
        $this->actingAs($staff)->get(route('products.index'))->assertOk();
        $this->actingAs($staff)->get(route('quotations.index'))->assertOk();
    }

    // ------------------------------------------------------------- role changes

    public function test_promoting_staff_to_accountant_opens_purchases_and_gst_and_demoting_closes_them_again(): void
    {
        $member = $this->joinAs('staff', 'sunita@sharmatraders.in', 'Sunita Patil');
        $membership = $this->membershipOf($member);

        $this->assertTurnedAway($this->actingAs($member)->get(route('purchases.index')), 'invoices.index', self::STAFF_DENIED, 'purchases as staff');
        $this->apiAs($member)->getJson('/api/finance/gstr3b')->assertForbidden();

        // Staff -> accountant.
        $this->actingAs($this->owner)->from(route('team.index'))
            ->patch(route('team.update', $membership), ['role' => 'accountant'])
            ->assertRedirect(route('team.index'))
            ->assertSessionHas('status', 'sunita@sharmatraders.in is now Accountant / CA.');
        $this->assertSame('accountant', $membership->fresh()->role);
        $log = AuditLog::where('action', 'team.role_changed')->latest('id')->firstOrFail();
        $this->assertSame('sunita@sharmatraders.in is now Accountant / CA', $log->summary);

        // The very next request already runs under the new role.
        $this->actingAs($member)->get(route('purchases.index'))->assertOk();
        $this->actingAs($member)->get(route('finance.gstr3b'))->assertOk();
        $this->actingAs($member)->get(route('dashboard'))->assertOk();
        $this->apiAs($member)->getJson('/api/finance/gstr3b')->assertOk();
        // Still not the team or the settings - and an accountant lands on the dashboard.
        $this->assertTurnedAway($this->actingAs($member)->get(route('team.index')), 'dashboard', self::ACCOUNTANT_DENIED, 'team as accountant');
        $this->assertTurnedAway($this->actingAs($member)->get(route('company.edit')), 'dashboard', self::ACCOUNTANT_DENIED, 'company settings as accountant');

        // Back to staff: closed again on the next request.
        $this->actingAs($this->owner)->from(route('team.index'))
            ->patch(route('team.update', $membership), ['role' => 'staff'])
            ->assertSessionHas('status', 'sunita@sharmatraders.in is now Billing staff.');
        $this->assertTurnedAway($this->actingAs($member)->get(route('purchases.index')), 'invoices.index', self::STAFF_DENIED, 'purchases after demotion');
        $this->assertTurnedAway($this->actingAs($member)->get(route('finance.gstr3b')), 'invoices.index', self::STAFF_DENIED, 'GSTR-3B after demotion');
        $this->apiAs($member)->getJson('/api/finance/gstr3b')->assertForbidden();
    }

    public function test_members_cannot_change_roles_and_the_owner_can_only_set_a_known_role_in_their_own_team(): void
    {
        $staff = $this->joinAs('staff', 'sunita@sharmatraders.in', 'Sunita Patil');
        $admin = $this->joinAs('admin', 'manager@sharmatraders.in', 'Manoj Manager');
        $staffMembership = $this->membershipOf($staff);

        // A clerk promoting themselves: turned away, role unchanged.
        $this->assertTurnedAway(
            $this->actingAs($staff)->from(route('invoices.index'))->patch(route('team.update', $staffMembership), ['role' => 'admin']),
            'invoices.index', self::STAFF_DENIED, 'self-promotion');
        $this->actingAs($staff)->patchJson(route('team.update', $staffMembership), ['role' => 'admin'])->assertForbidden();
        // Even an admin member does not manage the team.
        $this->assertTurnedAway(
            $this->actingAs($admin)->from(route('dashboard'))->patch(route('team.update', $staffMembership), ['role' => 'accountant']),
            'dashboard', 'Your role in Sharma Traders (Admin) does not include this page. Ask the owner if you need it.', 'admin changing a role');
        $this->actingAs($admin)->deleteJson(route('team.destroy', $staffMembership))->assertForbidden();
        $this->assertSame('staff', $staffMembership->fresh()->role);
        $this->assertSame(0, AuditLog::where('action', 'team.role_changed')->count());

        // The owner cannot invent a role.
        foreach (['owner', 'superadmin', ''] as $bad) {
            $this->actingAs($this->owner)->from(route('team.index'))
                ->patch(route('team.update', $staffMembership), ['role' => $bad])
                ->assertSessionHasErrors('role');
        }
        $this->assertSame('staff', $staffMembership->fresh()->role);

        // Another business's owner cannot touch this team.
        $other = $this->makeBusiness(['name' => 'Gupta Steel', 'gstin' => '07LMNOP4321Q1Z6', 'state_id' => $this->state('07')->id]);
        $otherOwner = $this->ownerOf($other);
        $this->actingAs($otherOwner)->patch(route('team.update', $staffMembership), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($otherOwner)->delete(route('team.destroy', $staffMembership))->assertForbidden();
        $this->assertSame('staff', $staffMembership->fresh()->role);
        $this->assertNotNull(CompanyMember::find($staffMembership->id));
    }

    // ------------------------------------------------------------- leaving and removal

    public function test_a_member_who_leaves_loses_access_and_is_back_in_their_own_business(): void
    {
        $issued = $this->webIssued($this->owner, $this->patil); // INV-0042
        $member = $this->joinAs('staff', 'sunita@sharmatraders.in', 'Sunita Patil');
        $own = $this->ownBusinessOf($member);
        $membership = $this->membershipOf($member);
        $token = $membership->token;

        $this->actingAs($member)->get(route('invoices.index'))->assertOk()->assertSee('INV-0042');

        // Nobody else can leave on their behalf (not even the owner).
        $this->actingAs($this->owner)->post(route('team.leave', $membership))->assertForbidden();
        $this->assertNotNull(CompanyMember::find($membership->id));

        $this->actingAs($member)->post(route('team.leave', $membership))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('status', 'You have left Sharma Traders.');
        $this->assertNull(CompanyMember::find($membership->id));
        $this->assertSame((int) $own->id, (int) $member->fresh()->active_company_id);

        // Their lists are their own business now.
        $list = $this->actingAs($member)->get(route('invoices.index'))->assertOk();
        $this->assertSame((int) $own->id, (int) $list->viewData('company')->id);
        $this->assertCount(0, $list->viewData('invoices')->items());
        $list->assertDontSee('INV-0042');
        $this->actingAs($member)->get(route('invoices.show', $issued))->assertForbidden();
        $this->actingAs($member)->post(route('companies.switch', $this->biz))->assertForbidden();
        $this->apiAs($member)->getJson('/api/invoices')->assertOk()->assertJsonCount(0, 'data');
        $this->apiAs($member)->getJson('/api/companies/active')->assertOk()->assertJsonPath('data.id', $own->id);
        $this->apiAs($member)->getJson('/api/companies')->assertOk()->assertJsonCount(0, 'meta.memberships');

        // The old invite link is dead.
        $this->actingAs($member)->get(route('team.join', $token))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'This invitation is no longer valid. Ask the owner to invite you again.');
        $this->actingAs($member)->post(route('team.accept', $token))->assertNotFound();
        $this->assertSame(0, CompanyMember::where('company_id', $this->biz->id)->count());
    }

    public function test_a_member_removed_mid_session_is_in_their_own_business_on_the_very_next_request(): void
    {
        // 1 x 1,000 @ 18% = 1,180 owed.
        $issued = $this->webIssued($this->owner, $this->patil);
        $member = $this->joinAs('accountant', 'ca@deshpande.in', 'CA Deshpande');
        $own = $this->ownBusinessOf($member);
        $membership = $this->membershipOf($member);

        $page = $this->actingAs($member)->get(route('invoices.index'))->assertOk();
        $this->assertSame($this->biz->id, (int) $page->viewData('company')->id);

        $this->actingAs($this->owner)->from(route('team.index'))
            ->delete(route('team.destroy', $membership))
            ->assertRedirect(route('team.index'))
            ->assertSessionHas('status', 'ca@deshpande.in no longer has access.');
        $this->assertSame('Removed ca@deshpande.in from the team', AuditLog::where('action', 'team.removed')->value('summary'));

        // The member still has the invoice page open and records a payment
        // on it: refused, nothing written.
        $this->actingAs($member)->from(route('invoices.show', $issued))
            ->post(route('invoices.payments', $issued), ['amount' => 500, 'method' => 'cash', 'received_at' => now()->toDateString()])
            ->assertForbidden();
        $this->assertSame(0, Payment::count());
        $this->assertMoney(1180, $issued->fresh()->balance);
        $this->assertSame((int) $own->id, (int) $member->fresh()->active_company_id, 'the first request after removal moved them home');

        $page = $this->actingAs($member)->get(route('invoices.index'))->assertOk();
        $this->assertSame((int) $own->id, (int) $page->viewData('company')->id);
        $page->assertDontSee('INV-0042');
        $this->apiAs($member)->getJson("/api/invoices/{$issued->id}")->assertForbidden();
        $this->apiAs($member)->postJson("/api/invoices/{$issued->id}/payments", ['amount' => 500, 'method' => 'cash', 'received_at' => now()->toDateString()])
            ->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    // ------------------------------------------------------------- the invitation itself

    public function test_an_invitation_is_accepted_once_by_the_invited_account_only_and_a_pending_one_opens_nothing(): void
    {
        // Duplicate invites and unknown roles are refused at the door.
        // (Same casing on purpose: the unique check compares the address as
        // typed, so a case variant relies on the database collation.)
        $invite = $this->invite('ca@deshpande.in', 'accountant');
        $this->actingAs($this->owner)->from(route('team.index'))
            ->post(route('team.invite'), ['email' => 'ca@deshpande.in', 'role' => 'staff'])
            ->assertSessionHasErrors(['email' => 'This person is already invited to this business.']);
        $this->actingAs($this->owner)->from(route('team.index'))
            ->post(route('team.invite'), ['email' => 'new@deshpande.in', 'role' => 'owner'])
            ->assertSessionHasErrors('role');
        $this->assertSame(1, CompanyMember::where('company_id', $this->biz->id)->count());
        Mail::assertSent(TeamInviteMail::class, 1);

        $invitee = User::factory()->create(['name' => 'CA Deshpande', 'email' => 'ca@deshpande.in']);
        $inviteeOwn = Company::factory()->create(['user_id' => $invitee->id, 'name' => 'Deshpande & Co', 'state_id' => $this->state('27')->id]);
        $invitee->forceFill(['active_company_id' => $inviteeOwn->id])->save();
        $stranger = User::factory()->create(['name' => 'Someone Else', 'email' => 'someone@else.in']);

        // Pending: the invited person cannot switch in yet, on the web or the app.
        $this->actingAs($invitee)->post(route('companies.switch', $this->biz))->assertForbidden();
        $this->apiAs($invitee)->postJson("/api/companies/{$this->biz->id}/switch")->assertForbidden();
        $this->apiAs($invitee)->getJson('/api/companies')->assertOk()->assertJsonCount(0, 'meta.memberships');
        $this->assertSame((int) $inviteeOwn->id, (int) $invitee->fresh()->active_company_id);

        // The wrong account cannot use the link.
        $this->actingAs($stranger)->from(route('team.join', $invite->token))
            ->post(route('team.accept', $invite->token))
            ->assertRedirect(route('team.join', $invite->token))
            ->assertSessionHas('error', 'This invitation is for ca@deshpande.in. Sign in with that account to accept it.');
        $this->assertNull($invite->fresh()->user_id);
        $this->assertNull($invite->fresh()->accepted_at);

        // The invited account accepts.
        $this->actingAs($invitee)->post(route('team.accept', $invite->token))->assertRedirect(route('dashboard'));
        $firstAccepted = $invite->fresh()->accepted_at->toDateTimeString();

        // A second accept an hour later changes nothing.
        $this->travel(1)->hours();
        $this->actingAs($invitee)->post(route('team.accept', $invite->token))->assertRedirect(route('dashboard'));
        $this->assertSame(1, CompanyMember::where('company_id', $this->biz->id)->count());
        $this->assertSame($firstAccepted, $invite->fresh()->accepted_at->toDateTimeString(), 'the join date is kept');
        $this->assertSame($invitee->id, (int) $invite->fresh()->user_id);

        // Once accepted, the link still cannot be taken over by another account.
        $this->actingAs($stranger)->post(route('team.accept', $invite->token))
            ->assertSessionHas('error', 'This invitation is for ca@deshpande.in. Sign in with that account to accept it.');
        $this->assertSame($invitee->id, (int) $invite->fresh()->user_id);
        $stranger->forceFill(['active_company_id' => $this->biz->id])->save();
        $page = $this->actingAs($stranger)->get(route('invoices.index'))->assertOk();
        $this->assertNotSame($this->biz->id, (int) $page->viewData('company')->id, 'pointing at the business by hand opens nothing');
    }

    public function test_a_member_cannot_switch_into_the_owners_other_business(): void
    {
        $exports = Company::factory()->create([
            'user_id' => $this->owner->id, 'name' => 'Sharma Exports', 'state_id' => $this->state('29')->id,
            'gstin' => '29ABCDE1234F1ZW', 'invoice_prefix' => 'EXP',
        ]);
        $member = $this->joinAs('admin', 'manager@sharmatraders.in', 'Manoj Manager');

        $this->actingAs($member)->post(route('companies.switch', $exports))->assertForbidden();
        $this->apiAs($member)->postJson("/api/companies/{$exports->id}/switch")->assertForbidden();
        $this->apiAs($member)->getJson("/api/companies/{$exports->id}")->assertForbidden();
        $this->assertSame($this->biz->id, (int) $member->fresh()->active_company_id);
        $this->assertSame($this->biz->id, (int) $this->owner->fresh()->active_company_id);
    }

    // ------------------------------------------------------------- the mobile app

    public function test_the_mobile_app_lets_billing_staff_bill_and_collect_but_nothing_more(): void
    {
        $ownerInvoice = $this->webIssued($this->owner, $this->patil); // INV-0042
        $staff = $this->joinAs('staff', 'ravi.kumar@example.in', 'Ravi Kumar');

        // 3 x 500 = 1,500 @ 5% intra-state: CGST 2.5% = 37.50, SGST 37.50;
        // grand 1,575.00, round-off 0.
        $created = $this->apiAs($staff)->postJson('/api/invoices', [
            'customer_id' => $this->patil->id, 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(15)->toDateString(),
            'items' => [$this->line(500, 5, 3)],
        ])->assertCreated()->assertJsonPath('data.status', 'draft');
        $invoice = Invoice::findOrFail($created->json('data.id'));
        $this->assertSame($this->owner->id, (int) $invoice->user_id);
        $this->assertSame($this->biz->id, (int) $invoice->company_id);
        $this->assertMoney(1500, $invoice->subtotal);
        $this->assertMoney(37.50, $invoice->total_cgst);
        $this->assertMoney(37.50, $invoice->total_sgst);
        $this->assertMoney(1575, $invoice->grand_total);

        // Issued from the phone, it continues the same series as the web: INV-0043.
        $this->apiAs($staff)->postJson("/api/invoices/{$invoice->id}/finalize")
            ->assertOk()->assertJsonPath('data.invoice_number', 'INV-0043');

        // ₹575 in cash: balance 1,575 - 575 = 1,000.
        $this->apiAs($staff)->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 575, 'method' => 'cash', 'received_at' => now()->toDateString(),
        ])->assertSuccessful();
        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertMoney(1000, $invoice->balance);
        $this->assertSame('RCPT-0001', Payment::where('invoice_id', $invoice->id)->value('receipt_number'));

        $this->assertStringEndsWith('by Ravi Kumar (Billing staff)',
            AuditLog::where('action', 'invoice.finalized')->where('subject_id', $invoice->id)->value('summary'));
        $this->assertStringEndsWith('by Ravi Kumar (Billing staff)',
            AuditLog::where('action', 'payment.recorded')->latest('id')->value('summary'));

        // The list is the business's.
        $this->apiAs($staff)->getJson('/api/invoices')->assertOk()->assertJsonCount(2, 'data');

        // Not part of the role: cancel, delete, the money reports, backups,
        // adding a business while working in this one.
        $this->apiAs($staff)->postJson("/api/invoices/{$ownerInvoice->id}/cancel", ['cancellation_reason' => 'Goods came back'])->assertForbidden();
        $this->assertSame('final', $ownerInvoice->fresh()->status);
        $draft = $this->webDraft($this->owner, $this->patil);
        $this->apiAs($staff)->deleteJson("/api/invoices/{$draft->id}")->assertForbidden();
        $this->assertNotNull(Invoice::find($draft->id));
        $this->apiAs($staff)->getJson('/api/finance/pnl')->assertForbidden();
        $this->apiAs($staff)->getJson('/api/finance/gstr3b')->assertForbidden();
        $this->apiAs($staff)->getJson('/api/finance/gstr1')->assertForbidden();
        $this->apiAs($staff)->getJson('/api/expenses')->assertForbidden();
        $this->apiAs($staff)->getJson('/api/backup')->assertForbidden();
        $before = Company::count();
        $this->apiAs($staff)->postJson('/api/companies', [
            'name' => 'Side Business', 'state_id' => $this->state('27')->id, 'country' => 'India',
            'default_currency' => 'INR', 'invoice_prefix' => 'SB', 'invoice_number_padding' => 4,
        ])->assertForbidden();
        $this->assertSame($before, Company::count());
    }

    // ------------------------------------------------------------- probes

    /**
     * After "Accept and open Sharma Traders" a billing clerk is sent to the
     * dashboard, which their role does not include, so the very first thing
     * they see in the business is the red "does not include this page" error
     * and the "Invitation accepted" confirmation is lost on the extra hop.
     * Expected: they land on a page their role opens, with the welcome.
     */
    public function test_a_billing_clerk_who_accepts_lands_on_the_welcome_not_a_role_error(): void
    {
        $invite = $this->invite('clerk@sharmatraders.in', 'staff');
        $clerk = User::factory()->create(['name' => 'Sunita Clerk', 'email' => 'clerk@sharmatraders.in']);

        $page = $this->actingAs($clerk)->followingRedirects()->post(route('team.accept', $invite->token));
        $page->assertOk();
        $page->assertSee('Invitation accepted. You now have access to Sharma Traders as Billing staff.');
        $page->assertDontSee('does not include this page');
    }

    /** Same hop on "Switch company" into a business where they are billing staff. */
    public function test_a_billing_clerk_switching_into_the_business_lands_on_a_page_their_role_opens(): void
    {
        $clerk = $this->joinAs('staff', 'clerk@sharmatraders.in', 'Sunita Clerk');
        $own = $this->ownBusinessOf($clerk);
        $this->actingAs($clerk)->from(route('companies.index'))->post(route('companies.switch', $own));
        $this->assertSame((int) $own->id, (int) $clerk->fresh()->active_company_id);

        $page = $this->actingAs($clerk)->followingRedirects()->from(route('companies.index'))->post(route('companies.switch', $this->biz));
        $page->assertOk();
        $page->assertSee('Switched to Sharma Traders.');
        $page->assertDontSee('does not include this page');
    }

    /**
     * While a member works in the business, every request runs as the owner,
     * and the account menu prints Auth::user() - so a clerk's own menu reads
     * "Arbaz Sharma / arbaz@sharmatraders.in" (layouts/topbar.blade.php).
     * Expected: the menu names the person signed in.
     */
    public function test_a_members_account_menu_names_the_member_not_the_owner(): void
    {
        $clerk = $this->joinAs('staff', 'clerk@sharmatraders.in', 'Sunita Clerk');

        $page = $this->actingAs($clerk)->get(route('invoices.index'))->assertOk();
        $page->assertDontSee('arbaz@sharmatraders.in');
        $page->assertSee('clerk@sharmatraders.in');
    }

    /**
     * Billing staff may not see expenses, purchases or accounts, but the
     * activity log ("profile.activity") is treated as the member's own page
     * (Team::OWN has "profile.*") while it lists the whole business's log:
     * expense amounts and payees, purchase invoices, bank-detail changes.
     * Expected: a clerk does not read the business's expense entries there.
     */
    public function test_billing_staff_cannot_read_the_salary_and_expense_entries_in_the_activity_log(): void
    {
        $this->actingAs($this->owner)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'salaries',
            'description' => 'October salary, Anil (accounts)', 'amount' => 25000, 'gst_amount' => 0, 'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Expense::count());
        $this->assertSame(1, AuditLog::where('action', 'expense.created')->count());

        $clerk = $this->joinAs('staff', 'clerk@sharmatraders.in', 'Sunita Clerk');
        // The expense list itself is closed to them...
        $this->assertTurnedAway($this->actingAs($clerk)->get(route('finance.expenses')), 'invoices.index', self::STAFF_DENIED, 'expenses');
        // ...and so must the same figures be in the activity log.
        $this->actingAs($clerk)->get(route('profile.activity'))->assertDontSee('October salary, Anil (accounts)');
    }
}
