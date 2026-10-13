<?php

namespace Tests\Feature\Audit\Sales;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * "Duplicate" copies the old invoice's is_interstate flag, place of supply and
 * CGST/SGST amounts into a new draft dated today, without recalculating.
 * finalize() only refuses a stale split when the customer row changed AFTER
 * the draft was saved, and the duplicate was saved just now - so a customer
 * who moved from Maharashtra to Karnataka months ago is billed CGST+SGST
 * with place of supply Maharashtra on a fresh tax invoice, while the
 * "Bill to" block (snapshot) shows Karnataka. Inter-state supply must carry
 * IGST (s.7(1) IGST Act); tax paid under the wrong head has to be paid again
 * under the right one (s.77 CGST / s.19 IGST).
 */
class DuplicateKeepsOldTaxSplitTest extends SalesAuditCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_duplicate_issued_after_customer_moved_state_uses_igst(): void
    {
        // June: same-state sale, CGST + SGST.
        Carbon::setTestNow('2026-06-01 10:00:00');
        $june = $this->issue($this->draft([
            ['description' => 'Monthly supply', 'hsn_sac' => '8471', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18],
        ]));
        $this->assertEqualsWithDelta(900.0, (float) $june->total_cgst, 0.001);

        // August: the customer relocates to Karnataka (new GSTIN there).
        Carbon::setTestNow('2026-08-01 10:00:00');
        $this->local->update(['state_id' => $this->ka->id, 'gstin' => '29FGHIJ5678K1Z3']);

        // October: repeat the June bill with "Duplicate", then Issue.
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->actingAs($this->user)->post(route('invoices.duplicate', $june))->assertRedirect();
        $copy = Invoice::latest('id')->first();
        $issued = $this->issue($copy);

        if ($issued->status === 'draft') {
            $this->assertTrue(true); // refusing to issue the stale split is also correct
            return;
        }
        $this->assertSame($this->ka->id, $issued->billedCustomer()->state_id, 'bill-to shows Karnataka');
        $this->assertEqualsWithDelta(0.0, (float) $issued->total_cgst, 0.001,
            'Karnataka customer billed CGST/SGST (place of supply ' . $issued->placeOfSupply?->name . ') on an inter-state supply');
        $this->assertEqualsWithDelta(1800.0, (float) $issued->total_igst, 0.001);
    }
}
