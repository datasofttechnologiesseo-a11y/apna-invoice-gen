<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        // Quick-invoice UX: login lands on the dashboard. The dashboard shows a
        // setup checklist for incomplete profiles, but never forces the wizard.
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_a_refused_login_always_says_why_on_the_page(): void
    {
        $user = User::factory()->create();

        // Wrong password: the message shows under the email field.
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertRedirect('/login');
        $this->get('/login')->assertSee(trans('auth.failed'));

        // A captcha message with no widget on the page still shows.
        config(['captcha.site_key' => null]);
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['cf-turnstile-response' => 'Captcha check failed.']))])
            ->get('/login')->assertSee('Captcha check failed.');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
