<?php

namespace Tests\Feature\Audit\Money;

use App\Models\CreditNote;

/**
 * CreditNoteController::store decides "this note has lines" from the raw
 * input (any line quantity > 0). With lines, the amount rule drops to
 * nullable|numeric (no min, no max) and the in-lock cap is skipped. But
 * CreditNoteLines::build rounds quantities to 3 decimals and returns null
 * when nothing is left, and the controller then falls back to the typed
 * amount - which was never capped. A quantity such as 0.0004 therefore
 * lets any amount through: more than the invoice (s.34), negative, or 0.
 */
class CreditNoteLinesBypassCapTest extends MoneyAuditBase
{
    private function bill()
    {
        return $this->issue([
            ['description' => 'Steel rack', 'hsn_sac' => '94032010', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18],
        ], '2026-09-05');
    }

    public function test_tiny_line_quantity_cannot_carry_an_amount_above_the_invoice(): void
    {
        $inv = $this->bill();
        $item = $inv->items()->firstOrFail();

        $this->creditNote($inv, ['lines' => [$item->id => '0.0004'], 'amount' => 5000]);

        $credited = (float) CreditNote::where('invoice_id', $inv->id)->sum('amount');
        $this->assertLessThanOrEqual(1180.0, $credited, sprintf(
            'A ₹%s credit note was issued against a ₹1,180 invoice; invoice balance is now %s.',
            $credited, $inv->fresh()->balance
        ));
    }

    public function test_tiny_line_quantity_cannot_carry_a_negative_amount(): void
    {
        $inv = $this->bill();
        $item = $inv->items()->firstOrFail();

        $this->creditNote($inv, ['lines' => [$item->id => '0.0004'], 'amount' => -500]);

        $this->assertSame(0, CreditNote::where('invoice_id', $inv->id)->where('amount', '<=', 0)->count(),
            'A credit note with a zero or negative amount was issued; invoice balance is now ' . $inv->fresh()->balance);
    }

    public function test_api_has_the_same_hole(): void
    {
        $inv = $this->bill();
        $item = $inv->items()->firstOrFail();

        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $resp = $this->postJson('/api/invoices/' . $inv->id . '/credit-notes', [
            'credit_note_date' => now()->toDateString(), 'reason' => 'rate_correction',
            'lines' => [$item->id => '0.0004'], 'amount' => 5000,
        ]);
        $this->assertNotSame(404, $resp->status(), 'API route not found');

        $this->assertLessThanOrEqual(1180.0, (float) CreditNote::where('invoice_id', $inv->id)->sum('amount'),
            'API: HTTP ' . $resp->status() . ' ' . $resp->getContent());
    }
}
