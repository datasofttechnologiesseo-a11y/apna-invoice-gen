<?php

namespace Tests\Feature\Audit\Purchases;

use App\Models\Company;
use App\Models\Expense;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Services\Gstr3bBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Audit repro: GST on expenses and the expense voucher of a purchase invoice. */
class ExpenseItcAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Company $company;
    private State $mh;

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
    }

    /**
     * Intra-state expense GST is halved into CGST and SGST in GSTR-3B and each
     * half rounded up: an odd-paise total (₹9.01) is claimed as 4.51 + 4.51 =
     * ₹9.02, a paisa more than was paid and than the Expenses screen / P&L
     * show as claimable. Every odd-paise expense in the month adds to it.
     */
    public function test_3b_itc_on_an_odd_paise_intra_state_expense_is_not_more_than_the_gst_paid(): void
    {
        foreach ([['2026-10-03', 9.01], ['2026-10-04', 27.03], ['2026-10-05', 0.05]] as [$date, $gst]) {
            $this->actingAs($this->owner)->post(route('finance.expenses.store'), [
                'entry_date' => $date, 'category' => 'misc', 'description' => 'Courier', 'amount' => 100, 'gst_amount' => $gst,
                'itc_eligible' => '1', 'payment_method' => 'bank',
            ])->assertSessionHasNoErrors();
        }
        // 9.01 + 27.03 + 0.05 = 36.09 of GST paid, all intra-state, all eligible.
        $screen = $this->actingAs($this->owner)->get(route('finance.expenses', ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->viewData('summary');
        $this->assertEqualsWithDelta(36.09, (float) $screen['itc_claimable'], 0.001);

        $oct = app(Gstr3bBuilder::class)->build($this->company, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertEqualsWithDelta(36.09, (float) $oct['itc']['total'], 0.001,
            'GSTR-3B claims CGST ' . $oct['itc']['cgst'] . ' + SGST ' . $oct['itc']['sgst'] . ' = ' . $oct['itc']['total'] . ' on 36.09 of GST paid');
    }

    /**
     * The "Expense voucher" PDF offered on every expense row, including the
     * row mirrored from a purchase invoice, prints "Total Cash Out" = taxable
     * + GST. For an UNPAID reverse-charge bill nothing has been paid to the
     * vendor (and the vendor is never owed the tax), yet the voucher states
     * ₹11,800 cash out to the vendor, with "Payment: BANK".
     */
    public function test_the_expense_voucher_of_an_unpaid_reverse_charge_bill_does_not_print_cash_out(): void
    {
        $vendor = $this->company->vendors()->create(['user_id' => $this->owner->id, 'name' => 'Sharma Advocates', 'state_id' => $this->mh->id]);
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'LEG/5', 'bill_date' => '2026-10-05', 'itc_eligible' => 1, 'reverse_charge' => 1,
            'expense_category' => 'professional',
            'items' => [['description' => 'Legal fees', 'hsn_sac' => '998211', 'quantity' => 1, 'unit' => 'OTH', 'rate' => 10000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $bill = PurchaseBill::firstOrFail();
        $this->assertEqualsWithDelta(10000.0, (float) $bill->grand_total, 0.001, 'vendor is owed 10,000 under RCM');
        $this->assertEqualsWithDelta(0.0, (float) $bill->paid_amount, 0.001, 'and nothing has been paid');

        $expense = Expense::where('purchase_bill_id', $bill->id)->firstOrFail();
        $res = $this->actingAs($this->owner)->get(route('finance.expenses.pdf', ['expense' => $expense, 'inline' => 1]));

        if ($res->isRedirect()) {
            $this->assertTrue(true); // sent to the purchase invoice, as a cash memo row is

            return;
        }
        // Render the same voucher the PDF is built from, to read its figures.
        $expense->load('company.state');
        $html = view('finance.expense-voucher', ['expense' => $expense, 'amountInWords' => ''])->render();
        $text = preg_replace('/\s+/', ' ', strip_tags($html));
        $this->assertFalse(str_contains($text, 'Total Cash Out Rs. 11,800'),
            'Voucher for an unpaid RCM bill prints "Total Cash Out Rs. 11,800" and "Payment: bank" (vendor owed 10,000, paid 0)');
    }
}
