<?php

namespace Tests\Feature\Auth;

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

    public function test_new_users_register_via_mobile_otp(): void
    {
        // Step 1 — submit details. Registration is now mobile-verified, so no
        // account is created yet; the user is sent to the OTP step.
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

        // Step 2 — verify the OTP. The account is created and authenticated.
        $verify = $this->post(route('register.verify.store'), ['code' => $code]);

        $this->assertAuthenticated();
        $verify->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => '+919876543210',
        ]);
    }

    public function test_registration_requires_a_mobile_number(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_accepted' => true,
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }
}
