<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Http\Controllers\QuotationController;
use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Quotation -> invoice. The customer accepted a price; the invoice must
 * charge exactly that, once.
 *
 * Quote (intra-state, Maharashtra -> Maharashtra), quote-level discount Rs 101:
 *   Line 1: 3 x 1,250.50 = 3,751.50 @ 18%
 *   Line 2: 7 x    99.99 =   699.93 @ 5%          gross 4,451.43
 *   Discount spread pro rata: 101 x 3,751.50 / 4,451.43 = 85.119 -> 85.12
 *                             101 x   699.93 / 4,451.43 = 15.881 -> 15.88  (sum 101.00)
 *   Taxable: 3,751.50 - 85.12 = 3,666.38 ; 699.93 - 15.88 = 684.05
 *   Line 1 CGST 9%   = 3,666.38 x 0.09  = 329.9742 -> 329.97, SGST 329.97
 *   Line 2 CGST 2.5% =   684.05 x 0.025 =  17.10125 -> 17.10, SGST 17.10
 *   Taxable 4,350.43; CGST 347.07; SGST 347.07; tax 694.14
 *   4,350.43 + 694.14 = 5,044.57 -> grand total 5,045.00, round-off +0.43
 *   Line totals 3,666.38 + 659.94 = 4,326.32 ; 684.05 + 34.20 = 718.25
 */
class QuotationConversionTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();
    }

    private function acceptedQuote(): Quotation
    {
        $this->actingAs($this->user)->post(route('quotations.store'), [
            'customer_id' => $this->customer->id,
            'quote_date' => '2026-10-01', 'valid_until' => '2026-10-31', 'currency' => 'INR', 'style' => 'classic',
            'quote_discount' => 101,
            'items' => [
                ['description' => 'Teak almirah', 'hsn_sac' => '94036000', 'quantity' => 3, 'unit' => 'NOS', 'rate' => 1250.50, 'discount' => 0, 'gst_rate' => 18],
                ['description' => 'Cotton dhurrie', 'hsn_sac' => '57023210', 'quantity' => 7, 'unit' => 'NOS', 'rate' => 99.99, 'discount' => 0, 'gst_rate' => 5],
            ],
        ])->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('quotations.send', $q))->assertRedirect();
        $this->actingAs($this->user)->post(route('quotations.accept', $q))->assertRedirect();
        $this->assertSame('accepted', $q->fresh()->status);

        return $q->fresh();
    }

    private function convert(Quotation $q): Invoice
    {
        $this->actingAs($this->user)->post(route('quotations.convert', $q))->assertRedirect();

        return Invoice::findOrFail($q->fresh()->converted_to_invoice_id);
    }

    private function assertHandWorkedTotals(Invoice $inv): void
    {
        $this->assertMoney(4350.43, $inv->subtotal, 'taxable');
        $this->assertMoney(347.07, $inv->total_cgst, 'CGST');
        $this->assertMoney(347.07, $inv->total_sgst, 'SGST');
        $this->assertMoney(0.00, $inv->total_igst, 'IGST');
        $this->assertMoney(694.14, $inv->total_tax, 'tax');
        $this->assertMoney(0.43, $inv->round_off, 'round-off');
        $this->assertMoney(5045.00, $inv->grand_total, 'grand total');

        $items = $inv->items()->get();
        $this->assertCount(2, $items);
        $expected = [
            // qty, rate, discount, taxable, cgst, sgst, total
            [3, 1250.50, 85.12, 3666.38, 329.97, 329.97, 4326.32],
            [7, 99.99, 15.88, 684.05, 17.10, 17.10, 718.25],
        ];
        foreach ($expected as $i => [$qty, $rate, $disc, $amount, $cgst, $sgst, $total]) {
            $l = $items[$i];
            $this->assertEqualsWithDelta($qty, (float) $l->quantity, 0.0005, "line $i qty");
            $this->assertMoney($rate, $l->rate, "line $i rate");
            $this->assertMoney($disc, $l->discount, "line $i discount");
            $this->assertMoney($amount, $l->amount, "line $i taxable");
            $this->assertMoney($cgst, $l->cgst_amount, "line $i CGST");
            $this->assertMoney($sgst, $l->sgst_amount, "line $i SGST");
            $this->assertMoney($total, $l->total, "line $i total");
        }
    }

    public function test_conversion_carries_every_amount_to_the_paisa_and_issuing_keeps_them(): void
    {
        $q = $this->acceptedQuote();
        $this->assertMoney(5045.00, $q->grand_total, 'the quote itself');

        $inv = $this->convert($q);
        $this->assertSame('draft', $inv->status);
        $this->assertNull($inv->invoice_number, 'a converted invoice is a draft; the number comes at issue');
        $this->assertSame($this->customer->id, $inv->customer_id);
        $this->assertMoney(5045.00, $inv->balance);
        $this->assertHandWorkedTotals($inv);

        $issued = $this->issue($inv);
        $this->assertSame('INV-0001', $issued->invoice_number);
        $this->assertHandWorkedTotals($issued);
    }

    public function test_a_converted_quotation_is_refused_a_second_conversion_on_the_web_and_the_app(): void
    {
        $q = $this->acceptedQuote();
        $this->convert($q);

        $this->actingAs($this->user)->post(route('quotations.convert', $q))->assertStatus(422);
        Sanctum::actingAs($this->user);
        $this->postJson("/api/quotations/{$q->id}/convert")->assertStatus(422);

        $this->assertSame(1, Invoice::count());
    }

    public function test_a_double_submitted_conversion_makes_only_one_invoice(): void
    {
        $q = $this->acceptedQuote();
        $stale = Quotation::find($q->id); // the second tab still shows "Accepted"

        $this->convert($q);
        $this->actingAs($this->user);
        try {
            app(QuotationController::class)->convertToInvoice($this->requestAs([]), $stale);
        } catch (HttpException) {
            // refused: fine
        }

        $this->assertSame(1, Invoice::count(), 'one accepted quote became two invoices; issuing both bills the customer twice');
    }

    public function test_deleting_the_converted_draft_reopens_the_quote_and_a_reconversion_matches_to_the_paisa(): void
    {
        $q = $this->acceptedQuote();
        $first = $this->convert($q);

        $this->actingAs($this->user)->delete(route('invoices.destroy', $first))->assertRedirect(route('invoices.index'));
        $q->refresh();
        $this->assertSame('accepted', $q->status);
        $this->assertNull($q->converted_to_invoice_id);
        $this->assertNull($q->converted_at);

        $second = $this->convert($q);
        $this->assertNotSame($first->id, $second->id);
        $this->assertHandWorkedTotals($second);
        $this->assertSame(0, (int) $this->company->fresh()->invoice_counter, 'no number was consumed by either draft');
    }

    public function test_once_the_converted_invoice_is_issued_the_quote_stays_converted_even_if_the_invoice_is_cancelled(): void
    {
        $q = $this->acceptedQuote();
        $inv = $this->issue($this->convert($q));

        $this->actingAs($this->user)->delete(route('invoices.destroy', $inv))->assertStatus(403);
        $this->cancel($inv)->assertRedirect();
        $this->actingAs($this->user)->delete(route('invoices.destroy', $inv))->assertRedirect();

        $q->refresh();
        $this->assertSame('converted', $q->status);
        $this->assertSame($inv->id, $q->converted_to_invoice_id, 'the quote still points at the invoice that was issued from it');
        $this->assertNotNull(Invoice::find($inv->id));
    }

    public function test_the_repair_migration_reopens_only_quotes_left_pointing_at_nothing(): void
    {
        $live = $this->issued();
        $make = fn (array $a) => Quotation::factory()->create(array_merge([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
        ], $a));
        $orphan = $make(['status' => 'converted', 'quote_number' => 'QT-0001', 'converted_to_invoice_id' => null, 'converted_at' => now()]);
        $linked = $make(['status' => 'converted', 'quote_number' => 'QT-0002', 'converted_to_invoice_id' => $live->id, 'converted_at' => now()]);
        $declined = $make(['status' => 'declined', 'quote_number' => 'QT-0003']);

        $migration = require base_path('database/migrations/2026_10_11_130000_reopen_quotations_whose_invoice_was_deleted.php');
        $migration->up();

        $this->assertSame('accepted', $orphan->fresh()->status);
        $this->assertNull($orphan->fresh()->converted_at);
        $this->assertSame('converted', $linked->fresh()->status);
        $this->assertSame($live->id, $linked->fresh()->converted_to_invoice_id);
        $this->assertSame('declined', $declined->fresh()->status);
    }
}
