<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Mail\OnboardingEmail;
use App\Mail\OtpEmail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A brand-new business, from the sign-up form to the first rupee collected:
 * register -> OTP -> guided setup (business, bank, UPI, logo) -> first
 * customer -> first item -> first invoice -> issue -> share link a customer
 * opens without logging in -> payment -> dashboard -> the guided tour.
 *
 * Every step asserts what the database holds, where the user is sent, what
 * the next screen says and which emails went out. Money is worked out by hand.
 */
class NewBusinessJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private const EMAIL = 'rohan@sharmatraders.example';
    private const PASSWORD = 'Ledger#2026-safe';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        Mail::fake();
        Storage::fake('public');
    }

    private function signupForm(array $over = []): array
    {
        return array_merge([
            'name' => 'Rohan Sharma',
            'phone' => '9876543210',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms_accepted' => '1',
            'marketing_opt_in' => '1',
        ], $over);
    }

    /** POST /register and return the code the dev channel exposes. */
    private function startSignup(array $over = []): string
    {
        $res = $this->post('/register', $this->signupForm($over));
        $res->assertRedirect(route('register.verify'));
        $code = (string) $res->getSession()->get('otp_dev_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code, 'a 6-digit code is issued');

        return $code;
    }

    private function wrongCodeFor(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    /** The onboarding business form exactly as a GST-registered owner fills it. */
    private function businessForm(array $over = []): array
    {
        return array_merge([
            'name' => 'Sharma Traders',
            // Typed in lower case: the business keeps the canonical upper case.
            'gstin' => '27abcde1234f1z0',
            'pan' => 'ABCDE1234F',
            'address_line1' => '12 MG Road',
            'address_line2' => 'Camp',
            'city' => 'Pune',
            'state_id' => $this->state('27')->id,
            'postal_code' => '411001',
            'country' => 'India',
            'phone' => '9876543210',
            'email' => 'billing@sharmatraders.example',
            'default_currency' => 'INR',
            'invoice_prefix' => '',
            'bank_name' => 'State Bank of India',
            'bank_account_name' => 'Sharma Traders',
            'bank_account_number' => '30123456789',
            'bank_account_type' => 'current',
            // Lower case with stray spaces, as pasted from a cheque leaf.
            'bank_ifsc' => ' sbin0001234 ',
            'upi_id' => ' sharmatraders@okicici ',
        ], $over);
    }

    public function test_a_new_business_signs_up_sets_up_issues_shares_and_collects_its_first_invoice(): void
    {
        // ── 1. Sign-up form ────────────────────────────────────────────────
        $this->get(route('register'))->assertOk()->assertSee('name="phone"', false);

        $res = $this->post('/register', $this->signupForm());
        $res->assertRedirect(route('register.verify'));
        $res->assertSessionHas('status', "We've emailed you a 6-digit verification code.");
        $code = (string) $res->getSession()->get('otp_dev_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // No account exists until the code is proved.
        $this->assertGuest();
        $this->assertSame(0, User::count());
        $pending = session('pending_registration');
        $this->assertSame(0, $pending['attempts']);
        $this->assertSame('+919876543210', $pending['phone'], 'the mobile is normalised to +91');
        $this->assertSame('email', $pending['channel'], 'with no SMS gateway the code goes by email');

        // The code went to the email address typed on the form, once.
        Mail::assertSent(OtpEmail::class, 1);
        Mail::assertSent(OtpEmail::class, fn (OtpEmail $m) => $m->code === $code && $m->hasTo(self::EMAIL));

        // ── 2. OTP screen ─────────────────────────────────────────────────
        $this->get(route('register.verify'))->assertOk()
            ->assertSee(self::EMAIL)
            ->assertSee($code);

        // A wrong guess costs one of the five attempts and creates nothing.
        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $this->wrongCodeFor($code)])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHasErrors(['code' => 'Incorrect code. 4 attempt(s) left.']);
        $this->assertSame(1, session('pending_registration')['attempts']);
        $this->assertGuest();
        $this->assertSame(0, User::count());

        // Asking for a new code straight away is refused (30 s cooldown; the
        // clock is frozen, so exactly 30 s remain).
        $this->from(route('register.verify'))
            ->post(route('register.resend'))
            ->assertRedirect(route('register.verify'))
            ->assertSessionHasErrors(['code' => 'Wait 30 seconds before requesting a new code.']);
        Mail::assertSent(OtpEmail::class, 1);

        // The right code creates the account and lands in guided setup.
        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $code])
            ->assertRedirect(route('onboarding.index'));

        $this->assertAuthenticated();
        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Rohan Sharma', $user->name);
        $this->assertSame('+919876543210', $user->phone);
        $this->assertNotNull($user->email_verified_at, 'the emailed code proves the email');
        $this->assertNull($user->phone_verified_at, 'no SMS was sent, so the phone is not claimed as verified');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password), 'the password is stored hashed');
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertNull(session('pending_registration'), 'the pending sign-up (with its password) is discarded');

        // Consents recorded at sign-up, with the policy version in force.
        foreach (['terms', 'privacy', 'marketing'] as $type) {
            $row = UserConsent::where('user_id', $user->id)->where('consent_type', $type)->latest('id')->firstOrFail();
            $this->assertTrue((bool) $row->given, "{$type} consent given at sign-up");
            $this->assertSame('signup', $row->context);
            $this->assertSame('2026-04-24', $row->policy_version);
        }
        $this->assertTrue($user->marketingConsentGiven());

        // Welcome email (stage "welcome"), recorded so it never goes twice.
        Mail::assertSent(OnboardingEmail::class, fn (OnboardingEmail $m) => $m->stage === 'welcome' && $m->hasTo(self::EMAIL));
        $this->assertSame(1, DB::table('onboarding_emails')->where('user_id', $user->id)->where('stage', 'welcome')->count());

        // ── 3. Guided setup: business ─────────────────────────────────────
        $this->actingAs($user)->get(route('onboarding.index'))->assertRedirect(route('onboarding.business'));
        $company = Company::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, Company::where('user_id', $user->id)->count(), 'exactly one starter business');
        $this->assertSame((int) $company->id, (int) $user->fresh()->active_company_id);
        $this->assertSame("Rohan Sharma's Company", $company->name);
        $this->assertFalse($company->isBusinessComplete(), 'no state yet');

        $this->actingAs($user)->get(route('onboarding.business'))->assertOk()->assertSee('Maharashtra');

        $logo = UploadedFile::fake()->image('logo.png', 400, 200);
        $this->actingAs($user)
            ->post(route('onboarding.business.save'), $this->businessForm(['logo' => $logo]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('onboarding.customer'))
            ->assertSessionHas('status', 'Business details saved.');

        $company->refresh();
        $this->assertSame('Sharma Traders', $company->name);
        $this->assertSame('27ABCDE1234F1Z0', $company->gstin);
        $this->assertSame('ABCDE1234F', $company->pan);
        $this->assertSame((int) $this->state('27')->id, (int) $company->state_id);
        $this->assertSame('12 MG Road', $company->address_line1);
        $this->assertSame('SBIN0001234', $company->bank_ifsc, 'IFSC trimmed and upper-cased');
        $this->assertSame('sharmatraders@okicici', $company->upi_id, 'UPI ID trimmed');
        $this->assertSame('30123456789', $company->bank_account_number);
        $this->assertSame('Current', $company->bankAccountTypeLabel());
        $this->assertSame('INV', $company->invoice_prefix, 'blank prefix falls back to INV');
        $this->assertSame('INV/{FY}/{N}', $company->invoice_number_format, 'new businesses get FY-reset numbering');
        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
        $this->assertNull($company->onboarded_at, 'not onboarded until the last step');

        // ── 4. Guided setup: first customer ───────────────────────────────
        $this->actingAs($user)->get(route('onboarding.customer'))->assertOk();
        $this->actingAs($user)->post(route('onboarding.customer.save'), [
            'name' => 'Patil Stores',
            'gstin' => '27AAACT2727Q1ZW',
            'address_line1' => '4 Shivaji Nagar',
            'city' => 'Pune',
            'state_id' => $this->state('27')->id,
            'postal_code' => '411005',
            'country' => 'India',
            'phone' => '9876500001',
            'email' => 'accounts@patilstores.example',
        ])->assertSessionHasNoErrors()->assertRedirect(route('onboarding.done'));

        $customer = Customer::where('company_id', $company->id)->firstOrFail();
        $this->assertSame('Patil Stores', $customer->name);
        $this->assertSame((int) $user->id, (int) $customer->user_id);
        $this->assertSame(1, Customer::count());

        // ── 5. Done ───────────────────────────────────────────────────────
        $this->actingAs($user)->get(route('onboarding.done'))->assertOk()
            ->assertSee('Setup complete')
            ->assertSee('Sharma Traders')
            ->assertSee('1 customer added')
            ->assertSee('Create your first invoice');
        $this->assertSame('2026-10-12 11:00:00', $company->fresh()->onboarded_at->format('Y-m-d H:i:s'));
        $this->actingAs($user)->get(route('onboarding.index'))->assertRedirect(route('onboarding.done'));

        // First dashboard: one setup step left, nothing billed yet, and the tour.
        $first = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $firstText = $this->flatten($first->getContent());
        $this->assertStringContainsString('One step left before your first invoice', $firstText);
        $this->assertStringContainsString('67%', $firstText, '2 of 3 setup steps = 66.7% -> 67%');
        $this->assertStringContainsString('After your first invoice, this page shows', $firstText);
        $first->assertSee('appTour(', false);

        // ── 6. First item ─────────────────────────────────────────────────
        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Steel almirah',
            'kind' => 'goods',
            'hsn_sac' => '94031010',
            'unit' => 'NOS',
            'rate' => 12500,
            'gst_rate' => 18,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('status', "Item 'Steel almirah' added.");
        $product = Product::where('company_id', $company->id)->firstOrFail();
        $this->assertMoney(12500, $product->rate);
        $this->assertMoney(18, $product->gst_rate);

        // ── 7. First invoice (draft) using the item ───────────────────────
        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-10-12',
            'due_date' => '2026-10-27',
            'items' => [[
                'product_id' => $product->id,
                'description' => '',  // the form leaves it blank: filled from the item
                'hsn_sac' => '94031010',
                'quantity' => 2,
                'unit' => 'NOS',
                'rate' => 12500,
                'gst_rate' => 18,
            ]],
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Draft invoice created.');

        $invoice = Invoice::where('company_id', $company->id)->latest('id')->firstOrFail();
        // 2 x 12,500 = 25,000 taxable. Pune -> Pune is intra-state, 18% =
        // CGST 9% 2,250 + SGST 9% 2,250 = 4,500 tax. Grand 29,500, no round-off.
        $this->assertSame('draft', $invoice->status);
        $this->assertNull($invoice->invoice_number, 'a draft carries no statutory number');
        $this->assertFalse((bool) $invoice->is_interstate);
        $this->assertMoney(25000, $invoice->subtotal);
        $this->assertMoney(2250, $invoice->total_cgst);
        $this->assertMoney(2250, $invoice->total_sgst);
        $this->assertMoney(0, $invoice->total_igst);
        $this->assertMoney(4500, $invoice->total_tax);
        $this->assertMoney(0, $invoice->round_off);
        $this->assertMoney(29500, $invoice->grand_total);
        $this->assertMoney(29500, $invoice->balance);
        $item = $invoice->items()->firstOrFail();
        $this->assertSame('Steel almirah', $item->description, 'blank description filled from the item');
        $this->assertSame((int) $product->id, (int) $item->product_id);

        // ── 8. Issue: number per the onboarding format ────────────────────
        // Format INV/{FY}/{N}, 12 Oct 2026 is in FY 2026-27, first number,
        // padding 4 -> "INV/2026-27/0001" (16 characters: the Rule 46(b) max).
        $this->actingAs($user)->post(route('invoices.finalize', $invoice))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('status', 'Invoice INV/2026-27/0001 issued.');
        $invoice->refresh();
        $this->assertSame('INV/2026-27/0001', $invoice->invoice_number);
        $this->assertSame(16, strlen($invoice->invoice_number));
        $this->assertSame('final', $invoice->status);
        $this->assertNotNull($invoice->finalized_at);
        $this->assertSame('Patil Stores', $invoice->customer_snapshot['name'] ?? null, 'recipient frozen at issue');
        $company->refresh();
        $this->assertSame(1, (int) $company->invoice_counter);
        $this->assertSame(2026, (int) $company->invoice_counter_fy);
        $this->assertSame(1, AuditLog::where('company_id', $company->id)->where('action', 'invoice.finalized')->count());

        $this->actingAs($user)->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee('INV/2026-27/0001')
            ->assertSee('29,500');

        // ── 9. Share link, opened by the customer without logging in ──────
        $link = $this->actingAs($user)->getJson(route('invoices.share.link', $invoice))->assertOk();
        $this->assertSame(30, (int) $link->json('expires_in_days'));
        $url = (string) $link->json('url');
        $this->assertStringContainsString('signature=', $url);

        $this->actingAs($user)->post(route('logout'));
        $this->assertGuest();

        $public = $this->get($url)->assertOk();
        $publicText = $this->flatten($public->getContent());
        $this->assertStringContainsString('Sharma Traders', $publicText);
        $this->assertStringContainsString('INV/2026-27/0001', $publicText);
        $this->assertStringContainsString('Billed to Patil Stores', $publicText);
        $this->assertStringContainsString('Amount due ₹29,500', $publicText);
        $this->assertStringContainsString('Pay ₹29,500 by UPI', $publicText);
        $public->assertSee('pa=sharmatraders%40okicici', false);
        $public->assertSee('am=29500.00', false);
        $this->assertGuest();

        // A tampered signature, the same signature on another business's
        // invoice, or no signature at all is refused.
        $tampered = preg_replace_callback('/signature=([0-9a-f])/', fn ($m) => 'signature=' . ($m[1] === 'a' ? 'b' : 'a'), $url);
        $this->assertNotSame($url, $tampered);
        $this->get($tampered)->assertForbidden();
        $otherBiz = $this->makeBusiness(['name' => 'Kulkarni Hardware', 'gstin' => '27PQRST5678K1Z2']);
        $otherInvoice = $this->webIssued($this->ownerOf($otherBiz), $this->customerOf($otherBiz));
        $this->post(route('logout'));
        $swapped = $this->get(str_replace('/i/' . $invoice->id . '?', '/i/' . $otherInvoice->id . '?', $url));
        $swapped->assertForbidden();
        $this->assertStringNotContainsString('Kulkarni Hardware', (string) $swapped->getContent());
        $this->get('/i/' . $invoice->id)->assertForbidden();
        $this->assertGuest();

        // ── 10. Record a payment ──────────────────────────────────────────
        $this->actingAs($user)->from(route('invoices.show', $invoice))
            ->post(route('invoices.payments', $invoice), [
                'amount' => 10000,
                'method' => 'upi',
                'received_at' => '2026-10-12',
                'reference_number' => 'UPI-628301',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('status', 'Payment recorded. Receipt RCPT-0001 issued.');

        // 29,500 - 10,000 = 19,500 still owed.
        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertMoney(10000, $invoice->paid_amount);
        $this->assertMoney(19500, $invoice->balance);
        $payment = Payment::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->assertSame('RCPT-0001', $payment->receipt_number);
        $this->assertSame('2026-10-12', $payment->received_at->toDateString());
        $this->assertMoney(10000, $payment->amount);

        // The same share link now shows what is still owed.
        $again = $this->flatten($this->get($url)->assertOk()->getContent());
        $this->assertStringContainsString('Amount due ₹19,500', $again);
        $this->assertStringContainsString('Pay ₹19,500 by UPI', $again);

        // ── 11. Dashboard shows the figures ───────────────────────────────
        $dash = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $text = $this->flatten($dash->getContent());
        $this->assertStringContainsString('Good morning, Rohan ji', $text, '11:00 IST is morning');
        $this->assertStringContainsString('1 invoice issued today', $text);
        $this->assertStringContainsString('₹10,000 received today', $text);
        // Sales: 29,500 billed in October, 10,000 of it collected:
        // 10,000 / 29,500 = 33.9% -> 34%.
        $this->assertStringContainsString('Sales this month ₹29,500', $text);
        $this->assertStringContainsString('34% of it collected', $text);
        $this->assertStringContainsString('Received this month ₹10,000 1 payment in October', $text);
        // Due 27 Oct, today is 12 Oct: owed but not overdue.
        $this->assertStringContainsString('Outstanding receivables ₹19,500 Nothing overdue', $text);
        $this->assertStringContainsString('Payables ₹0 Nothing to pay', $text);
        $this->assertStringNotContainsString('Getting started', $text, 'all three setup steps are done');
        $dash->assertSee('INV/2026-27/0001');

        // ── 12. The guided tour shows until it is completed, not after ────
        $dash->assertSee('appTour(', false);
        $this->actingAs($user)->get(route('invoices.index'))->assertOk()->assertSee('appTour(', false);
        $this->assertNull($user->fresh()->guide_completed_at);

        $this->actingAs($user)->post(route('guide.complete'))->assertNoContent();
        $this->assertSame('2026-10-12 11:00:00', $user->fresh()->guide_completed_at->format('Y-m-d H:i:s'));

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('appTour(', false);
        $this->actingAs($user)->get(route('invoices.index'))->assertOk()->assertDontSee('appTour(', false);
        // Still available on demand.
        $this->actingAs($user)->get(route('dashboard', ['tour' => 1]))->assertOk()->assertSee('appTour(', false);

        // Only the one welcome email and the one OTP email in the whole journey.
        Mail::assertSent(OtpEmail::class, 1);
        Mail::assertSent(OnboardingEmail::class, 1);
    }

    public function test_five_wrong_codes_use_up_the_attempts_and_the_next_try_starts_over(): void
    {
        $code = $this->startSignup();
        $wrong = $this->wrongCodeFor($code);

        foreach ([4, 3, 2, 1, 0] as $i => $left) {
            $this->from(route('register.verify'))
                ->post(route('register.verify.store'), ['code' => $wrong])
                ->assertSessionHasErrors(['code' => "Incorrect code. {$left} attempt(s) left."]);
            $this->assertSame($i + 1, session('pending_registration')['attempts']);
        }

        // Even the right code is refused now: the attempts are used up and the
        // pending sign-up is thrown away, with the form refilled (bar the password).
        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $code])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => 'Too many incorrect attempts. Submit the form again to get a new code.'])
            ->assertSessionHasInput('email', self::EMAIL)
            ->assertSessionHasInput('phone', '9876543210');
        $this->assertNull(session('pending_registration'));
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_a_code_that_is_not_six_digits_is_refused_without_using_an_attempt(): void
    {
        $this->startSignup();

        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => '12ab'])
            ->assertSessionHasErrors('code');
        $this->assertSame(0, session('pending_registration')['attempts']);
        $this->assertGuest();
    }

    public function test_a_new_code_can_be_requested_only_after_the_cooldown(): void
    {
        $first = $this->startSignup();
        $this->get(route('register.verify'))->assertOk();

        // 29 seconds in: still 1 second to wait.
        $this->travel(29)->seconds();
        $this->from(route('register.verify'))->post(route('register.resend'))
            ->assertRedirect(route('register.verify'))
            ->assertSessionHasErrors(['code' => 'Wait 1 seconds before requesting a new code.']);
        $this->assertSame(0, (int) session('pending_registration')['resends']);

        // One wrong guess, then past the cooldown: a fresh code, attempts reset.
        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $this->wrongCodeFor($first)]);
        $this->assertSame(1, session('pending_registration')['attempts']);

        $this->travel(2)->seconds();
        $res = $this->from(route('register.verify'))->post(route('register.resend'));
        $res->assertRedirect(route('register.verify'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'A new code is on its way.');
        $second = (string) $res->getSession()->get('otp_dev_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $second);
        $pending = session('pending_registration');
        $this->assertSame(0, $pending['attempts'], 'a new code gets a fresh set of attempts');
        $this->assertSame(1, $pending['resends']);

        // The superseded code no longer works (unless the new one happens to equal it).
        if ($second !== $first) {
            $this->from(route('register.verify'))
                ->post(route('register.verify.store'), ['code' => $first])
                ->assertSessionHasErrors('code');
            $this->assertGuest();
        }

        $this->from(route('register.verify'))
            ->post(route('register.verify.store'), ['code' => $second])
            ->assertRedirect(route('onboarding.index'));
        $this->assertAuthenticated();
        $this->assertSame(1, User::where('email', self::EMAIL)->count());
    }

    public function test_new_codes_are_capped_at_three_per_sign_up(): void
    {
        $this->startSignup();

        for ($n = 1; $n <= 3; $n++) {
            $this->travel(31)->seconds();
            $this->from(route('register.verify'))->post(route('register.resend'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', 'A new code is on its way.');
            $this->assertSame($n, session('pending_registration')['resends']);
        }

        // The fourth request ends the sign-up attempt: 3 resends x 5 attempts
        // is the whole guess budget.
        $this->travel(31)->seconds();
        $this->from(route('register.verify'))->post(route('register.resend'))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => 'Too many code requests. Submit the form again to start over.']);
        $this->assertNull(session('pending_registration'));
        $this->assertSame(0, User::count());
    }

    public function test_resend_without_a_pending_sign_up_goes_back_to_the_form(): void
    {
        $this->post(route('register.resend'))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['phone' => 'Your sign-up session has expired. Start again.']);
        Mail::assertNothingSent();
    }

    public function test_breeze_resend_sends_a_verification_link_only_to_an_unverified_email(): void
    {
        Notification::fake();

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->from(route('profile.edit'))
            ->post(route('verification.send'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($unverified, VerifyEmail::class);

        // Already verified: nothing is sent, the user just goes on to the app.
        $verified = User::factory()->create();
        $this->actingAs($verified)->post(route('verification.send'))
            ->assertRedirect(route('dashboard'));
        Notification::assertNotSentTo($verified, VerifyEmail::class);
        Notification::assertSentTimes(VerifyEmail::class, 1);
    }

    public function test_a_guest_cannot_ask_for_a_verification_link(): void
    {
        Notification::fake();
        $this->post(route('verification.send'))->assertRedirect(route('login'));
        Notification::assertNothingSent();
    }

    public function test_skipping_the_first_customer_still_finishes_setup(): void
    {
        $user = User::factory()->create(['name' => 'Meena Iyer']);
        $company = Company::factory()->create([
            'user_id' => $user->id, 'name' => 'Iyer Tailors', 'state_id' => $this->state('27')->id,
            'gstin' => null, 'onboarded_at' => null,
        ]);
        $user->forceFill(['active_company_id' => $company->id])->save();

        // Business is complete and there are no customers: the wizard asks for one.
        $this->actingAs($user)->get(route('onboarding.index'))->assertRedirect(route('onboarding.customer'));

        $this->actingAs($user)->get(route('onboarding.customer.skip'))->assertRedirect(route('onboarding.done'));
        $this->actingAs($user)->get(route('onboarding.done'))->assertOk()
            ->assertSee('None added yet')
            ->assertSee('Add a customer');
        $this->assertNotNull($company->fresh()->onboarded_at);
        $this->assertSame(0, Customer::count());

        // A guest is sent to log in.
        $this->app['auth']->forgetGuards();
        $this->get(route('onboarding.customer.skip'))->assertRedirect(route('login'));
    }

    public function test_a_gstin_from_another_state_is_refused_at_setup(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('onboarding.index'));
        $company = Company::where('user_id', $user->id)->firstOrFail();

        // 27 = Maharashtra, but Karnataka (29) is chosen as the state.
        $this->actingAs($user)->from(route('onboarding.business'))
            ->post(route('onboarding.business.save'), $this->businessForm(['state_id' => $this->state('29')->id]))
            ->assertRedirect(route('onboarding.business'))
            ->assertSessionHasErrors(['gstin' => "The GSTIN state code (first 2 digits) doesn't match the selected state (Karnataka, code 29)."]);

        // A GSTIN whose check character is wrong is refused too.
        $this->actingAs($user)->post(route('onboarding.business.save'), $this->businessForm(['gstin' => '27ABCDE1234F1Z1']))
            ->assertSessionHasErrors('gstin');

        $company->refresh();
        $this->assertNull($company->gstin, 'nothing was saved');
        $this->assertNull($company->state_id);
        $this->assertSame($user->name . "'s Company", $company->name);
    }

    public function test_a_malformed_ifsc_or_upi_id_is_refused_at_setup(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('onboarding.index'));
        $company = Company::where('user_id', $user->id)->firstOrFail();

        // 5th character of an IFSC is always 0.
        $this->actingAs($user)->post(route('onboarding.business.save'), $this->businessForm(['bank_ifsc' => 'SBIN1001234']))
            ->assertSessionHasErrors(['bank_ifsc' => 'IFSC should be 11 characters: 4 letters, a zero, then 6 letters or digits (e.g. SBIN0001234).']);
        // Too short.
        $this->actingAs($user)->post(route('onboarding.business.save'), $this->businessForm(['bank_ifsc' => 'SBIN000123']))
            ->assertSessionHasErrors('bank_ifsc');
        // A UPI ID needs a handle after "@".
        $this->actingAs($user)->post(route('onboarding.business.save'), $this->businessForm(['upi_id' => 'sharmatraders']))
            ->assertSessionHasErrors(['upi_id' => 'UPI ID should look like name@bank (e.g. sharmatraders@okicici).']);
        $this->actingAs($user)->post(route('onboarding.business.save'), $this->businessForm(['upi_id' => 'sharma traders@okicici']))
            ->assertSessionHasErrors('upi_id');
        // A logo must be an image.
        $this->actingAs($user)->post(route('onboarding.business.save'), $this->businessForm([
            'logo' => UploadedFile::fake()->create('logo.pdf', 20, 'application/pdf'),
        ]))->assertSessionHasErrors('logo');

        $company->refresh();
        $this->assertNull($company->bank_ifsc);
        $this->assertNull($company->upi_id);
        $this->assertNull($company->logo_path);
        $this->assertNull($company->state_id, 'no half-saved business');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_settings_hub_links_every_account_page(): void
    {
        $biz = $this->makeBusiness();
        $owner = $this->ownerOf($biz);

        $res = $this->actingAs($owner)->get(route('settings.index'))->assertOk();
        $res->assertSee('All settings');
        foreach (['profile.edit', 'profile.activity', 'profile.privacy', 'team.index', 'backup.index', 'referrals.index'] as $name) {
            $res->assertSee(route($name), false);
        }
        $res->assertSee('Data and privacy');
        $res->assertSee('Refer a friend');

        $this->app['auth']->forgetGuards();
        $this->get(route('settings.index'))->assertRedirect(route('login'));
    }

    public function test_completing_the_tour_needs_a_signed_in_person_and_is_remembered_per_person(): void
    {
        $this->post(route('guide.complete'))->assertRedirect(route('login'));

        $biz = $this->makeBusiness();
        $owner = $this->ownerOf($biz);
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('guide.complete'))->assertNoContent();
        $this->assertNotNull($owner->fresh()->guide_completed_at);
        $this->assertNull($other->fresh()->guide_completed_at, "another person's tour is untouched");
    }
}
