<?php

namespace Tests\Feature\Audit\Money;

use App\Models\CreditNote;

/**
 * The mirror of CreditNoteSplitAfterSalesReturnTest: a price reduction typed
 * as an amount first, goods returned after. The returned goods are worth
 * what is left on their line, so no rate gives back more tax than it charged.
 */
class ReturnAfterAmountNoteTest extends MoneyAuditBase
{
    public function test_a_return_after_an_amount_note_never_reverses_more_tax_than_its_rate_charged(): void
    {
        // Rice 1,000 @ 5% (25 + 25), rack 1,000 @ 18% (90 + 90): 2,230.
        $inv = $this->issue([
            ['description' => 'Rice bags', 'hsn_sac' => '10063020', 'quantity' => 10, 'unit' => 'NOS', 'rate' => 100, 'gst_rate' => 5],
            ['description' => 'Steel rack', 'hsn_sac' => '94032010', 'quantity' => 10, 'unit' => 'NOS', 'rate' => 100, 'gst_rate' => 18],
        ], '2026-09-05');
        $this->assertEqualsWithDelta(2230.0, (float) $inv->grand_total, 0.001);
        $rack = $inv->items()->where('hsn_sac', '94032010')->firstOrFail();

        // Half the bill off as a price reduction: half of every line, head by head.
        $this->creditNote($inv, ['amount' => 1115])->assertSessionHasNoErrors();

        // Then 9 of the 10 racks come back: 9/10 of what is left on the rack
        // line (taxable 500, CGST 45, SGST 45) = 450 + 40.50 + 40.50 = 531.
        $this->creditNote($inv->fresh(), ['reason' => 'sales_return', 'lines' => [$rack->id => 9], 'amount' => 1])
            ->assertSessionHasNoErrors();
        $ret = CreditNote::where('invoice_id', $inv->id)->orderByDesc('id')->firstOrFail();
        $this->assertEqualsWithDelta(450.00, (float) $ret->taxable_value, 0.001);
        $this->assertEqualsWithDelta(40.50, (float) $ret->total_cgst, 0.001);
        $this->assertEqualsWithDelta(531.00, (float) $ret->amount, 0.001);

        // At 18% the two notes give back 45 + 40.50 of the 90 CGST charged.
        $amountNote = CreditNote::where('invoice_id', $inv->id)->orderBy('id')->firstOrFail();
        $split = app(\App\Services\CreditNoteSplit::class)->leftByLine($inv->fresh(), null, true);
        $this->assertGreaterThanOrEqual(-0.001, $split[$rack->id]['total_cgst'], 'rack CGST left after both notes');
        $this->assertEqualsWithDelta(4.50, $split[$rack->id]['total_cgst'], 0.01);
        $this->assertNotNull($amountNote);
    }

    public function test_without_an_amount_note_a_return_is_valued_as_before(): void
    {
        $inv = $this->issue([
            ['description' => 'Steel rack', 'hsn_sac' => '94032010', 'quantity' => 10, 'unit' => 'NOS', 'rate' => 100, 'gst_rate' => 18],
        ], '2026-09-05');
        $rack = $inv->items()->firstOrFail();

        $this->creditNote($inv, ['reason' => 'sales_return', 'lines' => [$rack->id => 3], 'amount' => 1])
            ->assertSessionHasNoErrors();
        $ret = CreditNote::where('invoice_id', $inv->id)->firstOrFail();
        $this->assertEqualsWithDelta(300.00, (float) $ret->taxable_value, 0.001);
        $this->assertEqualsWithDelta(27.00, (float) $ret->total_cgst, 0.001);
        $this->assertEqualsWithDelta(354.00, (float) $ret->amount, 0.001);
    }
}
