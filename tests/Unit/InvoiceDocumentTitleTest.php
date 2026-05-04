<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three-way title rule for invoices:
 *
 *   • No GSTIN on company        → "Invoice"        (unregistered seller)
 *   • Composition dealer flag    → "Bill of Supply" (CGST Rule 49)
 *   • Otherwise                  → "Tax Invoice"    (CGST Rule 46)
 *
 * Drives heading + PDF + print view + email subject; one source of truth.
 */
class InvoiceDocumentTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_unregistered_seller_gets_plain_invoice_title(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'gstin' => null,
            'composition_dealer' => false,
        ]);
        $invoice = Invoice::factory()->recycle($user)->create(['company_id' => $company->id]);

        $this->assertSame('Invoice', $invoice->fresh()->documentTitle());
    }

    public function test_unregistered_seller_with_empty_gstin_gets_plain_invoice_title(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'gstin' => '',
            'composition_dealer' => false,
        ]);
        $invoice = Invoice::factory()->recycle($user)->create(['company_id' => $company->id]);

        $this->assertSame('Invoice', $invoice->fresh()->documentTitle());
    }

    public function test_registered_seller_gets_tax_invoice_title(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'gstin' => '27AAAPL1234C1Z5',
            'composition_dealer' => false,
        ]);
        $invoice = Invoice::factory()->recycle($user)->create(['company_id' => $company->id]);

        $this->assertSame('Tax Invoice', $invoice->fresh()->documentTitle());
    }

    public function test_composition_dealer_gets_bill_of_supply_title(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'gstin' => '27AAAPL1234C1Z5',
            'composition_dealer' => true,
        ]);
        $invoice = Invoice::factory()->recycle($user)->create(['company_id' => $company->id]);

        $this->assertSame('Bill of Supply', $invoice->fresh()->documentTitle());
    }

    public function test_composition_dealer_without_gstin_still_falls_back_to_invoice(): void
    {
        // Defensive: an unregistered seller can't legally be a composition dealer
        // (composition is a registration scheme), but if the data is malformed we
        // pick the most-conservative title — "Invoice" — because there's no
        // GSTIN to legally support a Bill of Supply either.
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'gstin' => null,
            'composition_dealer' => true,
        ]);
        $invoice = Invoice::factory()->recycle($user)->create(['company_id' => $company->id]);

        $this->assertSame('Invoice', $invoice->fresh()->documentTitle());
    }
}
