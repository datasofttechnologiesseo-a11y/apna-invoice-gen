<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workflow audit.
 *
 * The existing suite covers controllers and units well. What it does not do is
 * walk a whole journey and then check the MONEY at the end of it. These tests
 * assert arithmetic and state, not HTTP 200 - a screen that renders while
 * quietly charging the wrong tax is worse than one that errors.
 *
 * Grouped by the workflow a real business actually performs.
 */
class WorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $mh;
    private State $ka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);

        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'name' => 'Sharma Exports',
            'state_id' => $this->mh->id,
            'gstin' => '27ABCDE1234F1Z5',
            'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function customerIn(State $state): Customer
    {
        return Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $state->id]);
    }

    private function createInvoice(array $items, ?Customer $customer = null, array $extra = []): Invoice
    {
        $customer ??= $this->customerIn($this->mh);

        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => $items,
        ], $extra))->assertRedirect();

        return Invoice::latest('id')->firstOrFail();
    }

    // ================================================ 1. GST determination

    public function test_same_state_sale_splits_cgst_and_sgst_and_charges_no_igst(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Consulting', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ], $this->customerIn($this->mh));

        $this->assertFalse((bool) $inv->is_interstate);
        $this->assertEqualsWithDelta(900.0, (float) $inv->total_cgst, 0.01);
        $this->assertEqualsWithDelta(900.0, (float) $inv->total_sgst, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_igst, 0.01);
        $this->assertEqualsWithDelta(11800.0, (float) $inv->grand_total, 0.01);
    }

    public function test_other_state_sale_charges_igst_only(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Consulting', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ], $this->customerIn($this->ka));

        $this->assertTrue((bool) $inv->is_interstate);
        $this->assertEqualsWithDelta(1800.0, (float) $inv->total_igst, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_cgst, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_sgst, 0.01);
        $this->assertEqualsWithDelta(11800.0, (float) $inv->grand_total, 0.01);
    }

    public function test_place_of_supply_is_recorded_from_the_customer(): void
    {
        // GSTR-1 reports by place of supply, so a wrong value here misfiles the
        // return even when the tax split is right.
        $inv = $this->createInvoice([
            ['description' => 'X', 'quantity' => 1, 'rate' => 100, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ], $this->customerIn($this->ka));

        $this->assertSame($this->ka->id, $inv->place_of_supply_state_id);
    }

    // ================================================ 2. Arithmetic invariants

    public function test_totals_reconcile_and_round_off_absorbs_the_difference(): void
    {
        // 3 x 333.33 @ 18% = 999.99 + 179.998 -> raw 1179.99, rounds to 1180.
        $inv = $this->createInvoice([
            ['description' => 'Odd', 'quantity' => 3, 'rate' => 333.33, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);

        $tax = (float) $inv->total_cgst + (float) $inv->total_sgst + (float) $inv->total_igst;
        $raw = (float) $inv->subtotal + $tax;

        $this->assertEqualsWithDelta(round($raw), (float) $inv->grand_total, 0.01,
            'grand total must be the rounded sum of taxable value and tax');
        $this->assertEqualsWithDelta((float) $inv->grand_total - $raw, (float) $inv->round_off, 0.01,
            'round_off must be exactly the rounding difference, or the invoice does not add up');
        $this->assertLessThanOrEqual(0.5, abs((float) $inv->round_off),
            'round_off should never exceed half a rupee');
    }

    public function test_a_pre_tax_discount_reduces_the_taxable_value_not_just_the_total(): void
    {
        // Section 15(3): a discount shown on the invoice comes off the taxable
        // value, so GST is charged on the reduced amount. Taxing the
        // pre-discount figure overcharges the customer and overstates output tax.
        $inv = $this->createInvoice([
            ['description' => 'Goods', 'quantity' => 1, 'rate' => 10000, 'discount' => 1000,
                'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);

        $this->assertEqualsWithDelta(9000.0, (float) $inv->subtotal, 0.01);
        $this->assertEqualsWithDelta(810.0, (float) $inv->total_cgst, 0.01);
        $this->assertEqualsWithDelta(810.0, (float) $inv->total_sgst, 0.01);
        $this->assertEqualsWithDelta(10620.0, (float) $inv->grand_total, 0.01);
    }

    public function test_a_discount_cannot_push_a_line_negative(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Goods', 'quantity' => 1, 'rate' => 500, 'discount' => 9999,
                'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);

        $this->assertEqualsWithDelta(0.0, (float) $inv->subtotal, 0.01);
        $this->assertGreaterThanOrEqual(0.0, (float) $inv->grand_total);
    }

    public function test_mixed_gst_rates_are_taxed_per_line(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Book', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 0, 'hsn_sac' => '4901'],
            ['description' => 'Service', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
            ['description' => 'Luxury', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 28, 'hsn_sac' => '8703'],
        ]);

        // 0 + 180 + 280 = 460 total tax, split evenly as CGST/SGST.
        $this->assertEqualsWithDelta(3000.0, (float) $inv->subtotal, 0.01);
        $this->assertEqualsWithDelta(230.0, (float) $inv->total_cgst, 0.01);
        $this->assertEqualsWithDelta(230.0, (float) $inv->total_sgst, 0.01);
        $this->assertEqualsWithDelta(3460.0, (float) $inv->grand_total, 0.01);
    }

    public function test_reverse_charge_collects_no_tax_but_keeps_the_rate_on_the_line(): void
    {
        // Under RCM the recipient self-assesses, so the supplier must not
        // collect - but the rate has to stay visible so they know what to remit.
        $inv = $this->createInvoice([
            ['description' => 'Freight', 'quantity' => 1, 'rate' => 5000, 'gst_rate' => 5, 'hsn_sac' => '996511'],
        ], null, ['reverse_charge' => 1]);

        $this->assertEqualsWithDelta(0.0, (float) $inv->total_cgst, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_sgst, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_igst, 0.01);
        $this->assertEqualsWithDelta(5000.0, (float) $inv->grand_total, 0.01);
        $this->assertEqualsWithDelta(5.0, (float) $inv->items()->first()->gst_rate, 0.01);
    }

    public function test_line_totals_sum_to_the_invoice_total(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'A', 'quantity' => 2, 'rate' => 149.5, 'gst_rate' => 12, 'hsn_sac' => '998314'],
            ['description' => 'B', 'quantity' => 7, 'rate' => 33.33, 'gst_rate' => 18, 'hsn_sac' => '998314'],
            ['description' => 'C', 'quantity' => 1, 'rate' => 0.99, 'gst_rate' => 5, 'hsn_sac' => '998314'],
        ]);

        $lineSum = round($inv->items->sum(fn ($i) => (float) $i->total), 2);
        $this->assertEqualsWithDelta($lineSum + (float) $inv->round_off, (float) $inv->grand_total, 0.01,
            'the sum of the lines plus round-off must equal the invoice total');
    }

    // ================================================ 3. Issue / state machine

    public function test_issuing_assigns_a_number_and_locks_the_document(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'X', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);

        $this->assertTrue($inv->isDraft());
        $this->assertNull($inv->invoice_number);

        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertRedirect();
        $inv->refresh();

        $this->assertNotNull($inv->invoice_number);
        $this->assertNotNull($inv->finalized_at);
        $this->assertFalse($inv->isDraft());
    }

    public function test_an_issued_invoice_cannot_be_issued_twice(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'X', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $inv->refresh();
        $first = $inv->invoice_number;

        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));

        $this->assertSame($first, $inv->fresh()->invoice_number,
            'a second issue must not burn another number');
    }

    public function test_numbers_are_sequential_with_no_gaps_or_duplicates(): void
    {
        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $inv = $this->createInvoice([
                ['description' => "Item {$i}", 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
            ]);
            $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
            $numbers[] = $inv->fresh()->invoice_number;
        }

        $this->assertCount(5, array_unique($numbers), 'invoice numbers must be unique');

        $trailing = array_map(fn ($n) => (int) preg_replace('/[^0-9]/', '', substr($n, -6)), $numbers);
        for ($i = 1; $i < count($trailing); $i++) {
            $this->assertSame($trailing[$i - 1] + 1, $trailing[$i],
                'the series must advance by exactly one, with no gaps');
        }
    }

    // ================================================ 4. Payments

    public function test_partial_then_full_payment_moves_the_status_correctly(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'X', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $inv->refresh();
        $total = (float) $inv->grand_total;

        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => 5000, 'received_at' => now()->toDateString(), 'method' => 'upi',
        ])->assertRedirect();

        $inv->refresh();
        $this->assertEqualsWithDelta(5000.0, (float) $inv->paid_amount, 0.01);
        $this->assertEqualsWithDelta($total - 5000.0, (float) $inv->balance, 0.01);
        $this->assertSame('partially_paid', $inv->status);

        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => $total - 5000, 'received_at' => now()->toDateString(), 'method' => 'upi',
        ])->assertRedirect();

        $inv->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $inv->balance, 0.01);
        $this->assertSame('paid', $inv->status);
    }

    public function test_a_payment_larger_than_the_balance_is_refused(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'X', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $inv->refresh();

        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => (float) $inv->grand_total + 5000,
            'received_at' => now()->toDateString(), 'method' => 'cash',
        ]);

        $inv->refresh();
        $this->assertLessThanOrEqual((float) $inv->grand_total, (float) $inv->paid_amount,
            'a business should never be able to record more receipts than the invoice is worth');
    }

    // ================================================ 5. Returns

    public function test_gstr1_outward_matches_the_issued_invoices_and_excludes_drafts(): void
    {
        $issued = $this->createInvoice([
            ['description' => 'Sold', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);
        $this->actingAs($this->user)->post(route('invoices.finalize', $issued));

        // a draft that must NOT appear in the return
        $this->createInvoice([
            ['description' => 'Draft', 'quantity' => 1, 'rate' => 99999, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);

        $res = $this->actingAs($this->user)->get(route('invoices.gstr1'));
        $res->assertOk();

        $body = $res->streamedContent();
        $this->assertStringContainsString('10000', str_replace(',', '', $body),
            'the issued invoice should be in the GSTR-1 export');
        $this->assertStringNotContainsString('99999', str_replace(',', '', $body),
            'a draft must never reach a GST return');
    }

    public function test_a_cancelled_invoice_leaves_the_return(): void
    {
        $inv = $this->createInvoice([
            ['description' => 'Sold', 'quantity' => 1, 'rate' => 12345, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ]);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));

        $this->actingAs($this->user)->post(route('invoices.cancel', $inv), [
            'cancellation_reason' => 'Issued in error, before despatch.',
        ]);

        $inv->refresh();
        $this->assertSame('cancelled', $inv->status);

        $body = str_replace(',', '',
            $this->actingAs($this->user)->get(route('invoices.gstr1'))->streamedContent());
        $this->assertStringNotContainsString('12345', $body,
            'a cancelled invoice must not be reported as an outward supply');
    }

    // ================================================ 6. Full journey

    public function test_a_business_can_go_from_nothing_to_a_paid_invoice(): void
    {
        $customer = $this->customerIn($this->ka);

        $inv = $this->createInvoice([
            ['description' => 'Website build', 'quantity' => 1, 'rate' => 50000, 'gst_rate' => 18, 'hsn_sac' => '998314'],
        ], $customer);

        // issue
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertRedirect();
        $inv->refresh();
        $this->assertNotNull($inv->invoice_number);
        $this->assertEqualsWithDelta(9000.0, (float) $inv->total_igst, 0.01);
        $this->assertEqualsWithDelta(59000.0, (float) $inv->grand_total, 0.01);

        // it renders on screen and as a PDF
        $this->actingAs($this->user)->get(route('invoices.show', $inv))->assertOk();
        $pdf = $this->actingAs($this->user)->get(route('invoices.pdf', $inv));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // paid in full
        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => 59000, 'received_at' => now()->toDateString(), 'method' => 'neft',
        ])->assertRedirect();

        $inv->refresh();
        $this->assertSame('paid', $inv->status);
        $this->assertEqualsWithDelta(0.0, (float) $inv->balance, 0.01);
    }
}
