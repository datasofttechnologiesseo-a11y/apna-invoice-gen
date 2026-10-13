<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Account and privacy: marketing consent (an append-only log), the activity
 * log, closing an account (DPDP erasure reconciled with GST record
 * retention), the post-retention purge, and the same account actions over
 * the mobile API.
 *
 * Erasure rules under test (AccountErasureService):
 *  - no issued invoice -> everything the person owns is hard-deleted;
 *  - issued invoices   -> the GST books stay, everything else and the
 *    identity are erased; accounts:purge-erased removes the shell once the
 *    books are past the retention window.
 * Another person's data must never be touched by any of it.
 */
class AccountPrivacyJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
    }

    private function webGuard(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    /** Everything a person owns, counted table by table. */
    private function footprint(int $userId): array
    {
        return [
            'users' => User::whereKey($userId)->count(),
            'companies' => Company::where('user_id', $userId)->count(),
            'customers' => Customer::where('user_id', $userId)->count(),
            'products' => Product::where('user_id', $userId)->count(),
            'invoices' => Invoice::where('user_id', $userId)->count(),
            'invoice_items' => DB::table('invoice_items')->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->where('invoices.user_id', $userId)->count(),
            'payments' => Payment::where('user_id', $userId)->count(),
            'quotations' => Quotation::where('user_id', $userId)->count(),
            'audit_logs' => AuditLog::where('user_id', $userId)->count(),
            'consents' => UserConsent::where('user_id', $userId)->count(),
        ];
    }

    /**
     * A trading business with real history: one issued invoice (paid in part)
     * to Patil Stores, one draft to Gupta Sweets, an item and a quotation.
     *
     * Invoice: 2 x 1,250 = 2,500; 18% intra-state = CGST 225 + SGST 225;
     * grand 2,950; 1,000 received -> 1,950 owed.
     */
    private function businessWithHistory(array $company = [], array $user = []): array
    {
        $biz = $this->makeBusiness($company, $user);
        $owner = $this->ownerOf($biz);
        UserConsent::record($owner->id, 'terms', true, 'signup');
        UserConsent::record($owner->id, 'privacy', true, 'signup');
        UserConsent::record($owner->id, 'marketing', true, 'signup');

        $product = $this->productOf($biz, ['rate' => 1250]);
        $kept = $this->customerOf($biz, '27', ['name' => 'Patil Stores']);
        $draftOnly = $this->customerOf($biz, '27', ['name' => 'Gupta Sweets', 'email' => 'gupta@sweets.example']);

        $issued = $this->webIssued($owner, $kept, [$this->line(1250, 18, 2, ['product_id' => $product->id])]);
        $this->webPay($owner, $issued, 1000)->assertSessionHasNoErrors();
        $draft = $this->webDraft($owner, $draftOnly);
        $quote = Quotation::factory()->create(['user_id' => $owner->id, 'company_id' => $biz->id, 'customer_id' => $draftOnly->id]);

        return compact('biz', 'owner', 'product', 'kept', 'draftOnly', 'issued', 'draft', 'quote');
    }

    // ── Marketing consent ────────────────────────────────────────────────

    public function test_marketing_consent_can_be_withdrawn_and_given_again_and_every_decision_is_kept(): void
    {
        $biz = $this->makeBusiness();
        $owner = $this->ownerOf($biz);
        $signup = UserConsent::record($owner->id, 'marketing', true, 'signup');
        $neighbour = User::factory()->create();
        UserConsent::record($neighbour->id, 'marketing', true, 'signup');
        $this->assertTrue($owner->marketingConsentGiven());

        $page = $this->actingAs($owner)->get(route('profile.privacy'))->assertOk();
        $page->assertSee('Opted in')->assertSee('Withdraw consent');

        // Withdraw.
        $this->actingAs($owner)->from(route('profile.privacy'))
            ->post(route('profile.privacy.marketing'), ['marketing_opt_in' => '0'])
            ->assertRedirect(route('profile.privacy'))
            ->assertSessionHas('status', 'Unsubscribed from marketing emails.');

        $this->assertFalse($owner->fresh()->marketingConsentGiven());
        $this->assertSame(2, UserConsent::where('user_id', $owner->id)->count(), 'a new row, not an edit');
        $withdrawal = UserConsent::where('user_id', $owner->id)->latest('id')->firstOrFail();
        $this->assertSame('marketing', $withdrawal->consent_type);
        $this->assertFalse((bool) $withdrawal->given);
        $this->assertSame('privacy_center', $withdrawal->context);
        $this->assertSame('2026-04-24', $withdrawal->policy_version);
        $this->assertSame('127.0.0.1', $withdrawal->ip_address);
        $this->assertSame('2026-10-12 11:00:00', $withdrawal->created_at->format('Y-m-d H:i:s'));
        // The sign-up decision is still on record, unchanged.
        $original = $signup->fresh();
        $this->assertTrue((bool) $original->given);
        $this->assertSame('signup', $original->context);

        $text = $this->flatten($this->actingAs($owner)->get(route('profile.privacy'))->assertOk()->getContent());
        $this->assertStringContainsString('Opted out', $text);
        $this->assertStringContainsString('Opt in', $text);
        $this->assertStringContainsString('Withdrawn', $text);
        $this->assertStringContainsString('privacy center', $text);

        // Give it again an hour later.
        $this->travel(1)->hours();
        $this->actingAs($owner)->from(route('profile.privacy'))
            ->post(route('profile.privacy.marketing'), ['marketing_opt_in' => '1'])
            ->assertSessionHas('status', 'Subscribed to product and offer emails.');
        $this->assertTrue($owner->fresh()->marketingConsentGiven());
        $this->assertSame(
            [true, false, true],
            UserConsent::where('user_id', $owner->id)->where('consent_type', 'marketing')->orderBy('id')->pluck('given')->map(fn ($g) => (bool) $g)->all(),
            'the full history: given at sign-up, withdrawn, given again'
        );

        // The neighbour's consent is untouched throughout.
        $this->assertSame(1, UserConsent::where('user_id', $neighbour->id)->count());
        $this->assertTrue($neighbour->fresh()->marketingConsentGiven());
    }

    public function test_a_marketing_choice_must_be_yes_or_no_and_needs_a_signed_in_user(): void
    {
        $biz = $this->makeBusiness();
        $owner = $this->ownerOf($biz);

        $this->actingAs($owner)->post(route('profile.privacy.marketing'), [])->assertSessionHasErrors('marketing_opt_in');
        $this->actingAs($owner)->post(route('profile.privacy.marketing'), ['marketing_opt_in' => 'maybe'])->assertSessionHasErrors('marketing_opt_in');
        $this->assertSame(0, UserConsent::count());
        $this->assertFalse($owner->fresh()->marketingConsentGiven(), 'no record means no consent');

        $this->webGuard();
        $this->app['auth']->guard('web')->logout();
        $this->post(route('profile.privacy.marketing'), ['marketing_opt_in' => '1'])->assertRedirect(route('login'));
        $this->get(route('profile.privacy'))->assertRedirect(route('login'));
        $this->assertSame(0, UserConsent::count());
    }

    // ── Activity log ─────────────────────────────────────────────────────

    public function test_the_activity_log_shows_this_business_and_never_another(): void
    {
        $a = $this->businessWithHistory([], ['name' => 'Rohan Sharma']);
        $b = $this->businessWithHistory(
            ['name' => 'Kulkarni Hardware', 'gstin' => '27PQRST5678K1Z2', 'invoice_prefix' => 'OTH'],
            ['name' => 'Vikram Kulkarni']
        );

        $text = $this->flatten($this->actingAs($a['owner'])->get(route('profile.activity'))->assertOk()->getContent());
        // Issue: "Invoice INV-0001 finalized · ₹2,950"; receipt: "₹1,000 · UPI · Receipt RCPT-0001 · Invoice INV-0001".
        $this->assertStringContainsString('Invoice INV-0001 finalized · ₹2,950', $text);
        $this->assertStringContainsString('₹1,000 · UPI · Receipt RCPT-0001 · Invoice INV-0001', $text);
        $this->assertStringContainsString('Rohan Sharma', $text);
        $this->assertStringNotContainsString('OTH-0001', $text);
        $this->assertStringNotContainsString('Vikram Kulkarni', $text);

        // Two entries each, stored against the right business.
        $this->assertSame(2, AuditLog::where('company_id', $a['biz']->id)->count());
        $this->assertSame(2, AuditLog::where('company_id', $b['biz']->id)->count());

        $this->webGuard();
        $this->app['auth']->guard('web')->logout();
        $this->get(route('profile.activity'))->assertRedirect(route('login'));
    }

    public function test_billing_staff_do_not_see_salary_entries_in_the_activity_log(): void
    {
        // Billing staff "cannot see purchases, accounts or GST" (CompanyMember
        // ROLES). The activity log is a profile.* page, which a member opens
        // as themselves - but it lists the whole business's audit trail,
        // including expense amounts and payee names.
        $biz = $this->makeBusiness(['name' => 'Sharma Traders']);
        $owner = $this->ownerOf($biz);
        $this->actingAs($owner)->post(route('finance.expenses.store'), [
            'entry_date' => '2026-10-12',
            'category' => 'salaries',
            'vendor_name' => 'Priya Nair',
            'description' => 'October salary',
            'amount' => 45000,
            'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, AuditLog::where('company_id', $biz->id)->where('action', 'expense.created')->count());

        $staff = User::factory()->create(['name' => 'Counter Staff']);
        CompanyMember::create([
            'company_id' => $biz->id, 'user_id' => $staff->id, 'email' => $staff->email, 'role' => 'staff',
            'token' => CompanyMember::newToken(), 'invited_by' => $owner->id, 'accepted_at' => now(),
        ]);
        $staff->forceFill(['active_company_id' => $biz->id])->save();

        // The staff role is refused the expenses screen itself...
        $this->actingAs($staff)->get(route('finance.expenses'))->assertRedirect(route('invoices.index'));

        // ...so the activity log must not hand them the same figures.
        $html = (string) $this->actingAs($staff)->get(route('profile.activity'))->getContent();
        $this->assertFalse(str_contains($html, 'Priya Nair'),
            "billing staff can read the business's salary payment (payee 'Priya Nair', Rs 45,000) in the activity log");
        $this->assertFalse(str_contains($html, '45,000'), 'the salary amount is shown to billing staff');

        // The app feed is the same page for the phone.
        $feed = json_encode($this->apiAs($staff)->getJson('/api/profile/activity')->json());
        $this->assertFalse(str_contains((string) $feed, 'expense.created'), 'the app feed lists the salary entry to billing staff');
    }

    // ── Closing the account (web) ────────────────────────────────────────

    public function test_a_wrong_password_does_not_close_the_account(): void
    {
        $a = $this->businessWithHistory();
        $before = $this->footprint($a['owner']->id);

        $this->actingAs($a['owner'])->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'not-my-password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertAuthenticatedAs($a['owner']);
        $this->assertNull($a['owner']->fresh()->erased_at);
        $this->assertSame($before, $this->footprint($a['owner']->id));
    }

    public function test_closing_an_account_with_issued_invoices_keeps_the_gst_books_until_the_purge(): void
    {
        $a = $this->businessWithHistory(['name' => 'Sharma Traders'], [
            'name' => 'Rohan Sharma', 'email' => 'rohan@sharmatraders.example', 'phone' => '+919876543210',
        ]);
        $b = $this->businessWithHistory(
            ['name' => 'Kulkarni Hardware', 'gstin' => '27PQRST5678K1Z2', 'invoice_prefix' => 'OTH'],
            ['name' => 'Vikram Kulkarni']
        );
        $owner = $a['owner'];

        $expectedBefore = [
            'users' => 1, 'companies' => 1, 'customers' => 2, 'products' => 1, 'invoices' => 2,
            'invoice_items' => 2, 'payments' => 1, 'quotations' => 1, 'audit_logs' => 2, 'consents' => 3,
        ];
        $this->assertSame($expectedBefore, $this->footprint($owner->id));
        $this->assertSame($expectedBefore, $this->footprint($b['owner']->id));
        $bBefore = $this->footprint($b['owner']->id);

        // ── Close the account ─────────────────────────────────────────────
        $this->actingAs($owner)->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/')
            ->assertSessionHas('status', 'Account closed and personal data erased. GST invoice records are kept only as long as tax law requires, then deleted automatically.');
        $this->assertGuest();

        $shell = User::findOrFail($owner->id);
        $this->assertSame('Erased account', $shell->name);
        $this->assertSame('erased+' . $owner->id . '@erased.invalid', $shell->email);
        $this->assertSame('2026-10-12 11:00:00', $shell->erased_at->format('Y-m-d H:i:s'));
        $this->assertNull($shell->email_verified_at);
        $this->assertNull($shell->referral_code);
        $this->assertFalse(Hash::check('password', $shell->password), 'the old password no longer works');

        // Only the GST books survive: the issued invoice (with its line and the
        // receipt) and the customer printed on it. The draft, the quotation,
        // the item, the draft-only customer and the activity log are gone.
        $this->assertSame([
            'users' => 1, 'companies' => 1, 'customers' => 1, 'products' => 0, 'invoices' => 1,
            'invoice_items' => 1, 'payments' => 1, 'quotations' => 0, 'audit_logs' => 0, 'consents' => 5,
        ], $this->footprint($owner->id));
        $kept = Invoice::findOrFail($a['issued']->id);
        $this->assertSame('INV-0001', $kept->invoice_number);
        $this->assertMoney(2950, $kept->grand_total);
        $this->assertMoney(1950, $kept->balance);
        $this->assertNull($kept->items()->firstOrFail()->product_id, 'the deleted item is unlinked, the line is kept');
        $this->assertSame('Steel almirah', $kept->items()->firstOrFail()->description);
        $this->assertNull(Invoice::find($a['draft']->id));
        $this->assertNull(Customer::find($a['draftOnly']->id));
        $this->assertNotNull(Customer::find($a['kept']->id));

        // The erasure itself is on the consent log.
        $this->assertSame(1, UserConsent::where('user_id', $owner->id)->where('consent_type', 'account_erasure')->where('given', true)->where('context', 'erasure')->count());
        $this->assertFalse($shell->marketingConsentGiven(), 'marketing consent is withdrawn on erasure');

        // Nobody can sign in to it again.
        $this->post('/login', ['email' => 'rohan@sharmatraders.example', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // The other business is untouched.
        $this->assertSame($bBefore, $this->footprint($b['owner']->id));

        // ── Purge: nothing yet, the books are inside the retention window ──
        $this->artisan('accounts:purge-erased')
            ->expectsOutput('Found 0 erased account(s) past retention.')
            ->assertExitCode(0);
        $this->assertNotNull(User::find($owner->id));

        // ── Long after any reading of the window (2 Jan 2034) ─────────────
        $this->travelTo(\Illuminate\Support\Carbon::parse('2034-01-02 10:00:00', 'Asia/Kolkata'));
        $this->artisan('accounts:purge-erased', ['--dry' => true])
            ->expectsOutput('Found 1 erased account(s) past retention.')
            ->expectsOutput("  [dry] user #{$owner->id} (erased 2026-10-12)")
            ->assertExitCode(0);
        $this->assertNotNull(User::find($owner->id), 'a dry run deletes nothing');

        $this->artisan('accounts:purge-erased')
            ->expectsOutput('Found 1 erased account(s) past retention.')
            ->assertExitCode(0);
        $this->assertSame(array_fill_keys(array_keys($expectedBefore), 0), $this->footprint($owner->id));
        $this->assertNull(Invoice::find($a['issued']->id));
        $this->assertNull(Company::find($a['biz']->id));

        // ...and the other business still has every row.
        $this->assertSame($bBefore, $this->footprint($b['owner']->id));
        $this->assertNotNull(Invoice::find($b['issued']->id));
    }

    public function test_closing_an_account_with_no_issued_invoice_deletes_everything_it_owns(): void
    {
        $biz = $this->makeBusiness();
        $owner = $this->ownerOf($biz);
        UserConsent::record($owner->id, 'terms', true, 'signup');
        $customer = $this->customerOf($biz);
        $this->productOf($biz);
        $this->webDraft($owner, $customer);
        Quotation::factory()->create(['user_id' => $owner->id, 'company_id' => $biz->id, 'customer_id' => $customer->id]);

        $other = $this->businessWithHistory(['name' => 'Kulkarni Hardware', 'gstin' => '27PQRST5678K1Z2', 'invoice_prefix' => 'OTH']);
        $otherBefore = $this->footprint($other['owner']->id);

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/')
            ->assertSessionHas('status', 'Account and all personal data permanently deleted.');
        $this->assertGuest();

        $this->assertSame([
            'users' => 0, 'companies' => 0, 'customers' => 0, 'products' => 0, 'invoices' => 0,
            'invoice_items' => 0, 'payments' => 0, 'quotations' => 0, 'audit_logs' => 0, 'consents' => 0,
        ], $this->footprint($owner->id));
        $this->assertSame($otherBefore, $this->footprint($other['owner']->id));
    }

    public function test_closing_an_account_erases_the_mobile_number_too(): void
    {
        // DPDP s.12: on erasure only what another law requires may be kept.
        // GST law needs the invoices (which print the business's details),
        // not the account holder's personal mobile number.
        $a = $this->businessWithHistory([], ['phone' => '+919876543210']);
        $owner = $a['owner'];
        $owner->forceFill(['phone_verified_at' => now()])->save();

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        $shell = User::findOrFail($owner->id);
        $this->assertNotNull($shell->erased_at);
        $this->assertNull($shell->phone, 'the personal mobile number survives erasure');
        $this->assertNull($shell->phone_verified_at);
    }

    public function test_erased_gst_books_are_kept_for_72_months_from_the_annual_return_due_date(): void
    {
        // CGST Act s.36: records are kept until 72 months from the due date of
        // the annual return for the year they belong to (config/legal.php says
        // so too). The invoice is dated 12 Oct 2026 -> FY 2026-27 -> annual
        // return (GSTR-9) due 31 Dec 2027 -> keep until 31 Dec 2033.
        // 13 Oct 2032 is 72 months after the invoice but 14 months early.
        $a = $this->businessWithHistory();
        $this->actingAs($a['owner'])->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        $this->travelTo(\Illuminate\Support\Carbon::parse('2032-10-13 10:00:00', 'Asia/Kolkata'));
        $this->artisan('accounts:purge-erased')->assertExitCode(0);

        $this->assertNotNull(Invoice::find($a['issued']->id),
            'the purge deleted FY 2026-27 GST records on 13 Oct 2032, before the s.36 window ends on 31 Dec 2033');
    }

    public function test_closing_the_account_on_the_web_also_signs_out_the_mobile_app(): void
    {
        // AuthenticatedSessionController: "A DPDP-erased account must never be
        // usable again". The app's token was issued before the erasure.
        $a = $this->businessWithHistory([], ['email' => 'rohan@sharmatraders.example']);
        $token = $this->postJson('/api/auth/login', [
            'email' => 'rohan@sharmatraders.example', 'password' => 'password', 'device_name' => 'phone',
        ])->assertOk()->json('token');
        $this->webGuard();

        $this->actingAs($a['owner'])->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertNotNull($a['owner']->fresh()->erased_at);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    // ── Account over the mobile API ──────────────────────────────────────

    public function test_profile_can_be_updated_over_the_api_but_not_to_someone_elses_email(): void
    {
        $biz = $this->makeBusiness([], ['name' => 'Rohan Sharma', 'email' => 'rohan@sharmatraders.example']);
        $owner = $this->ownerOf($biz);
        $taken = User::factory()->create(['email' => 'vikram@kulkarni.example']);

        $this->apiAs($owner)->putJson('/api/profile', ['name' => 'Rohan S. Sharma', 'email' => 'vikram@kulkarni.example'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->apiAs($owner)->putJson('/api/profile', ['email' => 'rohan@sharmatraders.example'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertSame('Rohan Sharma', $owner->fresh()->name);

        // Same email: still verified.
        $this->apiAs($owner)->putJson('/api/profile', ['name' => 'Rohan S. Sharma', 'email' => 'rohan@sharmatraders.example'])
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('user.name', 'Rohan S. Sharma')
            ->assertJsonPath('user.email_verified', true);

        // New email: must be verified again.
        $this->apiAs($owner)->putJson('/api/profile', ['name' => 'Rohan S. Sharma', 'email' => 'accounts@sharmatraders.example'])
            ->assertOk()
            ->assertJsonPath('user.email', 'accounts@sharmatraders.example')
            ->assertJsonPath('user.email_verified', false);
        $fresh = $owner->fresh();
        $this->assertSame('accounts@sharmatraders.example', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        $this->assertSame('vikram@kulkarni.example', $taken->fresh()->email, 'the other account is untouched');
    }

    public function test_changing_the_password_over_the_api_needs_the_current_one(): void
    {
        $biz = $this->makeBusiness([], ['email' => 'rohan@sharmatraders.example']);
        $owner = $this->ownerOf($biz);

        $this->apiAs($owner)->putJson('/api/profile/password', [
            'current_password' => 'guess', 'password' => 'NewLedger#2027', 'password_confirmation' => 'NewLedger#2027',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->apiAs($owner)->putJson('/api/profile/password', [
            'current_password' => 'password', 'password' => 'NewLedger#2027', 'password_confirmation' => 'Different#2027',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('password', $owner->fresh()->password), 'unchanged after the refusals');

        $this->apiAs($owner)->putJson('/api/profile/password', [
            'current_password' => 'password', 'password' => 'NewLedger#2027', 'password_confirmation' => 'NewLedger#2027',
        ])->assertOk()->assertJsonPath('message', 'Password updated.');
        $this->assertTrue(Hash::check('NewLedger#2027', $owner->fresh()->password));
        $this->webGuard();

        $this->postJson('/api/auth/login', ['email' => 'rohan@sharmatraders.example', 'password' => 'password'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'rohan@sharmatraders.example', 'password' => 'NewLedger#2027'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_the_app_activity_feed_lists_only_this_business(): void
    {
        $a = $this->businessWithHistory();
        $b = $this->businessWithHistory(['name' => 'Kulkarni Hardware', 'gstin' => '27PQRST5678K1Z2', 'invoice_prefix' => 'OTH']);

        $res = $this->apiAs($a['owner'])->getJson('/api/profile/activity')->assertOk();
        $res->assertJsonPath('meta.total', 2);
        $this->assertEqualsCanonicalizing(['invoice.finalized', 'payment.recorded'], array_column($res->json('data'), 'action'));
        $ids = AuditLog::where('company_id', $a['biz']->id)->pluck('id')->map(fn ($i) => (int) $i)->all();
        foreach ($res->json('data') as $row) {
            $this->assertContains((int) $row['id'], $ids, 'an entry from another business leaked into the feed');
        }
        $this->assertStringNotContainsString('OTH-0001', json_encode($res->json()));

        $this->webGuard();
        $this->getJson('/api/profile/activity')->assertUnauthorized();
    }

    public function test_the_app_activity_feed_carries_each_entrys_description(): void
    {
        // The audit row stores its text in `summary`; the web page prints it.
        // The app feed must send the same words, not an empty field.
        $a = $this->businessWithHistory();

        $rows = collect($this->apiAs($a['owner'])->getJson('/api/profile/activity')->assertOk()->json('data'))->keyBy('action');
        $this->assertSame('Invoice INV-0001 finalized · ₹2,950', $rows['invoice.finalized']['description'] ?? null,
            'the app shows a blank line for every activity entry');
        $this->assertSame('₹1,000 · UPI · Receipt RCPT-0001 · Invoice INV-0001', $rows['payment.recorded']['description'] ?? null);
    }

    public function test_logging_out_of_the_app_revokes_only_that_device(): void
    {
        $biz = $this->makeBusiness([], ['email' => 'rohan@sharmatraders.example']);
        $owner = $this->ownerOf($biz);
        $creds = ['email' => 'rohan@sharmatraders.example', 'password' => 'password'];
        $phone = $this->postJson('/api/auth/login', $creds + ['device_name' => 'phone'])->assertOk()->json('token');
        $tablet = $this->postJson('/api/auth/login', $creds + ['device_name' => 'tablet'])->assertOk()->json('token');
        $this->assertSame(2, $owner->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'rohan@sharmatraders.example');

        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->postJson('/api/auth/logout')->assertOk()->assertJsonPath('message', 'Logged out.');
        $this->assertSame(['tablet'], $owner->tokens()->pluck('name')->all());

        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->getJson('/api/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($tablet)->getJson('/api/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken('1|not-a-real-token')->postJson('/api/auth/logout')->assertUnauthorized();
        $this->assertSame(1, $owner->tokens()->count());
    }

    public function test_deleting_an_account_over_the_api_removes_only_that_account(): void
    {
        // A mobile sign-up: account + starter business, nothing billed yet.
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Asha Patel', 'phone' => '9812345678', 'email' => 'asha@patelcrafts.example',
            'password' => 'Crafts#2026x', 'password_confirmation' => 'Crafts#2026x', 'device_name' => 'phone',
        ])->assertCreated()->json('token');
        $asha = User::where('email', 'asha@patelcrafts.example')->firstOrFail();
        $this->assertSame(1, Company::where('user_id', $asha->id)->count());

        $other = $this->businessWithHistory(['name' => 'Kulkarni Hardware', 'gstin' => '27PQRST5678K1Z2', 'invoice_prefix' => 'OTH']);
        $otherBefore = $this->footprint($other['owner']->id);
        $this->webGuard();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->deleteJson('/api/profile', ['password' => 'wrong'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertNotNull(User::find($asha->id));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->deleteJson('/api/profile', ['password' => 'Crafts#2026x'])
            ->assertOk()->assertJsonPath('message', 'Account deleted.');
        $this->assertNull(User::find($asha->id));
        $this->assertSame(0, Company::where('user_id', $asha->id)->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $asha->id)->where('tokenable_type', User::class)->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->assertSame($otherBefore, $this->footprint($other['owner']->id));
    }

    public function test_deleting_an_account_with_issued_invoices_over_the_api_keeps_the_gst_books(): void
    {
        // The web keeps issued invoices and erases the person (DPDP s.8(7) +
        // CGST s.36). The app's DELETE /api/profile must end the same way,
        // not fail half-way. (Api\ProfileController::destroy deletes the
        // tokens, then $user->delete() hits the restrictOnDelete FK on
        // invoices.user_id: 500, account intact, app signed out.)
        $a = $this->businessWithHistory([], ['email' => 'rohan@sharmatraders.example']);
        $owner = $a['owner'];
        $token = $this->postJson('/api/auth/login', [
            'email' => 'rohan@sharmatraders.example', 'password' => 'password', 'device_name' => 'phone',
        ])->assertOk()->json('token');

        $this->app['auth']->forgetGuards();
        $res = $this->withToken($token)->deleteJson('/api/profile', ['password' => 'password']);

        $this->assertNotNull(Invoice::find($a['issued']->id), 'the issued GST invoice must be retained');
        $this->assertSame(200, $res->getStatusCode(), 'DELETE /api/profile failed for an account with issued invoices');
        $shell = User::find($owner->id);
        $this->assertNotNull($shell, 'the account row stays while its GST books are retained');
        $this->assertNotNull($shell->erased_at, 'the person must be erased, as on the web');
        $this->assertNotSame('rohan@sharmatraders.example', $shell->email);
        $this->assertSame(0, $owner->tokens()->count());
    }
}
