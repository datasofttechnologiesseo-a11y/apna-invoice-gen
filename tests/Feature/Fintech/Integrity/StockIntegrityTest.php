<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\StockMovement;
use App\Models\Vendor;
use App\Reports\StockSummary;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The stock identity, checked after every document event:
 *
 *     on hand = opening stock + sum(signed movements)
 *
 * and the Stock Summary report for ANY period agrees with it:
 *     report opening = opening stock + movements before the period
 *     report closing = report opening + in - out = on hand at the period end.
 *
 * Item: OPC Cement 50kg, tracked, opening stock 100 bags, sold at Rs 400 + 18%.
 */
class StockIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    private Product $cement;
    private Product $rods;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();

        $this->actingAs($this->user)->post(route('products.store'), [
            'name' => 'OPC Cement 50kg', 'kind' => 'goods', 'hsn_sac' => '25232910', 'unit' => 'BAG', 'rate' => 400, 'gst_rate' => 18,
            'is_active' => 1, 'track_stock' => 1, 'opening_stock' => 100, 'purchase_rate' => 320,
        ])->assertSessionHasNoErrors();
        $this->cement = Product::where('name', 'OPC Cement 50kg')->firstOrFail();

        $this->actingAs($this->user)->post(route('products.store'), [
            'name' => 'TMT Rod 12mm', 'kind' => 'goods', 'hsn_sac' => '72142090', 'unit' => 'NOS', 'rate' => 650, 'gst_rate' => 18,
            'is_active' => 1, 'track_stock' => 1, 'opening_stock' => 0,
        ])->assertSessionHasNoErrors();
        $this->rods = Product::where('name', 'TMT Rod 12mm')->firstOrFail();

        $this->vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'ACC Ltd', 'state_id' => $this->mh->id, 'gstin' => '27PQRST1234U1Z1']);
    }

    private function sell(float $qty, string $date = '2026-10-12'): Invoice
    {
        return $this->issued(['invoice_date' => $date], [
            ['product_id' => $this->cement->id, 'description' => 'OPC Cement 50kg', 'hsn_sac' => '25232910', 'quantity' => $qty, 'unit' => 'BAG', 'rate' => 400, 'gst_rate' => 18],
        ]);
    }

    /** on hand = opening + movements, three ways, and the open-ended report agrees. */
    private function assertLedgerTies(Product $p, float $expected): void
    {
        $p = $p->fresh();
        $sum = (float) StockMovement::where('product_id', $p->id)->sum('quantity');
        $this->assertEqualsWithDelta($expected, (float) $p->opening_stock + $sum, 0.0005, "{$p->name}: opening + movements");
        $this->assertEqualsWithDelta($expected, $p->stockOnHand(), 0.0005, "{$p->name}: on hand");
        $this->assertEqualsWithDelta($expected, app(StockLedger::class)->onHand($p), 0.0005, "{$p->name}: ledger");
        $this->assertEqualsWithDelta($expected, $this->summary($p)['closing'], 0.0005, "{$p->name}: stock summary closing");
    }

    /** One item's row of the Stock Summary report for a period. */
    private function summary(Product $p, ?string $from = null, ?string $to = null): array
    {
        $report = new StockSummary($this->company, Request::create('/reports/stock-summary', 'GET', array_filter(['from' => $from, 'to' => $to])));
        foreach ($report->rows() as $row) {
            if ($row['name'] === $p->name) {
                return $row;
            }
        }
        $this->fail("{$p->name} is missing from the stock summary");
    }

    private function assertPeriod(Product $p, ?string $from, ?string $to, float $opening, float $in, float $out, float $closing): void
    {
        $row = $this->summary($p, $from, $to);
        $label = "{$p->name} " . ($from ?? '...') . ' to ' . ($to ?? '...');
        $this->assertEqualsWithDelta($opening, $row['opening'], 0.0005, "$label opening");
        $this->assertEqualsWithDelta($in, $row['in'], 0.0005, "$label in");
        $this->assertEqualsWithDelta($out, $row['out'], 0.0005, "$label out");
        $this->assertEqualsWithDelta($closing, $row['closing'], 0.0005, "$label closing");
        $this->assertEqualsWithDelta($row['opening'] + $row['in'] - $row['out'], $row['closing'], 0.0005, "$label opening + in - out = closing");
    }

    public function test_issue_cancel_and_reissue_keep_stock_equal_to_opening_plus_movements(): void
    {
        $inv = $this->sell(30, '2026-10-05');               // 100 - 30 = 70
        $this->assertLedgerTies($this->cement, 70);

        $this->cancel($inv)->assertRedirect();              // back in on 12 Oct: 70 + 30 = 100
        $this->assertLedgerTies($this->cement, 100);
        $this->cancel($inv);                                // a second cancel puts nothing back twice
        $this->assertLedgerTies($this->cement, 100);

        // Re-issue: copy the cancelled invoice and issue the copy (dated 12 Oct).
        $this->actingAs($this->user)->post(route('invoices.duplicate', $inv))->assertRedirect();
        $this->issue(Invoice::latest('id')->firstOrFail());  // 100 - 30 = 70
        $this->assertLedgerTies($this->cement, 70);

        //                    from          to            opening  in   out  closing
        $this->assertPeriod($this->cement, '2026-10-01', '2026-10-31', 100, 30, 60, 70);
        $this->assertPeriod($this->cement, '2026-10-01', '2026-10-05', 100, 0, 30, 70);
        $this->assertPeriod($this->cement, '2026-10-06', '2026-10-11', 70, 0, 0, 70);
        $this->assertPeriod($this->cement, '2026-10-12', '2026-10-12', 70, 30, 30, 70);
        $this->assertEqualsWithDelta(100.0, app(StockLedger::class)->onHand($this->cement, '2026-10-04'), 0.0005);
        $this->assertEqualsWithDelta(70.0, app(StockLedger::class)->onHand($this->cement, '2026-10-05'), 0.0005);
    }

    public function test_editing_a_purchase_invoice_again_and_again_reposts_instead_of_piling_up(): void
    {
        $line = fn (Product $p, float $q) => ['product_id' => $p->id, 'description' => $p->name, 'hsn_sac' => $p->hsn_sac, 'quantity' => $q, 'unit' => $p->unit, 'rate' => 320, 'gst_rate' => 18];
        $body = fn (string $date, array $lines) => [
            'vendor_id' => $this->vendor->id, 'bill_number' => 'ACC/9', 'bill_date' => $date, 'itc_eligible' => 1, 'expense_category' => 'purchases', 'items' => $lines,
        ];

        $this->actingAs($this->user)->post(route('purchases.store'), $body('2026-10-02', [$line($this->cement, 50)]))->assertSessionHasNoErrors();
        $bill = PurchaseBill::firstOrFail();
        $this->assertLedgerTies($this->cement, 150);                     // 100 + 50

        $edit = fn (array $b) => $this->actingAs($this->user)->put(route('purchases.update', $bill), $b)->assertSessionHasNoErrors();

        $edit($body('2026-10-02', [$line($this->cement, 40)]));
        $this->assertLedgerTies($this->cement, 140);                     // 100 + 40, not 100 + 50 + 40

        $edit($body('2026-10-02', [$line($this->cement, 40), $line($this->cement, 5)]));
        $this->assertLedgerTies($this->cement, 145);                     // two lines of one item: 40 + 5

        $edit($body('2026-10-08', [$line($this->cement, 20)]));          // re-dated and cut
        $this->assertLedgerTies($this->cement, 120);
        $this->assertSame(['2026-10-08'], $bill->stockMovements()->get()->map(fn ($m) => $m->moved_on->toDateString())->all());
        $this->assertPeriod($this->cement, '2026-10-01', '2026-10-07', 100, 0, 0, 100);
        $this->assertPeriod($this->cement, '2026-10-08', '2026-10-31', 100, 20, 0, 120);

        $edit($body('2026-10-08', [$line($this->rods, 10)]));            // the line now buys rods, not cement
        $this->assertLedgerTies($this->cement, 100);
        $this->assertLedgerTies($this->rods, 10);

        $this->actingAs($this->user)->delete(route('purchases.destroy', $bill))->assertRedirect();
        $this->assertLedgerTies($this->cement, 100);
        $this->assertLedgerTies($this->rods, 0);
        $this->assertSame(0, StockMovement::count());
    }

    private function salesReturn(Invoice $inv, float $qty, string $reason = 'sales_return'): CreditNote
    {
        $itemId = $inv->items()->firstOrFail()->id;

        return $this->credit($inv, 0, ['amount' => null, 'reason' => $reason, 'lines' => [$itemId => $qty]]);
    }

    public function test_a_sales_return_puts_the_goods_back_and_reversing_it_takes_them_out_again(): void
    {
        // 30 x 400 = 12,000; CGST 9% 1,080; SGST 9% 1,080; total 14,160.
        $inv = $this->sell(30);
        $this->assertMoney(14160.00, $inv->grand_total);
        $this->assertLedgerTies($this->cement, 70);

        // 10 of 30 come back: 12,000 x 10/30 = 4,000; CGST 360; SGST 360; note 4,720.
        $cn = $this->salesReturn($inv, 10);
        $this->assertMoney(4720.00, $cn->amount);
        $this->assertMoney(9440.00, $inv->fresh()->balance);        // 14,160 - 4,720
        $this->assertLedgerTies($this->cement, 80);
        $this->assertSame('sale_return', StockMovement::where('source_id', $cn->id)->where('source_type', $cn->getMorphClass())->value('kind'));

        $this->actingAs($this->user)->delete(route('credit-notes.destroy', $cn))->assertRedirect();
        $this->assertLedgerTies($this->cement, 70);
        $this->assertMoney(14160.00, $inv->fresh()->balance);
    }

    public function test_reversing_a_sales_return_from_the_app_takes_the_goods_back_out(): void
    {
        $inv = $this->sell(30);
        $cn = $this->salesReturn($inv, 10);
        $this->assertLedgerTies($this->cement, 80);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/credit-notes/{$cn->id}")->assertOk();

        $this->assertNull(CreditNote::find($cn->id));
        $this->assertLedgerTies($this->cement, 70); // the 10 bags never came back once the return is undone
    }

    public function test_a_price_reduction_note_on_lines_moves_no_stock(): void
    {
        $inv = $this->sell(30);
        $this->salesReturn($inv, 10, 'rate_correction');

        $this->assertLedgerTies($this->cement, 70);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_adjustments_and_sales_tie_out_for_any_period(): void
    {
        $adjust = fn (string $dir, float $q, string $on) => $this->actingAs($this->user)->post(route('products.stock.adjust', $this->cement), [
            'direction' => $dir, 'quantity' => $q, 'moved_on' => $on, 'reason' => 'Physical count',
        ])->assertSessionHasNoErrors();

        $adjust('in', 20, '2026-10-01');    // +20
        $adjust('out', 5, '2026-10-03');    // -5
        $this->sell(12, '2026-10-05');      // -12
        $adjust('out', 2.5, '2026-10-07');  // -2.5
        $adjust('in', 0.75, '2026-10-10');  // +0.75

        // 100 + 20 - 5 - 12 - 2.5 + 0.75 = 101.25
        $this->assertLedgerTies($this->cement, 101.25);
        //                    from          to            opening  in     out    closing
        $this->assertPeriod($this->cement, '2026-09-01', '2026-09-30', 100, 0, 0, 100);
        $this->assertPeriod($this->cement, '2026-10-01', '2026-10-31', 100, 20.75, 19.5, 101.25);
        $this->assertPeriod($this->cement, '2026-10-04', '2026-10-07', 115, 0, 14.5, 100.5);   // 100+20-5 = 115; out 12 + 2.5
        $this->assertPeriod($this->cement, '2026-10-08', '2026-10-09', 100.5, 0, 0, 100.5);
        $this->assertPeriod($this->cement, '2026-10-10', null, 100.5, 0.75, 0, 101.25);
        $this->assertEqualsWithDelta(103.0, app(StockLedger::class)->onHand($this->cement, '2026-10-06'), 0.0005); // 100+20-5-12

        $this->actingAs($this->user)->get(route('reports.show', ['report' => 'stock-summary', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSee('OPC Cement 50kg');
    }

    public function test_every_movement_has_a_readable_kind(): void
    {
        $inv = $this->sell(30);
        $this->salesReturn($inv, 5);
        $this->actingAs($this->user)->post(route('products.stock.adjust', $this->cement), ['direction' => 'in', 'quantity' => 1, 'moved_on' => '2026-10-12', 'reason' => 'Count'])->assertSessionHasNoErrors();

        foreach (StockMovement::all() as $m) {
            $this->assertStringNotContainsString('_', $m->kindLabel(), "kind '{$m->kind}' shows as a raw code on the stock page");
        }
    }
}
