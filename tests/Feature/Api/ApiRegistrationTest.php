<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile-app registration endpoint must enforce the same mandatory-mobile
 * policy as web signup — every channel that creates a user captures a phone.
 */
class ApiRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_registration_requires_a_mobile_number(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'App User',
            'email' => 'app@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'app@example.com']);
    }

    public function test_api_registration_rejects_invalid_mobile(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'App User',
            'phone' => '12345',
            'email' => 'app@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_api_registration_stores_normalised_mobile_and_returns_it(): void
    {
        $resp = $this->postJson('/api/auth/register', [
            'name' => 'App User',
            'phone' => '98765 43210',
            'email' => 'app@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $resp->assertStatus(201)->assertJsonPath('user.phone', '+919876543210');

        $user = User::where('email', 'app@example.com')->first();
        $this->assertSame('+919876543210', $user->phone);
    }

    public function test_api_registration_accepts_prefixed_formats(): void
    {
        foreach ([['+919876543210', 'a@example.com'], ['09876543210', 'b@example.com']] as [$phone, $email]) {
            $this->postJson('/api/auth/register', [
                'name' => 'App User',
                'phone' => $phone,
                'email' => $email,
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
            ])->assertStatus(201);

            $this->assertSame('+919876543210', User::where('email', $email)->first()->phone);
        }
    }
}
