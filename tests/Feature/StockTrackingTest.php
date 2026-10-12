<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opt-in stock: issued invoices take stock out, cancelling puts it back,
 * purchase invoices bring it in, adjustments cover the rest. Items that don't
 * track stock, and drafts, never move it.
 */
class StockTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;
    private Product $cement;
    private Product $service;

    protected function setUp(): void
    {
        parent::setUp();
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'invoice_prefix' => 'INV', 'invoice_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $mh->id]);

        $this->actingAs($this->user)->post(route('products.store'), [
            'name' => 'OPC Cement 50kg', 'kind' => 'goods', 'hsn_sac' => '25232910', 'unit' => 'BAG', 'rate' => 400, 'gst_rate' => 18,
            'is_active' => 1, 'track_stock' => 1, 'opening_stock' => 100, 'low_stock_level' => 20, 'purchase_rate' => 320,
        ])->assertSessionHasNoErrors();
        $this->cement = Product::where('name', 'OPC Cement 50kg')->firstOrFail();

        $this->service = Product::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'Delivery', 'kind' => 'service',
            'hsn_sac' => '996511', 'unit' => 'OTH', 'rate' => 500, 'gst_rate' => 5, 'is_active' => true,
        ]);
    }

    private function draft(float $qty): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->cement->id, 'description' => 'OPC Cement 50kg', 'hsn_sac' => '25232910', 'quantity' => $qty, 'unit' => 'BAG', 'rate' => 400, 'gst_rate' => 18],
                ['product_id' => $this->service->id, 'description' => 'Delivery', 'hsn_sac' => '996511', 'quantity' => 1, 'rate' => 500, 'gst_rate' => 5],
            ],
        ])->assertSessionHasNoErrors();

        return Invoice::latest('id')->firstOrFail();
    }

    public function test_the_item_is_saved_with_its_stock_settings(): void
    {
        $this->assertTrue($this->cement->track_stock);
        $this->assertEqualsWithDelta(100.0, $this->cement->stockOnHand(), 0.001);
        $this->assertEqualsWithDelta(320.0, (float) $this->cement->purchase_rate, 0.001);
    }

    public function test_a_draft_moves_nothing_and_issuing_takes_stock_out_once(): void
    {
        $inv = $this->draft(30);
        $this->assertSame(0, StockMovement::count(), 'a draft never moves stock');

        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $this->assertEqualsWithDelta(70.0, $this->cement->fresh()->stockOnHand(), 0.001);
        $this->assertSame(1, StockMovement::count(), 'the service line moves nothing');

        // A second "issue" (double click) does not take it out again.
        app(\App\Services\StockLedger::class)->recordInvoice($inv->fresh());
        $this->assertEqualsWithDelta(70.0, $this->cement->fresh()->stockOnHand(), 0.001);
    }

    public function test_cancelling_puts_the_stock_back(): void
    {
        $inv = $this->draft(30);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $this->actingAs($this->user)->post(route('invoices.cancel', $inv), ['cancellation_reason' => 'Wrong customer billed'])->assertRedirect();

        $this->assertEqualsWithDelta(100.0, $this->cement->fresh()->stockOnHand(), 0.001);
        $this->assertSame(['sale', 'sale_cancelled'], StockMovement::orderBy('id')->pluck('kind')->all());
    }

    public function test_a_purchase_invoice_brings_stock_in_and_edits_and_deletes_follow(): void
    {
        $vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'ACC Ltd', 'state_id' => $this->company->state_id, 'gstin' => '27PQRST1234U1Z1']);
        $payload = fn (float $qty) => [
            'vendor_id' => $vendor->id, 'bill_number' => 'ACC/9', 'bill_date' => now()->toDateString(), 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['product_id' => $this->cement->id, 'description' => 'Cement', 'hsn_sac' => '25232910', 'quantity' => $qty, 'unit' => 'BAG', 'rate' => 320, 'gst_rate' => 18]],
        ];
        $this->actingAs($this->user)->post(route('purchases.store'), $payload(50))->assertSessionHasNoErrors();
        $bill = PurchaseBill::firstOrFail();
        $this->assertEqualsWithDelta(150.0, $this->cement->fresh()->stockOnHand(), 0.001);
        $this->assertSame($this->cement->id, $bill->items->first()->product_id);

        $this->actingAs($this->user)->put(route('purchases.update', $bill), $payload(40))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(140.0, $this->cement->fresh()->stockOnHand(), 0.001, 'an edit re-posts, not adds');

        $this->actingAs($this->user)->delete(route('purchases.destroy', $bill));
        $this->assertEqualsWithDelta(100.0, $this->cement->fresh()->stockOnHand(), 0.001);
    }

    public function test_a_purchase_fills_a_missing_purchase_price_but_never_overwrites_one(): void
    {
        $rods = Product::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'TMT Rod 12mm', 'kind' => 'goods',
            'hsn_sac' => '72142090', 'unit' => 'KGS', 'rate' => 70, 'gst_rate' => 18, 'is_active' => true, 'track_stock' => true,
        ]);
        $vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'Steel Co', 'state_id' => $this->company->state_id]);
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'SC/1', 'bill_date' => now()->toDateString(), 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [
                ['product_id' => $rods->id, 'description' => 'TMT Rod', 'hsn_sac' => '72142090', 'quantity' => 100, 'unit' => 'KGS', 'rate' => 58, 'gst_rate' => 18],
                ['product_id' => $this->cement->id, 'description' => 'Cement', 'hsn_sac' => '25232910', 'quantity' => 10, 'unit' => 'BAG', 'rate' => 300, 'gst_rate' => 18],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(58.0, (float) $rods->fresh()->purchase_rate, 0.001, 'blank price taken from the bill');
        $this->assertEqualsWithDelta(320.0, (float) $this->cement->fresh()->purchase_rate, 0.001, 'entered price left alone');
    }

    public function test_the_items_list_summarises_stock_and_filters_low_and_out(): void
    {
        $empty = Product::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'White Cement 1kg', 'kind' => 'goods',
            'hsn_sac' => '25232100', 'unit' => 'NOS', 'rate' => 60, 'gst_rate' => 18, 'is_active' => true, 'track_stock' => true, 'opening_stock' => 0,
        ]);
        app(\App\Services\StockLedger::class)->adjust($this->cement, now(), -85, 'Damaged / expired', $this->user);

        // 15 bags at the 320 purchase price
        $this->actingAs($this->user)->get(route('products.index'))->assertOk()
            ->assertSee('Stock value')->assertSee('4,800');

        $this->actingAs($this->user)->get(route('products.index', ['stock' => 'low']))->assertOk()
            ->assertSee('OPC Cement 50kg')->assertDontSee('White Cement 1kg')->assertDontSee('>Delivery<', false);
        $this->actingAs($this->user)->get(route('products.index', ['stock' => 'out']))->assertOk()
            ->assertSee('White Cement 1kg')->assertDontSee('OPC Cement 50kg');
    }

    public function test_a_purchase_line_cannot_link_another_companys_item(): void
    {
        $stranger = Product::create([
            'user_id' => User::factory()->create()->id, 'company_id' => Company::factory()->create()->id, 'name' => 'Theirs',
            'kind' => 'goods', 'hsn_sac' => '25232910', 'unit' => 'BAG', 'rate' => 1, 'gst_rate' => 18, 'is_active' => true, 'track_stock' => true,
        ]);
        $vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'ACC Ltd', 'state_id' => $this->company->state_id]);
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'X1', 'bill_date' => now()->toDateString(), 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['product_id' => $stranger->id, 'description' => 'Cement', 'hsn_sac' => '25232910', 'quantity' => 10, 'unit' => 'BAG', 'rate' => 1, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();

        $this->assertNull(PurchaseBill::firstOrFail()->items->first()->product_id);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_adjustments_and_the_stock_page(): void
    {
        $this->actingAs($this->user)->post(route('products.stock.adjust', $this->cement), [
            'direction' => 'out', 'quantity' => 85, 'moved_on' => now()->toDateString(), 'reason' => 'Damaged / expired',
        ])->assertRedirect(route('products.stock', $this->cement));
        $this->assertEqualsWithDelta(15.0, $this->cement->fresh()->stockOnHand(), 0.001);
        $this->assertTrue($this->cement->fresh()->isLowOnStock());

        $this->actingAs($this->user)->get(route('products.stock', $this->cement))->assertOk()
            ->assertSee('Damaged / expired')->assertSee('Low stock, reorder');
        $this->actingAs($this->user)->get(route('products.index'))->assertOk()->assertSee('Low');
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee('OPC Cement 50kg is low on stock');

        $this->actingAs($this->user)->post(route('products.stock.adjust', $this->cement), [
            'direction' => 'in', 'quantity' => 0, 'moved_on' => now()->toDateString(), 'reason' => 'x',
        ])->assertSessionHasErrors('quantity');
    }

    public function test_the_stock_summary_report(): void
    {
        $inv = $this->draft(30);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));

        $csv = $this->actingAs($this->user)->get(route('reports.export', ['report' => 'stock-summary', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('OPC Cement 50kg', $csv);
        $this->assertStringContainsString('100,0,30,70', str_replace('"', '', $csv));
        $this->assertStringContainsString('22400.00', $csv, '70 bags valued at purchase price 320');
        $this->assertStringNotContainsString('Delivery', $csv);
    }

    public function test_items_that_do_not_track_stock_never_move(): void
    {
        $this->cement->update(['track_stock' => false]);
        $inv = $this->draft(30);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $this->assertSame(0, StockMovement::count());
    }

    public function test_the_purchase_form_puts_the_item_first_and_asks_for_it(): void
    {
        $this->cement->update(['sku' => 'OPC-50']);
        $html = $this->actingAs($this->user)->get(route('purchases.create'))->assertOk()
            ->assertSee('Pick the item to update stock')
            ->assertSee('Search name, SKU or HSN')
            ->getContent();
        // The items are on the page to filter by name, SKU or HSN as you type.
        $this->assertStringContainsString('OPC-50', $html);
        $this->assertStringContainsString('items[${idx}][product_id]', $html, 'field names unchanged');
    }

    public function test_a_purchase_of_goods_items_is_booked_as_stock_by_default(): void
    {
        $vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'ACC Ltd', 'state_id' => $this->company->state_id]);
        $line = ['description' => 'Cement', 'hsn_sac' => '25232910', 'quantity' => 5, 'unit' => 'BAG', 'rate' => 320, 'gst_rate' => 18];

        // No category chosen: a line linked to a goods item makes it a stock purchase.
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'ACC/1', 'bill_date' => now()->toDateString(), 'itc_eligible' => 1,
            'items' => [$line + ['product_id' => $this->cement->id]],
        ])->assertSessionHasNoErrors();
        $bill = PurchaseBill::where('bill_number', 'ACC/1')->firstOrFail();
        $this->assertSame('purchases', $bill->expense_category);
        $this->assertSame('purchases', \App\Models\Expense::findOrFail($bill->expense_id)->category);

        // Unlinked lines keep the old default; a category chosen by hand always wins.
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'ACC/2', 'bill_date' => now()->toDateString(), 'itc_eligible' => 1, 'items' => [$line],
        ])->assertSessionHasNoErrors();
        $this->assertSame('misc', PurchaseBill::where('bill_number', 'ACC/2')->value('expense_category'));
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'ACC/3', 'bill_date' => now()->toDateString(), 'itc_eligible' => 1, 'expense_category' => 'repairs',
            'items' => [$line + ['product_id' => $this->cement->id]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('repairs', PurchaseBill::where('bill_number', 'ACC/3')->value('expense_category'));
    }

    public function test_a_customer_return_reads_plainly_on_the_stock_page(): void
    {
        StockMovement::create([
            'company_id' => $this->company->id, 'product_id' => $this->cement->id, 'user_id' => $this->user->id,
            'moved_on' => now()->toDateString(), 'quantity' => 2, 'kind' => 'sale_return', 'note' => 'Credit note CRN-7',
        ]);
        $this->actingAs($this->user)->get(route('products.stock', $this->cement))->assertOk()
            ->assertSee('Returned by customer')->assertSee('Credit note CRN-7')
            ->assertSee('Balance');
    }

    public function test_the_search_endpoint_tells_the_invoice_line_what_is_in_stock(): void
    {
        $res = $this->actingAs($this->user)->getJson(route('products.search', ['q' => 'Cement']))->assertOk();
        $this->assertEqualsWithDelta(100.0, (float) $res->json('0.stock'), 0.001);
        $res = $this->actingAs($this->user)->getJson(route('products.search', ['q' => 'Delivery']))->assertOk();
        $this->assertNull($res->json('0.stock'));
    }
}
