<?php

namespace Tests\Feature\Audit\Sales;

use App\Models\Invoice;
use Laravel\Sanctum\Sanctum;

/**
 * A draft whose discount sits on one line (made through the API, which takes
 * per-line discounts, or saved by the older per-line form) is opened in the
 * web editor to change only the notes. The editor has one discount box; it
 * used to post the sum of the line discounts as one invoice discount, which
 * the server spread pro rata over all lines, so lines at different GST rates
 * carried different taxable values and the bill changed on a no-op save.
 *
 *   Line A Rs 1,000 @18% less Rs 100, line B Rs 1,000 @5%:
 *   tax 162 + 50 = 212, grand total Rs 2,112 (was Rs 2,119 after the save).
 *
 * Now the form keeps each line's own discount until the discount box is
 * changed, posting them per line with no invoice discount.
 */
class LineDiscountRespreadOnWebSaveTest extends SalesAuditCase
{
    private function apiDraft(): Invoice
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/invoices', [
            'customer_id' => $this->local->id,
            'invoice_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Laptop bag', 'hsn_sac' => '4202', 'quantity' => 1, 'rate' => 1000, 'discount' => 100, 'gst_rate' => 18],
                ['description' => 'Notebook', 'hsn_sac' => '4820', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 5],
            ],
        ])->assertCreated();
        $inv = Invoice::latest('id')->first();
        $this->assertEqualsWithDelta(2112.0, (float) $inv->grand_total, 0.001, 'precondition: API draft total');

        return $inv;
    }

    /** Run the page's own invoiceForm() in node and return what it would show and post. */
    private function formState(string $html, bool $touchDiscount = false): array
    {
        $node = $this->node();
        if ($node === null) {
            $this->markTestSkipped('node is not available');
        }
        $this->assertSame(1, preg_match('#<script>\s*(const UT_STATE_IDS.*?)</script>#s', $html, $m), 'invoiceForm script not found');
        $this->assertSame(1, preg_match("#x-data='invoiceForm\((.*?)\)'#s", $html, $args), 'invoiceForm call not found');

        $js = "var window = globalThis;\n" . $m[1] . "\n"
            . 'const f = invoiceForm(' . html_entity_decode($args[1], ENT_QUOTES) . ");\n"
            . "f.customerId = '" . $this->local->id . "';\n"
            . "f.\$refs = { paidInput: { value: '0' } };\n"
            . "f.recompute();\n"
            . ($touchDiscount ? "f.keepLines = false; f.discountInput = 100; f.recompute();\n" : '')
            . 'console.log(JSON.stringify({ keep: f.keepLines, totals: f.totals, lines: f.items.map(i => i.discount),'
            . ' posted: { invoice_discount: f.keepLines ? 0 : f.totals.discount, lines: f.items.map(i => f.keepLines ? i.discount : 0) } }));';

        $tmp = tempnam(sys_get_temp_dir(), 'form') . '.js';
        file_put_contents($tmp, $js);
        $out = [];
        $code = 0;
        exec(escapeshellarg($node) . ' ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, implode("\n", $out));

        return json_decode(end($out), true);
    }

    public function test_saving_an_untouched_draft_in_the_web_editor_keeps_its_total(): void
    {
        $inv = $this->apiDraft();
        $page = $this->actingAs($this->user)->get(route('invoices.edit', $inv))->assertOk()->getContent();

        // The form shows the draft's own figures and will post them per line.
        $state = $this->formState($page);
        $this->assertTrue($state['keep'], 'the form keeps the per-line discounts');
        $this->assertEqualsWithDelta(2112.0, $state['totals']['grandTotal'], 0.001, 'on-screen total');
        $this->assertSame(0, (int) $state['posted']['invoice_discount']);
        $this->assertEqualsWithDelta(100.0, $state['posted']['lines'][0], 0.001);
        $this->assertEqualsWithDelta(0.0, $state['posted']['lines'][1], 0.001);

        // What that form posts when only the notes are changed.
        $this->actingAs($this->user)->patch(route('invoices.update', $inv), [
            'customer_id' => $this->local->id,
            'invoice_date' => $inv->invoice_date->toDateString(),
            'currency' => 'INR',
            'invoice_discount' => 0,
            'paid_amount' => 0,
            'notes' => 'Deliver after 5 pm',
            'items' => [
                ['product_id' => '', 'description' => 'Laptop bag', 'hsn_sac' => '4202', 'quantity' => 1, 'unit' => '', 'rate' => 1000, 'discount' => 100, 'gst_rate' => 18],
                ['product_id' => '', 'description' => 'Notebook', 'hsn_sac' => '4820', 'quantity' => 1, 'unit' => '', 'rate' => 1000, 'discount' => 0, 'gst_rate' => 5],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $inv->refresh();
        $this->assertSame('Deliver after 5 pm', $inv->notes);
        $this->assertEqualsWithDelta(2112.0, (float) $inv->grand_total, 0.001, 'grand total changed on a notes-only save');
    }

    public function test_changing_the_discount_box_spreads_one_amount_as_before(): void
    {
        $inv = $this->apiDraft();
        $page = $this->actingAs($this->user)->get(route('invoices.edit', $inv))->assertOk()->getContent();

        // Typing in the box switches to one discount spread by value: Rs 50 each.
        $state = $this->formState($page, touchDiscount: true);
        $this->assertFalse($state['keep']);
        $this->assertEqualsWithDelta(100.0, $state['posted']['invoice_discount'], 0.001);
        $this->assertSame([0, 0], array_map('intval', $state['posted']['lines']));
        $this->assertEqualsWithDelta(2119.0, $state['totals']['grandTotal'], 0.001);
    }

    public function test_a_draft_saved_by_the_web_form_opens_in_the_usual_one_box_mode(): void
    {
        // Rs 100 spread by value over two equal lines is Rs 50 each: nothing to keep.
        $inv = $this->draft([
            ['description' => 'A', 'hsn_sac' => '4202', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
            ['description' => 'B', 'hsn_sac' => '4820', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 5],
        ], $this->local, ['invoice_discount' => 100]);
        $page = $this->actingAs($this->user)->get(route('invoices.edit', $inv))->assertOk()->getContent();

        $state = $this->formState($page);
        $this->assertFalse($state['keep']);
        $this->assertEqualsWithDelta((float) $inv->grand_total, $state['totals']['grandTotal'], 0.001);
    }
}
