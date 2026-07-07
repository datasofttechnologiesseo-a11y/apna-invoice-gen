<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_register_via_otp_with_mobile(): void
    {
        // Step 1 — submit details. No account is created yet; the user is
        // sent to the OTP step.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'phone' => '9876543210',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_accepted' => true,
        ]);

        $response->assertRedirect(route('register.verify'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);

        // In non-production the OTP is exposed via flashed session data.
        $code = $response->getSession()->get('otp_dev_code');
        $this->assertNotEmpty($code);

        // Step 2 — verify the OTP. The account is created, authenticated and
        // sent straight into guided onboarding.
        $verify = $this->post(route('register.verify.store'), ['code' => $code]);

        $this->assertAuthenticated();
        $verify->assertRedirect(route('onboarding.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => '+919876543210',
        ]);
    }

    public function test_mobile_number_is_required_at_registration(): void
    {
        // The mobile is mandatory so the team can reach every new user, even
        // though the verification code itself is delivered by email.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'nophone@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_accepted' => true,
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nophone@example.com']);
    }

    public function test_code_is_delivered_by_email_and_phone_is_stored(): void
    {
        // With no SMS gateway, the OTP goes to email (which is what gets
        // verified) while the mobile is captured on the account.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'phone' => '9876543210',
            'email' => 'stored@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_accepted' => true,
        ]);

        $code = $response->getSession()->get('otp_dev_code');
        $this->post(route('register.verify.store'), ['code' => $code]);

        $user = User::where('email', 'stored@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('+919876543210', $user->phone);
        // Code went to email → email verified; phone captured but not yet
        // OTP-verified (no SMS gateway).
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_invalid_mobile_number_is_rejected(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'phone' => '12345',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_accepted' => true,
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_expired_code_does_not_kill_the_signup_session(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'phone' => '9876543210',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_accepted' => true,
        ]);

        // Simulate the code expiring.
        $reg = session('pending_registration');
        $reg['expires_at'] = now()->subMinute()->timestamp;
        session(['pending_registration' => $reg]);

        $verify = $this->post(route('register.verify.store'), ['code' => '000000']);

        // The user is bounced back to the verify screen (not to /register)
        // and the pending registration survives so "Resend code" works.
        $verify->assertSessionHasErrors('code');
        $this->assertNotNull(session('pending_registration'));
        $this->assertGuest();
    }
}
