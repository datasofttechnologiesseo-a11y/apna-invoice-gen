<?php

namespace Tests\Feature;

use App\Models\State;
use Database\Seeders\LocalTestUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalAutoLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_with_the_flag_on_opens_the_app_signed_in(): void
    {
        State::factory()->create(['gst_code' => '27']);
        $this->app['env'] = 'local';
        config(['app.local_auto_login' => true]);

        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticated();
        $this->assertSame(LocalTestUserSeeder::EMAIL, auth()->user()->email);
    }

    public function test_the_flag_does_nothing_outside_local(): void
    {
        // A flag copied onto a server by mistake must not hand out a session.
        foreach (['production', 'staging', 'testing'] as $env) {
            $this->app['env'] = $env;
            config(['app.local_auto_login' => true]);

            $this->get('/dashboard')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_local_without_the_flag_still_asks_for_login(): void
    {
        $this->app['env'] = 'local';
        config(['app.local_auto_login' => false]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
