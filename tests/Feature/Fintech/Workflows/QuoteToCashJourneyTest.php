<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Mail\PaymentReminderMail;
use App\Mail\QuotationMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Quote to cash, walked the way a business owner does it on the website:
 * draft a quotation, send it, the customer opens the public link, accepts,
 * the quote becomes a draft tax invoice, it is issued, paid in two parts
 * (the first with TDS deducted by the customer), receipts are printed, and
 * a paid invoice is never chased for money again.
 *
 * The one quotation used throughout (hand-worked):
 *   3 x 1,250.50 = 3,751.50 taxable (intra-state, Maharashtra -> Maharashtra)
 *   CGST 9% = 337.635 -> 337.64 ; SGST 9% = 337.635 -> 337.64
 *   (each head is rounded on its own, the NIC e-invoice way, so the tax is
 *    675.28 and not 18% of 3,751.50 = 675.27 rounded once)
 *   raw total 3,751.50 + 675.28 = 4,426.78 -> grand total 4,427, round off +0.22
 */
class QuoteToCashJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();

        $this->biz = $this->makeBusiness([
            'quote_prefix' => 'QT',
            'quote_counter' => 0,
            'receipt_prefix' => 'RCPT',
            'receipt_counter' => 0,
            'receipt_number_padding' => 4,
        ]);
        $this->owner = $this->ownerOf($this->biz);
        $this->customer = $this->customerOf($this->biz, '27');
    }

    // ------------------------------------------------------------------ journey

    public function test_a_quote_turns_into_cash_from_first_draft_to_paid_receipts(): void
    {
        Mail::fake();

        // ---- 1. Draft the quotation --------------------------------------
        $res = $this->actingAs($this->owner)->post(route('quotations.store'), $this->quotePayload());
        $quote = Quotation::where('company_id', $this->biz->id)->latest('id')->firstOrFail();
        $res->assertRedirect(route('quotations.show', $quote))->assertSessionHas('status', 'Quotation draft saved.');

        $this->assertSame('draft', $quote->status);
        $this->assertNull($quote->quote_number, 'a draft has no number until it is sent');
        $this->assertFalse((bool) $quote->is_interstate, 'Maharashtra to Maharashtra is intra-state');
        $this->assertQuoteTotals($quote);
        $this->assertCount(1, $quote->items);
        $line = $quote->items->first();
        $this->assertMoney(3, $line->quantity);
        $this->assertMoney(1250.50, $line->rate);
        $this->assertMoney(3751.50, $line->amount);          // 3 x 1,250.50
        $this->assertMoney(337.64, $line->cgst_amount);
        $this->assertMoney(337.64, $line->sgst_amount);
        $this->assertMoney(0, $line->igst_amount);

        $page = $this->flatten($this->actingAs($this->owner)->get(route('quotations.show', $quote))->assertOk()->getContent());
        $this->assertStringContainsString('Grand total ₹4,427', $page);

        // ---- 2. Send it: the number is assigned now ----------------------
        $this->actingAs($this->owner)->post(route('quotations.send', $quote))
            ->assertRedirect(route('quotations.show', $quote))
            ->assertSessionHas('status', 'Quotation QT-0001 is ready to share by WhatsApp or email.');
        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertSame('QT-0001', $quote->quote_number);
        $this->assertNotNull($quote->sent_at);
        $this->assertSame(1, (int) $this->biz->fresh()->quote_counter);

        // ---- 3. Email it to the customer --------------------------------
        $this->actingAs($this->owner)->post(route('quotations.share.email', $quote), [
            'to' => 'accounts@patilstores.example',
            'subject' => 'Quotation QT-0001 from Sharma Traders',
            'body' => 'Please find our quotation attached.',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Quotation emailed to accounts@patilstores.example.');

        Mail::assertSent(QuotationMail::class, 1);
        $mailed = null;
        Mail::assertSent(QuotationMail::class, function (QuotationMail $m) use ($quote, &$mailed) {
            $mailed = $m;

            return $m->hasTo('accounts@patilstores.example')
                && $m->quotation->is($quote)
                && $m->customSubject === 'Quotation QT-0001 from Sharma Traders';
        });
        $this->assertSame('QT-0001', $quote->fresh()->quote_number, 'emailing a sent quote does not renumber it');
        $this->assertSame(1, (int) $this->biz->fresh()->quote_counter, 'and does not burn a number');
        $this->assertStringContainsString('/q/' . $quote->id, (string) $mailed->publicUrl);
        $this->assertStringContainsString('signature=', (string) $mailed->publicUrl);

        // ---- 4. The customer opens the public link, without logging in ---
        $link = $this->actingAs($this->owner)->get(route('quotations.share.link', $quote))->assertOk()->json();
        $this->assertSame(30, (int) $link['expires_in_days']);
        $url = $link['url'];
        // A second, real quotation, to point a stolen signature at.
        $this->actingAs($this->owner)->post(route('quotations.store'), $this->quotePayload(['subject' => 'Another quote']))->assertSessionHasNoErrors();
        $otherQuote = Quotation::where('company_id', $this->biz->id)->latest('id')->firstOrFail();
        $this->assertNotSame($quote->id, $otherQuote->id);

        $this->app['auth']->forgetGuards();
        $this->assertGuest();
        foreach ([$url, $mailed->publicUrl] as $publicUrl) {
            $public = $this->flatten($this->get($publicUrl)->assertOk()->getContent());
            $this->assertStringContainsString('Quotation no. QT-0001', $public);
            $this->assertStringContainsString('Prepared for Patil Stores', $public);
            $this->assertStringContainsString('Valid until 11 Nov 2026', $public);
            $this->assertStringContainsString('Quoted total ₹4,427', $public);
        }

        // A tampered link is refused: a changed signature, a stretched
        // expiry, or the signature of this quote pointed at another one.
        $this->get(preg_replace('/signature=([0-9a-f])/', 'signature=' . ($this->firstHexChar($url) === 'a' ? 'b' : 'a'), $url))->assertForbidden();
        $this->get(preg_replace('/expires=(\d+)/', 'expires=9999999999', $url))->assertForbidden();
        $this->get(str_replace('/q/' . $quote->id . '?', '/q/' . $otherQuote->id . '?', $url))->assertForbidden();
        $this->get('/q/' . $quote->id)->assertForbidden();

        // ... and so is a genuine link after its 30 days.
        $this->travelTo(Carbon::parse('2026-11-11 11:01:00', 'Asia/Kolkata'));
        $this->get($url)->assertForbidden();
        $this->travelTo(Carbon::parse('2026-10-12 11:00:00', 'Asia/Kolkata'));
        $this->get($url)->assertOk();

        // ---- 5. Accepted, then converted to a draft tax invoice ----------
        $this->actingAs($this->owner)->post(route('quotations.accept', $quote))
            ->assertRedirect(route('quotations.show', $quote))
            ->assertSessionHas('status', 'Quotation marked as accepted. It can now be converted to a tax invoice.');
        $this->assertSame('accepted', $quote->fresh()->status);

        $res = $this->actingAs($this->owner)->post(route('quotations.convert', $quote));
        $quote->refresh();
        $invoice = Invoice::findOrFail($quote->converted_to_invoice_id);
        $res->assertRedirect(route('invoices.edit', $invoice))
            ->assertSessionHas('status', 'Quotation converted to a draft invoice. Check it, then click Issue.');

        $this->assertSame('converted', $quote->status);
        $this->assertNotNull($quote->converted_at);
        $this->assertSame('draft', $invoice->status);
        $this->assertNull($invoice->invoice_number, 'the legal number is given at issue, not at conversion');
        $this->assertSame($this->customer->id, (int) $invoice->customer_id);
        $this->assertFalse((bool) $invoice->is_interstate);
        $this->assertSame('2026-10-12', $invoice->invoice_date->toDateString());
        $this->assertSame('2026-11-11', $invoice->due_date->toDateString());
        // Identical money to the quote, head by head.
        foreach (['subtotal', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax', 'round_off', 'grand_total'] as $f) {
            $this->assertMoney((float) $quote->$f, $invoice->$f, "invoice {$f} must equal the quote's");
        }
        $this->assertMoney(4427, $invoice->balance);
        $this->assertMoney(0, $invoice->paid_amount);
        $this->assertCount(1, $invoice->items);
        $il = $invoice->items->first();
        $this->assertSame('Steel almirah', $il->description);
        $this->assertSame('94031010', $il->hsn_sac);
        $this->assertSame('NOS', $il->unit);
        $this->assertMoney(3, $il->quantity);
        $this->assertMoney(1250.50, $il->rate);
        $this->assertMoney(3751.50, $il->amount);
        $this->assertMoney(337.64, $il->cgst_amount);
        $this->assertMoney(337.64, $il->sgst_amount);
        $this->assertMoney(4426.78, $il->total);                 // 3,751.50 + 337.64 + 337.64

        // Converting twice is refused, and no second invoice appears.
        $this->actingAs($this->owner)->post(route('quotations.convert', $quote))->assertStatus(422);
        $this->assertSame(1, Invoice::where('company_id', $this->biz->id)->count());

        // ---- 6. Issue ------------------------------------------------------
        $invoice = $this->webIssue($this->owner, $invoice);
        $this->assertSame('INV-0001', $invoice->invoice_number);
        $this->assertSame('final', $invoice->status);
        $this->assertNotNull($invoice->finalized_at);

        // ---- 7. Part payment with TDS --------------------------------------
        // The customer deducts TDS u/s 194J (professional fees) at 10% of the
        // taxable value - TDS is on the value before GST (CBDT Circular
        // 23/2017): 10% x 3,751.50 = 375.15. They transfer 1,624.85 by NEFT.
        // The form takes the gross settled (cash + TDS) = 2,000.00, with the
        // TDS part recorded for Form 26AS.  Balance: 4,427 - 2,000 = 2,427.
        $this->actingAs($this->owner)->post(route('invoices.payments', $invoice), [
            'amount' => 2000.00,
            'tds_amount' => 375.15,
            'tds_section' => '194J_prof',
            'tds_rate' => 10,
            'method' => 'neft',
            'reference_number' => 'NEFT-UTR-0001',
            'received_at' => '2026-10-12',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Payment recorded. Receipt RCPT-0001 issued.');

        $first = Payment::where('invoice_id', $invoice->id)->orderBy('id')->firstOrFail();
        $this->assertSame('RCPT-0001', $first->receipt_number);
        $this->assertMoney(2000.00, $first->amount);
        $this->assertMoney(375.15, $first->tds_amount);
        $this->assertSame('194J_prof', $first->tds_section);
        $this->assertMoney(10, $first->tds_rate);
        $this->assertMoney(1624.85, $first->netReceived(), 'cash to bank = 2,000 - 375.15');
        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertMoney(2000.00, $invoice->paid_amount);
        $this->assertMoney(2427.00, $invoice->balance, 'the balance falls by cash 1,624.85 + TDS 375.15');

        // ---- 8. Overpaying the balance is refused --------------------------
        $this->webPay($this->owner, $invoice, 2427.01)->assertSessionHasErrors('amount');
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, (int) $this->biz->fresh()->receipt_counter, 'a refused payment burns no receipt number');
        $this->assertMoney(2427.00, $invoice->fresh()->balance);

        // ---- 9. Final payment closes the invoice ---------------------------
        $this->webPay($this->owner, $invoice, 2427.00)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Payment recorded. Receipt RCPT-0002 issued.');
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertMoney(0, $invoice->balance);
        $this->assertMoney(4427.00, $invoice->paid_amount);      // 2,000 + 2,427
        $second = Payment::where('invoice_id', $invoice->id)->orderByDesc('id')->firstOrFail();
        $this->assertSame('RCPT-0002', $second->receipt_number, 'receipt numbers run in sequence');

        // ---- 10. Receipt PDFs ---------------------------------------------
        [$pdf, $text] = $this->receipt($first);
        $this->assertStringContainsString('receipt-RCPT-0001.pdf', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringContainsString('Receipt no. RCPT-0001', $text);
        $this->assertStringContainsString('Against invoice INV-0001', $text);
        $this->assertStringContainsString('Received from Patil Stores', $text);
        $this->assertStringContainsString('Invoice total ₹ 4,427', $text);
        $this->assertMatchesRegularExpression('/Previously paid ₹ 0(?![\d,.])/u', $text);
        $this->assertStringContainsString('Balance after this payment ₹ 2,427', $text);
        $this->assertStringContainsString('Amount received ₹ 2,000 Rupees Two Thousand Only', $text);
        $this->assertStringContainsString('Gross amount applied to invoice ₹ 2,000', $text);
        $this->assertStringContainsString('- ₹ 375.15', $text);
        $this->assertStringContainsString('Net cash received in bank ₹ 1,624.85', $text);
        $this->assertStringContainsString('NEFT-UTR-0001', $text);
        $this->assertStringNotContainsString('PAID IN FULL', $text, 'the first instalment does not settle the bill');

        [$pdf, $text] = $this->receipt($second);
        $this->assertStringContainsString('receipt-RCPT-0002.pdf', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringContainsString('Receipt no. RCPT-0002', $text);
        $this->assertStringContainsString('Previously paid ₹ 2,000', $text);
        $this->assertMatchesRegularExpression('/Balance after this payment ₹ 0(?![\d,.])/u', $text);
        $this->assertStringContainsString('Amount received ₹ 2,427 Rupees Two Thousand Four Hundred Twenty Seven Only', $text);
        $this->assertStringNotContainsString('TDS deducted at source', $text, 'no TDS on the second payment');
        $this->assertStringContainsString('PAID IN FULL', $text);

        // ---- 11. A paid invoice is never chased ---------------------------
        $this->actingAs($this->owner)->post(route('invoices.remind', $invoice), ['channel' => 'email'])->assertStatus(422);

        foreach (['2026-11-11 08:00:00', '2026-11-14 08:00:00', '2026-12-11 08:00:00'] as $day) {
            $this->travelTo(Carbon::parse($day, 'Asia/Kolkata'));
            $this->artisan('invoices:send-reminders')->assertSuccessful();
        }
        Mail::assertNotSent(PaymentReminderMail::class);
        $this->assertSame(0, InvoiceReminder::where('invoice_id', $invoice->id)->count());
    }

    // ------------------------------------------------------- focused checks

    public function test_the_receipt_prints_the_tds_rate_that_was_deducted(): void
    {
        // TDS u/s 194J at 10% on taxable 3,751.50 = 375.15. The receipt goes
        // to the customer's accountant for Form 26AS / 16A matching, so it
        // must say 10%, not 1%.
        $invoice = $this->issuedFromQuote();
        $this->actingAs($this->owner)->post(route('invoices.payments', $invoice), [
            'amount' => 4427, 'tds_amount' => 375.15, 'tds_section' => '194J_prof', 'tds_rate' => 10,
            'method' => 'neft', 'received_at' => '2026-10-12',
        ])->assertSessionHasNoErrors();

        [, $text] = $this->receipt($this->latestPayment());
        $this->assertStringContainsString('Net cash received in bank ₹ 4,051.85', $text);   // 4,427 - 375.15
        $this->assertStringContainsString('(10%)', $text,
            'a 10% TDS rate must print as 10%: inr(10) gives "10", then rtrim("10", "0") leaves "1"');
        $this->assertStringNotContainsString('(1%)', $text);
    }

    public function test_a_payment_larger_than_the_balance_is_refused_even_when_part_of_it_is_tds(): void
    {
        $invoice = $this->issuedFromQuote();

        // Cash 4,427 + TDS 73 = gross 4,500 against a 4,427 bill: 73 too much.
        $this->actingAs($this->owner)->post(route('invoices.payments', $invoice), [
            'amount' => 4500, 'tds_amount' => 73, 'tds_section' => '194C_other', 'tds_rate' => 2,
            'method' => 'neft', 'received_at' => '2026-10-12',
        ])->assertSessionHasErrors('amount');
        // One paisa over is refused too.
        $this->webPay($this->owner, $invoice, 4427.01)->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, (int) $this->biz->fresh()->receipt_counter);
        $this->assertSame('final', $invoice->fresh()->status);

        // The exact balance, TDS included, settles it with receipt 0001 (no gap).
        // 194C company contractor 2% x 3,751.50 = 75.03; cash 4,351.97.
        $this->actingAs($this->owner)->post(route('invoices.payments', $invoice), [
            'amount' => 4427, 'tds_amount' => 75.03, 'tds_section' => '194C_other', 'tds_rate' => 2,
            'method' => 'neft', 'received_at' => '2026-10-12',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Payment recorded. Receipt RCPT-0001 issued.');
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertMoney(0, $invoice->balance);
        $this->assertMoney(4351.97, $this->latestPayment()->netReceived());

        // Nothing more can be received on a settled bill.
        $this->webPay($this->owner, $invoice, 1)->assertSessionHasErrors('amount');
        $this->assertSame(1, Payment::count());
    }

    public function test_converting_reprices_the_tax_for_where_the_customer_is_now(): void
    {
        // Quoted intra-state (CGST+SGST). Before the order is confirmed the
        // customer's billing state changes to Karnataka (29): the place of
        // supply is now outside Maharashtra, so the invoice must carry IGST
        // (s.10 IGST Act), worked out today, not copied from the quote.
        //   IGST 18% x 3,751.50 = 675.27; raw 4,426.77 -> 4,427, round off +0.23
        $quote = $this->sentQuote();
        $this->actingAs($this->owner)->post(route('quotations.accept', $quote))->assertRedirect();
        $this->customer->update(['state_id' => $this->state('29')->id]);

        $this->actingAs($this->owner)->post(route('quotations.convert', $quote))->assertRedirect();
        $invoice = Invoice::findOrFail($quote->fresh()->converted_to_invoice_id);

        $this->assertTrue((bool) $invoice->is_interstate);
        $this->assertSame($this->state('29')->id, (int) $invoice->place_of_supply_state_id);
        $this->assertMoney(3751.50, $invoice->subtotal);
        $this->assertMoney(0, $invoice->total_cgst);
        $this->assertMoney(0, $invoice->total_sgst);
        $this->assertMoney(675.27, $invoice->total_igst);
        $this->assertMoney(675.27, $invoice->total_tax);
        $this->assertMoney(0.23, $invoice->round_off);
        $this->assertMoney(4427, $invoice->grand_total);
        $this->assertMoney(675.27, $invoice->items->first()->igst_amount);

        // and it issues without a stale-tax complaint.
        $issued = $this->webIssue($this->owner, $invoice);
        $this->assertSame('INV-0001', $issued->invoice_number);
    }

    public function test_a_quote_converted_on_the_website_cannot_be_converted_again_from_the_app(): void
    {
        $quote = $this->sentQuote();
        $this->actingAs($this->owner)->post(route('quotations.accept', $quote))->assertRedirect();
        $this->actingAs($this->owner)->post(route('quotations.convert', $quote))->assertRedirect();

        $this->apiAs($this->owner)->postJson("/api/quotations/{$quote->id}/convert")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This quotation is already converted or declined, so it cannot be converted.');
        $this->assertSame(1, Invoice::count());
    }

    public function test_another_business_cannot_touch_this_quote_its_invoice_or_its_receipts(): void
    {
        Mail::fake();
        $invoice = $this->issuedFromQuote();
        $quote = Quotation::where('converted_to_invoice_id', $invoice->id)->firstOrFail();
        $this->webPay($this->owner, $invoice, 1000)->assertSessionHasNoErrors();
        $payment = $this->latestPayment();

        $rival = $this->ownerOf($this->makeBusiness(['name' => 'Rival Traders', 'gstin' => '27AAACT2727Q1ZW']));

        $this->actingAs($rival)->get(route('quotations.show', $quote))->assertForbidden();
        $this->actingAs($rival)->get(route('quotations.share.link', $quote))->assertForbidden();
        $this->actingAs($rival)->post(route('quotations.share.email', $quote), [
            'to' => 'x@example.com', 'subject' => 'x', 'body' => 'x',
        ])->assertForbidden();
        $this->actingAs($rival)->get(route('payments.receipt', $payment))->assertForbidden();
        $status = $this->actingAs($rival)->post(route('invoices.payments', $invoice), [
            'amount' => 10, 'method' => 'cash', 'received_at' => '2026-10-12',
        ])->getStatusCode();
        $this->assertContains($status, [403, 404]);

        Mail::assertNothingSent();
        $this->assertSame(1, Payment::count());
        $this->assertMoney(3427, $invoice->fresh()->balance);   // 4,427 - 1,000
    }

    // ---------------------------------------------------------------- helpers

    private function quotePayload(array $over = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'quote_date' => '2026-10-12',
            'valid_until' => '2026-11-11',
            'subject' => 'Supply of steel almirahs',
            'currency' => 'INR',
            'style' => 'classic',
            'items' => [[
                'description' => 'Steel almirah',
                'hsn_sac' => '94031010',
                'quantity' => 3,
                'unit' => 'NOS',
                'rate' => 1250.50,
                'gst_rate' => 18,
            ]],
        ], $over);
    }

    private function assertQuoteTotals(Quotation $q): void
    {
        $this->assertMoney(3751.50, $q->subtotal);      // 3 x 1,250.50
        $this->assertMoney(337.64, $q->total_cgst);     // 9% = 337.635 -> 337.64
        $this->assertMoney(337.64, $q->total_sgst);
        $this->assertMoney(0, $q->total_igst);
        $this->assertMoney(675.28, $q->total_tax);
        $this->assertMoney(0.22, $q->round_off);        // 4,427 - 4,426.78
        $this->assertMoney(4427, $q->grand_total);
    }

    private function sentQuote(): Quotation
    {
        $this->actingAs($this->owner)->post(route('quotations.store'), $this->quotePayload())->assertSessionHasNoErrors();
        $quote = Quotation::where('company_id', $this->biz->id)->latest('id')->firstOrFail();
        $this->actingAs($this->owner)->post(route('quotations.send', $quote))->assertSessionHasNoErrors();

        return $quote->fresh();
    }

    private function issuedFromQuote(): Invoice
    {
        $quote = $this->sentQuote();
        $this->actingAs($this->owner)->post(route('quotations.accept', $quote))->assertRedirect();
        $this->actingAs($this->owner)->post(route('quotations.convert', $quote))->assertRedirect();

        return $this->webIssue($this->owner, Invoice::findOrFail($quote->fresh()->converted_to_invoice_id));
    }

    /**
     * Download the receipt through the real route and return [response,
     * visible text of the exact HTML the PDF was drawn from]. The PDF text
     * itself is font-subset encoded, so the view the route rendered is
     * captured instead.
     *
     * @return array{0: \Illuminate\Testing\TestResponse, 1: string}
     */
    private function receipt(Payment $payment): array
    {
        $captured = null;
        View::composer('payments.receipt', function ($view) use (&$captured) {
            $captured = $view;
        });
        $res = $this->actingAs($this->owner)->get(route('payments.receipt', $payment));
        $this->assertIsPdf($res);
        $this->assertNotNull($captured, 'the receipt route should render payments.receipt');
        $this->assertTrue($captured->getData()['payment']->is($payment));

        return [$res, $this->flatten($captured->render())];
    }

    private function firstHexChar(string $url): string
    {
        preg_match('/signature=([0-9a-f])/', $url, $m);

        return $m[1] ?? '0';
    }
}
