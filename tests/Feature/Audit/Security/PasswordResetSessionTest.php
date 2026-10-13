<?php

namespace Tests\Feature\Audit\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * Recovering a compromised account. App tokens never expire
 * (config/sanctum.php 'expiration' => null). Account erasure revokes them
 * ("the mobile app's tokens were issued before the erasure and would
 * otherwise keep working"), but resetting or changing the password does
 * not: whoever signed in to the app with the stolen password keeps reading
 * and changing the books after the owner has reset it.
 */
class PasswordResetSessionTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    public function test_resetting_a_forgotten_password_signs_the_app_out_everywhere(): void
    {
        $this->freezeClock();
        $a = $this->buildBusiness('A');
        $owner = $a['owner']->fresh();

        // Someone with the password signs in to the mobile app.
        $stolen = $this->postJson('/api/auth/login', ['email' => $owner->email, 'password' => 'password'])->assertOk()->json('token');
        $this->app['auth']->forgetGuards();

        // The owner notices and resets the password by email.
        $resetToken = Password::broker()->createToken($owner);
        $this->post(route('password.store'), [
            'token' => $resetToken, 'email' => $owner->email,
            'password' => 'Brand-New#Pass2026', 'password_confirmation' => 'Brand-New#Pass2026',
        ])->assertRedirect(route('login'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Brand-New#Pass2026', $owner->fresh()->password), 'fixture: password reset');
        $this->app['auth']->forgetGuards();

        $res = $this->withHeader('Authorization', 'Bearer ' . $stolen)->getJson('/api/customers');
        $this->assertSame(401, $res->getStatusCode(),
            'the app token issued before the password reset still works (status ' . $res->getStatusCode() . ')'
            . (str_contains((string) $res->getContent(), 'SECRET-CUSTOMER-A') ? ' and lists the customers' : ''));
    }

    public function test_changing_the_password_in_settings_signs_the_app_out_everywhere(): void
    {
        $this->freezeClock();
        $a = $this->buildBusiness('A');
        $owner = $a['owner']->fresh();
        $stolen = $this->postJson('/api/auth/login', ['email' => $owner->email, 'password' => 'password'])->assertOk()->json('token');
        $this->app['auth']->forgetGuards();

        $this->actingAs($owner)->put(route('password.update'), [
            'current_password' => 'password', 'password' => 'Brand-New#Pass2026', 'password_confirmation' => 'Brand-New#Pass2026',
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $res = $this->withHeader('Authorization', 'Bearer ' . $stolen)->postJson("/api/invoices/{$a['invoiceOpen']->id}/payments", [
            'amount' => 1, 'method' => 'cash', 'received_at' => '2026-09-29',
        ]);
        $this->assertSame(401, $res->getStatusCode(),
            'the app token issued before the password change still works (status ' . $res->getStatusCode() . ')');
    }
}
