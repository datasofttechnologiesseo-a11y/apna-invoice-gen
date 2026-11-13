<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Notifications, search, reports and the GSTR-1 files in the app: the same
 * rows and files as the web, scoped to the open business and the team role.
 */
class ApiWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $this->owner->id, 'state_id' => $state->id,
            'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now()]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id,
            'state_id' => $state->id, 'name' => 'Kirana Store', 'gstin' => null]);
    }

    private function invoice(array $attrs = []): Invoice
    {
        $invoice = Invoice::factory()->finalized()->create(array_merge([
            'user_id' => $this->owner->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'place_of_supply_state_id' => $this->company->state_id,
        ], $attrs));
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'hsn_sac' => '998314', 'quantity' => 1,
            'rate' => 1000, 'gst_rate' => 18]);

        return $invoice;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $this->company->members()->create(['email' => $user->email, 'role' => 'staff', 'token' => CompanyMember::newToken(),
            'invited_by' => $this->owner->id, 'user_id' => $user->id, 'accepted_at' => now()]);
        $user->forceFill(['active_company_id' => $this->company->id])->save();

        return $user->fresh();
    }

    public function test_notifications_list_overdue_and_recent_activity_and_clear_the_dot(): void
    {
        $overdue = $this->invoice(['invoice_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(10)->toDateString()]);
        Sanctum::actingAs($this->owner);

        $res = $this->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('overdue_count', 1)
            ->assertJsonPath('overdue.0.invoice_id', $overdue->id)
            ->assertJsonPath('overdue.0.customer_name', 'Kirana Store')
            ->assertJsonPath('overdue.0.days_overdue', 10)
            ->assertJsonPath('recent_invoices.0.invoice_id', $overdue->id)
            ->assertJsonPath('has_unseen', true);
        $this->assertSame(1180.0, (float) $res->json('overdue.0.balance'));

        $this->postJson('/api/notifications/seen')->assertOk()->assertJsonPath('ok', true);
        $this->assertNotNull($this->owner->fresh()->notifications_seen_at);
        $this->getJson('/api/notifications')->assertJsonPath('has_unseen', false)->assertJsonPath('overdue_count', 1);
    }

    public function test_notifications_are_for_the_open_business_only(): void
    {
        $second = Company::factory()->create(['user_id' => $this->owner->id, 'state_id' => $this->company->state_id]);
        $this->invoice(['due_date' => now()->subDays(5)->toDateString()]);
        $this->owner->forceFill(['active_company_id' => $second->id])->save();

        Sanctum::actingAs($this->owner->fresh());
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('overdue_count', 0)->assertJsonPath('total_count', 0);
    }

    public function test_search_suggests_invoices_and_customers_with_ids(): void
    {
        $invoice = $this->invoice(['invoice_number' => 'INV-0042']);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/search/suggest?q=INV-0042')->assertOk()
            ->assertJsonPath('groups.0.label', 'Invoices')
            ->assertJsonPath('groups.0.items.0.type', 'invoice')
            ->assertJsonPath('groups.0.items.0.id', $invoice->id)
            ->assertJsonPath('groups.0.items.0.badge', 'Unpaid');

        $this->getJson('/api/search/suggest?q=kirana&scope=customers')->assertOk()
            ->assertJsonPath('groups.0.label', 'Customers')
            ->assertJsonPath('groups.0.items.0.type', 'customer')
            ->assertJsonPath('groups.0.items.0.id', $this->customer->id);

        $this->getJson('/api/search/suggest?q=')->assertOk()->assertJsonPath('groups', []);

        // Another user's business never shows up.
        $other = User::factory()->create();
        Company::factory()->create(['user_id' => $other->id]);
        Sanctum::actingAs($other);
        $this->getJson('/api/search/suggest?q=kirana')->assertOk()->assertJsonPath('groups', []);
    }

    public function test_reports_hub_rows_and_files(): void
    {
        $this->invoice(['invoice_number' => 'INV-0007']);
        Sanctum::actingAs($this->owner);

        $hub = $this->getJson('/api/reports')->assertOk();
        $this->assertContains('sales-register', array_column($hub->json('data'), 'key'));
        $this->assertContains('hsn-summary', array_column($hub->json('data'), 'key'));
        $this->assertSame(['xlsx', 'csv', 'pdf'], $hub->json('meta.formats'));

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
        $sales = $this->getJson("/api/reports/sales-register?from={$from}&to={$to}")->assertOk()
            ->assertJsonPath('key', 'sales-register')
            ->assertJsonPath('truncated', false);
        $this->assertCount(1, $sales->json('rows'));
        $this->assertNotEmpty($sales->json('columns'));
        $this->assertIsArray($sales->json('totals'));

        $this->getJson('/api/reports/hsn-summary')->assertOk()->assertJsonPath('rows.0.hsn', '998314');
        $this->getJson('/api/reports/nope')->assertNotFound();

        $this->get('/api/reports/sales-register/export/csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get('/api/reports/sales-register/export/xlsx')->assertOk();
        $this->get('/api/reports/customers/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/api/reports/sales-register/export/print')->assertNotFound();
        $this->assertTrue(AuditLog::where('action', 'report.exported')->exists());
    }

    public function test_billing_staff_cannot_open_reports_or_gst(): void
    {
        $staff = $this->staff();
        foreach (['/api/reports', '/api/reports/sales-register', '/api/finance/gstr1/export/json', '/api/finance/gstr1/export/xlsx'] as $uri) {
            Sanctum::actingAs($staff->fresh());
            $this->getJson($uri)->assertForbidden();
        }
        // ...but search and notifications are part of the role, as on the web.
        Sanctum::actingAs($staff->fresh());
        $this->getJson('/api/search/suggest?q=kirana')->assertOk();
        Sanctum::actingAs($staff->fresh());
        $this->getJson('/api/notifications')->assertOk();
    }

    public function test_gstr1_has_hsn_rows_and_the_portal_files(): void
    {
        $this->invoice(['invoice_date' => now()->startOfMonth()->toDateString()]);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/finance/gstr1')->assertOk()->assertJsonPath('hsn.0.hsn', '998314');

        $month = now()->format('Y-m');
        $json = $this->get("/api/finance/gstr1/export/json?month={$month}")->assertOk();
        $this->assertStringContainsString('GSTR1_27ABCDE1234F1Z5_', (string) $json->headers->get('content-disposition'));
        $this->assertSame('27ABCDE1234F1Z5', json_decode($json->streamedContent(), true)['gstin']);

        $this->get("/api/finance/gstr1/export/xlsx?month={$month}")->assertOk();

        $this->company->update(['gstin' => null]);
        $this->getJson("/api/finance/gstr1/export/json?month={$month}")->assertStatus(422);
    }
}
