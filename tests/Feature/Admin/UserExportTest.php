<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin download of registered users (Excel / CSV).
 */
class UserExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_super_admin' => true, 'email' => 'admin@apnainvoice.test']);
    }

    private function csv(array $query = []): string
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.export', ['format' => 'csv'] + $query));
        $response->assertOk();

        return $response->streamedContent();
    }

    public function test_only_super_admins_can_download(): void
    {
        $this->get(route('admin.users.export'))->assertRedirect(route('login'));

        $user = User::factory()->create(['is_super_admin' => false]);
        $this->actingAs($user)->get(route('admin.users.export'))->assertForbidden();
    }

    public function test_verified_users_by_default_with_contact_and_business_location(): void
    {
        $state = State::factory()->create(['name' => 'Maharashtra', 'gst_code' => '27']);
        $verified = User::factory()->create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.in', 'phone' => '+919876543210',
            'email_verified_at' => now(), 'phone_verified_at' => now(),
        ]);
        $company = Company::factory()->recycle($verified)->create([
            'name' => 'Ravi Traders', 'city' => 'Pune', 'state_id' => $state->id, 'postal_code' => '411001',
        ]);
        $verified->forceFill(['active_company_id' => $company->id])->save();

        User::factory()->create(['email' => 'pending@example.in', 'email_verified_at' => null, 'phone_verified_at' => null]);
        User::factory()->create(['email' => 'gone@erased.invalid', 'erased_at' => now()]);

        $csv = $this->csv();

        $this->assertStringContainsString('Verified users', $csv);
        $this->assertStringContainsString('ravi@example.in', $csv);
        $this->assertStringContainsString('+919876543210', $csv);
        $this->assertStringContainsString('Ravi Traders', $csv);
        $this->assertStringContainsString('Pune', $csv);
        $this->assertStringContainsString('Maharashtra', $csv);
        $this->assertStringContainsString('411001', $csv);
        $this->assertStringNotContainsString('pending@example.in', $csv);
        $this->assertStringNotContainsString('erased.invalid', $csv);
    }

    public function test_all_users_includes_unverified_but_never_erased_accounts(): void
    {
        User::factory()->create(['email' => 'pending@example.in', 'email_verified_at' => null, 'phone_verified_at' => null]);
        User::factory()->create(['email' => 'gone@erased.invalid', 'erased_at' => now()]);

        $csv = $this->csv(['scope' => 'all']);

        $this->assertStringContainsString('All users', $csv);
        $this->assertStringContainsString('pending@example.in', $csv);
        $this->assertStringNotContainsString('erased.invalid', $csv);
    }

    public function test_a_mobile_verified_user_counts_as_verified(): void
    {
        User::factory()->create(['email' => 'mobile-only@example.in', 'email_verified_at' => null, 'phone_verified_at' => now()]);

        $this->assertStringContainsString('mobile-only@example.in', $this->csv());
    }

    public function test_the_search_on_the_list_narrows_the_download(): void
    {
        User::factory()->create(['name' => 'Sunita Sharma', 'email' => 'sunita@example.in']);
        User::factory()->create(['name' => 'Amit Verma', 'email' => 'amit@example.in']);

        $csv = $this->csv(['search' => 'sunita']);

        $this->assertStringContainsString('sunita@example.in', $csv);
        $this->assertStringNotContainsString('amit@example.in', $csv);
    }

    public function test_a_name_that_looks_like_a_formula_stays_text(): void
    {
        User::factory()->create(['name' => '=HYPERLINK("http://x","Open")', 'email' => 'odd@example.in']);

        $this->assertStringContainsString("'=HYPERLINK", $this->csv());
    }

    public function test_excel_download_and_audit_entry(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.export'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('apna-invoice-verified-users-', $response->headers->get('Content-Disposition'));
        $response->streamedContent();

        $this->assertTrue(AuditLog::where('action', 'admin.users.exported')->where('user_id', $this->admin->id)->exists());
    }

    public function test_the_users_page_offers_the_download(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Download')
            ->assertSee(route('admin.users.export', ['format' => 'csv']), false);
    }
}
