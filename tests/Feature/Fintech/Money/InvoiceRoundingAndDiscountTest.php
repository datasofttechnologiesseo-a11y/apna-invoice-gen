<?php

namespace Tests\Feature\Fintech\Money;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-line rounding over many lines, decimal quantities and prices, discounts
 * (per line and invoice-level spread pro rata) and the rupee round-off,
 * through the real store and finalize routes.
 */
class InvoiceRoundingAndDiscountTest extends TestCase
{
    use MoneyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootMoney('27');
    }

    // ---------------------------------------------------------------- rounding

    public function test_fifty_lines_accumulate_per_line_rounding_and_round_half_up(): void
    {
        // 50 lines of 1 x 10.01 @ 18%, intra-state.
        // Per line: 9% of 10.01 = 0.9009 -> CGST 0.90, SGST 0.90, tax 1.80.
        // 50 lines: taxable 500.50; CGST 45.00; SGST 45.00; tax 90.00.
        // (18% of the whole 500.50 would be 90.09: the 9 paise are lost to
        // per-line rounding, which is how the e-invoice computes it.)
        // Raw total 590.50 is exactly half a rupee: rounds UP to 591, +0.50.
        $items = array_map(fn ($n) => $this->line(10.01, 18, 1, ['description' => "Part {$n}"]), range(1, 50));
        $inv = $this->issue($items, $this->customerIn('27'));

        $this->assertCount(50, $inv->items);
        $this->assertMoney(500.50, $inv->subtotal);
        $this->assertMoney(45.00, $inv->total_cgst);
        $this->assertMoney(45.00, $inv->total_sgst);
        $this->assertMoney(90.00, $inv->total_tax);
        $this->assertMoney(0.50, $inv->round_off);
        $this->assertMoney(591.00, $inv->grand_total);
        $this->assertInvoiceAddsUp($inv);

        // Lines keep their order 1..50 on the document.
        $this->assertSame('Part 1', $inv->items->first()->description);
        $this->assertSame('Part 50', $inv->items->last()->description);
    }

    public function test_fifty_mixed_lines_add_up_inter_state(): void
    {
        // Line n (1..50): qty n x 1.37 @ 18% IGST.
        // Taxable_n = 1.37n exactly (2 dp). IGST_n = round(0.2466n, 2).
        // Sum taxable = 1.37 x 1275 = 1,746.75.
        // Sum IGST worked out line by line (0.2466n rounded):
        //   n=1..50 -> see $expectedIgst below (computed by hand-rule, not the app).
        $expectedIgst = 0.0;
        $items = [];
        foreach (range(1, 50) as $n) {
            $items[] = $this->line(1.37, 18, $n, ['description' => "Bolt lot {$n}"]);
            $expectedIgst += round(0.2466 * $n + 1e-9, 2); // half-up to the paisa
        }
        $expectedIgst = round($expectedIgst, 2);
        $inv = $this->issue($items, $this->customerIn('29'));

        $this->assertMoney(1746.75, $inv->subtotal);
        $this->assertMoney($expectedIgst, $inv->total_igst);
        $this->assertMoney(round(1746.75 + $expectedIgst), $inv->grand_total);
        $this->assertInvoiceAddsUp($inv);
    }

    public function test_round_off_goes_down_below_half_and_up_from_half(): void
    {
        $c = $this->customerIn('27');

        // 0% lines so the raw total is just the value.
        // 100.49 -> 100, round off -0.49
        $down = $this->issue([$this->line(100.49, 0)], $c);
        $this->assertMoney(100.00, $down->grand_total);
        $this->assertMoney(-0.49, $down->round_off);

        // 100.51 -> 101, +0.49
        $up = $this->issue([$this->line(100.51, 0)], $c);
        $this->assertMoney(101.00, $up->grand_total);
        $this->assertMoney(0.49, $up->round_off);

        // exactly 100.50 -> 101, +0.50 (half rounds up, never to even)
        $half = $this->issue([$this->line(100.50, 0)], $c);
        $this->assertMoney(101.00, $half->grand_total);
        $this->assertMoney(0.50, $half->round_off);

        // 101.50 -> 102 (banker's rounding would give 102 too; 102.50 -> 103 tells them apart)
        $half2 = $this->issue([$this->line(102.50, 0)], $c);
        $this->assertMoney(103.00, $half2->grand_total);
        $this->assertMoney(0.50, $half2->round_off);

        // With tax: 84.75 @ 18% -> 9% = 7.6275 -> 7.63 each; raw 100.01 -> 100, -0.01.
        $tax = $this->issue([$this->line(84.75, 18)], $c);
        $this->assertMoney(100.00, $tax->grand_total);
        $this->assertMoney(-0.01, $tax->round_off);

        foreach ([$down, $up, $half, $half2, $tax] as $inv) {
            $this->assertInvoiceAddsUp($inv);
        }
    }

    // ---------------------------------------------------------------- quantities and prices

    public function test_decimal_quantities_are_priced_to_the_gram(): void
    {
        // 0.001 kg x 1,00,000/kg = 100.00 @ 18% -> CGST 9.00, SGST 9.00.
        // 2.5 kg x 47.30 = 118.25 @ 5% -> 2.5% = 2.95625 -> 2.96 each.
        // Taxable 218.25; CGST 11.96; SGST 11.96; tax 23.92; raw 242.17 -> 242, -0.17.
        $inv = $this->issue([
            $this->line(100000, 18, 0.001, ['unit' => 'KGS', 'hsn_sac' => '71081300']),
            $this->line(47.30, 5, 2.5, ['unit' => 'KGS', 'hsn_sac' => '09024020']),
        ], $this->customerIn('27'));

        [$gold, $tea] = $inv->items->values();
        $this->assertMoney(0.001, $gold->quantity);
        $this->assertMoney(100.00, $gold->amount);
        $this->assertMoney(9.00, $gold->cgst_amount);
        $this->assertMoney(2.5, $tea->quantity);
        $this->assertMoney(118.25, $tea->amount);
        $this->assertMoney(2.96, $tea->cgst_amount);
        $this->assertMoney(218.25, $inv->subtotal);
        $this->assertMoney(23.92, $inv->total_tax);
        $this->assertMoney(-0.17, $inv->round_off);
        $this->assertMoney(242.00, $inv->grand_total);
        $this->assertInvoiceAddsUp($inv);
    }

    public function test_a_quantity_below_one_gram_is_refused(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customerIn('27')->id, 'invoice_date' => now()->toDateString(),
            'items' => [$this->line(100, 18, 0.0004)],
        ])->assertSessionHasErrors('items.0.quantity');
    }

    public function test_a_price_with_four_decimals_prints_a_rate_that_reproduces_the_line_value(): void
    {
        // A screw at ₹0.1234 each, 1,000 pieces: 1,000 x 0.1234 = 123.40.
        // Whatever the app does with the 4th decimal, the document must add
        // up: the printed Rate x Qty has to give the printed taxable value
        // (or the price must be refused). Storing the rate as 0.12 while the
        // value stays 123.40 prints "1,000 x 0.12 = 123.40" (0.12 x 1,000 is 120.00).
        $customer = $this->customerIn('27');
        $response = $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(),
            'items' => [$this->line(0.1234, 18, 1000, ['unit' => 'PCS', 'hsn_sac' => '73181500'])],
        ]);

        if (session('errors')?->has('items.0.rate')) {
            $this->assertSame(0, Invoice::count(), 'refused: nothing saved');

            return;
        }
        $item = Invoice::latest('id')->firstOrFail()->items()->firstOrFail();
        $this->assertMoney(123.40, $item->amount, '1,000 x 0.1234');
        $this->assertEqualsWithDelta(
            (float) $item->amount,
            round((float) $item->rate * (float) $item->quantity, 2),
            0.005,
            'stored rate ' . $item->rate . ' x qty ' . $item->quantity . ' must reproduce the taxable value ' . $item->amount
        );
    }

    public function test_a_quantity_with_four_decimals_prints_a_quantity_that_reproduces_the_line_value(): void
    {
        // 1.2345 kg x 100.00 = 123.45. Quantity is stored to 3 decimals; if it
        // is kept as 1.235 the document reads 1.235 x 100 = 123.50, not 123.45.
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customerIn('27')->id, 'invoice_date' => now()->toDateString(),
            'items' => [$this->line(100, 5, 1.2345, ['unit' => 'KGS', 'hsn_sac' => '09024020'])],
        ]);

        if (session('errors')?->has('items.0.quantity')) {
            $this->assertSame(0, Invoice::count());

            return;
        }
        $item = Invoice::latest('id')->firstOrFail()->items()->firstOrFail();
        $this->assertEqualsWithDelta(
            (float) $item->amount,
            round((float) $item->rate * (float) $item->quantity, 2),
            0.005,
            'stored qty ' . $item->quantity . ' x rate ' . $item->rate . ' must reproduce the taxable value ' . $item->amount
        );
    }

    // ---------------------------------------------------------------- discounts

    public function test_a_per_line_discount_reduces_that_line_before_tax(): void
    {
        // API-style per-line discount (no invoice_discount key).
        // A: 3 x 400 = 1,200 less 150 = 1,050 @ 18% -> 94.50 each head.
        // B: 1 x 999.99, no discount @ 5% -> 2.5% = 24.99975 -> 25.00 each.
        // Taxable 2,049.99; CGST 119.50; SGST 119.50; tax 239.00; raw 2,288.99 -> 2,289 (+0.01).
        $inv = $this->issue([
            $this->line(400, 18, 3, ['discount' => 150]),
            $this->line(999.99, 5),
        ], $this->customerIn('27'));

        [$a, $b] = $inv->items->values();
        $this->assertMoney(150.00, $a->discount);
        $this->assertMoney(1050.00, $a->amount);
        $this->assertMoney(94.50, $a->cgst_amount);
        $this->assertMoney(0.00, $b->discount);
        $this->assertMoney(25.00, $b->cgst_amount);
        $this->assertMoney(2049.99, $inv->subtotal);
        $this->assertMoney(239.00, $inv->total_tax);
        $this->assertMoney(0.01, $inv->round_off);
        $this->assertMoney(2289.00, $inv->grand_total);
        $this->assertInvoiceAddsUp($inv);
    }

    public function test_an_invoice_discount_is_spread_pro_rata_and_the_last_line_takes_the_remainder(): void
    {
        // Lines: A 1,000 @ 18%, B 2,000 @ 5%, C 3,000 @ 12%; gross 6,000; discount 100.00.
        // A share = 100 x 1,000/6,000 = 16.666.. -> 16.67
        // B share = 100 x 2,000/6,000 = 33.333.. -> 33.33
        // C (last) = 100 - 16.67 - 33.33 = 50.00            (sum exactly 100.00)
        // Taxable: A 983.33, B 1,966.67, C 2,950.00 = 5,900.00
        // A 9% of 983.33 = 88.4997 -> 88.50 ; B 2.5% of 1,966.67 = 49.16675 -> 49.17 ;
        // C 6% of 2,950 = 177.00.  CGST = SGST = 314.67; tax 629.34.
        // Raw 6,529.34 -> 6,529, round off -0.34.
        $inv = $this->issue([
            $this->line(1000, 18), $this->line(2000, 5), $this->line(3000, 12),
        ], $this->customerIn('27'), ['invoice_discount' => 100]);

        [$a, $b, $c] = $inv->items->values();
        $this->assertMoney(16.67, $a->discount);
        $this->assertMoney(33.33, $b->discount);
        $this->assertMoney(50.00, $c->discount);
        $this->assertMoney(100.00, $inv->items->sum(fn ($i) => (float) $i->discount));
        $this->assertMoney(88.50, $a->cgst_amount);
        $this->assertMoney(49.17, $b->cgst_amount);
        $this->assertMoney(177.00, $c->cgst_amount);
        $this->assertMoney(5900.00, $inv->subtotal);
        $this->assertMoney(314.67, $inv->total_cgst);
        $this->assertMoney(629.34, $inv->total_tax);
        $this->assertMoney(-0.34, $inv->round_off);
        $this->assertMoney(6529.00, $inv->grand_total);
        $this->assertInvoiceAddsUp($inv);
    }

    public function test_an_invoice_discount_adds_back_exactly_even_when_the_last_line_is_tiny(): void
    {
        // Nine lines of ₹1,000 and a ₹1 carry bag last; ₹8,995 off the bill.
        // Gross 9,001. Each ₹1,000 line: 8,995 x 1,000 / 9,001 = 999.3334 -> 999.33,
        // nine of them 8,993.97. The remainder 1.03 is more than the bag's ₹1,
        // so the bag can only take 1.00 and 3 paise of the discount must go to
        // an earlier line. The parts must still add to 8,995.00 and the taxable
        // value must be 9,001 - 8,995 = 6.00 (s.15(3)(a): the discount shown
        // on the invoice comes off the value).
        $items = array_map(fn ($n) => $this->line(1000, 0, 1, ['description' => "Chair {$n}"]), range(1, 9));
        $items[] = $this->line(1, 0, 1, ['description' => 'Carry bag']);
        $inv = $this->draft($items, $this->customerIn('27'), ['invoice_discount' => 8995]);

        $this->assertMoney(8995.00, $inv->items()->get()->sum(fn ($i) => (float) $i->discount), 'the discount parts add back to the discount typed');
        $this->assertMoney(6.00, $inv->subtotal, 'taxable value = 9,001 - 8,995');
        foreach ($inv->items as $i) {
            $this->assertGreaterThanOrEqual(0.0, (float) $i->amount, 'no line goes below zero');
        }
    }

    public function test_a_line_discount_larger_than_the_line_is_capped_and_never_eats_other_lines(): void
    {
        // A: 1 x 500 with 9,999 off -> capped at 500, taxable 0, tax 0.
        // B: 1 x 1,000 @ 18% untouched -> 90 + 90; grand 1,180.
        $inv = $this->draft([
            $this->line(500, 18, 1, ['discount' => 9999]),
            $this->line(1000, 18),
        ], $this->customerIn('27'));

        [$a, $b] = $inv->items->values();
        $this->assertMoney(500.00, $a->discount, 'the stored discount is capped at the line value');
        $this->assertMoney(0.00, $a->amount);
        $this->assertMoney(0.00, $a->cgst_amount);
        $this->assertMoney(1000.00, $b->amount);
        $this->assertMoney(90.00, $b->cgst_amount);
        $this->assertMoney(1180.00, $inv->grand_total);
        $this->assertInvoiceAddsUp($inv);
    }

    public function test_an_invoice_discount_above_the_items_or_below_zero_is_refused(): void
    {
        $c = $this->customerIn('27');
        // Items 2 x 499.99 = 999.98; a discount of 999.99 is 1 paisa too much.
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $c->id, 'invoice_date' => now()->toDateString(), 'invoice_discount' => 999.99,
            'items' => [$this->line(499.99, 18, 2)],
        ])->assertSessionHasErrors('invoice_discount');

        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $c->id, 'invoice_date' => now()->toDateString(), 'invoice_discount' => -50,
            'items' => [$this->line(499.99, 18, 2)],
        ])->assertSessionHasErrors('invoice_discount');

        $this->assertSame(0, Invoice::count());

        // Exactly the items total is allowed: a free supply of ₹0, which then
        // cannot be issued as a zero-amount invoice.
        $inv = $this->draft([$this->line(499.99, 18, 2)], $c, ['invoice_discount' => 999.98]);
        $this->assertMoney(0.00, $inv->subtotal);
        $this->assertMoney(0.00, $inv->total_tax);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasErrors('finalize');
    }

    public function test_a_line_discount_and_an_invoice_discount_together_keep_both(): void
    {
        // An API/older client sends a per-line discount AND the form's invoice
        // discount. A: 1 x 1,000 less 100 on the line; B: 1 x 1,000. Then
        // ₹180 off the invoice, spread on what is left (900 : 1,000):
        //   A 180 x 900/1,900 = 85.26 ; B (last) 94.74.
        // Total discount 100 + 180 = 280; taxable 2,000 - 280 = 1,720.
        // If the line discount is silently replaced by the spread, the
        // customer is billed ₹100 more than agreed.
        $inv = $this->draft([
            $this->line(1000, 0, 1, ['discount' => 100]),
            $this->line(1000, 0),
        ], $this->customerIn('27'), ['invoice_discount' => 180]);

        $this->assertMoney(280.00, $inv->items()->get()->sum(fn ($i) => (float) $i->discount), 'line discount 100 + invoice discount 180');
        $this->assertMoney(1720.00, $inv->subtotal);
    }
}
