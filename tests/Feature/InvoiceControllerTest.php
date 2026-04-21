<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_users_invoice(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobInvoice = Invoice::factory()->recycle($bob)->create();

        $this->actingAs($alice)->get(route('invoices.show', $bobInvoice))
            ->assertStatus(403);
    }

    public function test_user_cannot_edit_another_users_invoice(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobInvoice = Invoice::factory()->recycle($bob)->create();

        $this->actingAs($alice)->get(route('invoices.edit', $bobInvoice))
            ->assertStatus(403);
    }

    public function test_user_cannot_download_another_users_invoice_pdf(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobInvoice = Invoice::factory()->recycle($bob)->finalized()->create();

        $this->actingAs($alice)->get(route('invoices.pdf', $bobInvoice))
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_another_users_invoice(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobInvoice = Invoice::factory()->recycle($bob)->create();

        $this->actingAs($alice)->delete(route('invoices.destroy', $bobInvoice))
            ->assertStatus(403);

        $this->assertDatabaseHas('invoices', ['id' => $bobInvoice->id]);
    }

    public function test_draft_invoice_can_be_deleted_by_owner(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->recycle($user)->create(['status' => 'draft']);

        $this->actingAs($user)->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_finalized_invoice_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->recycle($user)->finalized()->create();

        $this->actingAs($user)->delete(route('invoices.destroy', $invoice))
            ->assertStatus(403);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_finalized_invoice_allows_soft_edit_of_notes_and_transporter(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->recycle($user)->finalized()->create([
            'invoice_number' => 'INV-0042',
            'notes' => 'old note',
            'transporter_name' => null,
        ]);
        $originalCustomerId = $invoice->customer_id;
        $originalSubtotal = (float) $invoice->subtotal;

        $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertStatus(200);

        $this->actingAs($user)->patch(route('invoices.update', $invoice), [
            'notes' => 'Updated after finalisation',
            'transporter_name' => 'Blue Dart',
            'vehicle_number' => 'DL01AB1234',
        ])->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('Updated after finalisation', $invoice->notes);
        $this->assertSame('Blue Dart', $invoice->transporter_name);
        $this->assertSame('DL01AB1234', $invoice->vehicle_number);
        // Immutable fields must not change
        $this->assertSame('INV-0042', $invoice->invoice_number);
        $this->assertSame($originalCustomerId, $invoice->customer_id);
        $this->assertSame($originalSubtotal, (float) $invoice->subtotal);
    }

    public function test_finalize_assigns_invoice_number_and_sets_finalized_at(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'invoice_number_padding' => 4,
        ]);
        $invoice = Invoice::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'status' => 'draft',
            'invoice_number' => null,
            'finalized_at' => null,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->actingAs($user)->post(route('invoices.finalize', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('INV-0001', $invoice->invoice_number);
        $this->assertNotNull($invoice->finalized_at);
        $this->assertNotSame('draft', $invoice->status);
    }

    public function test_finalize_increments_counter_for_subsequent_invoices(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'invoice_prefix' => 'INV',
            'invoice_counter' => 41,
            'invoice_number_padding' => 4,
        ]);
        $invoice = Invoice::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'status' => 'draft',
            'invoice_number' => null,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->actingAs($user)->post(route('invoices.finalize', $invoice));

        $this->assertSame('INV-0042', $invoice->fresh()->invoice_number);
        $this->assertSame(42, $company->fresh()->invoice_counter);
    }

    public function test_unauthenticated_user_cannot_access_invoices(): void
    {
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
    }

    public function test_pdf_download_works_with_fy_style_invoice_number_containing_slashes(): void
    {
        // Regression: FY invoice numbers like "ACME/2026-27/0001" contain '/'
        // which Symfony's Content-Disposition header rejects. The filename
        // must be sanitised before download().
        $user = User::factory()->create();
        $invoice = Invoice::factory()->recycle($user)->finalized()->create([
            'invoice_number' => 'ACME/2026-27/0001',
        ]);

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        // Header must not contain the raw slashes.
        $this->assertStringNotContainsString('ACME/2026-27/0001', $response->headers->get('Content-Disposition'));
    }
}
