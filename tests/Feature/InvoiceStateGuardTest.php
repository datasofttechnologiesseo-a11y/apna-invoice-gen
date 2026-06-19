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
 * GST place-of-supply integrity guard.
 *
 * When the company's own state is unknown, the calculator cannot tell an
 * intra-state sale (CGST/SGST) from an inter-state one (IGST) and defaults to
 * intra-state — so a taxed invoice could otherwise be issued with the wrong
 * tax heads. finalize() must block until the state is set.
 */
class InvoiceStateGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeTaxedDraftWithoutCompanyState(): array
    {
        $user = User::factory()->create();
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);

        // Registered company, but state NOT set — the risky condition.
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'gstin' => '27ABCDE1234F1Z5',
            'composition_dealer' => false,
            'state_id' => null,
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
            'total_cgst' => 90, 'total_sgst' => 90, 'total_igst' => 0, 'total_tax' => 180,
            'grand_total' => 1180,
            'paid_amount' => 0,
            'balance' => 1180,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Service A',
            'hsn_sac' => '998314',
            'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'discount' => 0, 'gst_rate' => 18,
            'amount' => 1000, 'cgst_amount' => 90, 'sgst_amount' => 90, 'igst_amount' => 0,
        ]);

        return ['user' => $user, 'company' => $company, 'invoice' => $invoice, 'state' => $state];
    }

    public function test_finalize_is_blocked_when_company_state_missing(): void
    {
        ['user' => $user, 'invoice' => $invoice] = $this->makeTaxedDraftWithoutCompanyState();

        $this->actingAs($user)
            ->post(route('invoices.finalize', $invoice))
            ->assertSessionHas('error');

        $invoice->refresh();
        $this->assertTrue($invoice->isDraft(), 'Taxed invoice must stay a draft until the company state is set');
        $this->assertNull($invoice->invoice_number);
    }

    public function test_finalize_succeeds_once_company_state_is_set(): void
    {
        ['user' => $user, 'company' => $company, 'invoice' => $invoice, 'state' => $state]
            = $this->makeTaxedDraftWithoutCompanyState();

        $company->forceFill(['state_id' => $state->id])->save();

        $this->actingAs($user)
            ->post(route('invoices.finalize', $invoice))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertFalse($invoice->isDraft());
        $this->assertSame('INV-0001', $invoice->invoice_number);
        $this->assertNotNull($invoice->finalized_at);
    }
}
