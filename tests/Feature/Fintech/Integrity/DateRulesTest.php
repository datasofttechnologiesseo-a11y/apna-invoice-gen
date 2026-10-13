<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dates that cannot be true. Today is 12 Oct 2026; the standard invoice is
 * dated 10 Oct 2026 for Rs 1,180 (1 x 1,000 @ 18%).
 *
 *  - a receipt against an invoice cannot predate the invoice (the app takes
 *    no payment on a draft; an advance is entered on the draft and booked at
 *    issue, dated the invoice date);
 *  - a credit note cannot predate the invoice it reduces (s.34);
 *  - a due date cannot precede the invoice date;
 *  - an invoice cannot be issued with a date after the day it is issued
 *    (s.31: issued before or at the time of supply; the e-invoice IRP refuses
 *    a future document date);
 *  - no receipt, credit note or purchase invoice can be dated in the future.
 */
class DateRulesTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();
    }

    private function issuedOn10Oct(): Invoice
    {
        return $this->issued(['invoice_date' => '2026-10-10']);
    }

    public function test_a_receipt_dated_before_its_invoice_is_refused(): void
    {
        $inv = $this->issuedOn10Oct();

        $this->actingAs($this->user)->post(route('invoices.payments', $inv), [
            'amount' => 500, 'method' => 'cash', 'received_at' => '2026-10-01',
        ])->assertSessionHasErrors('received_at');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_receipt_dated_before_its_invoice_is_refused_over_the_app_api(): void
    {
        $inv = $this->issuedOn10Oct();
        Sanctum::actingAs($this->user);

        $this->postJson("/api/invoices/{$inv->id}/payments", [
            'amount' => 500, 'method' => 'cash', 'received_at' => '2026-10-01',
        ])->assertStatus(422);

        $this->assertSame(0, Payment::count());
    }

    public function test_a_credit_note_dated_before_its_invoice_is_refused_over_the_app_api(): void
    {
        $inv = $this->issuedOn10Oct();
        Sanctum::actingAs($this->user);

        $this->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => '2026-10-09', 'amount' => 100, 'reason' => 'rate_correction',
        ])->assertStatus(422)->assertJsonValidationErrors('credit_note_date');

        $this->assertSame(0, CreditNote::count());
    }

    public function test_receipts_and_credit_notes_dated_in_the_future_are_refused(): void
    {
        $inv = $this->issuedOn10Oct();

        $this->actingAs($this->user)->post(route('invoices.payments', $inv), ['amount' => 500, 'method' => 'cash', 'received_at' => '2026-10-13'])
            ->assertSessionHasErrors('received_at');
        $this->actingAs($this->user)->post(route('credit-notes.store', $inv), ['credit_note_date' => '2026-10-13', 'amount' => 100, 'reason' => 'rate_correction'])
            ->assertSessionHasErrors('credit_note_date');

        Sanctum::actingAs($this->user);
        $this->postJson("/api/invoices/{$inv->id}/payments", ['amount' => 500, 'method' => 'cash', 'received_at' => '2026-10-13'])->assertStatus(422);
        $this->postJson("/api/invoices/{$inv->id}/credit-notes", ['credit_note_date' => '2026-10-13', 'amount' => 100, 'reason' => 'rate_correction'])->assertStatus(422);

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, CreditNote::count());
        $this->assertMoney(1180.00, $inv->fresh()->balance);
    }

    public function test_a_draft_due_date_before_its_invoice_date_is_refused_on_the_web_and_the_app(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-10', 'due_date' => '2026-10-05', 'items' => [$this->line()],
        ])->assertSessionHasErrors('due_date');

        Sanctum::actingAs($this->user);
        $this->postJson('/api/invoices', [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-10', 'due_date' => '2026-10-05', 'items' => [$this->line()],
        ])->assertStatus(422)->assertJsonValidationErrors('due_date');

        $this->assertSame(0, Invoice::count());
    }

    public function test_an_issued_invoices_due_date_cannot_be_moved_before_its_invoice_date(): void
    {
        $inv = $this->issuedOn10Oct(); // due 10 Oct

        // The issued-invoice edit (notes, terms, due date, transport) checks
        // the due date only as "a date": 5 Oct would make it overdue from
        // before it existed and age it into the wrong bucket.
        $this->actingAs($this->user)->put(route('invoices.update', $inv), ['due_date' => '2026-10-05', 'notes' => 'x']);
        Sanctum::actingAs($this->user);
        $this->patchJson("/api/invoices/{$inv->id}", ['due_date' => '2026-10-05']);

        $this->assertSame('2026-10-10', $inv->fresh()->due_date?->toDateString(), 'due date moved before the invoice date');
    }

    public function test_an_invoice_dated_in_the_future_cannot_be_issued(): void
    {
        $draft = $this->draft(['invoice_date' => '2026-10-20']);

        $this->actingAs($this->user)->post(route('invoices.finalize', $draft));

        $fresh = $draft->fresh();
        $this->assertSame('draft', $fresh->status, 'an invoice dated 20 Oct was issued on 12 Oct');
        $this->assertNull($fresh->invoice_number);
        $this->assertSame(0, (int) $this->company->fresh()->invoice_counter);
    }

    public function test_an_invoice_dated_in_the_future_cannot_be_issued_over_the_app_api(): void
    {
        $draft = $this->draft(['invoice_date' => '2026-10-20']);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/invoices/{$draft->id}/finalize")->assertStatus(422);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_a_purchase_invoice_dated_in_the_future_is_refused(): void
    {
        $vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'Mumbai Metals', 'state_id' => $this->mh->id]);
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'MM/9', 'bill_date' => '2026-10-13', 'itc_eligible' => 1,
            'items' => [['description' => 'Steel', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertSessionHasErrors('bill_date');
    }
}
