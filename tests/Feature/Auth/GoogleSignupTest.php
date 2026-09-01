<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Google sign-in had no test at all, which matters more than its size
 * suggests: it is one of the two doors into the product, and only 24% of
 * registered users ever finish setup. A silent break here is invisible except
 * as a number that fails to grow.
 */
class GoogleSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
    }

    private function fakeGoogleUser(string $email, string $id = 'g-123', string $name = 'Priya Sharma'): void
    {
        $user = (new SocialiteUser)->map([
            'id' => $id, 'name' => $name, 'email' => $email, 'avatar' => 'https://example.test/a.jpg',
        ]);
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_a_new_google_user_is_asked_for_a_mobile_before_the_account_exists(): void
    {
        $this->fakeGoogleUser('priya@example.test');

        $this->get('/auth/google/callback')->assertRedirect(route('register.mobile'));

        // The account must NOT exist yet - the mobile number is mandatory so
        // the team can reach every signup.
        $this->assertNull(User::where('email', 'priya@example.test')->first());
        $this->assertNotNull(session('pending_registration'));
    }

    public function test_supplying_the_mobile_creates_a_verified_account_with_no_otp(): void
    {
        $this->fakeGoogleUser('priya@example.test');
        $this->get('/auth/google/callback');

        $this->post(route('register.mobile.store'), ['phone' => '9876500123'])
            ->assertRedirect(route('onboarding.index'));

        $user = User::where('email', 'priya@example.test')->first();
        $this->assertNotNull($user, 'the account should exist after the mobile step');
        // Google already proved the email, so this path must not demand an OTP.
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('g-123', $user->google_id);
        $this->assertStringContainsString('9876500123', $user->phone);
        $this->assertAuthenticated();
    }

    public function test_the_mobile_step_rejects_a_number_that_is_not_an_indian_mobile(): void
    {
        $this->fakeGoogleUser('priya@example.test');
        $this->get('/auth/google/callback');

        $this->post(route('register.mobile.store'), ['phone' => '1234567890'])
            ->assertSessionHasErrors('phone');

        $this->assertNull(User::where('email', 'priya@example.test')->first());
    }

    public function test_a_returning_google_user_logs_straight_in(): void
    {
        $existing = User::factory()->create(['email' => 'ravi@example.test', 'google_id' => 'g-999']);
        $this->fakeGoogleUser('ravi@example.test', 'g-999', 'Ravi');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::where('email', 'ravi@example.test')->count());
    }

    public function test_google_links_to_an_existing_password_account_instead_of_duplicating_it(): void
    {
        $existing = User::factory()->create([
            'email' => 'ravi@example.test', 'google_id' => null, 'email_verified_at' => null,
        ]);
        $this->fakeGoogleUser('ravi@example.test', 'g-777');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

        $existing->refresh();
        $this->assertSame('g-777', $existing->google_id);
        // Google proved ownership of the address, so it counts as verified.
        $this->assertNotNull($existing->email_verified_at);
        $this->assertSame(1, User::where('email', 'ravi@example.test')->count());
    }

    public function test_an_erased_account_is_never_reopened_through_google(): void
    {
        // DPDP erasure has to be final: matching an erased row would silently
        // restore a user who asked to be forgotten.
        User::factory()->create([
            'email' => 'gone@example.test', 'google_id' => 'g-555', 'erased_at' => now(),
        ]);
        $this->fakeGoogleUser('gone@example.test', 'g-555');

        $this->get('/auth/google/callback')->assertRedirect(route('register.mobile'));
        $this->assertGuest();
    }

    public function test_google_routes_404_when_oauth_is_not_configured(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);

        $this->get('/auth/google/redirect')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->get('/auth/facebook/redirect')->assertNotFound();
    }
}
