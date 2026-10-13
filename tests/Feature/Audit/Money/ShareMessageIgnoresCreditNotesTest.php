<?php

namespace Tests\Feature\Audit\Money;

/**
 * The WhatsApp / share / reminder text (Invoice::shareMessageText) decides
 * between "paid", "part paid" and "unpaid" on paid_amount alone. A credit
 * note with no payment falls into the "unpaid" branch, which quotes the
 * full grand total as due/overdue and offers "Pay via UPI".
 */
class ShareMessageIgnoresCreditNotesTest extends MoneyAuditBase
{
    private function invoice()
    {
        // 1,000 + 18% = 1,180, dated 5 Sep, due 15 Sep (15 days overdue on 30 Sep).
        return $this->issue([
            ['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ], '2026-09-05', '2026-09-15');
    }

    public function test_part_credited_unpaid_invoice_message_quotes_the_balance_not_the_full_total(): void
    {
        $inv = $this->invoice();
        $this->creditNote($inv, ['amount' => 590])->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertEqualsWithDelta(590.0, (float) $inv->balance, 0.001);

        $text = $inv->shareMessageText('reminder');

        // What is owed is ₹590. The message tells the customer ₹1,180 is overdue.
        $this->assertStringContainsString('590', $text, "Message sent to customer:\n" . $text);
    }

    public function test_fully_credited_invoice_message_does_not_chase_payment(): void
    {
        $inv = $this->invoice();
        $this->creditNote($inv, ['amount' => 1180, 'reason' => 'sales_return'])->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $inv->balance, 0.001);
        $this->assertSame('paid', $inv->status);

        $text = $inv->shareMessageText('share');

        // Nothing is owed (the whole bill was credited), yet the text reads
        // "... for ₹1,180 is 15 day(s) overdue." and "Pay via UPI: ...".
        $this->assertStringNotContainsString('overdue', $text, "Message:\n" . $text);
        $this->assertStringNotContainsString('Pay via UPI', $text, "Message:\n" . $text);
    }

    public function test_paid_plus_credited_invoice_thanks_for_what_was_actually_paid(): void
    {
        $inv = $this->invoice();
        $this->pay($inv, ['amount' => 500, 'received_at' => '2026-09-20'])->assertSessionHasNoErrors();
        $this->creditNote($inv->fresh(), ['amount' => 680])->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $inv->paid_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inv->balance, 0.001);

        $text = $inv->shareMessageText('share');

        // The customer paid ₹500; the rest was credited. The text thanks them
        // for "the payment of ₹1,180".
        $this->assertStringNotContainsString('payment of ₹1,180', $text, "Message:\n" . $text);
    }

    public function test_reminder_email_breakdown_adds_up_when_a_credit_note_exists(): void
    {
        $inv = $this->invoice();
        $this->creditNote($inv, ['amount' => 590])->assertSessionHasNoErrors();
        $inv->refresh();

        $html = view('emails.payment-reminder', [
            'invoice' => $inv, 'daysPastDue' => 15, 'publicUrl' => 'https://example.test/x',
        ])->render();

        // "Balance due ₹590" over "Invoice total: ₹1,180" with no credit-note
        // line: the customer cannot see why 1,180 became 590.
        $this->assertStringContainsString('590', $html);
        $this->assertMatchesRegularExpression('/[Cc]redit/', $html, 'Reminder email omits the credit note that reduced the balance.');
    }
}
