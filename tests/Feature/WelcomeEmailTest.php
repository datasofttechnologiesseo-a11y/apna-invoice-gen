<?php

namespace Tests\Feature;

use App\Mail\OnboardingEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every new account gets the welcome email once, whichever way it signed up.
 * The website and Google paths are covered in NewBusinessJourneyTest; this
 * covers the mobile app and the email itself.
 */
class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_app_sign_up_gets_the_welcome_email_once(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Ravi Kumar',
            'phone' => '9876543210',
            'email' => 'ravi@example.in',
            'password' => 'Str0ng-pass-123',
            'password_confirmation' => 'Str0ng-pass-123',
        ])->assertCreated();

        $user = User::where('email', 'ravi@example.in')->firstOrFail();
        Mail::assertSent(OnboardingEmail::class, fn (OnboardingEmail $m) => $m->stage === 'welcome' && $m->hasTo('ravi@example.in'));
        $this->assertSame(1, DB::table('onboarding_emails')->where('user_id', $user->id)->where('stage', 'welcome')->count());
    }

    public function test_the_welcome_email_greets_by_first_name_and_lists_the_first_steps(): void
    {
        $user = User::factory()->create(['name' => 'Ravi Kumar']);

        $html = (new OnboardingEmail($user, 'welcome'))->render();

        $this->assertStringContainsString('Welcome to Apna Invoice', $html);
        $this->assertStringContainsString('Hi Ravi,', $html);
        $this->assertStringContainsString('Add your business details and GSTIN.', $html);
        $this->assertStringContainsString('Create your first invoice', $html);
        $this->assertStringContainsString('Team Apna Invoice', $html);
        $this->assertStringNotContainsString('!', strip_tags($html));
    }
}
