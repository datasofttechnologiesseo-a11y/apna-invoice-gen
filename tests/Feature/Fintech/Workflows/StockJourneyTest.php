<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock, vendors and purchases, walked the way a shop owner does it, through
 * the real web and mobile routes.
 *
 * The item: "Steel almirah" (HSN 94032010, NOS), sold at 12,000 @ 18%,
 * opening stock 10, reorder alert at 12, no purchase price entered.
 *
 * Movements (hand-worked; "on hand" after each):
 *   master   opening stock                                   10
 *   20 Sep   purchase GIS/0912   5 @ 8,000 (paid on the spot) 15  <- September
 *   02 Oct   purchase GIS/1002  20 @ 8,200                    35
 *   05 Oct   invoice            24 sold                       11  <- low (<= 12)
 *   08 Oct   adjustment out      1 damaged in transit         10
 *   10 Oct   credit note         3 returned (sales return)    13
 *   11 Oct   adjustment in       4 found in physical count    17
 *   12 Oct   invoice             2 sold, then cancelled   15 -> 17
 *
 * October stock summary (1-31 Oct):
 *   opening = 10 + 5                         = 15
 *   in      = 20 + 3 + 4 (+ 2 cancelled sale) = 27 (29)
 *   out     = 24 + 1     (+ 2 sale)           = 25 (27)
 *   closing = 15 + 27 - 25 = 17 (15 + 29 - 27 = 17)
 *   value   = 17 x 8,000 (first purchase price, never overwritten) = 1,36,000
 */
class StockJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        $this->biz = $this->makeBusiness();
        $this->owner = $this->ownerOf($this->biz);
    }

    // ------------------------------------------------------------------
    // Helpers (fixtures only - no expected value is computed here)
    // ------------------------------------------------------------------

    private function stranger(): Company
    {
        return $this->makeBusiness(['name' => 'Bengaluru Furnishers', 'state_id' => $this->state('29')->id, 'gstin' => '29ABCDE1234F1ZW']);
    }

    private function almirahLine(Product $p, float $qty): array
    {
        return $this->line(12000, 18, $qty, ['product_id' => $p->id, 'description' => 'Steel almirah', 'hsn_sac' => '94032010']);
    }

    private function billPayload(Vendor $v, Product $p, string $no, string $date, float $qty, float $rate, array $extra = []): array
    {
        return array_merge([
            'vendor_id' => $v->id, 'bill_number' => $no, 'bill_date' => $date, 'itc_eligible' => 1,
            'items' => [['product_id' => $p->id, 'description' => 'Steel almirah', 'hsn_sac' => '94032010', 'quantity' => $qty, 'unit' => 'NOS', 'rate' => $rate, 'gst_rate' => 18]],
        ], $extra);
    }

    private function stockSummaryRow(array $query): array
    {
        $rows = $this->csvRows($this->actingAs($this->owner)->get(route('reports.export', ['report' => 'stock-summary', 'format' => 'csv'] + $query))->assertOk());
        $this->assertSame(['Item', 'Item Code', 'HSN', 'Unit', 'Opening', 'In', 'Out', 'Closing', 'Status', 'Valued at', 'Stock Value'], $rows[4], 'column headings');
        foreach ($rows as $r) {
            if (($r[0] ?? '') === 'Steel almirah') {
                return $r;
            }
        }
        $this->fail('Steel almirah is missing from the stock summary');
    }

    private function vendorOf(Company $c, string $name = 'Godrej Interio Supplies', ?string $gstin = '27AAACT2727Q1ZW'): Vendor
    {
        return $c->vendors()->create(['user_id' => $c->user_id, 'name' => $name, 'gstin' => $gstin, 'state_id' => $c->state_id]);
    }

    // ------------------------------------------------------------------
    // The journey
    // ------------------------------------------------------------------

    public function test_buy_sell_adjust_return_and_cancel_keep_stock_and_the_month_summary_tied(): void
    {
        $customer = $this->customerOf($this->biz);

        // 1. The item, with stock tracking, from the New item page.
        $this->actingAs($this->owner)->get(route('products.create'))->assertOk()
            ->assertSee('New item')->assertSee('name="track_stock"', false)->assertSee('name="low_stock_level"', false);
        $this->actingAs($this->owner)->post(route('products.store'), [
            'name' => 'Steel almirah', 'kind' => 'goods', 'hsn_sac' => '94032010', 'unit' => 'NOS', 'rate' => 12000, 'gst_rate' => 18,
            'is_active' => 1, 'track_stock' => 1, 'opening_stock' => 10, 'low_stock_level' => 12,
        ])->assertRedirect(route('products.index'))->assertSessionHas('status', "Item 'Steel almirah' added.");
        $almirah = Product::where('company_id', $this->biz->id)->where('name', 'Steel almirah')->firstOrFail();
        $this->assertTrue($almirah->track_stock);
        $this->assertMoney(10, $almirah->stockOnHand());
        $this->assertNull($almirah->purchase_rate, 'no purchase price entered');

        // 2. The vendor, from the New vendor page. The state is read from the GSTIN.
        $this->actingAs($this->owner)->get(route('vendors.create'))->assertOk()->assertSee('New vendor');
        $this->actingAs($this->owner)->post(route('vendors.store'), [
            'name' => 'Godrej Interio Supplies', 'gstin' => '27aaact2727q1zw', 'phone' => '02024400000',
        ])->assertRedirect(route('vendors.index'))->assertSessionHas('status', 'Vendor Godrej Interio Supplies added.');
        $vendor = Vendor::where('company_id', $this->biz->id)->firstOrFail();
        $this->assertSame('27AAACT2727Q1ZW', $vendor->gstin, 'stored upper case');
        $this->assertSame($this->state('27')->id, (int) $vendor->state_id, 'state taken from the GSTIN prefix 27');

        // 3. September purchase, paid on the spot: 5 x 8,000 = 40,000; CGST 9% 3,600 + SGST 3,600; total 47,200.
        $this->actingAs($this->owner)->post(route('purchases.store'), $this->billPayload($vendor, $almirah, 'GIS/0912', '2026-09-20', 5, 8000, [
            'paid_now' => 47200, 'paid_on' => '2026-09-20', 'payment_method' => 'neft',
        ]))->assertSessionHasNoErrors();
        $sepBill = PurchaseBill::where('bill_number', 'GIS/0912')->firstOrFail();
        $this->assertMoney(40000, $sepBill->subtotal);
        $this->assertMoney(3600, $sepBill->total_cgst);
        $this->assertMoney(3600, $sepBill->total_sgst);
        $this->assertMoney(47200, $sepBill->grand_total);
        $this->assertSame('paid', $sepBill->status);
        $this->assertMoney(15, $almirah->fresh()->stockOnHand(), '10 + 5');
        // First purchase fills the blank purchase price: 40,000 / 5 = 8,000.
        $this->assertMoney(8000, $almirah->fresh()->purchase_rate);
        // 15 > 12: no alert any more.
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertDontSee('Steel almirah is low on stock');

        // 4. October purchase, on credit: 20 x 8,200 = 1,64,000; CGST 14,760 + SGST 14,760; total 1,93,520.
        $this->actingAs($this->owner)->post(route('purchases.store'), $this->billPayload($vendor, $almirah, 'GIS/1002', '2026-10-02', 20, 8200, [
            'due_date' => '2026-11-01',
        ]))->assertSessionHasNoErrors();
        $octBill = PurchaseBill::where('bill_number', 'GIS/1002')->firstOrFail();
        $this->assertMoney(164000, $octBill->subtotal);
        $this->assertMoney(14760, $octBill->total_cgst);
        $this->assertMoney(14760, $octBill->total_sgst);
        $this->assertMoney(193520, $octBill->grand_total);
        $this->assertMoney(193520, $octBill->balance);
        $this->assertSame('unpaid', $octBill->status);
        $this->assertMoney(35, $almirah->fresh()->stockOnHand(), '15 + 20');
        $this->assertMoney(8000, $almirah->fresh()->purchase_rate, 'a price already set is never overwritten by a later bill');

        // 5. Sell 24 on an issued invoice: 24 x 12,000 = 2,88,000; CGST 25,920 + SGST 25,920; grand 3,39,840.
        $sale = $this->webIssued($this->owner, $customer, [$this->almirahLine($almirah, 24)], ['invoice_date' => '2026-10-05']);
        $this->assertMoney(288000, $sale->subtotal);
        $this->assertMoney(25920, $sale->total_cgst);
        $this->assertMoney(25920, $sale->total_sgst);
        $this->assertMoney(339840, $sale->grand_total);
        $this->assertMoney(11, $almirah->fresh()->stockOnHand(), '35 - 24');
        $out = StockMovement::where('kind', 'sale')->firstOrFail();
        $this->assertMoney(-24, $out->quantity);
        $this->assertSame('2026-10-05', $out->moved_on->toDateString(), 'dated the invoice date');

        // 6. 11 <= 12: the dashboard says so, and its link lists the item.
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('Steel almirah is low on stock');
        $this->actingAs($this->owner)->get(route('reports.show', ['report' => 'stock-summary', 'status' => 'low']))->assertOk()->assertSee('Steel almirah');

        // 7. One damaged: adjust out 1 on 8 Oct.
        $this->actingAs($this->owner)->post(route('products.stock.adjust', $almirah), [
            'direction' => 'out', 'quantity' => 1, 'moved_on' => '2026-10-08', 'reason' => 'Damaged in transit',
        ])->assertRedirect(route('products.stock', $almirah))->assertSessionHas('status', 'Removed 1 NOS. In stock now: 10.');

        // 8. The customer returns 3 (credit note on the line). 3/24 of the line:
        //    taxable 2,88,000 x 3/24 = 36,000; CGST 25,920 x 3/24 = 3,240; SGST 3,240; note 42,480.
        $line = $sale->items()->firstOrFail();
        $this->actingAs($this->owner)->post(route('credit-notes.store', $sale), [
            'credit_note_date' => '2026-10-10', 'reason' => 'sales_return', 'lines' => [$line->id => 3],
        ])->assertRedirect(route('invoices.show', $sale))->assertSessionHasNoErrors();
        $cn = CreditNote::where('invoice_id', $sale->id)->firstOrFail();
        $this->assertSame('Credit note ' . $cn->credit_note_number . ' issued for ₹42,480', session('status'));
        $this->assertMoney(36000, $cn->taxable_value);
        $this->assertMoney(3240, $cn->total_cgst);
        $this->assertMoney(3240, $cn->total_sgst);
        $this->assertMoney(0, $cn->total_igst);
        $this->assertMoney(42480, $cn->amount);
        // Invoice owes 3,39,840 - 42,480 = 2,97,360; still "final" (nothing paid).
        $this->assertMoney(297360, $sale->fresh()->balance);
        $this->assertMoney(42480, $sale->fresh()->credited_amount);
        $this->assertSame('final', $sale->fresh()->status);
        $this->assertMoney(13, $almirah->fresh()->stockOnHand(), '10 + 3 back on the shelf');
        $back = StockMovement::where('kind', 'sale_return')->firstOrFail();
        $this->assertMoney(3, $back->quantity);
        $this->assertSame('2026-10-10', $back->moved_on->toDateString(), 'dated the credit note date');

        // 9. A physical count finds 4 more.
        $this->actingAs($this->owner)->post(route('products.stock.adjust', $almirah), [
            'direction' => 'in', 'quantity' => 4, 'moved_on' => '2026-10-11', 'reason' => 'Found in physical count',
        ])->assertSessionHas('status', 'Added 4 NOS. In stock now: 17.');
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertDontSee('Steel almirah is low on stock');

        // 10. The October summary ties: 15 + 27 - 25 = 17, valued 17 x 8,000 = 1,36,000.
        $row = $this->stockSummaryRow(['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertSame(['94032010', 'NOS', '15', '27', '25', '17', 'OK', '8000.00', '136000.00'], array_slice($row, 2));
        // September: 10 + 5 - 0 = 15.
        $row = $this->stockSummaryRow(['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertSame(['10', '5', '0', '15'], array_slice($row, 4, 4));

        // The item's stock page agrees, movement by movement.
        $page = $this->actingAs($this->owner)->get(route('products.stock', $almirah))->assertOk()
            ->assertSee('Damaged in transit')->assertSee('Found in physical count');
        $this->assertMoney(17, $page->viewData('onHand'));
        $this->assertSame([17.0, 13.0, 10.0, 11.0, 35.0, 15.0], $page->viewData('rows')->map(fn ($r) => (float) $r['balance'])->all(), 'running balance, newest first');

        // 11. Sell 2 today, then cancel: the 2 come back (cancelling restores stock).
        $second = $this->webIssued($this->owner, $customer, [$this->almirahLine($almirah, 2)]);
        $this->assertMoney(15, $almirah->fresh()->stockOnHand());
        $this->actingAs($this->owner)->post(route('invoices.cancel', $second), ['cancellation_reason' => 'Customer cancelled the order'])
            ->assertRedirect(route('invoices.show', $second))->assertSessionHas('status', 'Invoice cancelled. The record is kept for GST audit.');
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertMoney(17, $almirah->fresh()->stockOnHand(), '15 + 2 restored');
        $restored = StockMovement::where('kind', 'sale_cancelled')->firstOrFail();
        $this->assertMoney(2, $restored->quantity);
        // Cancelling twice (double click) restores nothing more.
        app(\App\Services\StockLedger::class)->reverseInvoice($second->fresh());
        $this->assertMoney(17, $almirah->fresh()->stockOnHand());

        // The month still ties with the cancelled sale on both sides: 15 + 29 - 27 = 17.
        $row = $this->stockSummaryRow(['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertSame(['15', '29', '27', '17'], array_slice($row, 4, 4));

        // Items list and the mobile app see the same stock and value.
        $this->actingAs($this->owner)->get(route('products.index'))->assertOk()->assertSee('Stock value')->assertSee('1,36,000');
        $this->apiAs($this->owner)->getJson("/api/products/{$almirah->id}")->assertOk()
            ->assertJsonPath('data.track_stock', true)->assertJsonPath('data.stock', 17)
            ->assertJsonPath('data.low_stock_level', 12);
        $this->assertSame(8, StockMovement::where('product_id', $almirah->id)->count(), '2 purchases, 2 sales, 1 cancel, 2 adjustments, 1 return');
    }

    // ------------------------------------------------------------------
    // Vendors
    // ------------------------------------------------------------------

    public function test_vendor_pages_store_edit_update_and_their_refusals(): void
    {
        // store refuses a GSTIN with a wrong check digit (last char W -> X).
        $this->actingAs($this->owner)->from(route('vendors.create'))->post(route('vendors.store'), [
            'name' => 'Bad GSTIN Traders', 'gstin' => '27AAACT2727Q1ZX',
        ])->assertRedirect(route('vendors.create'))->assertSessionHasErrors('gstin');
        $this->assertSame(0, Vendor::count());
        // The browser lands back on the form with what was typed still there.
        $this->actingAs($this->owner)->get(route('vendors.create'))->assertOk()->assertSee('Bad GSTIN Traders');

        $vendor = $this->vendorOf($this->biz);
        $this->actingAs($this->owner)->get(route('vendors.edit', $vendor))->assertOk()
            ->assertSee('Edit Godrej Interio Supplies')->assertSee('27AAACT2727Q1ZW');

        $this->actingAs($this->owner)->put(route('vendors.update', $vendor), [
            'name' => 'Godrej Interio Supplies Pvt Ltd', 'gstin' => '27AAACT2727Q1ZW', 'state_id' => $this->state('27')->id,
            'city' => 'Pune', 'phone' => '02024401111',
        ])->assertRedirect(route('vendors.index'))->assertSessionHas('status', 'Vendor Godrej Interio Supplies Pvt Ltd updated.');
        $vendor->refresh();
        $this->assertSame('Godrej Interio Supplies Pvt Ltd', $vendor->name);
        $this->assertSame('Pune', $vendor->city);

        // update refuses a blank name and changes nothing.
        $this->actingAs($this->owner)->from(route('vendors.edit', $vendor))->put(route('vendors.update', $vendor), [
            'name' => '', 'gstin' => '27AAACT2727Q1ZW',
        ])->assertRedirect(route('vendors.edit', $vendor))->assertSessionHasErrors('name');
        $this->assertSame('Godrej Interio Supplies Pvt Ltd', $vendor->fresh()->name);

        // Another business cannot open, change or delete it.
        $strangerOwner = $this->ownerOf($this->stranger());
        $this->actingAs($strangerOwner)->get(route('vendors.edit', $vendor))->assertForbidden();
        $this->actingAs($strangerOwner)->put(route('vendors.update', $vendor), ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($strangerOwner)->delete(route('vendors.destroy', $vendor))->assertForbidden();
        $this->assertSame('Godrej Interio Supplies Pvt Ltd', $vendor->fresh()->name);
    }

    public function test_the_new_vendor_page_is_closed_to_guests_and_billing_staff(): void
    {
        $this->get(route('vendors.create'))->assertRedirect(route('login'));

        $staff = User::factory()->create();
        CompanyMember::create([
            'company_id' => $this->biz->id, 'user_id' => $staff->id, 'email' => $staff->email, 'role' => 'staff',
            'token' => CompanyMember::newToken(), 'invited_by' => $this->owner->id, 'accepted_at' => now(),
        ]);
        $staff->forceFill(['active_company_id' => $this->biz->id])->save();

        $this->actingAs($staff)->get(route('vendors.create'))
            ->assertRedirect(route('invoices.index'))
            ->assertSessionHas('error', 'Your role in Sharma Traders (Billing staff) does not include this page. Ask the owner if you need it.');
    }

    public function test_a_vendor_with_purchase_invoices_cannot_be_deleted_one_without_can(): void
    {
        $vendor = $this->vendorOf($this->biz);
        $spare = $this->vendorOf($this->biz, 'One-off Supplier', null);
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'GIS/1', 'bill_date' => '2026-10-01', 'itc_eligible' => 1,
            'items' => [['description' => 'Office chairs', 'hsn_sac' => '94013000', 'quantity' => 2, 'unit' => 'NOS', 'rate' => 3000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->from(route('vendors.index'))->delete(route('vendors.destroy', $vendor))
            ->assertRedirect(route('vendors.index'))
            ->assertSessionHas('error', 'Godrej Interio Supplies has purchase invoices on record and cannot be deleted. Delete those purchase invoices first.');
        $this->assertNotNull(Vendor::find($vendor->id), 'kept: no orphan purchase invoices');
        $this->assertSame($vendor->id, (int) PurchaseBill::where('bill_number', 'GIS/1')->value('vendor_id'));

        $this->actingAs($this->owner)->delete(route('vendors.destroy', $spare))
            ->assertRedirect(route('vendors.index'))->assertSessionHas('status', 'Vendor One-off Supplier deleted.');
        $this->assertNull(Vendor::find($spare->id));
    }

    // ------------------------------------------------------------------
    // Purchase payments
    // ------------------------------------------------------------------

    public function test_removing_a_vendor_payment_restores_the_bill_balance_and_status(): void
    {
        $vendor = $this->vendorOf($this->biz);
        $chairs = ['description' => 'Office chairs', 'hsn_sac' => '94013000', 'unit' => 'NOS', 'gst_rate' => 18];
        // 20 x 8,200 = 1,64,000; CGST 14,760 + SGST 14,760; total 1,93,520.
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'GIS/1002', 'bill_date' => '2026-10-02', 'itc_eligible' => 1,
            'items' => [$chairs + ['quantity' => 20, 'rate' => 8200]],
        ])->assertSessionHasNoErrors();
        $bill = PurchaseBill::where('bill_number', 'GIS/1002')->firstOrFail();
        $this->assertMoney(193520, $bill->grand_total);

        $show = route('purchases.show', $bill);
        $this->actingAs($this->owner)->from($show)->post(route('purchases.payments.store', $bill), [
            'amount' => 100000, 'method' => 'neft', 'paid_on' => '2026-10-06', 'reference_number' => 'UTR-GIS-1',
        ])->assertRedirect($show)->assertSessionHas('status', 'Payment of ₹1,00,000 to Godrej Interio Supplies recorded.');
        $bill->refresh();
        // 1,93,520 - 1,00,000 = 93,520 left.
        $this->assertMoney(93520, $bill->balance);
        $this->assertSame('partial', $bill->status);

        $this->actingAs($this->owner)->from($show)->post(route('purchases.payments.store', $bill), [
            'amount' => 93520, 'method' => 'neft', 'paid_on' => '2026-10-09',
        ])->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertMoney(0, $bill->balance);
        $this->assertMoney(193520, $bill->paid_amount);
        $this->assertSame('paid', $bill->status);

        // Nothing is owed: one more rupee is refused.
        $this->actingAs($this->owner)->from($show)->post(route('purchases.payments.store', $bill), [
            'amount' => 1, 'method' => 'neft', 'paid_on' => '2026-10-10',
        ])->assertSessionHasErrors('amount');
        $this->assertSame(2, PurchasePayment::count());

        // Remove the 1,00,000: paid 93,520, balance back to 1,00,000, partly paid.
        $first = PurchasePayment::where('amount', 100000)->firstOrFail();
        $this->actingAs($this->owner)->from($show)->delete(route('purchases.payments.destroy', [$bill, $first]))
            ->assertRedirect($show)->assertSessionHas('status', 'Payment removed. Purchase invoice balance restored.');
        $bill->refresh();
        $this->assertNull(PurchasePayment::find($first->id));
        $this->assertMoney(93520, $bill->paid_amount);
        $this->assertMoney(100000, $bill->balance);
        $this->assertSame('partial', $bill->status);

        // Remove the other: back to unpaid in full.
        $second = PurchasePayment::firstOrFail();
        $this->actingAs($this->owner)->from($show)->delete(route('purchases.payments.destroy', [$bill, $second]))->assertRedirect($show);
        $bill->refresh();
        $this->assertMoney(0, $bill->paid_amount);
        $this->assertMoney(193520, $bill->balance);
        $this->assertSame('unpaid', $bill->status);
        $this->assertMoney(193520, $vendor->fresh()->payable(), 'the vendor list owes the whole bill again');
    }

    public function test_a_vendor_payment_cannot_be_removed_by_another_business_or_through_another_bill(): void
    {
        $vendor = $this->vendorOf($this->biz);
        $line = ['description' => 'Office chairs', 'hsn_sac' => '94013000', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18];
        // Each bill: 1,000 + CGST 90 + SGST 90 = 1,180, paid in full on the spot.
        foreach (['A/1', 'A/2'] as $no) {
            $this->actingAs($this->owner)->post(route('purchases.store'), [
                'vendor_id' => $vendor->id, 'bill_number' => $no, 'bill_date' => '2026-10-01', 'itc_eligible' => 1,
                'paid_now' => 1180, 'paid_on' => '2026-10-01', 'payment_method' => 'upi', 'items' => [$line],
            ])->assertSessionHasNoErrors();
        }
        $a1 = PurchaseBill::where('bill_number', 'A/1')->firstOrFail();
        $a2 = PurchaseBill::where('bill_number', 'A/2')->firstOrFail();
        $payA2 = PurchasePayment::where('purchase_bill_id', $a2->id)->firstOrFail();

        // Another business: refused.
        $strangerOwner = $this->ownerOf($this->stranger());
        $this->actingAs($strangerOwner)->delete(route('purchases.payments.destroy', [$a2, $payA2]))->assertForbidden();

        // A2's payment through A1's address: not found, nothing changes.
        $this->actingAs($this->owner)->delete(route('purchases.payments.destroy', [$a1, $payA2]))->assertNotFound();

        $this->assertNotNull(PurchasePayment::find($payA2->id));
        $this->assertSame('paid', $a1->fresh()->status);
        $this->assertSame('paid', $a2->fresh()->status);
        $this->assertMoney(0, $a2->fresh()->balance);
    }

    // ------------------------------------------------------------------
    // Item, customer pages and the mobile item endpoints
    // ------------------------------------------------------------------

    public function test_new_item_and_new_customer_pages_open_for_the_owner_not_for_a_guest(): void
    {
        $this->actingAs($this->owner)->get(route('products.create'))->assertOk()->assertSee('New item');
        $this->actingAs($this->owner)->get(route('customers.create'))->assertOk()->assertSee('New customer')->assertSee('name="gstin"', false);

        auth()->logout();
        $this->get(route('products.create'))->assertRedirect(route('login'));
        $this->get(route('customers.create'))->assertRedirect(route('login'));
    }

    public function test_the_edit_item_page_shows_the_stock_settings_and_is_closed_to_another_business(): void
    {
        $item = $this->productOf($this->biz, ['name' => 'Steel almirah', 'track_stock' => true, 'opening_stock' => 10, 'low_stock_level' => 12]);
        $html = $this->actingAs($this->owner)->get(route('products.edit', $item))->assertOk()->assertSee('Edit item')->getContent();
        $this->assertStringContainsString('Steel almirah', $html);
        $this->assertMatchesRegularExpression('/name="opening_stock"[^>]*value="10(\.0+)?"|value="10(\.0+)?"[^>]*name="opening_stock"/s', $html, 'opening stock prefilled');

        $strangerOwner = $this->ownerOf($this->stranger());
        $this->actingAs($strangerOwner)->get(route('products.edit', $item))->assertForbidden();
    }

    public function test_the_mobile_item_list_and_search_show_only_this_business_active_items(): void
    {
        $this->productOf($this->biz, ['name' => 'Steel almirah', 'track_stock' => true, 'opening_stock' => 7]);
        $this->productOf($this->biz, ['name' => 'Steel almirah (old model)', 'is_active' => false]);
        // The same owner's second business, not the active one.
        $branch = Company::factory()->create(['user_id' => $this->owner->id, 'name' => 'Sharma Traders Nashik', 'state_id' => $this->state('27')->id]);
        $this->productOf($branch, ['name' => 'Steel almirah Nashik']);
        $strangerItem = $this->productOf($this->stranger(), ['name' => 'Steel almirah Bengaluru']);

        $names = collect($this->apiAs($this->owner)->getJson('/api/products')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['Steel almirah'], $names);

        $found = $this->apiAs($this->owner)->getJson('/api/products/search?q=almirah')->assertOk()->json();
        $this->assertSame(['Steel almirah'], array_column($found, 'name'), 'archived, other branch and other business left out');

        // The archived ones are one switch away, still only this business.
        $archived = collect($this->apiAs($this->owner)->getJson('/api/products?only_inactive=1')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['Steel almirah (old model)'], $archived);

        // One item: its stock; another business's item: refused.
        $mine = Product::where('name', 'Steel almirah')->firstOrFail();
        $this->apiAs($this->owner)->getJson("/api/products/{$mine->id}")->assertOk()->assertJsonPath('data.stock', 7);
        $this->apiAs($this->owner)->getJson("/api/products/{$strangerItem->id}")->assertForbidden();
    }

    public function test_the_mobile_item_endpoints_need_a_token(): void
    {
        $item = $this->productOf($this->biz);
        $this->getJson('/api/products')->assertUnauthorized();
        $this->getJson('/api/products/search?q=almirah')->assertUnauthorized();
        $this->getJson("/api/products/{$item->id}")->assertUnauthorized();
    }

    // ------------------------------------------------------------------
    // Stock adjustment refusals
    // ------------------------------------------------------------------

    public function test_a_stock_adjustment_is_refused_for_another_business_a_future_date_or_an_untracked_item(): void
    {
        $tracked = $this->productOf($this->biz, ['name' => 'Steel almirah', 'track_stock' => true, 'opening_stock' => 10]);
        $untracked = $this->productOf($this->biz, ['name' => 'Fitting service', 'kind' => 'service', 'hsn_sac' => '998719']);
        $ok = ['direction' => 'out', 'quantity' => 2, 'moved_on' => '2026-10-12', 'reason' => 'Damaged'];

        $this->actingAs($this->ownerOf($this->stranger()))->post(route('products.stock.adjust', $tracked), $ok)->assertForbidden();
        $this->actingAs($this->owner)->post(route('products.stock.adjust', $tracked), ['moved_on' => '2026-10-13'] + $ok)
            ->assertSessionHasErrors('moved_on');
        $this->actingAs($this->owner)->post(route('products.stock.adjust', $untracked), $ok)->assertStatus(422);

        $this->assertSame(0, StockMovement::count());
        $this->assertMoney(10, $tracked->fresh()->stockOnHand());
    }

    // ------------------------------------------------------------------
    // Beyond the happy path
    // ------------------------------------------------------------------

    /**
     * An item bought on a purchase invoice (so it has stock history) but not
     * yet sold. The web archives such an item (ProductController::destroy
     * checks invoices, purchase invoices, stock entries and quotations). The
     * mobile app must do the same: deleting it would erase its stock ledger
     * (stock_movements cascade) and unlink it from the purchase invoice line.
     */
    public function test_the_mobile_app_archives_an_item_with_purchase_and_stock_history_instead_of_deleting_it(): void
    {
        $item = $this->productOf($this->biz, ['name' => 'Steel almirah', 'track_stock' => true, 'opening_stock' => 0]);
        $vendor = $this->vendorOf($this->biz);
        $this->actingAs($this->owner)->post(route('purchases.store'), $this->billPayload($vendor, $item, 'GIS/7', '2026-10-01', 5, 8000))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, StockMovement::where('product_id', $item->id)->count());

        $this->apiAs($this->owner)->deleteJson("/api/products/{$item->id}")->assertOk();

        $this->assertNotNull(Product::find($item->id), 'an item with purchase and stock history must be archived, not deleted');
        $this->assertFalse((bool) Product::find($item->id)?->is_active);
        $this->assertSame(1, StockMovement::where('product_id', $item->id)->count(), 'stock ledger kept');
        $this->assertSame($item->id, (int) PurchaseBill::where('bill_number', 'GIS/7')->firstOrFail()->items()->value('product_id'));
    }

    /**
     * An item that is archived (no longer sold) is not something to reorder.
     * The items list already leaves archived items out of its low/out counts;
     * the dashboard's "low on stock" alert should agree.
     */
    public function test_an_archived_item_does_not_raise_a_low_stock_alert_on_the_dashboard(): void
    {
        $item = $this->productOf($this->biz, ['name' => 'Old pattern almirah', 'track_stock' => true, 'opening_stock' => 1, 'low_stock_level' => 5]);
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('Old pattern almirah is low on stock');

        // Archive: it has a stock entry, so destroy archives rather than deletes.
        app(\App\Services\StockLedger::class)->adjust($item, '2026-10-01', -1, 'Sold off as scrap', $this->owner);
        $this->actingAs($this->owner)->delete(route('products.destroy', $item))->assertRedirect(route('products.index'));
        $this->assertFalse($item->fresh()->is_active);

        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertDontSee('Old pattern almirah is low on stock');
    }
}
