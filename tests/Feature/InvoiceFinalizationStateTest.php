<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the bug "non-GST invoice still shows Draft after
 * finalising". Locks down:
 *
 *   • finalize() correctly transitions status draft → final for non-GST companies
 *   • Invoice::displayNumber() returns the assigned invoice_number (NOT "Draft #")
 *     once status flips off draft
 *   • Invoice::documentTitle() returns "Invoice" (not "Tax Invoice") when no GSTIN
 *   • The show page heading does not contain "Draft" after finalise
 */
class InvoiceFinalizationStateTest extends TestCase
{
    use RefreshDatabase;

    private function setupNonGstScenario(): array
    {
        $user = User::factory()->create();
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        // Non-GST: company has NO GSTIN, no composition flag.
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'gstin' => null,
            'composition_dealer' => false,
            'state_id' => $state->id,
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
        ]);
        $user->forceFill(['active_company_id' => $company->id])->save();
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'state_id' => $state->id,
        ]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'invoice_number' => null,
            'invoice_date' => now()->toDateString(),
            'subtotal' => 1000,
            'total_cgst' => 0, 'total_sgst' => 0, 'total_igst' => 0, 'total_tax' => 0,
            'grand_total' => 1000,
            'paid_amount' => 0,
            'balance' => 1000,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Service A',
            'hsn_sac' => '998314',
            'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'discount' => 0, 'gst_rate' => 0,
            'amount' => 1000, 'cgst_amount' => 0, 'sgst_amount' => 0, 'igst_amount' => 0,
        ]);

        return ['user' => $user, 'company' => $company, 'invoice' => $invoice];
    }

    public function test_finalize_transitions_non_gst_invoice_off_draft(): void
    {
        ['user' => $user, 'invoice' => $invoice] = $this->setupNonGstScenario();

        $this->assertTrue($invoice->isDraft());
        $this->assertNull($invoice->invoice_number);
        $this->assertSame('Draft #' . $invoice->id, $invoice->displayNumber());

        $this->actingAs($user)->post(route('invoices.finalize', $invoice))->assertRedirect();

        $invoice->refresh();
        $this->assertFalse($invoice->isDraft(), 'Invoice should no longer be in draft state');
        $this->assertSame('final', $invoice->status);
        $this->assertSame('INV-0001', $invoice->invoice_number);
        $this->assertSame('INV-0001', $invoice->displayNumber());
        $this->assertSame('Invoice', $invoice->documentTitle()); // no GSTIN → "Invoice"
        $this->assertNotNull($invoice->finalized_at);
    }

    public function test_show_page_does_not_render_Draft_after_finalize_for_non_gst(): void
    {
        ['user' => $user, 'invoice' => $invoice] = $this->setupNonGstScenario();

        $this->actingAs($user)->post(route('invoices.finalize', $invoice));
        $invoice->refresh();

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));
        $response->assertOk();
        // The heading should show "Invoice INV-0001" — not "Draft"
        $response->assertSee('Invoice INV-0001', false);
        $response->assertDontSee('Draft #' . $invoice->id, false);
        $response->assertDontSee('Not yet issued', false);
    }

    public function test_displayNumber_handles_defensive_edge_cases(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create();

        // Draft + null number → "Draft #ID"
        $draft = Invoice::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'status' => 'draft',
            'invoice_number' => null,
        ]);
        $this->assertSame('Draft #' . $draft->id, $draft->displayNumber());

        // Final + valid number → invoice_number
        $final = Invoice::factory()->recycle($user)->finalized()->create([
            'company_id' => $company->id,
            'invoice_number' => 'INV-0042',
        ]);
        $this->assertSame('INV-0042', $final->displayNumber());

        // Final + null number (defensive — shouldn't normally happen but won't render blank)
        $orphan = Invoice::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'status' => 'final',
            'invoice_number' => null,
        ]);
        $this->assertSame('Draft #' . $orphan->id, $orphan->displayNumber());

        // Cancelled with a number → invoice_number (still legally referable)
        $cancelled = Invoice::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'status' => 'cancelled',
            'invoice_number' => 'INV-0099',
        ]);
        $this->assertSame('INV-0099', $cancelled->displayNumber());
    }
}
