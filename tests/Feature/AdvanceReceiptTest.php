<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * An invoice raised with a total, an amount already paid and a balance gets a
 * receipt for the paid amount the moment it is issued: numbered, dated the
 * invoice date, with the method and reference chosen on the form, and the
 * receipt shows the figures as they stood when that money came in.
 */
class AdvanceReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5',
            'composition_dealer' => false, 'invoice_prefix' => 'INV', 'invoice_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id,
            'state_id' => $mh->id, 'gstin' => '27FGHIJ5678K1Z3',
        ]);
    }

    /** ₹50,000 + 18% = ₹59,000, with whatever was paid on the form. */
    private function issue(array $paid): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Consulting', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 50000, 'gst_rate' => 18]],
        ] + $paid)->assertRedirect();
        $inv = Invoice::latest('id')->first();
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertRedirect();

        return $inv->fresh();
    }

    public function test_part_paid_invoice_gets_a_receipt_for_the_paid_amount_with_its_method(): void
    {
        $inv = $this->issue(['paid_amount' => 20000, 'paid_method' => 'upi', 'paid_reference' => 'UPI-4471']);

        $this->assertSame('partially_paid', $inv->status);
        $this->assertEqualsWithDelta(59000.00, (float) $inv->grand_total, 0.001);
        $this->assertEqualsWithDelta(20000.00, (float) $inv->paid_amount, 0.001);
        $this->assertEqualsWithDelta(39000.00, (float) $inv->balance, 0.001);

        $receipts = $inv->payments()->get();
        $this->assertCount(1, $receipts);
        $r = $receipts->first();
        $this->assertEqualsWithDelta(20000.00, (float) $r->amount, 0.001);
        $this->assertSame('upi', $r->method);
        $this->assertSame('UPI-4471', $r->reference_number);
        $this->assertSame($inv->invoice_date->toDateString(), $r->received_at->toDateString());
        $this->assertNotEmpty($r->receipt_number);

        // The invoice page lists it with its PDF, and the PDF downloads.
        $this->actingAs($this->user)->get(route('invoices.show', $inv))
            ->assertOk()->assertSee($r->receipt_number)->assertSee(route('payments.receipt', $r), false);
        $pdf = $this->actingAs($this->user)->get(route('payments.receipt', $r))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // What the receipt says: ₹20,000 by UPI, ref UPI-4471, ₹39,000 still due.
        $html = view('payments.receipt', ['payment' => $r->load('invoice.company.state', 'invoice.customer.state')])->render();
        $this->assertStringContainsString('UPI-4471', $html);
        $this->assertStringContainsString('>UPI<', $html);
        $this->assertStringContainsString('20,000', $html);
        $this->assertStringContainsString('39,000', $html);
    }

    public function test_fully_paid_invoice_is_paid_with_one_receipt_for_the_total(): void
    {
        $inv = $this->issue(['paid_amount' => 59000, 'paid_method' => 'neft', 'paid_reference' => 'NEFT0099']);

        $this->assertSame('paid', $inv->status);
        $this->assertEqualsWithDelta(0.00, (float) $inv->balance, 0.001);
        $r = $inv->payments()->sole();
        $this->assertEqualsWithDelta(59000.00, (float) $r->amount, 0.001);
        $this->assertSame('neft', $r->method);
    }

    public function test_without_a_method_the_receipt_is_cash_as_before(): void
    {
        // Older forms and API clients send no method: unchanged behaviour.
        $inv = $this->issue(['paid_amount' => 5000]);

        $r = $inv->payments()->sole();
        $this->assertSame('cash', $r->method);
        $this->assertNull($r->reference_number);
    }

    public function test_nothing_paid_means_no_receipt_and_no_method_kept(): void
    {
        $inv = $this->issue(['paid_amount' => 0, 'paid_method' => 'upi', 'paid_reference' => 'X1']);

        $this->assertSame('final', $inv->status);
        $this->assertSame(0, $inv->payments()->count());
        $this->assertNull($inv->paid_method);
        $this->assertNull($inv->paid_reference);
    }

    public function test_an_unknown_method_is_refused(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'rate' => 100, 'gst_rate' => 18]],
            'paid_amount' => 50, 'paid_method' => 'bitcoin',
        ])->assertSessionHasErrors('paid_method');
    }

    public function test_receipt_counts_a_same_day_credit_note_only_if_it_came_first(): void
    {
        Carbon::setTestNow('2026-10-11 10:00:00');
        $inv = $this->issue(['paid_amount' => 10000, 'paid_method' => 'cash']);
        $advance = $inv->payments()->sole();

        // Later the same day: a ₹5,000 credit note.
        Carbon::setTestNow('2026-10-11 14:00:00');
        CreditNote::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'invoice_id' => $inv->id,
            'credit_note_number' => 'CRN-T1', 'credit_note_date' => '2026-10-11', 'reason' => 'rate_change',
            'amount' => 5000, 'taxable_value' => 4237.29, 'total_cgst' => 381.36, 'total_sgst' => 381.35, 'total_igst' => 0,
        ]);
        // And a payment after that.
        $later = Payment::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'invoice_id' => $inv->id,
            'receipt_number' => 'RCPT-T2', 'received_at' => '2026-10-11', 'amount' => 20000, 'tds_amount' => 0, 'method' => 'upi',
        ]);
        Carbon::setTestNow();

        $render = fn (Payment $p) => view('payments.receipt', ['payment' => $p->fresh()->load('invoice.company.state', 'invoice.customer.state')])->render();

        // The advance came before the note: 59,000 - 10,000 = 49,000 due after it.
        $first = $render($advance);
        $this->assertStringNotContainsString('Less credit notes', $first);
        $this->assertStringContainsString('49,000', $first);

        // The later payment sees the note: 59,000 - 5,000 - 10,000 - 20,000 = 24,000.
        $second = $render($later);
        $this->assertStringContainsString('Less credit notes', $second);
        $this->assertStringContainsString('24,000', $second);
    }
}
