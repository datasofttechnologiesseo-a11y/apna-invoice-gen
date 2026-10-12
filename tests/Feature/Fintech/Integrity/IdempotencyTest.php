<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Http\Controllers\Api\CreditNoteController as ApiCreditNoteController;
use App\Http\Controllers\Api\InvoiceController as ApiInvoiceController;
use App\Http\Controllers\InvoiceShareController;
use App\Http\Controllers\PurchaseBillController;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Double submits and retries. A second tab, a double tap, or the mobile app
 * re-sending after a timeout delivers the same request again, holding the
 * state as it was BEFORE the first one landed. These probes replay that by
 * calling the controller with a stale copy of the document, as the existing
 * MoneySafetyRegressionTest does for the web routes; here the app (API)
 * routes, vendor payments and the cancel/pay race are covered.
 *
 * Standard invoice: 1 x 1,000 @ 18% = Rs 1,180.
 */
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();
    }

    public function test_a_retried_app_issue_keeps_the_first_number(): void
    {
        $draft = $this->draft();
        $stale = Invoice::find($draft->id);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/invoices/{$draft->id}/finalize")->assertOk()->assertJsonPath('data.invoice_number', 'INV-0001');

        $this->actingAs($this->user);
        app(ApiInvoiceController::class)->finalize($this->requestAs([]), $stale);

        $this->assertSame('INV-0001', $draft->fresh()->invoice_number);
        $this->assertSame(1, (int) $this->company->fresh()->invoice_counter, 'the retry must not consume INV-0002');
        $this->assertSame(1, Invoice::whereNotNull('finalized_at')->count());
    }

    public function test_a_retried_app_payment_cannot_pay_the_invoice_twice(): void
    {
        $inv = $this->issued();
        $stale = Invoice::find($inv->id);
        $pay = ['amount' => 1180, 'method' => 'upi', 'received_at' => '2026-10-12'];

        Sanctum::actingAs($this->user);
        $this->postJson("/api/invoices/{$inv->id}/payments", $pay)->assertCreated();

        $this->actingAs($this->user);
        try {
            app(ApiInvoiceController::class)->recordPayment($this->requestAs($pay), $stale);
            $this->fail('the retried payment was accepted');
        } catch (ValidationException) {
        }
        $this->assertSame(1, Payment::count());
        $this->assertMoney(1180.00, $inv->fresh()->paid_amount);
        $this->assertMoney(0.00, $inv->fresh()->balance);
    }

    public function test_a_retried_app_credit_note_cannot_credit_more_than_the_invoice(): void
    {
        $inv = $this->issued();
        $stale = Invoice::find($inv->id);
        $cn = ['credit_note_date' => '2026-10-12', 'amount' => 1180, 'reason' => 'rate_correction'];

        Sanctum::actingAs($this->user);
        $this->postJson("/api/invoices/{$inv->id}/credit-notes", $cn)->assertCreated();

        $this->actingAs($this->user);
        try {
            app(ApiCreditNoteController::class)->store($this->requestAs($cn), $stale);
            $this->fail('the retried credit note was accepted');
        } catch (ValidationException) {
        }
        $this->assertSame(1, CreditNote::count());
        $this->assertMoney(1180.00, $inv->fresh()->credited_amount);
        $this->assertSame(1, (int) $this->company->fresh()->credit_note_counter, 'no credit-note number burnt by the retry');
    }

    public function test_the_same_bank_reference_is_not_recorded_twice_against_one_invoice(): void
    {
        // Rs 500 by NEFT, UTR N612345678901. A double submit of a PART payment
        // passes the balance cap (500 + 500 <= 1,180), so the cap alone does
        // not stop it; the bank reference identifies the transfer.
        $inv = $this->issued();
        $pay = ['amount' => 500, 'method' => 'neft', 'received_at' => '2026-10-12', 'reference_number' => 'N612345678901'];

        $this->pay($inv, 500, $pay);
        $this->actingAs($this->user)->post(route('invoices.payments', $inv), $pay);

        $this->assertSame(1, Payment::where('invoice_id', $inv->id)->count(), 'one NEFT transfer was booked as two receipts');
        $this->assertMoney(680.00, $inv->fresh()->balance, '1,180 - 500 = 680 still owed');
    }

    public function test_an_app_retry_of_a_part_payment_with_the_same_bank_reference_is_not_booked_twice(): void
    {
        // The mobile app re-sends after a timeout; the first request had landed.
        $inv = $this->issued();
        $pay = ['amount' => 500, 'method' => 'upi', 'received_at' => '2026-10-12', 'reference_number' => '612345678901'];

        Sanctum::actingAs($this->user);
        $this->postJson("/api/invoices/{$inv->id}/payments", $pay)->assertCreated();
        $this->postJson("/api/invoices/{$inv->id}/payments", $pay);

        $this->assertSame(1, Payment::where('invoice_id', $inv->id)->count(), 'one UPI transfer (UTR 612345678901) was booked as two receipts');
        $this->assertMoney(680.00, $inv->fresh()->balance);
    }

    public function test_a_double_submitted_vendor_payment_cannot_overpay_the_purchase_invoice(): void
    {
        $bill = $this->purchaseBill(); // 1 x 1,000 @ 18% = 1,180 owed to the vendor
        $stale = PurchaseBill::find($bill->id);
        $pay = ['amount' => 1180, 'method' => 'neft', 'paid_on' => '2026-10-12', 'reference_number' => 'N700000000001'];

        $this->actingAs($this->user)->post(route('purchases.payments.store', $bill), $pay)->assertSessionHasNoErrors();
        $this->assertMoney(0.00, $bill->fresh()->balance);

        try {
            app(PurchaseBillController::class)->storePayment($this->requestAs($pay), $stale);
        } catch (ValidationException) {
        }

        $this->assertSame(1, PurchasePayment::count(), 'the vendor was recorded as paid twice');
        $this->assertMoney(1180.00, $bill->fresh()->paid_amount);
        $this->assertMoney(0.00, $bill->fresh()->balance, 'a negative payable is money nobody owes');
    }

    public function test_a_payment_that_races_a_cancellation_is_refused(): void
    {
        $inv = $this->issued();
        $stale = Invoice::find($inv->id); // the payment tab still shows "Issued"
        $this->cancel($inv)->assertRedirect();

        $this->actingAs($this->user);
        try {
            app(\App\Http\Controllers\InvoiceController::class)->recordPayment(
                $this->requestAs(['amount' => 1180, 'method' => 'cash', 'received_at' => '2026-10-12']), $stale);
            $this->fail('money was booked on a cancelled invoice');
        } catch (ValidationException) {
        }
        $this->assertSame(0, Payment::count());
        $this->assertSame('cancelled', $inv->fresh()->status);
    }

    public function test_a_cancellation_that_races_a_payment_is_refused_and_the_money_stays(): void
    {
        $inv = $this->issued();
        $stale = Invoice::find($inv->id); // the cancel tab still shows nothing received
        $receipt = $this->pay($inv, 500);

        $this->actingAs($this->user);
        app(InvoiceShareController::class)->cancel($this->requestAs(['cancellation_reason' => 'Billed to the wrong customer']), $stale);

        $fresh = $inv->fresh();
        $this->assertSame('partially_paid', $fresh->status);
        $this->assertNull($fresh->cancelled_at);
        $this->assertNotNull(Payment::find($receipt->id));
        $this->assertMoney(680.00, $fresh->balance);
    }

    public function test_reversing_the_same_receipt_twice_changes_nothing_the_second_time(): void
    {
        $inv = $this->issued();
        $receipt = $this->pay($inv, 500);

        $this->actingAs($this->user)->delete(route('payments.destroy', $receipt))->assertRedirect();
        $this->actingAs($this->user)->delete(route('payments.destroy', $receipt))->assertNotFound();
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/payments/{$receipt->id}")->assertNotFound();

        $this->assertSame('final', $inv->fresh()->status);
        $this->assertMoney(1180.00, $inv->fresh()->balance);
    }

    private function purchaseBill(): PurchaseBill
    {
        $vendor = $this->company->vendors()->create([
            'user_id' => $this->user->id, 'name' => 'Mumbai Metals', 'state_id' => $this->mh->id, 'gstin' => '27PQRST1234U1Z1',
        ]);
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'MM/77', 'bill_date' => '2026-10-10', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Steel sheet', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();

        $bill = PurchaseBill::latest('id')->firstOrFail();
        $this->assertMoney(1180.00, $bill->grand_total);

        return $bill;
    }
}
