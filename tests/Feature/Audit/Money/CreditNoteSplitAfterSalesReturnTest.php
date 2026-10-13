<?php

namespace Tests\Feature\Audit\Money;

use App\Models\CreditNote;

/**
 * CreditNoteSplit spreads an amount-only note pro rata over the ORIGINAL
 * invoice (subtotal + tax), then caps each head at what is left. After a
 * lined note (sales return) has credited one line in full, the pro rata is
 * over goods that are no longer there: the tax heads hit their cap, the
 * taxable value cannot absorb the difference (> ₹1), and the note is saved
 * with taxable + tax != amount - the invariant the class itself promises
 * ("a note always reads taxable + tax = amount").
 */
class CreditNoteSplitAfterSalesReturnTest extends MoneyAuditBase
{
    public function test_amount_note_after_a_line_return_still_adds_up(): void
    {
        // Line A 1,000 @ 5% (25 + 25), line B 1,000 @ 18% (90 + 90): 2,230.
        $inv = $this->issue([
            ['description' => 'Rice bags', 'hsn_sac' => '10063020', 'quantity' => 10, 'unit' => 'NOS', 'rate' => 100, 'gst_rate' => 5],
            ['description' => 'Steel rack', 'hsn_sac' => '94032010', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18],
        ], '2026-09-05');
        $this->assertEqualsWithDelta(2230.0, (float) $inv->grand_total, 0.001);
        $rack = $inv->items()->where('hsn_sac', '94032010')->firstOrFail();

        // The rack comes back: lined note for 1,180 (1,000 + 90 + 90).
        $this->creditNote($inv, ['reason' => 'sales_return', 'lines' => [$rack->id => 1], 'amount' => 1])
            ->assertSessionHasNoErrors();
        $ret = CreditNote::where('invoice_id', $inv->id)->orderBy('id')->firstOrFail();
        $this->assertEqualsWithDelta(1180.0, (float) $ret->amount, 0.001);

        // Then a ₹525 price reduction on the rice (half its value incl. 5% GST).
        $this->creditNote($inv->fresh(), ['amount' => 525])->assertSessionHasNoErrors();
        $cn = CreditNote::where('invoice_id', $inv->id)->orderByDesc('id')->firstOrFail();

        $sum = round((float) $cn->taxable_value + (float) $cn->total_cgst + (float) $cn->total_sgst + (float) $cn->total_igst, 2);
        $this->assertEqualsWithDelta(525.0, $sum, 0.01, sprintf(
            'Credit note %s for ₹525 stored taxable %.2f + CGST %.2f + SGST %.2f = %.2f',
            $cn->credit_note_number, $cn->taxable_value, $cn->total_cgst, $cn->total_sgst, $sum
        ));
    }

    public function test_gstr1_reports_the_amount_note_with_the_tax_the_note_carries(): void
    {
        $inv = $this->issue([
            ['description' => 'Rice bags', 'hsn_sac' => '10063020', 'quantity' => 10, 'unit' => 'NOS', 'rate' => 100, 'gst_rate' => 5],
            ['description' => 'Steel rack', 'hsn_sac' => '94032010', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18],
        ], '2026-09-05');
        $rack = $inv->items()->where('hsn_sac', '94032010')->firstOrFail();
        $this->creditNote($inv, ['reason' => 'sales_return', 'lines' => [$rack->id => 1], 'amount' => 1])->assertSessionHasNoErrors();
        $this->creditNote($inv->fresh(), ['amount' => 525])->assertSessionHasNoErrors();
        $cn = CreditNote::where('invoice_id', $inv->id)->orderByDesc('id')->firstOrFail();

        $g = app(\App\Services\Gstr1Builder::class)->build($this->company, now()->startOfMonth(), now()->endOfMonth());
        $rows = collect($g['cdnr'])->where('number', $cn->credit_note_number);
        $reportedCgst = round($rows->sum('cgst'), 2);

        // GSTR-3B takes the note's stored CGST; GSTR-1 must agree with it.
        $this->assertEqualsWithDelta((float) $cn->total_cgst, $reportedCgst, 0.01, sprintf(
            'GSTR-1 CDNR reports CGST %.2f (rates %s) for %s, the note carries %.2f',
            $reportedCgst, $rows->pluck('rate')->implode('/'), $cn->credit_note_number, $cn->total_cgst
        ));
    }
}
