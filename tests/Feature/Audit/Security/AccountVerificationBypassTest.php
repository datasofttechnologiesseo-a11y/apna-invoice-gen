<?php

namespace Tests\Feature\Audit\Security;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Customer;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Proof of email ownership. Registration promises "NO account yet" until the
 * emailed code is entered, and Google sign-in links to an existing account
 * because "Google has verified ownership of this email address". Both rest
 * on the email of an account having actually been proven.
 */
class AccountVerificationBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
    }

    /**
     * Web sign-up step 1 stashes `pending_registration`; the Google mobile
     * step (register/mobile) reads the SAME session key and creates the
     * account with email_verified_at set, without checking the payload came
     * from Google. So: fill the normal form with someone else's email, skip
     * the code, post any mobile number.
     */
    public function test_the_email_code_cannot_be_skipped_by_posting_to_the_google_mobile_step(): void
    {
        // Works whether or not Google sign-in is configured.
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);

        $this->post('/register', [
            'name' => 'Not The Owner', 'phone' => '9876500123', 'email' => 'victim@example.test',
            'password' => 'Attacker#Pass2026', 'password_confirmation' => 'Attacker#Pass2026', 'terms_accepted' => '1',
        ])->assertRedirect(route('register.verify'));
        $this->assertNull(User::where('email', 'victim@example.test')->first(), 'fixture: no account before the code');

        // The code mailed to victim@example.test is never entered.
        $this->post(route('register.mobile.store'), ['phone' => '9876500123']);

        $user = User::where('email', 'victim@example.test')->first();
        $this->assertTrue($user === null, 'an account was created for an email whose code was never entered'
            . ($user ? ' (email_verified_at=' . $user->email_verified_at . ', signed in=' . (auth()->check() ? 'yes' : 'no') . ')' : ''));
        $this->assertGuest();
    }

    /**
     * Account pre-hijack. The app's sign-up (no code at all) creates an
     * account for an email nobody proved; when the real owner of that email
     * later uses "Sign in with Google", the callback links Google to that
     * account and logs them in. The attacker's password and token keep
     * working, so everything the victim puts in is readable by the attacker.
     */
    public function test_google_sign_in_does_not_hand_the_real_owner_an_account_someone_else_pre_registered(): void
    {
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra', 'code' => 'MH']);

        // Attacker: app sign-up with the victim's address, no code asked.
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Priya', 'phone' => '9876500123', 'email' => 'priya@example.test',
            'password' => 'Attacker#Pass2026', 'password_confirmation' => 'Attacker#Pass2026',
        ])->assertCreated()->json('token');
        $this->assertNotEmpty($token);
        $this->app['auth']->forgetGuards();

        // The real Priya signs in with Google and starts using "her" account.
        $googleUser = (new SocialiteUser)->map(['id' => 'g-priya', 'name' => 'Priya Sharma', 'email' => 'priya@example.test', 'avatar' => null]);
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->get('/auth/google/callback');

        if (! auth()->check()) {
            $this->assertTrue(true);

            return; // refused: nothing more to prove
        }
        $victimAccount = auth()->user();
        $this->post(route('customers.store'), ['name' => 'PRIYA-PRIVATE-CUSTOMER', 'state_id' => $state->id, 'country' => 'India'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Customer::where('name', 'PRIYA-PRIVATE-CUSTOMER')->where('user_id', $victimAccount->id)->count(), 'fixture');

        // Attacker comes back with the token and the password from sign-up.
        // From their own client: without flushSession() the test's session
        // store still holds her web login, which Sanctum checks before any
        // token, so even a made-up token would read her customers.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $res = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/customers');
        $leakedByToken = str_contains((string) $res->getContent(), 'PRIYA-PRIVATE-CUSTOMER');
        $passwordStillWorks = Hash::check('Attacker#Pass2026', $victimAccount->fresh()->password);
        $this->assertFalse($leakedByToken || $passwordStillWorks,
            "the account the real owner signed into with Google is still the attacker's: "
            . ($leakedByToken ? "the attacker's sign-up token reads her customers; " : '')
            . ($passwordStillWorks ? 'the attacker\'s password still opens it' : ''));
    }

    /**
     * A team invitation is bound to an email ("This invitation is for X.
     * Sign in with that account to accept it"), but the email on an account
     * can be changed on the profile page to any free address without proof.
     * Anyone holding a forwarded / leaked invite link joins the business.
     */
    public function test_an_invitation_bound_to_an_email_cannot_be_taken_by_renaming_your_own_email(): void
    {
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra', 'code' => 'MH']);
        $owner = User::factory()->create(['email' => 'owner@firm.test']);
        $company = Company::factory()->create(['user_id' => $owner->id, 'state_id' => $state->id, 'name' => 'Owner Firm', 'onboarded_at' => now()]);
        $owner->forceFill(['active_company_id' => $company->id])->save();
        $this->actingAs($owner->fresh())->post(route('team.invite'), ['email' => 'ca@firm.test', 'role' => 'accountant'])
            ->assertSessionHasNoErrors();
        $invite = CompanyMember::where('email', 'ca@firm.test')->sole();

        $outsider = User::factory()->create(['email' => 'outsider@else.test']);
        Company::factory()->create(['user_id' => $outsider->id, 'state_id' => $state->id]);
        // Rename own email to the invitee's (not proven; the app only clears email_verified_at).
        $this->actingAs($outsider->fresh())->patch(route('profile.update'), ['name' => 'Outsider', 'email' => 'ca@firm.test'])
            ->assertSessionHasNoErrors();
        $this->assertNull($outsider->fresh()->email_verified_at, 'fixture: the new email is unverified');

        $this->actingAs($outsider->fresh())->post(route('team.accept', $invite->token));

        $this->assertTrue($invite->fresh()->accepted_at === null, 'an unverified email accepted the invitation (outsider is now ' . $invite->fresh()->role . ')');
        $this->assertNotSame($company->id, (int) $outsider->fresh()->active_company_id, 'the outsider is now working in the business');
    }
}
