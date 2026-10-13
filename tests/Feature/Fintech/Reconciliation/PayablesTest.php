<?php

namespace Tests\Feature\Fintech\Reconciliation;

use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identity 4: what the business owes its vendors, read on 5 Apr 2027: bill
 * balances, Payables aging, the vendor list, the purchases screen, the
 * purchase register and the dashboard all say the same.
 */
class PayablesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    public function test_each_bill_balance_is_its_total_less_what_was_paid(): void
    {
        $failures = [];
        foreach ($this->bill as $k => $bill) {
            $bill->refresh();
            $paid = round((float) PurchasePayment::where('purchase_bill_id', $bill->id)->sum('amount'), 2);
            $this->check($failures, "{$k} paid = its payments", $paid, (float) $bill->paid_amount);
            $this->check($failures, "{$k} balance = total - paid", round((float) $bill->grand_total - $paid, 2), (float) $bill->balance);
        }
        // Vendor payments in FY 2026-27: B1 70,800 + B2 31,500 + B3 10,000 + B4 10,500 + B5 2,000
        // + B18 9,000 + B6 25,000 + B7 39,333 + B17 1,500 + B9 20,000 + B10 8,000 + B12 23,600
        // + B14 15,000 + B15 15,750 + B20 6,000 = 2,87,983.
        $register = $this->registerTotals('vendor-payments', '2026-04-01', '2027-03-31');
        $this->check($failures, 'vendor payment register FY (hand)', 287983.00, $register['amount']);
        $this->check($failures, 'vendor payment register = payments table', round((float) PurchasePayment::where('company_id', $this->company->id)->sum('amount'), 2), $register['amount']);
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_payables_aging_vendor_list_purchases_screen_and_dashboard_are_one_number(): void
    {
        // Open bills on 5 Apr 2027, days past due -> bucket:
        //  0-30   B16 5,900 (due 30-04-27, not due) + B19 13,020 (1 d)               =   18,920
        //  61-90  B13 70,800 (due 19-01-27, 76 d)                                    =   70,800
        //  90+    B2 31,500 + B8 31,500 + B9 15,400 + B11 1,18,000 + B18 50,000       = 2,46,400
        //  total 3,36,120: Mumbai Metals 92,100 (B9, B13, B16), Mysore Motors 2,44,020.
        $total = 336120.00;
        $failures = [];
        $this->check($failures, 'sum of bill balances', $total, (float) PurchaseBill::where('company_id', $this->company->id)->where('balance', '>', 0)->sum('balance'));

        $aging = $this->report('payables-aging');
        foreach (['total' => $total, 'b0_30' => 18920.00, 'b31_60' => 0.00, 'b61_90' => 70800.00, 'b90' => 246400.00] as $k => $v) {
            $this->check($failures, "Payables aging {$k}", $v, (float) $aging['totals'][$k]);
        }
        $rows = collect($aging['rows'])->keyBy('party');
        $this->check($failures, 'aging row Mumbai Metals', 92100.00, (float) $rows['Mumbai Metals']['total']);
        $this->check($failures, 'aging row Mysore Motors', 244020.00, (float) $rows['Mysore Motors']['total']);

        $vendors = collect($this->report('vendors')['rows'])->keyBy('name');
        $this->check($failures, 'vendor list payable total', $total, (float) $this->report('vendors')['totals']['payable']);
        $this->check($failures, 'vendor list Mumbai Metals', 92100.00, (float) $vendors['Mumbai Metals']['payable']);
        $this->check($failures, 'vendor list Mysore Motors', 244020.00, (float) $vendors['Mysore Motors']['payable']);
        $vendorScreen = $this->actingAs($this->user)->get(route('vendors.index'))->viewData('vendors');
        $this->check($failures, 'vendors screen payable', $total, (float) collect(method_exists($vendorScreen, 'items') ? $vendorScreen->items() : $vendorScreen)->sum('payable'));

        $summary = $this->actingAs($this->user)->get(route('purchases.index'))->viewData('summary');
        $this->check($failures, 'purchases screen payable', $total, (float) $summary['payable']);
        $this->check($failures, 'purchase register balance (all dates)', $total, (float) $this->report('purchase-register')['totals']['balance']);
        $stats = $this->actingAs($this->user)->get(route('dashboard'))->viewData('stats');
        $this->check($failures, 'dashboard payables', $total, (float) $stats['payables']);
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
