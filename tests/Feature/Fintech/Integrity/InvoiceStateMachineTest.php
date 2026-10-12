<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Http\Controllers\InvoiceShareController;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * What each state allows. States as the owner reads them: Draft, Issued
 * ("final"), Partly paid, Paid, Cancelled. Actions: edit lines, edit notes,
 * delete, cancel, record payment, credit note, send reminder, share link,
 * duplicate.
 *
 * Standard invoice: 1 x Rs 1,000 @ 18% intra-state = 1,000 + 90 + 90 = Rs 1,180.
 *
 *                 lines notes delete cancel pay  credit remind share copy
 *   Draft          yes   yes   yes    no    no    no     no     no   yes
 *   Issued         no    yes   no     yes   yes   yes    yes    yes  yes
 *   Partly paid    no    yes   no     no    yes   yes    yes    yes  yes
 *   Paid           no    yes   no     no    no    yes    no     yes  yes
 *   Cancelled      no    no    no     no    no    no     no     no*  yes
 *   (* a link may be produced, but the public page answers 410 Gone)
 */
class InvoiceStateMachineTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();
        Mail::fake();
    }

    /** Attempt a full edit (new rate Rs 5,000, new number, new date, an advance) on a non-draft. */
    private function tryToRewriteLines(Invoice $invoice): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->put(route('invoices.update', $invoice), [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'HACK-1',
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-11-30',
            'paid_amount' => 5900,
            'items' => [$this->line(5000)],
            'notes' => 'Deliver to the back gate',
        ]);
    }

    private function assertUntouched(Invoice $invoice, string $number, float $paid = 0): void
    {
        $fresh = $invoice->fresh();
        $this->assertSame($number, $fresh->invoice_number);
        $this->assertSame('2026-10-12', $fresh->invoice_date->toDateString());
        $this->assertMoney(1000.00, $fresh->subtotal);
        $this->assertMoney(90.00, $fresh->total_cgst);
        $this->assertMoney(90.00, $fresh->total_sgst);
        $this->assertMoney(1180.00, $fresh->grand_total);
        $this->assertMoney($paid, $fresh->paid_amount);
        $this->assertCount(1, $fresh->items);
        $this->assertMoney(1000.00, $fresh->items->first()->rate);
    }

    private function shareLink(Invoice $invoice): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->getJson(route('invoices.share.link', $invoice));
    }

    private function remind(Invoice $invoice): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post(route('invoices.remind', $invoice), ['channel' => 'email']);
    }

    private function duplicate(Invoice $invoice): Invoice
    {
        $before = Invoice::max('id');
        $this->actingAs($this->user)->post(route('invoices.duplicate', $invoice))->assertRedirect();
        $copy = Invoice::where('id', '>', $before)->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->invoice_number, 'a copy is a fresh draft with no number');
        $this->assertMoney(0, $copy->paid_amount);
        $this->assertMoney((float) $invoice->grand_total, $copy->grand_total);

        return $copy;
    }

    public function test_a_draft_can_be_edited_copied_and_deleted_but_cannot_take_money_or_be_cancelled(): void
    {
        $d = $this->draft();

        // Full edit: 1 x 2,000 @ 18% = 2,000 + 180 + 180 = 2,360.
        $this->actingAs($this->user)->put(route('invoices.update', $d), [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-12', 'due_date' => '2026-10-12',
            'items' => [$this->line(2000)], 'notes' => 'Deliver Monday',
        ])->assertSessionHasNoErrors();
        $this->assertMoney(2360.00, $d->fresh()->grand_total);
        $this->assertSame('Deliver Monday', $d->fresh()->notes);

        $this->cancel($d)->assertStatus(422);
        $this->actingAs($this->user)->post(route('invoices.payments', $d), ['amount' => 100, 'method' => 'cash', 'received_at' => '2026-10-12'])->assertStatus(422);
        $this->actingAs($this->user)->post(route('credit-notes.store', $d), ['credit_note_date' => '2026-10-12', 'amount' => 100, 'reason' => 'rate_correction'])->assertStatus(422);
        $this->remind($d)->assertStatus(422);
        $this->assertSame(0, Payment::count());
        $this->assertSame('draft', $d->fresh()->status);

        $this->duplicate($d->fresh());

        $this->actingAs($this->user)->delete(route('invoices.destroy', $d))->assertRedirect(route('invoices.index'));
        $this->assertNull(Invoice::find($d->id));
    }

    public function test_a_draft_cannot_be_given_a_public_share_link(): void
    {
        // Email and the app's share-link both refuse a draft ("Issue the
        // invoice before sharing it"): its amounts can still change and it
        // has no number. The web "Copy link" must refuse it the same way.
        $d = $this->draft();

        $status = $this->shareLink($d)->status();
        $this->assertContains($status, [403, 404, 422], "a draft got a public link (HTTP {$status})");
    }

    public function test_an_issued_invoice_takes_money_and_can_be_shared_but_its_lines_are_frozen_and_it_cannot_be_deleted(): void
    {
        $i = $this->issued();
        $this->assertSame('final', $i->status);

        $this->tryToRewriteLines($i)->assertRedirect(route('invoices.show', $i));
        $this->assertUntouched($i, 'INV-0001');
        $this->assertSame('Deliver to the back gate', $i->fresh()->notes, 'notes may still be corrected');

        $this->actingAs($this->user)->delete(route('invoices.destroy', $i))->assertStatus(403);
        $this->assertNotNull(Invoice::find($i->id));

        $url = $this->shareLink($i)->assertOk()->json('url');
        $this->get($url)->assertOk();

        $this->remind($i)->assertSessionHasNoErrors();
        $this->assertSame('sent', InvoiceReminder::where('invoice_id', $i->id)->value('status'));

        $this->duplicate($i);

        // Money: credit 100 then receive 80 -> 1,180 - 100 - 80 = 1,000 owed, partly paid.
        $this->credit($i, 100);
        $this->pay($i, 80);
        $fresh = $i->fresh();
        $this->assertSame('partially_paid', $fresh->status);
        $this->assertMoney(1000.00, $fresh->balance);

        // Cancelling is allowed on an issued invoice no money has touched.
        $other = $this->issued();
        $this->cancel($other)->assertRedirect(route('invoices.show', $other));
        $this->assertSame('cancelled', $other->fresh()->status);
    }

    public function test_a_part_paid_invoice_cannot_be_cancelled_or_deleted_and_its_receipt_stays(): void
    {
        $i = $this->issued();
        $receipt = $this->pay($i, 500); // 1,180 - 500 = 680 owed

        $this->cancel($i)->assertStatus(422);
        Sanctum::actingAs($this->user);
        $this->postJson("/api/invoices/{$i->id}/cancel", ['cancellation_reason' => 'Billed to the wrong customer'])->assertStatus(422);
        $this->actingAs($this->user)->delete(route('invoices.destroy', $i))->assertStatus(403);
        $this->deleteJson("/api/invoices/{$i->id}")->assertStatus(422);

        $fresh = $i->fresh();
        $this->assertSame('partially_paid', $fresh->status, 'the Rs 500 received must not vanish with a cancellation');
        $this->assertNotNull(Payment::find($receipt->id));
        $this->assertMoney(500.00, $fresh->paid_amount);
        $this->assertMoney(680.00, $fresh->balance);

        $this->tryToRewriteLines($i);
        $this->assertUntouched($i, 'INV-0001', 500);

        $this->remind($i)->assertSessionHasNoErrors();
        $this->shareLink($i)->assertOk();
        $this->credit($i, 100);   // 680 - 100 = 580
        $this->pay($i, 580);      // settled
        $this->assertSame('paid', $i->fresh()->status);
        $this->assertMoney(0.00, $i->fresh()->balance);
    }

    public function test_a_paid_invoice_refuses_more_money_and_reminders_but_can_still_be_credited(): void
    {
        $i = $this->issued();
        $this->pay($i, 1180);
        $this->assertSame('paid', $i->fresh()->status);

        $this->actingAs($this->user)->post(route('invoices.payments', $i), ['amount' => 1, 'method' => 'cash', 'received_at' => '2026-10-12'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(1, Payment::count());
        $this->remind($i)->assertStatus(422);
        $this->cancel($i)->assertStatus(422);
        $this->actingAs($this->user)->delete(route('invoices.destroy', $i))->assertStatus(403);

        $this->tryToRewriteLines($i);
        $this->assertUntouched($i, 'INV-0001', 1180);

        // A Rs 180 credit note on a paid invoice is the refund owed: balance -180.
        $this->credit($i, 180);
        $fresh = $i->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertMoney(-180.00, $fresh->balance);

        $this->shareLink($i)->assertOk();
        $this->duplicate($i);
    }

    public function test_a_cancelled_invoice_refuses_every_change_but_can_be_copied(): void
    {
        $i = $this->issued();
        $url = $this->shareLink($i)->json('url'); // shared on WhatsApp before cancelling
        $this->cancel($i)->assertRedirect();

        $this->tryToRewriteLines($i)->assertStatus(403);
        $this->actingAs($this->user)->put(route('invoices.update', $i), ['notes' => 'x'])->assertStatus(403);
        $this->actingAs($this->user)->post(route('invoices.payments', $i), ['amount' => 100, 'method' => 'cash', 'received_at' => '2026-10-12'])->assertStatus(422);
        $this->actingAs($this->user)->post(route('credit-notes.store', $i), ['credit_note_date' => '2026-10-12', 'amount' => 100, 'reason' => 'rate_correction'])->assertStatus(422);
        $this->remind($i)->assertStatus(422);
        $this->cancel($i)->assertStatus(422);
        $this->actingAs($this->user)->post(route('invoices.finalize', $i))->assertStatus(403);

        $this->actingAs($this->user)->delete(route('invoices.destroy', $i))->assertRedirect();
        $this->assertNotNull(Invoice::find($i->id), 'a cancelled invoice stays on record for GST');

        $this->get($url)->assertStatus(410);

        $fresh = $i->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('INV-0001', $fresh->invoice_number);
        $this->assertMoney(1180.00, $fresh->grand_total);

        $this->duplicate($fresh);
    }

    public function test_a_cancelled_issued_invoice_cannot_be_deleted_over_the_app_api(): void
    {
        $i = $this->issued();
        $this->cancel($i)->assertRedirect();

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/invoices/{$i->id}")->assertStatus(422);

        $this->assertNotNull(Invoice::find($i->id), 'INV-0001 must stay in the register (GSTR-1 Table 13 reports it as cancelled)');
        $this->getJson("/api/invoices/{$i->id}")->assertOk()->assertJsonPath('data.can.delete', false);
    }

    public function test_the_app_api_cannot_change_an_issued_invoices_amounts(): void
    {
        $i = $this->issued();
        Sanctum::actingAs($this->user);

        $this->patchJson("/api/invoices/{$i->id}", [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-01', 'paid_amount' => 5900,
            'items' => [$this->line(5000)], 'notes' => 'Deliver to the back gate',
        ])->assertOk();

        $this->assertUntouched($i, 'INV-0001');
        $this->assertSame('Deliver to the back gate', $i->fresh()->notes);
    }

    public function test_reversing_the_payment_then_the_credit_note_unwinds_the_invoice_step_by_step(): void
    {
        $i = $this->issued();
        $pay = $this->pay($i, 500);       // owed 1,180 - 500 = 680, partly paid
        $cn = $this->credit($i, 680);     // 1,180 - 500 - 680 = 0, settled
        $this->assertState($i, 'paid', 500, 680, 0);

        $this->actingAs($this->user)->delete(route('payments.destroy', $pay))->assertRedirect();
        $this->assertState($i, 'final', 0, 680, 500);       // 1,180 - 0 - 680 = 500

        $this->actingAs($this->user)->delete(route('credit-notes.destroy', $cn))->assertRedirect();
        $this->assertState($i, 'final', 0, 0, 1180);

        // Fully unwound, it is an untouched issued invoice again and may be cancelled.
        $this->cancel($i)->assertRedirect();
        $this->assertSame('cancelled', $i->fresh()->status);
    }

    public function test_reversing_the_credit_note_then_the_payment_unwinds_the_invoice_step_by_step(): void
    {
        $i = $this->issued();
        $pay = $this->pay($i, 500);
        $cn = $this->credit($i, 680);
        $this->assertState($i, 'paid', 500, 680, 0);

        $this->actingAs($this->user)->delete(route('credit-notes.destroy', $cn))->assertRedirect();
        $this->assertState($i, 'partially_paid', 500, 0, 680);   // 1,180 - 500 = 680

        $this->actingAs($this->user)->delete(route('payments.destroy', $pay))->assertRedirect();
        $this->assertState($i, 'final', 0, 0, 1180);
    }

    public function test_a_credit_note_on_a_paid_invoice_shows_the_refund_owed_and_unwinds_in_any_order(): void
    {
        $i = $this->issued();
        $full = $this->pay($i, 1180);
        $cn = $this->credit($i, 180);
        $this->assertState($i, 'paid', 1180, 180, -180);         // Rs 180 to refund

        $this->actingAs($this->user)->delete(route('payments.destroy', $full))->assertRedirect();
        $this->assertState($i, 'final', 0, 180, 1000);           // 1,180 - 180

        $again = $this->pay($i, 1000);                           // the cap is now 1,000
        $this->assertState($i, 'paid', 1000, 180, 0);

        $this->actingAs($this->user)->delete(route('credit-notes.destroy', $cn))->assertRedirect();
        $this->assertState($i, 'partially_paid', 1000, 0, 180);

        $this->actingAs($this->user)->delete(route('payments.destroy', $again))->assertRedirect();
        $this->assertState($i, 'final', 0, 0, 1180);
    }

    private function assertState(Invoice $invoice, string $status, float $paid, float $credited, float $balance): void
    {
        $f = $invoice->fresh();
        $this->assertSame($status, $f->status, 'status');
        $this->assertMoney($paid, $f->paid_amount, 'paid');
        $this->assertMoney($credited, $f->credited_amount, 'credited');
        $this->assertMoney($balance, $f->balance, 'balance');
        // The stored figures always agree with the documents behind them.
        $this->assertMoney((float) $f->payments()->sum('amount'), $f->paid_amount, 'paid = sum of receipts');
        $this->assertMoney((float) $f->creditNotes()->reorder()->sum('amount'), $f->credited_amount, 'credited = sum of notes');
        $this->assertMoney(round((float) $f->grand_total - (float) $f->paid_amount - (float) $f->credited_amount, 2), $f->balance, 'balance = total - paid - credited');
    }
}
