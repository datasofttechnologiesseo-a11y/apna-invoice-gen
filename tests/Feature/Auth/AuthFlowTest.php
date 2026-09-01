<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Login, logout, password reset and email verification. None of these had a
 * test, which is a strange gap for the paths that every single user walks and
 * that lock people out of their own data when they break.
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-1')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-1'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_the_wrong_password_does_not_log_anyone_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-1')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_repeated_failures_are_rate_limited(): void
    {
        // Without this an attacker can grind passwords at full speed against a
        // known email address.
        $user = User::factory()->create(['password' => Hash::make('correct-horse-1')]);
        RateLimiter::clear('');

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-'.$i]);
        }

        $res = $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-1']);
        $res->assertSessionHasErrors('email');
        // Even the CORRECT password must be refused while the throttle holds.
        $this->assertGuest();
        $this->assertStringContainsString(
            'seconds',
            (string) session('errors')->first('email')
        );
    }

    public function test_a_password_reset_link_can_be_requested_and_used(): void
    {
        Notification::fake();
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'brand-new-pass-9',
                'password_confirmation' => 'brand-new-pass-9',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });

        Event::assertDispatched(PasswordReset::class);
        $this->assertTrue(Hash::check('brand-new-pass-9', $user->fresh()->password));
    }

    public function test_a_reset_token_for_one_account_cannot_reset_another(): void
    {
        Notification::fake();
        $victim = User::factory()->create(['password' => Hash::make('victim-pass-1')]);
        $attacker = User::factory()->create();

        $this->post('/forgot-password', ['email' => $attacker->email]);

        Notification::assertSentTo($attacker, ResetPassword::class, function ($notification) use ($victim) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $victim->email,
                'password' => 'hijacked-pass-1',
                'password_confirmation' => 'hijacked-pass-1',
            ])->assertSessionHasErrors();

            return true;
        });

        $this->assertTrue(Hash::check('victim-pass-1', $victim->fresh()->password));
    }

    public function test_an_unknown_email_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        // Laravel reports this as an error on the email field rather than a
        // success; what matters is that no mail goes out.
        $this->post('/forgot-password', ['email' => 'nobody@example.test']);

        Notification::assertNothingSent();
    }

    public function test_email_verification_marks_the_account_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $bad = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1('someone-else@example.test'),
        ]);

        $this->actingAs($user)->get($bad)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
