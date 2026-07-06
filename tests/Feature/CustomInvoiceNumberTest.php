<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Custom invoice numbers — available only to non-GST businesses (Rule 46
 * binds GST-registered suppliers to a consecutive series). Blank = auto.
 * Custom numbers never advance the auto counter.
 */
class CustomInvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        // Factory default company has NO gstin → the custom-number path.
        $this->company = Company::factory()->recycle($this->user)->create();
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->recycle($this->user)->recycle($this->company)->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 0],
            ],
        ], $overrides);
    }

    public function test_non_gst_business_can_set_custom_number_and_it_survives_finalize(): void
    {
        $counterBefore = $this->company->fresh()->invoice_counter;

        $this->actingAs($this->user)
            ->post(route('invoices.store'), $this->payload(['invoice_number' => 'SHOP-2026-42']))
            ->assertSessionHasNoErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('SHOP-2026-42', $invoice->invoice_number);
        $this->assertTrue($invoice->isDraft());

        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice));

        $invoice->refresh();
        $this->assertSame('SHOP-2026-42', $invoice->invoice_number);
        $this->assertNotNull($invoice->finalized_at);
        // The auto counter must NOT advance for a custom number.
        $this->assertSame($counterBefore, $this->company->fresh()->invoice_counter);
    }

    public function test_duplicate_custom_number_is_rejected(): void
    {
        Invoice::factory()->recycle($this->user)->recycle($this->company)
            ->create(['invoice_number' => 'SHOP-2026-42']);

        $this->actingAs($this->user)
            ->post(route('invoices.store'), $this->payload(['invoice_number' => 'SHOP-2026-42']))
            ->assertSessionHasErrors('invoice_number');
    }

    public function test_auto_numbering_skips_a_number_claimed_by_a_custom_invoice(): void
    {
        // Squat the exact number the auto counter would produce next.
        $nextAuto = $this->company->nextInvoiceNumber(now()->toDateString());
        $this->actingAs($this->user)
            ->post(route('invoices.store'), $this->payload(['invoice_number' => $nextAuto]))
            ->assertSessionHasNoErrors();
        $squatter = Invoice::latest('id')->first();
        $this->actingAs($this->user)->post(route('invoices.finalize', $squatter));

        // A blank-numbered invoice must finalize cleanly with a DIFFERENT number.
        $this->actingAs($this->user)
            ->post(route('invoices.store'), $this->payload())
            ->assertSessionHasNoErrors();
        $auto = Invoice::latest('id')->first();
        $this->actingAs($this->user)->post(route('invoices.finalize', $auto))
            ->assertSessionHasNoErrors();

        $auto->refresh();
        $this->assertNotNull($auto->invoice_number);
        $this->assertNotSame($nextAuto, $auto->invoice_number);
    }

    public function test_gst_registered_business_cannot_set_custom_number(): void
    {
        // Give the company a GSTIN matching its state code so ValidGstin-style
        // constraints elsewhere stay coherent; here only presence matters.
        $this->company->forceFill(['gstin' => '27AAPFU0939F1ZV'])->save();

        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload([
            'invoice_number' => 'HACK-001',
            // GSTIN present → HSN becomes required on items.
            'items' => [
                ['description' => 'Service', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
            ],
        ]))->assertSessionHasNoErrors();

        $invoice = Invoice::latest('id')->first();
        // The submitted number is excluded by validation — draft stays unnumbered.
        $this->assertNull($invoice->invoice_number);

        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice));
        $invoice->refresh();
        // Auto series number assigned, not the smuggled one.
        $this->assertNotNull($invoice->invoice_number);
        $this->assertNotSame('HACK-001', $invoice->invoice_number);
    }
}
