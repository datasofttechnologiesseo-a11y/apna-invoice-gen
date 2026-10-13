<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Purchase bills: supplier invoices received. The purchase side of GST -
 * input credit, reverse charge, payables - checked the way an accountant
 * would: by the money at the end of each flow.
 */
class PurchaseBillFlowTest extends TestCase
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
            'state_id' => $this->mh->id,
            'gstin' => '27ABCDE1234F1Z0',
            'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function vendorIn(State $state, array $extra = []): Vendor
    {
        return $this->company->vendors()->create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Deepcool India',
            'state_id' => $state->id,
        ], $extra));
    }

    private function billPayload(Vendor $vendor, array $overrides = []): array
    {
        return array_merge([
            'vendor_id' => $vendor->id,
            'bill_number' => 'DC/2026/0117',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'itc_eligible' => 1,
            'expense_category' => 'office_supplies',
            'payment_method' => 'neft',
            'items' => [
                ['description' => 'Cooling fans', 'hsn_sac' => '84145910', 'quantity' => 2, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18],
                ['description' => 'Thermal paste', 'hsn_sac' => '34031900', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 5],
            ],
        ], $overrides);
    }

    public function test_saved_vendors_are_offered_in_the_bill_form(): void
    {
        $this->vendorIn($this->mh, ['name' => 'Deepcool India', 'gstin' => '27ABCDE1234F1Z0']);
        $this->actingAs($this->user)->get(route('purchases.create'))->assertOk()
            ->assertSee('Deepcool India', false)->assertSee('27ABCDE1234F1Z0', false);
    }

    public function test_a_vendor_can_be_quick_added_from_the_bill_form(): void
    {
        $this->actingAs($this->user)->postJson(route('vendors.store'), [
            'name' => 'Sharma Traders', 'gstin' => '29ABCDE1234F1ZW', 'state_id' => $this->ka->id,
        ])->assertCreated()->assertJsonPath('name', 'Sharma Traders')->assertJsonPath('state_name', 'Karnataka');

        $this->assertSame(1, $this->company->vendors()->count());
    }

    public function test_a_vendor_gstin_must_match_its_state(): void
    {
        $this->actingAs($this->user)->post(route('vendors.store'), [
            'name' => 'Sharma Traders', 'gstin' => '29ABCDE1234F1ZW', 'state_id' => $this->mh->id,
        ])->assertSessionHasErrors('gstin');
    }

    public function test_a_same_state_bill_splits_cgst_sgst_and_mirrors_into_the_expense_ledger(): void
    {
        $vendor = $this->vendorIn($this->mh);

        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor))->assertRedirect();

        $bill = PurchaseBill::firstOrFail();
        // 10,000 @ 18% -> 900 + 900 ; 5,000 @ 5% -> 125 + 125
        $this->assertFalse((bool) $bill->is_interstate);
        $this->assertEqualsWithDelta(15000.0, (float) $bill->subtotal, 0.01);
        $this->assertEqualsWithDelta(1025.0, (float) $bill->total_cgst, 0.01);
        $this->assertEqualsWithDelta(1025.0, (float) $bill->total_sgst, 0.01);
        $this->assertEqualsWithDelta(2050.0, (float) $bill->total_tax, 0.01);
        $this->assertEqualsWithDelta(17050.0, (float) $bill->grand_total, 0.01);
        $this->assertEqualsWithDelta(17050.0, (float) $bill->balance, 0.01);
        $this->assertSame('unpaid', $bill->status);
        $this->assertCount(2, $bill->items);

        $expense = Expense::where('purchase_bill_id', $bill->id)->firstOrFail();
        $this->assertEqualsWithDelta(15000.0, (float) $expense->amount, 0.01);
        $this->assertEqualsWithDelta(2050.0, (float) $expense->gst_amount, 0.01);
        $this->assertTrue((bool) $expense->itc_eligible);
        $this->assertSame($expense->id, $bill->expense_id);
    }

    public function test_a_bill_from_another_state_carries_igst(): void
    {
        $vendor = $this->vendorIn($this->ka);

        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor))->assertRedirect();

        $bill = PurchaseBill::firstOrFail();
        $this->assertTrue((bool) $bill->is_interstate);
        $this->assertEqualsWithDelta(2050.0, (float) $bill->total_igst, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $bill->total_cgst, 0.01);
    }

    public function test_paid_now_books_a_payment_and_settles_the_bill(): void
    {
        $vendor = $this->vendorIn($this->mh);

        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor, [
            'paid_now' => 17050, 'paid_on' => now()->toDateString(), 'payment_method' => 'upi',
        ]))->assertRedirect();

        $bill = PurchaseBill::firstOrFail();
        $this->assertSame('paid', $bill->status);
        $this->assertEqualsWithDelta(0.0, (float) $bill->balance, 0.01);
        $this->assertCount(1, $bill->payments);
        $this->assertSame('upi', $bill->payments->first()->method);
    }

    public function test_partial_payments_move_the_status_and_cannot_overpay(): void
    {
        $vendor = $this->vendorIn($this->mh);
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor));
        $bill = PurchaseBill::firstOrFail();

        $this->actingAs($this->user)->post(route('purchases.payments.store', $bill), [
            'amount' => 7050, 'method' => 'neft', 'paid_on' => now()->toDateString(), 'reference_number' => 'UTR123',
        ])->assertRedirect();
        $bill->refresh();
        $this->assertSame('partial', $bill->status);
        $this->assertEqualsWithDelta(10000.0, (float) $bill->balance, 0.01);

        $this->actingAs($this->user)->post(route('purchases.payments.store', $bill), [
            'amount' => 10000.01, 'method' => 'neft', 'paid_on' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->user)->post(route('purchases.payments.store', $bill), [
            'amount' => 10000, 'method' => 'cash', 'paid_on' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_a_reverse_charge_bill_owes_the_vendor_only_the_taxable_value_and_reports_in_3b_3_1_d(): void
    {
        // s.9(3): e.g. a GTA bill. The transporter charges no tax; you pay
        // 5% to the government and (here) claim it back.
        $vendor = $this->vendorIn($this->mh, ['name' => 'Fast Freight GTA']);

        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor, [
            'reverse_charge' => 1,
            'items' => [['description' => 'Freight Mumbai-Pune', 'hsn_sac' => '996511', 'quantity' => 1, 'unit' => 'TRIP', 'rate' => 10000, 'gst_rate' => 5]],
        ]))->assertRedirect();

        $bill = PurchaseBill::firstOrFail();
        $this->assertTrue((bool) $bill->reverse_charge);
        $this->assertEqualsWithDelta(10000.0, (float) $bill->grand_total, 0.01, 'the vendor is owed the taxable value only');
        $this->assertEqualsWithDelta(250.0, (float) $bill->total_cgst, 0.01);
        $this->assertEqualsWithDelta(250.0, (float) $bill->total_sgst, 0.01);

        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSeeInOrder(['Inward supplies', '(liable to reverse charge)', '10,000', '0', '250', '250']);
    }

    public function test_gstr3b_counts_a_bill_once_not_also_its_mirrored_expense(): void
    {
        $vendor = $this->vendorIn($this->mh);
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor, [
            'items' => [['description' => 'Printer', 'hsn_sac' => '84433210', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ]));

        // The mirrored expense carries the same ₹1,800; counting both would
        // double the credit. Section 4 must read CGST 900 / SGST 900.
        $this->assertSame(1, Expense::where('purchase_bill_id', '!=', null)->count());
        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSeeInOrder(['All other ITC', '0', '900', '900']);
    }

    public function test_blocked_credit_is_left_out_of_itc(): void
    {
        $vendor = $this->vendorIn($this->mh);
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor, [
            'itc_eligible' => 0,
            'items' => [['description' => 'Staff lunch', 'hsn_sac' => '996331', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 5]],
        ]));

        $this->assertFalse((bool) PurchaseBill::firstOrFail()->itc_eligible);
        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSeeInOrder(['All other ITC', '0', '0', '0']);
    }

    public function test_a_bill_level_discount_is_spread_before_tax(): void
    {
        $vendor = $this->vendorIn($this->mh);
        // 10,000 @ 18% + 5,000 @ 5%, ₹1,500 off: 1,000 + 500 pro rata.
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor, ['bill_discount' => 1500]))->assertRedirect();

        $bill = PurchaseBill::firstOrFail();
        $this->assertEqualsWithDelta(13500.0, (float) $bill->subtotal, 0.01);
        $this->assertEqualsWithDelta(1845.0, (float) $bill->total_tax, 0.01); // 1,620 + 225
        $this->assertEqualsWithDelta(15345.0, (float) $bill->grand_total, 0.01);
    }

    public function test_deleting_a_bill_removes_its_expense_entry(): void
    {
        $vendor = $this->vendorIn($this->mh);
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor));
        $bill = PurchaseBill::firstOrFail();

        $this->actingAs($this->user)->delete(route('purchases.destroy', $bill))->assertRedirect();

        $this->assertSame(0, PurchaseBill::count());
        $this->assertSame(0, Expense::count());
    }

    public function test_another_users_bill_is_out_of_reach(): void
    {
        $vendor = $this->vendorIn($this->mh);
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor));
        $bill = PurchaseBill::firstOrFail();

        $stranger = User::factory()->create();
        Company::factory()->recycle($stranger)->create(['state_id' => $this->mh->id]);
        $stranger->forceFill(['active_company_id' => $stranger->companies()->first()->id])->save();

        $this->actingAs($stranger)->get(route('purchases.show', $bill))->assertForbidden();
        $this->actingAs($stranger)->get(route('purchases.index'))->assertOk()->assertDontSee('DC/2026/0117');
    }

    public function test_a_vendor_name_opens_that_vendors_purchase_invoices(): void
    {
        $deepcool = $this->vendorIn($this->mh);
        $other = $this->vendorIn($this->ka, ['name' => 'Bengaluru Metals']);
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($deepcool))->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($other, ['bill_number' => 'BM/77']))->assertSessionHasNoErrors();

        $this->actingAs($this->user)->get(route('vendors.index'))->assertOk()
            ->assertSee(route('purchases.index', ['vendor' => $deepcool->id]), false);
        $this->actingAs($this->user)->get(route('purchases.index', ['vendor' => $deepcool->id]))->assertOk()
            ->assertSee('Showing purchase invoices from')
            ->assertSee('DC/2026/0117')->assertDontSee('BM/77');
        // Another business's vendor id filters to nothing of theirs.
        $this->actingAs($this->user)->get(route('purchases.index', ['vendor' => 999999]))->assertOk()
            ->assertSee('DC/2026/0117')->assertSee('BM/77');
    }

    public function test_a_repeated_invoice_number_is_explained_under_its_field(): void
    {
        $vendor = $this->vendorIn($this->mh);
        $this->actingAs($this->user)->post(route('purchases.store'), $this->billPayload($vendor))->assertSessionHasNoErrors();
        $this->actingAs($this->user)->from(route('purchases.create'))->post(route('purchases.store'), $this->billPayload($vendor))
            ->assertSessionHasErrors(['bill_number' => 'This invoice number from this vendor is already recorded.']);
        $this->actingAs($this->user)->withViewErrors(['bill_number' => 'This invoice number from this vendor is already recorded.'])
            ->get(route('purchases.create'))->assertOk()
            ->assertSee('id="bill_number-error"', false);
    }
}
