<?php

namespace Tests\Feature\Audit\Purchases;

use App\Models\Company;
use App\Models\State;
use App\Models\User;
use App\Services\Gstr3bBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Audit repro: second proviso to s.16(2) read with Rule 37 - credit on a
 * supplier invoice not paid within 180 days of its date must be reversed
 * (GSTR-3B Table 4(B)(2)) and is re-availed when paid. The app records
 * every vendor payment, but GSTR-3B's 4(B) row is hard-coded 0 and nothing
 * in the builder looks at unpaid age, so the credit stays claimed.
 */
class Rule37ReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_on_a_supplier_invoice_unpaid_for_180_days_is_reversed(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 11:00', 'Asia/Kolkata'));
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now()]);
        $owner->forceFill(['active_company_id' => $company->id])->save();
        $vendor = $company->vendors()->create(['user_id' => $owner->id, 'name' => 'Ajmera Steel', 'state_id' => $mh->id, 'gstin' => '27AAPFU0939F1ZV']);

        // 1 Mar 2026: bill of 10,000 + 1,800, credit claimed in March, never paid.
        $this->actingAs($owner)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'AS/1', 'bill_date' => '2026-03-01', 'due_date' => '2026-03-31', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Steel sheets', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();

        $builder = app(Gstr3bBuilder::class);
        $this->assertEqualsWithDelta(1800.0, (float) $builder->build($company, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'))['itc']['total'], 0.001);

        // 180 days run out on 28 Aug 2026; the bill is still unpaid in September.
        $this->travelTo(Carbon::parse('2026-09-15 11:00', 'Asia/Kolkata'));
        $aug = $builder->build($company, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        // The 1,800 must come back (4(B)(2)).
        $this->assertEqualsWithDelta(1800.0, (float) $aug['itc_reversed']['total'], 0.001,
            'August 2026 GSTR-3B: no Rule 37 reversal for a bill unpaid 180 days');
        // March had no sales, so that credit was never used: it is still in the
        // credit ledger in August and the reversal simply takes it out again.
        $this->assertEqualsWithDelta(1800.0, (float) $aug['itc_carry_in']['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $aug['netCash']['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $aug['itc_carried']['total'], 0.001);
    }

    public function test_a_reversal_of_credit_already_used_is_paid_in_cash(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 11:00', 'Asia/Kolkata'));
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now()]);
        $owner->forceFill(['active_company_id' => $company->id])->save();
        $vendor = $company->vendors()->create(['user_id' => $owner->id, 'name' => 'Ajmera Steel', 'state_id' => $mh->id, 'gstin' => '27AAPFU0939F1ZV']);
        $buyer = \App\Models\Customer::factory()->create(['user_id' => $owner->id, 'company_id' => $company->id, 'state_id' => $mh->id, 'gstin' => '27AAACB1234C1Z9']);

        $this->actingAs($owner)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'AS/1', 'bill_date' => '2026-03-01', 'due_date' => '2026-03-31', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Steel sheets', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        // A March sale with the same tax uses the March credit in full.
        $this->actingAs($owner)->post(route('invoices.store'), [
            'customer_id' => $buyer->id, 'invoice_date' => '2026-03-01', 'due_date' => '2026-03-31',
            'items' => [['description' => 'Racks', 'hsn_sac' => '94032090', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('invoices.finalize', \App\Models\Invoice::latest('id')->first()))->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-09-15 11:00', 'Asia/Kolkata'));
        $aug = app(Gstr3bBuilder::class)->build($company, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertEqualsWithDelta(0.0, (float) $aug['itc_carry_in']['total'], 0.001);
        $this->assertEqualsWithDelta(1800.0, (float) $aug['netCash']['total'], 0.001, 'credit used in March, reversed in August: paid in cash');
    }
}
