<?php

namespace Tests\Feature\Audit\Purchases;

use App\Models\Company;
use App\Models\Gstr2bEntry;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Gstr3bBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Audit repro: supplier invoices that arrive after their month is locked
 * (booked_on, s.16(4)) and the s.16(4) time limit itself.
 */
class LateBookedBillTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Company $company;
    private State $mh;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-12 11:00', 'Asia/Kolkata'));
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id, 'state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now(),
        ]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
        $this->vendor = $this->company->vendors()->create([
            'user_id' => $this->owner->id, 'name' => 'Ajmera Steel', 'state_id' => $this->mh->id, 'gstin' => '27PQRST1234U1Z1',
        ]);
    }

    private function postBill(string $number, string $date, float $rate = 10000): PurchaseBill
    {
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'vendor_id' => $this->vendor->id, 'bill_number' => $number, 'bill_date' => $date, 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Steel sheets', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => $rate, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();

        return PurchaseBill::where('bill_number', $number)->firstOrFail();
    }

    /**
     * CGST s.16(4): credit on an invoice of FY 2024-25 cannot be taken after
     * 30 Nov 2025. The app enforces the twin deadline on the sales side
     * (s.34(2), Invoice::creditNoteDeadline) but books a time-barred supplier
     * invoice as fully claimable in the current month's GSTR-3B.
     */
    public function test_time_barred_supplier_invoice_itc_is_not_claimed_in_current_3b(): void
    {
        $this->company->forceFill(['books_locked_until' => '2026-09-30'])->save();

        // A March 2025 bill (FY 2024-25) found in a drawer and entered on 12 Oct 2026.
        $bill = $this->postBill('AS/OLD/9', '2025-03-15');
        $this->assertSame('2026-10-12', $bill->booked_on?->toDateString(), 'booked late into October 2026');

        $oct = app(Gstr3bBuilder::class)->build($this->company, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        // The s.16(4) deadline for FY 2024-25 was 30 Nov 2025: nothing is claimable now.
        $this->assertEqualsWithDelta(0.0, (float) $oct['itc']['total'], 0.001,
            'GSTR-3B Oct 2026 claims ITC of ' . $oct['itc']['total'] . ' on a FY 2024-25 invoice, past the s.16(4) deadline (30 Nov 2025)');
    }

    /**
     * The purchases screen's "ITC this month" tile reads bill_date, while
     * GSTR-3B, the P&L and the Expenses screen all count a late-booked bill
     * in its booked month.
     */
    public function test_purchases_screen_itc_this_month_matches_gstr3b_for_a_late_booked_bill(): void
    {
        $this->company->forceFill(['books_locked_until' => '2026-09-30'])->save();
        $this->postBill('AS/77', '2026-09-25');

        $oct = app(Gstr3bBuilder::class)->build($this->company, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertEqualsWithDelta(1800.0, (float) $oct['itc']['total'], 0.001);

        $summary = $this->actingAs($this->owner)->get(route('purchases.index'))->assertOk()->viewData('summary');
        $this->assertEqualsWithDelta(1800.0, (float) $summary['itc_this_month'], 0.001,
            'Purchases screen "ITC this month" shows ' . $summary['itc_this_month'] . ' while October GSTR-3B claims 1800');
    }

    /**
     * Purchase Register filters by bill_date, so its ITC / reverse-charge
     * columns put a late-booked bill in the locked month, while GSTR-3B,
     * P&L and the expense ledger put it in the booked month.
     */
    public function test_purchase_register_itc_for_the_month_matches_gstr3b_for_a_late_booked_bill(): void
    {
        $this->company->forceFill(['books_locked_until' => '2026-09-30'])->save();
        $this->postBill('AS/77', '2026-09-25');

        $builder = app(Gstr3bBuilder::class);
        $sep3b = $builder->build($this->company, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        $oct3b = $builder->build($this->company, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $reg = fn (string $from, string $to) => (float) collect(
            $this->actingAs($this->owner)->get(route('reports.show', ['report' => 'purchase-register', 'from' => $from, 'to' => $to]))->assertOk()->viewData('rows')
        )->sum('itc');

        $sepReg = $reg('2026-09-01', '2026-09-30');
        $octReg = $reg('2026-10-01', '2026-10-31');

        $this->assertSame(
            ['sep' => (float) $sep3b['itc']['total'], 'oct' => (float) $oct3b['itc']['total']],
            ['sep' => $sepReg, 'oct' => $octReg],
            'Purchase Register ITC by month disagrees with GSTR-3B ITC by month for a late-booked bill'
        );
    }

    /**
     * The GSTR-2B check in GSTR-3B only looks at the 2B of the return's own
     * months. A September invoice booked late in October sits in September's
     * 2B, so October's check reports it "not in 2B" (hold the credit) and
     * overstates "cash if 2B only", though the credit is confirmed by 2B.
     */
    public function test_2b_check_finds_a_late_booked_bill_in_the_2b_of_its_own_month(): void
    {
        $this->company->forceFill(['books_locked_until' => '2026-09-30'])->save();
        // September 2B shows the supplier's invoice; October 2B was uploaded too.
        Gstr2bEntry::create([
            'company_id' => $this->company->id, 'period' => '2026-09', 'kind' => 'invoice', 'supplier_gstin' => '27PQRST1234U1Z1',
            'supplier_name' => 'Ajmera Steel', 'document_number' => 'AS/77', 'document_date' => '2026-09-25', 'document_value' => 11800,
            'taxable_value' => 10000, 'igst' => 0, 'cgst' => 900, 'sgst' => 900, 'cess' => 0, 'reverse_charge' => false, 'itc_available' => true,
        ]);
        Gstr2bEntry::create([
            'company_id' => $this->company->id, 'period' => '2026-10', 'kind' => 'invoice', 'supplier_gstin' => '27PQRST1234U1Z1',
            'supplier_name' => 'Ajmera Steel', 'document_number' => 'AS/80', 'document_date' => '2026-10-02', 'document_value' => 1180,
            'taxable_value' => 1000, 'igst' => 0, 'cgst' => 90, 'sgst' => 90, 'cess' => 0, 'reverse_charge' => false, 'itc_available' => true,
        ]);

        $this->postBill('AS/77', '2026-09-25');           // late-booked into October
        $this->postBill('AS/80', '2026-10-02', 1000);      // October's own bill

        $oct = app(Gstr3bBuilder::class)->build($this->company, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertNotNull($oct['itc_2b']);
        $this->assertEqualsWithDelta(1980.0, (float) $oct['itc']['total'], 0.001);

        $this->assertEqualsWithDelta(1980.0, (float) $oct['itc_2b']['in_2b']['total'], 0.001,
            'October 3B 2B-check: in_2b = ' . $oct['itc_2b']['in_2b']['total'] . ', not_in_2b = ' . $oct['itc_2b']['not_in_2b']['total']
            . ' - the late-booked AS/77 is in September 2B but is reported "not in 2B"');
    }
}
