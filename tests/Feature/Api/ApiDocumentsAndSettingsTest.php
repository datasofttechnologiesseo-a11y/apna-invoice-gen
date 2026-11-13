<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\CashMemo;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Duplicate, thermal print, receipt / quotation / expense PDFs, cash memo
 * edit and the company settings the app could not reach before.
 */
class ApiDocumentsAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    private State $state;

    private User $owner;

    private Company $company;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $this->owner->id, 'state_id' => $this->state->id,
            'invoice_prefix' => 'INV', 'invoice_counter' => 0, 'onboarded_at' => now()]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id, 'state_id' => $this->state->id]);
    }

    private function invoice(array $attrs = []): Invoice
    {
        $invoice = Invoice::factory()->finalized()->create(array_merge([
            'user_id' => $this->owner->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'place_of_supply_state_id' => $this->state->id, 'notes' => 'Thanks', 'terms' => 'Net 15',
        ], $attrs));
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'description' => 'Consulting', 'hsn_sac' => '998314']);

        return $invoice;
    }

    private function memberOf(string $role): User
    {
        $user = User::factory()->create();
        $this->company->members()->create(['email' => $user->email, 'role' => $role, 'token' => CompanyMember::newToken(),
            'invited_by' => $this->owner->id, 'user_id' => $user->id, 'accepted_at' => now()]);
        $user->forceFill(['active_company_id' => $this->company->id])->save();

        return $user->fresh();
    }

    private function intruder(): User
    {
        $user = User::factory()->create();
        Company::factory()->create(['user_id' => $user->id, 'state_id' => $this->state->id]);

        return $user;
    }

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->company->name, 'state_id' => $this->state->id, 'country' => 'India', 'default_currency' => 'INR',
            'invoice_prefix' => 'INV', 'invoice_number_padding' => 4,
        ], $overrides);
    }

    public function test_an_invoice_is_duplicated_into_a_fresh_draft(): void
    {
        $original = $this->invoice(['paid_amount' => 500, 'balance' => 680, 'status' => 'partially_paid']);
        Sanctum::actingAs($this->owner);

        $res = $this->postJson("/api/invoices/{$original->id}/duplicate")->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.invoice_number', null);
        $copy = Invoice::findOrFail($res->json('data.id'));
        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame(0.0, (float) $copy->paid_amount);
        $this->assertSame('Net 15', $copy->terms);
        $this->assertSame(1, $copy->items()->count());
        $this->assertTrue(AuditLog::where('action', 'invoice.duplicated')->exists());

        Sanctum::actingAs($this->intruder());
        $this->postJson("/api/invoices/{$original->id}/duplicate")->assertForbidden();
        $this->assertSame(2, Invoice::count());
    }

    public function test_the_thermal_slip_comes_back_as_printable_html(): void
    {
        $invoice = $this->invoice(['invoice_number' => 'INV-0099']);
        Sanctum::actingAs($this->owner);

        $res = $this->getJson("/api/invoices/{$invoice->id}/print")->assertOk()
            ->assertJsonPath('format', 'thermal')->assertJsonPath('paper_width_mm', 80);
        $html = $res->json('html');
        $this->assertStringContainsString('INV-0099', $html);
        $this->assertStringContainsString('80mm', $html);
        $this->assertStringNotContainsString('window.print()', $html, 'the app prints it; no browser auto-print');

        // The web page keeps its toolbar and auto-print.
        $this->actingAs($this->owner)->get(route('invoices.print', $invoice).'?format=thermal')
            ->assertOk()->assertSee('window.print()', false);

        Sanctum::actingAs($this->intruder());
        $this->getJson("/api/invoices/{$invoice->id}/print")->assertForbidden();
    }

    public function test_invoice_templates_and_preview(): void
    {
        Sanctum::actingAs($this->owner);
        $res = $this->getJson('/api/invoice-templates')->assertOk();
        $this->assertContains('blank', array_column($res->json('data'), 'key'));
        $this->assertContains('classic', array_column($res->json('meta.styles'), 'key'));

        $this->get('/api/invoice-templates/blank/preview')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/api/invoice-templates/nope/preview')->assertNotFound();
    }

    public function test_receipt_quotation_and_expense_pdfs(): void
    {
        $invoice = $this->invoice();
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 500, 'method' => 'upi', 'received_at' => now()->toDateString(),
        ])->assertSuccessful();
        $payment = $invoice->payments()->firstOrFail();
        $this->get("/api/payments/{$payment->id}/receipt")->assertOk()->assertHeader('content-type', 'application/pdf');

        $quotation = Quotation::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id]);
        $this->get("/api/quotations/{$quotation->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        $expense = Expense::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id]);
        $this->get("/api/expenses/{$expense->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        $intruder = $this->intruder();
        foreach (["/api/payments/{$payment->id}/receipt", "/api/quotations/{$quotation->id}/pdf", "/api/expenses/{$expense->id}/pdf"] as $uri) {
            Sanctum::actingAs($intruder);
            $this->getJson($uri)->assertForbidden();
        }
    }

    public function test_billing_staff_get_receipts_and_quotations_but_not_expense_vouchers(): void
    {
        $invoice = $this->invoice();
        $expense = Expense::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id]);
        $staff = $this->memberOf('staff');

        Sanctum::actingAs($staff);
        $this->postJson("/api/invoices/{$invoice->id}/duplicate")->assertCreated();
        Sanctum::actingAs($staff->fresh());
        $this->getJson("/api/invoices/{$invoice->id}/print")->assertOk();
        Sanctum::actingAs($staff->fresh());
        $this->getJson("/api/expenses/{$expense->id}/pdf")->assertForbidden();
    }

    public function test_a_cash_memo_is_edited_with_its_expense(): void
    {
        Sanctum::actingAs($this->owner);
        $memoId = $this->postJson('/api/cash-memos', [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Local Supplier', 'payment_mode' => 'cash',
            'items' => [['description' => 'Paper', 'quantity' => 2, 'rate' => 100]],
        ])->assertCreated()->json('data.id');
        $memo = CashMemo::findOrFail($memoId);

        $this->putJson("/api/cash-memos/{$memo->id}", [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Local Supplier & Sons', 'payment_mode' => 'upi',
            'gst_rate' => 18, 'items' => [['description' => 'Paper', 'quantity' => 3, 'rate' => 100]],
        ])->assertOk()->assertJsonPath('data.seller_name', 'Local Supplier & Sons')->assertJsonPath('data.grand_total', 354);

        $expense = Expense::findOrFail($memo->fresh()->expense_id);
        $this->assertSame(300.0, (float) $expense->amount);
        $this->assertSame(54.0, (float) $expense->gst_amount);
        $this->assertSame('upi', $expense->payment_method);
        $this->assertTrue(AuditLog::where('action', 'cash_memo.updated')->exists());

        $this->putJson("/api/cash-memos/{$memo->id}", ['seller_name' => 'X'])->assertStatus(422)->assertJsonValidationErrors(['memo_date', 'items']);

        // Books lock and ownership.
        $this->company->update(['books_locked_until' => now()->toDateString()]);
        $this->putJson("/api/cash-memos/{$memo->id}", [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Late edit', 'payment_mode' => 'cash',
            'items' => [['description' => 'Paper', 'quantity' => 1, 'rate' => 1]],
        ])->assertStatus(422);
        Sanctum::actingAs($this->intruder());
        $this->putJson("/api/cash-memos/{$memo->id}", [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Hijack', 'payment_mode' => 'cash',
            'items' => [['description' => 'Paper', 'quantity' => 1, 'rate' => 1]],
        ])->assertForbidden();
        $this->assertSame('Local Supplier & Sons', $memo->fresh()->seller_name);
    }

    public function test_company_settings_lut_next_number_and_numbering_rules(): void
    {
        Sanctum::actingAs($this->owner);
        $this->putJson("/api/companies/{$this->company->id}", $this->settings([
            'lut_number' => 'AD270526000123X', 'next_invoice_number' => 145, 'bank_ifsc' => 'sbin0001234',
        ]))->assertOk()
            ->assertJsonPath('data.lut_number', 'AD270526000123X')
            ->assertJsonPath('data.bank_ifsc', 'SBIN0001234')
            ->assertJsonPath('data.next_invoice_number', 'INV-0145')
            ->assertJsonPath('data.invoice_number_padding', 4);
        $this->assertSame(144, (int) $this->company->fresh()->invoice_counter);

        // Going backwards is refused without undoing the rest of the save.
        $this->putJson("/api/companies/{$this->company->id}", $this->settings(['name' => 'Renamed', 'next_invoice_number' => 10]))
            ->assertOk()->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('meta.warnings.next_invoice_number', fn ($m) => str_contains($m, 'cannot be set to 10'));
        $this->assertSame(144, (int) $this->company->fresh()->invoice_counter);

        // Rule 46(b) as on the web.
        $this->putJson("/api/companies/{$this->company->id}", $this->settings(['invoice_number_format' => 'VERYLONGPREFIX/{FY}/{N}']))
            ->assertStatus(422)->assertJsonValidationErrors('invoice_number_format');
    }

    public function test_logo_and_signature_upload_and_removal(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->owner);

        $res = $this->post("/api/companies/{$this->company->id}/branding", [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 200),
            'signature' => UploadedFile::fake()->image('sign.png', 300, 100),
        ], ['Accept' => 'application/json'])->assertOk();
        $company = $this->company->fresh();
        $this->assertNotNull($company->logo_path);
        $this->assertNotNull($company->signature_path);
        Storage::disk('public')->assertExists($company->logo_path);
        $this->assertNotNull($res->json('data.logo_url'));
        $this->assertTrue(AuditLog::where('action', 'company.updated')->where('summary', 'like', '%logo replaced%')->exists());

        $this->post("/api/companies/{$this->company->id}/branding", ['remove_logo' => true], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.logo_url', null);
        Storage::disk('public')->assertMissing($company->logo_path);
        $this->assertNotNull($this->company->fresh()->signature_path);

        $this->post("/api/companies/{$this->company->id}/branding", ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')],
            ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('logo');

        // Someone else's business, and a member role without settings.
        Sanctum::actingAs($this->intruder());
        $this->postJson("/api/companies/{$this->company->id}/branding", ['remove_signature' => true])->assertForbidden();
        Sanctum::actingAs($this->memberOf('accountant'));
        $this->postJson("/api/companies/{$this->company->id}/branding", ['remove_signature' => true])->assertForbidden();
        $this->assertNotNull($this->company->fresh()->signature_path);
    }
}
