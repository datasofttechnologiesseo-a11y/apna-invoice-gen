<?php

namespace Tests\Feature\Audit\Money;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * A part payment with no reference (cash, or UPI without a UTR) sent twice -
 * double click, back button, a second tab or an app retry - used to book two
 * receipts and mark ₹1,000 paid on a ₹500 payment. The same amount, method
 * and date on the same invoice within a few minutes is now refused unless the
 * user confirms it is a separate payment.
 */
class RepeatedPaymentTest extends MoneyAuditBase
{
    private function invoice(): \App\Models\Invoice
    {
        return $this->issue([
            ['description' => 'Repair', 'hsn_sac' => '998719', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ], '2026-09-05');
    }

    public function test_the_same_cash_part_payment_sent_twice_is_booked_once(): void
    {
        $inv = $this->invoice();
        $payment = ['amount' => 500, 'method' => 'cash', 'received_at' => '2026-09-05'];

        $this->pay($inv, $payment)->assertSessionHasNoErrors();
        $this->pay($inv, $payment)->assertSessionHasErrors('confirm_duplicate');

        $this->assertSame(1, Payment::where('invoice_id', $inv->id)->count());
        $this->assertEqualsWithDelta(500.0, (float) $inv->fresh()->paid_amount, 0.001);
    }

    public function test_a_genuine_second_payment_goes_through_once_confirmed(): void
    {
        $inv = $this->invoice();
        $payment = ['amount' => 500, 'method' => 'cash', 'received_at' => '2026-09-05'];

        $this->pay($inv, $payment)->assertSessionHasNoErrors();
        $this->pay($inv, $payment + ['confirm_duplicate' => 1])->assertSessionHasNoErrors();

        $this->assertSame(2, Payment::where('invoice_id', $inv->id)->count());
        $this->assertEqualsWithDelta(1000.0, (float) $inv->fresh()->paid_amount, 0.001);
    }

    public function test_a_different_amount_method_or_date_is_not_a_repeat(): void
    {
        $inv = $this->invoice();
        $this->pay($inv, ['amount' => 300, 'method' => 'cash', 'received_at' => '2026-09-05'])->assertSessionHasNoErrors();

        $this->pay($inv, ['amount' => 200, 'method' => 'cash', 'received_at' => '2026-09-05'])->assertSessionHasNoErrors();
        $this->pay($inv, ['amount' => 300, 'method' => 'upi', 'received_at' => '2026-09-05'])->assertSessionHasNoErrors();

        $this->assertSame(3, Payment::where('invoice_id', $inv->id)->count());
    }

    public function test_the_same_payment_much_later_is_taken_as_a_new_one(): void
    {
        $inv = $this->invoice();
        $payment = ['amount' => 500, 'method' => 'cash', 'received_at' => '2026-09-05'];
        $this->pay($inv, $payment)->assertSessionHasNoErrors();

        $this->travel(\App\Models\Invoice::DUPLICATE_PAYMENT_MINUTES + 1)->minutes();
        $this->pay($inv, $payment)->assertSessionHasNoErrors();

        $this->assertSame(2, Payment::where('invoice_id', $inv->id)->count());
    }

    public function test_the_page_explains_and_offers_to_record_it_anyway(): void
    {
        $inv = $this->invoice();
        $payment = ['amount' => 500, 'method' => 'cash', 'received_at' => '2026-09-05'];
        $this->pay($inv, $payment);

        $this->actingAs($this->user)->from(route('invoices.show', $inv))
            ->followingRedirects()
            ->post(route('invoices.payments', $inv), $payment)
            ->assertOk()
            ->assertSee('A payment of ₹500 by Cash on this date was recorded', false)
            ->assertSee('Record as a separate payment');
    }

    public function test_an_app_retry_is_refused_the_same_way(): void
    {
        $inv = $this->invoice();
        $payment = ['amount' => 500, 'method' => 'cash', 'received_at' => '2026-09-05'];
        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        $this->postJson("/api/invoices/{$inv->id}/payments", $payment)->assertSuccessful();
        $this->postJson("/api/invoices/{$inv->id}/payments", $payment)->assertStatus(422)->assertJsonValidationErrors('confirm_duplicate');
        $this->postJson("/api/invoices/{$inv->id}/payments", $payment + ['confirm_duplicate' => true])->assertSuccessful();

        $this->assertSame(2, DB::table('payments')->where('invoice_id', $inv->id)->count());
    }
}
