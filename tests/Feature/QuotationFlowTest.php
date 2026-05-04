<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a self-consistent {user → company → customer} bundle that the
     * QuotationController can actually drive (needs company.state_id and a
     * customer that resolves through `ensureCompany()`).
     */
    private function setupOwner(array $companyOverrides = [], array $customerOverrides = []): array
    {
        $user = User::factory()->create();
        $companyState = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $customerState = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);

        $company = Company::factory()->create(array_merge([
            'user_id' => $user->id,
            'state_id' => $companyState->id,
        ], $companyOverrides));

        $user->forceFill(['active_company_id' => $company->id])->save();

        $customer = Customer::factory()->create(array_merge([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'state_id' => $customerState->id,
        ], $customerOverrides));

        return ['user' => $user, 'company' => $company, 'customer' => $customer];
    }

    public function test_user_can_create_a_draft_quotation(): void
    {
        ['user' => $user, 'customer' => $customer] = $this->setupOwner();

        $this->actingAs($user)->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'INR',
            'style' => 'classic',
            'items' => [[
                'description' => 'Website redesign',
                'hsn_sac' => '998314',
                'quantity' => 1,
                'unit' => 'NOS',
                'rate' => 50000,
                'discount' => 0,
                'gst_rate' => 18,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseCount('quotations', 1);
        $q = Quotation::first();
        $this->assertSame('draft', $q->status);
        $this->assertNull($q->quote_number);
        $this->assertSame(50000.0, (float) $q->subtotal);
        $this->assertSame(9000.0, (float) $q->total_igst); // interstate (MH→KA)
        $this->assertCount(1, $q->items);
    }

    public function test_quotation_persists_subject_reference_and_delivery_period(): void
    {
        ['user' => $user, 'customer' => $customer] = $this->setupOwner();

        $this->actingAs($user)->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subject' => 'Quotation for supply of office furniture',
            'reference' => 'Your enquiry ABC/RFQ/26-27/021 dated 12-Apr-2026',
            'delivery_period' => '15-20 working days from order confirmation',
            'currency' => 'INR',
            'items' => [[
                'description' => 'Executive desk',
                'hsn_sac' => '9403',
                'quantity' => 2,
                'rate' => 25000,
                'gst_rate' => 18,
            ]],
        ])->assertRedirect();

        $q = Quotation::first();
        $this->assertSame('Quotation for supply of office furniture', $q->subject);
        $this->assertSame('Your enquiry ABC/RFQ/26-27/021 dated 12-Apr-2026', $q->reference);
        $this->assertSame('15-20 working days from order confirmation', $q->delivery_period);
    }

    public function test_days_until_expiry_helper(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();

        $future = Quotation::factory()->sent()->create([
            'user_id' => $user->id, 'company_id' => $company->id, 'customer_id' => $customer->id,
            'valid_until' => now()->addDays(5)->toDateString(),
        ]);
        $expired = Quotation::factory()->sent()->create([
            'user_id' => $user->id, 'company_id' => $company->id, 'customer_id' => $customer->id,
            'valid_until' => now()->subDays(2)->toDateString(),
        ]);
        $noExpiry = Quotation::factory()->sent()->create([
            'user_id' => $user->id, 'company_id' => $company->id, 'customer_id' => $customer->id,
            'valid_until' => null,
        ]);

        $this->assertSame(5, $future->daysUntilExpiry());
        $this->assertSame(-2, $expired->daysUntilExpiry());
        $this->assertNull($noExpiry->daysUntilExpiry());
    }

    public function test_marking_as_sent_assigns_quote_number(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner([
            'quote_prefix' => 'QT',
            'quote_counter' => 0,
        ]);

        $q = Quotation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)->post(route('quotations.send', $q))->assertRedirect();

        $q->refresh();
        $this->assertSame('sent', $q->status);
        $this->assertSame('QT-0001', $q->quote_number);
        $this->assertNotNull($q->sent_at);
    }

    public function test_only_drafts_can_be_sent(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();

        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)->post(route('quotations.send', $q))->assertStatus(422);
    }

    public function test_sent_quotation_can_be_accepted(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)->post(route('quotations.accept', $q))->assertRedirect();
        $q->refresh();
        $this->assertSame('accepted', $q->status);
        $this->assertNotNull($q->accepted_at);
    }

    public function test_sent_quotation_can_be_declined_with_reason(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)
            ->post(route('quotations.decline', $q), ['decline_reason' => 'Going with someone else'])
            ->assertRedirect();

        $q->refresh();
        $this->assertSame('declined', $q->status);
        $this->assertSame('Going with someone else', $q->decline_reason);
    }

    public function test_quotation_converts_to_a_draft_invoice(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();

        $q = Quotation::factory()->accepted()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'subtotal' => 1000,
            'total_cgst' => 90,
            'total_sgst' => 90,
            'total_igst' => 0,
            'total_tax' => 180,
            'grand_total' => 1180,
        ]);
        QuotationItem::create([
            'quotation_id' => $q->id,
            'description' => 'Design',
            'hsn_sac' => '998314',
            'quantity' => 1,
            'unit' => 'NOS',
            'rate' => 1000,
            'discount' => 0,
            'gst_rate' => 18,
            'amount' => 1000,
            'cgst_amount' => 90,
            'sgst_amount' => 90,
            'igst_amount' => 0,
        ]);

        $this->actingAs($user)->post(route('quotations.convert', $q))->assertRedirect();

        $q->refresh();
        $this->assertSame('converted', $q->status);
        $this->assertNotNull($q->converted_at);
        $this->assertNotNull($q->converted_to_invoice_id);

        $invoice = Invoice::find($q->converted_to_invoice_id);
        $this->assertNotNull($invoice);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame(1180.0, (float) $invoice->grand_total);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('Design', $invoice->items->first()->description);
    }

    public function test_already_converted_quotation_cannot_be_converted_again(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();

        $q = Quotation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status' => 'converted',
        ]);

        $this->actingAs($user)->post(route('quotations.convert', $q))->assertStatus(422);
    }

    public function test_declined_quotation_cannot_be_converted(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();

        $q = Quotation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status' => 'declined',
        ]);

        $this->actingAs($user)->post(route('quotations.convert', $q))->assertStatus(422);
    }

    public function test_isExpired_flips_when_valid_until_passes_for_sent_quote(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();

        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($q->isExpired());
        $this->assertSame('expired', $q->effectiveStatus());
    }

    public function test_isExpired_returns_false_for_accepted_quote_even_after_validity(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();

        $q = Quotation::factory()->accepted()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($q->isExpired());
        $this->assertSame('accepted', $q->effectiveStatus());
    }

    public function test_draft_quotation_can_be_deleted_by_owner(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)->delete(route('quotations.destroy', $q))->assertRedirect();
        $this->assertDatabaseMissing('quotations', ['id' => $q->id]);
    }

    public function test_sent_quotation_cannot_be_deleted(): void
    {
        ['user' => $user, 'company' => $company, 'customer' => $customer] = $this->setupOwner();
        $q = Quotation::factory()->sent()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)->delete(route('quotations.destroy', $q))->assertRedirect();
        $this->assertDatabaseHas('quotations', ['id' => $q->id]);
    }

    // ─── Cross-user isolation ──────────────────────────────────────────────

    public function test_user_cannot_view_another_users_quotation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobQuote = Quotation::factory()->recycle($bob)->create();

        $this->actingAs($alice)->get(route('quotations.show', $bobQuote))->assertStatus(403);
    }

    public function test_user_cannot_edit_another_users_quotation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobQuote = Quotation::factory()->recycle($bob)->create();

        $this->actingAs($alice)->get(route('quotations.edit', $bobQuote))->assertStatus(403);
    }

    public function test_user_cannot_delete_another_users_quotation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobQuote = Quotation::factory()->recycle($bob)->create();

        $this->actingAs($alice)->delete(route('quotations.destroy', $bobQuote))->assertStatus(403);
        $this->assertDatabaseHas('quotations', ['id' => $bobQuote->id]);
    }

    public function test_user_cannot_send_another_users_quotation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobQuote = Quotation::factory()->recycle($bob)->create();

        $this->actingAs($alice)->post(route('quotations.send', $bobQuote))->assertStatus(403);
    }

    public function test_user_cannot_convert_another_users_quotation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobQuote = Quotation::factory()->recycle($bob)->accepted()->create();

        $this->actingAs($alice)->post(route('quotations.convert', $bobQuote))->assertStatus(403);
    }

    public function test_user_cannot_download_another_users_quotation_pdf(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobQuote = Quotation::factory()->recycle($bob)->create();

        $this->actingAs($alice)->get(route('quotations.pdf', $bobQuote))->assertStatus(403);
    }
}
