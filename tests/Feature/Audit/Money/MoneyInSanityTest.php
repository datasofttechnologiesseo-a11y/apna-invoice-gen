<?php

namespace Tests\Feature\Audit\Money;

use App\Models\Payment;

/**
 * Behaviour checked during the audit and found correct. Kept so the report's
 * "checked and correct" line is backed by a run, not a reading.
 */
class MoneyInSanityTest extends MoneyAuditBase
{
    private function bill()
    {
        return $this->issue([
            ['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ], '2026-09-05', '2026-09-15');
    }

    public function test_two_payments_then_reverse_the_first(): void
    {
        $inv = $this->bill();
        $this->pay($inv, ['amount' => 400, 'received_at' => '2026-09-10'])->assertSessionHasNoErrors();
        $this->pay($inv->fresh(), ['amount' => 780, 'received_at' => '2026-09-12'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $inv->fresh()->status);

        $first = Payment::where('invoice_id', $inv->id)->orderBy('id')->first();
        $this->actingAs($this->user)->delete(route('payments.destroy', $first))->assertRedirect();
        $inv->refresh();
        $this->assertSame('partially_paid', $inv->status);
        $this->assertEqualsWithDelta(780.0, (float) $inv->paid_amount, 0.001);
        $this->assertEqualsWithDelta(400.0, (float) $inv->balance, 0.001);
    }

    public function test_guards_on_amount_date_and_cancelled(): void
    {
        $inv = $this->bill();
        $this->pay($inv, ['amount' => 1180.01, 'received_at' => '2026-09-10'])->assertSessionHasErrors('amount');
        $this->pay($inv, ['amount' => 100, 'received_at' => '2026-09-04'])->assertSessionHasErrors('received_at');
        $this->pay($inv, ['amount' => 100, 'received_at' => '2026-10-01'])->assertSessionHasErrors('received_at');
        $this->pay($inv, ['amount' => 100, 'tds_amount' => 100, 'received_at' => '2026-09-10'])->assertSessionHasErrors('tds_amount');
        $this->assertSame(0, Payment::count());
    }

    public function test_credit_note_on_paid_invoice_leaves_refund_due_and_blocks_more_payment(): void
    {
        $inv = $this->bill();
        $this->pay($inv, ['amount' => 1180, 'received_at' => '2026-09-10'])->assertSessionHasNoErrors();
        $this->creditNote($inv->fresh(), ['amount' => 300])->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertEqualsWithDelta(-300.0, (float) $inv->balance, 0.001);
        $this->assertSame('paid', $inv->status);
        $this->pay($inv, ['amount' => 1, 'received_at' => '2026-09-20'])->assertSessionHasErrors('amount');
        $this->creditNote($inv, ['amount' => 880.01])->assertSessionHasErrors('amount');
    }

    public function test_locked_books_refuse_receipts_notes_and_reversals_inside_the_lock(): void
    {
        $inv = $this->bill();
        $this->pay($inv, ['amount' => 100, 'received_at' => '2026-09-07'])->assertSessionHasNoErrors();
        $this->company->forceFill(['books_locked_until' => '2026-09-08'])->save();

        $this->pay($inv->fresh(), ['amount' => 100, 'received_at' => '2026-09-08'])->assertSessionHasErrors('received_at');
        $this->creditNote($inv->fresh(), ['amount' => 100, 'credit_note_date' => '2026-09-08']);
        $this->assertSame(0, $inv->creditNotes()->count());
        $p = Payment::where('invoice_id', $inv->id)->sole();
        $this->actingAs($this->user)->delete(route('payments.destroy', $p));
        $this->assertNotNull(Payment::find($p->id));
    }

    public function test_customer_ledger_equals_invoices_less_notes_less_receipts(): void
    {
        $a = $this->bill();
        $b = $this->issue([['description' => 'More', 'hsn_sac' => '72085100', 'quantity' => 2, 'rate' => 500, 'gst_rate' => 18]], '2026-09-06');
        $this->pay($a, ['amount' => 1180, 'received_at' => '2026-09-10', 'tds_amount' => 20])->assertSessionHasNoErrors();
        $this->creditNote($a->fresh(), ['amount' => 200])->assertSessionHasNoErrors();
        $this->creditNote($b, ['amount' => 180])->assertSessionHasNoErrors();
        $this->pay($b->fresh(), ['amount' => 500, 'received_at' => '2026-09-20'])->assertSessionHasNoErrors();

        $view = $this->actingAs($this->user)->get(route('customers.ledger', $this->customer))->assertOk();
        $totals = $view->viewData('totals');
        $entries = $view->viewData('entries');
        // 2,360 invoiced - 380 credited - 1,680 received = 300.
        $this->assertEqualsWithDelta(300.0, $totals['outstanding'], 0.001);
        $this->assertEqualsWithDelta(300.0, (float) $entries->last()['balance'], 0.001);
    }

    public function test_receipt_shows_figures_as_they_stood(): void
    {
        $inv = $this->bill();
        $this->pay($inv, ['amount' => 400, 'received_at' => '2026-09-10'])->assertSessionHasNoErrors();
        $this->creditNote($inv->fresh(), ['amount' => 100, 'credit_note_date' => '2026-09-11'])->assertSessionHasNoErrors();
        $this->pay($inv->fresh(), ['amount' => 680, 'received_at' => '2026-09-12'])->assertSessionHasNoErrors();
        [$p1, $p2] = Payment::where('invoice_id', $inv->id)->orderBy('id')->get()->all();

        $h1 = view('payments.receipt', ['payment' => $p1->load('invoice.company.state', 'invoice.customer.state')])->render();
        $this->assertStringContainsString('780', $h1); // 1,180 - 400
        $this->assertStringNotContainsString('PAID IN FULL', $h1);
        $h2 = view('payments.receipt', ['payment' => $p2->fresh()->load('invoice.company.state', 'invoice.customer.state')])->render();
        $this->assertStringContainsString('PAID IN FULL', $h2);
    }

    public function test_section_34_window_closes_on_30_november_after_the_fy(): void
    {
        $inv = $this->issue([['description' => 'Old', 'hsn_sac' => '72085100', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]], '2025-05-01');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-30 23:00', 'Asia/Kolkata'));
        $this->creditNote($inv, ['amount' => 100])->assertSessionHasNoErrors();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 09:00', 'Asia/Kolkata'));
        $this->creditNote($inv->fresh(), ['amount' => 100]);
        $this->assertSame(1, $inv->creditNotes()->count());
    }
}
