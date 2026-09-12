<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

/**
 * The gap between "the app says it sent an email" and "an email arrived".
 *
 * Reported as "forgot password logic is not working properly". The logic was
 * fine - the token was issued, the link was well formed, the reset page worked
 * - and no mail arrived, because MAIL_MAILER was `log`. Every reset link had
 * been written to storage/logs/laravel.log while the screen said "sent".
 *
 * Nothing in the reset flow can detect that: handing a message to the `log`
 * mailer succeeds. So the app grew two ways to notice - a server-side check,
 * and a note on the page itself outside production - and this pins both, plus
 * the part of the reset flow that was never actually broken.
 */
class MailDeliverabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reset_link_is_addressed_and_carries_a_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'shopkeeper@example.in']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            // The email must be in the link as well as the token: the reset
            // form reads it out of the query string to prefill the field, and
            // Password::reset refuses a token without a matching address.
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/reset-password/' . $notification->token)
                && str_contains($url, urlencode($user->email));
        });
    }

    public function test_a_google_only_account_is_sent_back_to_google_rather_than_emailed(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'google@example.in',
            'password' => null,
            'google_id' => '1234567890',
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        // Issuing a reset here would let whoever holds the inbox walk around
        // Google being the only sign-in factor on the account.
        Notification::assertNothingSent();
    }

    public function test_the_check_command_fails_when_mail_goes_nowhere(): void
    {
        config(['mail.default' => 'log', 'app.url' => 'https://apnainvoice.com']);

        $this->artisan('mail:check')
            ->expectsOutputToContain('MAIL_MAILER is "log"')
            ->assertExitCode(1);
    }

    public function test_the_check_command_fails_when_emailed_links_point_at_localhost(): void
    {
        // Sends perfectly, and every link in the mail is useless to the reader.
        config(['mail.default' => 'smtp', 'app.url' => 'http://localhost:8000']);

        $this->artisan('mail:check')
            ->expectsOutputToContain('links inside emails will point at this machine')
            ->assertExitCode(1);
    }

    public function test_the_check_command_passes_on_a_deliverable_setup(): void
    {
        config([
            'mail.default' => 'smtp',
            'app.url' => 'https://apnainvoice.com',
            'mail.from.address' => 'billing@apnainvoice.com',
        ]);

        $this->artisan('mail:check')
            ->expectsOutputToContain('Configuration looks deliverable')
            ->assertExitCode(0);
    }

    public function test_the_forgot_password_page_admits_locally_that_nothing_was_sent(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->create(['email' => 'shopkeeper@example.in']);

        // Two explicit steps rather than followingRedirects(): the broker
        // throttles a second reset for the same user inside a minute, so any
        // extra POST in this test would flash an error instead of the status.
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('We have emailed your password reset link.', false)
            ->assertSee('no email was actually sent', false)
            ->assertSee('storage/logs/laravel.log', false);
    }

    public function test_that_admission_never_reaches_production(): void
    {
        config(['mail.default' => 'log']);
        // Swap the env string rather than re-running detectEnvironment(), which
        // sends the console kernel looking for confirmation prompts and hangs
        // the run.
        $this->app['env'] = 'production';

        // The status is seeded straight into the session rather than earned by
        // posting the form: under a production environment the Turnstile rule
        // fails closed on the missing captcha secret, which is correct and is
        // not what this test is about.
        $this->withSession(['status' => 'We have emailed your password reset link.'])
            ->get(route('password.request'))
            ->assertOk()
            ->assertSee('We have emailed your password reset link.', false)
            ->assertDontSee('no email was actually sent', false)
            ->assertDontSee('MAIL_MAILER', false);
    }
}
