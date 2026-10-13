<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Mail\PaymentReminderMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Chasing money: the manual "Send email reminder" button, the WhatsApp
 * hand-off, the daily invoices:send-reminders job and the app's
 * POST /api/invoices/{invoice}/remind.
 *
 * Fixture invoice (hand-worked): 1 x 1,000 @ 18% intra-state
 *   = 1,000 + CGST 90 + SGST 90 = 1,180.00, dated Mon 12 Oct 2026,
 *   due 30 days later = Wed 11 Nov 2026.
 * Reminder thresholds (config/reminders.php): 0, 3, 7, 15, 30 days past due
 *   -> 11 Nov, 14 Nov, 18 Nov, 26 Nov and 11 Dec 2026.
 */
class PaymentRemindersJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        Mail::fake();

        $this->biz = $this->makeBusiness(['upi_id' => 'sharmatraders@okicici']);
        $this->owner = $this->ownerOf($this->biz);
        $this->customer = $this->customerOf($this->biz);   // accounts@patilstores.example, 9876500001
    }

    // ------------------------------------------------------- manual, web

    public function test_a_manual_email_reminder_goes_to_the_customer_and_is_logged_once(): void
    {
        $invoice = $this->webIssued($this->owner, $this->customer);
        $this->assertSame('INV-0001', $invoice->invoice_number);
        $this->assertSame('2026-11-11', $invoice->due_date->toDateString());

        $this->actingAs($this->owner)->from(route('invoices.show', $invoice))
            ->post(route('invoices.remind', $invoice), ['channel' => 'email'])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Reminder sent via email to accounts@patilstores.example.');

        Mail::assertSent(PaymentReminderMail::class, 1);
        Mail::assertSent(PaymentReminderMail::class, fn (PaymentReminderMail $m) => $m->hasTo('accounts@patilstores.example')
            && $m->invoice->is($invoice));

        $rows = InvoiceReminder::where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('email', $row->channel);
        $this->assertSame('sent', $row->status);
        $this->assertSame('manual', $row->trigger);
        $this->assertSame('accounts@patilstores.example', $row->recipient);
        $this->assertSame($this->biz->id, (int) $row->company_id);
        $this->assertSame(-30, (int) $row->days_past_due, '12 Oct is 30 days before the 11 Nov due date');

        // The invoice page lists it under recent reminders.
        $page = $this->flatten($this->actingAs($this->owner)->get(route('invoices.show', $invoice))->assertOk()->getContent());
        $this->assertStringContainsString('Recent reminders', $page);
        $this->assertStringContainsString('Due in 30 days.', $page);
    }

    public function test_a_reminder_sent_before_the_due_date_names_the_due_date_not_today(): void
    {
        // Sent by hand on 12 Oct for a bill due 11 Nov: telling the customer
        // it is "due today" would be false. PaymentReminderMail has wording
        // for this ("is due on 11 Nov 2026"), but it is only reached if the
        // real days-past-due (-30) is passed through.
        $invoice = $this->webIssued($this->owner, $this->customer);
        $this->actingAs($this->owner)->post(route('invoices.remind', $invoice), ['channel' => 'email'])->assertSessionHasNoErrors();

        $mail = $this->mailsFor($invoice)->sole();
        $this->assertSame(-30, $mail->daysPastDue, 'the mail must get the real (negative) days, not max(0, days)');
        $this->assertSame('Payment reminder: invoice INV-0001 is due on 11 Nov 2026', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('is due on <strong>11 Nov 2026</strong>', $html);
        $this->assertStringNotContainsString('due today', $html);
    }

    public function test_an_overdue_manual_reminder_states_the_days_overdue_and_the_balance(): void
    {
        $invoice = $this->webIssued($this->owner, $this->customer);
        $this->webPay($this->owner, $invoice, 500)->assertSessionHasNoErrors();   // balance 1,180 - 500 = 680

        // Mon 23 Nov 2026 is 12 days after the 11 Nov due date.
        $this->travelTo(Carbon::parse('2026-11-23 10:00:00', 'Asia/Kolkata'));
        $this->actingAs($this->owner)->post(route('invoices.remind', $invoice), ['channel' => 'email'])->assertSessionHasNoErrors();

        $mail = $this->mailsFor($invoice)->sole();
        $this->assertSame(12, $mail->daysPastDue);
        $this->assertSame('Payment reminder: invoice INV-0001 is 12 days overdue', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('was due on <strong>11 Nov 2026</strong> and is now <strong>12 days overdue</strong>', $html);
        $text = $this->flatten($html);
        $this->assertStringContainsString('Balance due ₹ 680', $text, 'the reminder asks for what is still owed, not the bill total');
        $this->assertStringContainsString('Invoice total: ₹1,180 · Paid: ₹500', $text);
        $this->assertStringContainsString('Pay via UPI: sharmatraders@okicici', $text);
        $this->assertSame(12, (int) InvoiceReminder::where('invoice_id', $invoice->id)->value('days_past_due'));
    }

    public function test_whatsapp_reminders_open_a_wa_me_chat_with_the_91_code_and_the_right_amount(): void
    {
        $invoice = $this->webIssued($this->owner, $this->customer);

        // There is no WhatsApp Business API account behind the remind route,
        // so the route refuses it plainly (and logs the attempt) rather than
        // pretending it was sent.
        $this->actingAs($this->owner)->from(route('invoices.show', $invoice))
            ->post(route('invoices.remind', $invoice), ['channel' => 'whatsapp'])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasErrors(['reminder' => 'Reminders by whatsapp are not set up.']);
        Mail::assertNothingSent();
        $row = InvoiceReminder::where('invoice_id', $invoice->id)->sole();
        $this->assertSame(['whatsapp', 'failed', 'manual'], [$row->channel, $row->status, $row->trigger]);

        // The WhatsApp button on the invoice page is the reminder: a wa.me
        // chat with the bare 10-digit mobile turned into 91 + number.
        [$number, $text] = $this->waLinkOnPage($invoice);
        $this->assertSame('919876500001', $number);
        $this->assertStringContainsString('Hi Patil Stores,', $text);
        $this->assertStringContainsString('INV-0001 for ₹1,180. Due by 11 Nov 2026.', $text);
        $this->assertStringContainsString('/i/' . $invoice->id . '?', $text, 'the message carries the public invoice link');
        $this->assertStringContainsString('Pay via UPI: sharmatraders@okicici', $text);

        // After a part payment the message asks for the balance only.
        $this->webPay($this->owner, $invoice, 500)->assertSessionHasNoErrors();
        [, $text] = $this->waLinkOnPage($invoice);
        $this->assertStringContainsString('Already received: ₹500.', $text);
        $this->assertStringContainsString('Balance: ₹680 (due by 11 Nov 2026).', $text);   // 1,180 - 500

        // An unpaid bill seen on Sat 14 Nov is 3 days late.
        $late = $this->webIssued($this->owner, $this->customer);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Kolkata'));
        [$number, $text] = $this->waLinkOnPage($late);
        $this->assertSame('919876500001', $number);
        $this->assertStringContainsString('INV-0002 for ₹1,180 is 3 day(s) overdue.', $text);

        // A number saved with the code already is not given a second 91,
        // and the app gets the same link.
        $this->customer->update(['phone' => '+91 98765 00001']);
        [$number] = $this->waLinkOnPage($late);
        $this->assertSame('919876500001', $number);
        $api = $this->apiAs($this->owner)->getJson("/api/invoices/{$late->id}")->assertOk()->json('data.whatsapp_link');
        $this->assertStringStartsWith('https://wa.me/919876500001?text=', $api);
        $this->assertStringContainsString('INV-0002 for ₹1,180 is 3 day(s) overdue.', rawurldecode((string) parse_url($api, PHP_URL_QUERY)));
    }

    public function test_the_per_invoice_reminder_cap_refuses_the_next_reminder(): void
    {
        // routes/web.php promises a "hard cap to prevent spam against a single
        // customer" of 5. What exists is throttle:5,1 - five a minute per
        // signed-in user - which resets every minute, so one customer can be
        // sent 5 x 60 = 300 reminder emails an hour for the same bill. A real
        // cap counts what this invoice has already been sent today.
        $invoice = $this->webIssued($this->owner, $this->customer);

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->owner)->post(route('invoices.remind', $invoice), ['channel' => 'email'])
                ->assertSessionHasNoErrors();
        }
        Mail::assertSent(PaymentReminderMail::class, 5);

        $this->travel(2)->minutes();   // past the throttle window, same day
        $res = $this->actingAs($this->owner)->post(route('invoices.remind', $invoice), ['channel' => 'email']);

        Mail::assertSent(PaymentReminderMail::class, 5);   // fails today: a 6th mail goes out
        $this->assertTrue($res->status() >= 400 || session()->has('errors'), 'the 6th reminder on the same invoice the same day must be refused');
        $this->assertSame(5, InvoiceReminder::where('invoice_id', $invoice->id)->where('status', 'sent')->count());
    }

    public function test_reminders_are_refused_for_drafts_paid_and_cancelled_invoices_and_other_businesses(): void
    {
        $draft = $this->webDraft($this->owner, $this->customer);
        $paid = $this->webIssued($this->owner, $this->customer);
        $this->webPay($this->owner, $paid, 1180)->assertSessionHasNoErrors();
        $cancelled = $this->webIssued($this->owner, $this->customer);
        $this->actingAs($this->owner)->post(route('invoices.cancel', $cancelled), ['cancellation_reason' => 'Order withdrawn by customer'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $open = $this->webIssued($this->owner, $this->customer);

        foreach ([$draft, $paid, $cancelled] as $inv) {
            $this->nextMinute();
            $this->actingAs($this->owner)->post(route('invoices.remind', $inv), ['channel' => 'email'])->assertStatus(422);
        }

        // Someone else's business cannot chase this customer.
        $rival = $this->ownerOf($this->makeBusiness(['name' => 'Rival Traders', 'gstin' => '27AAACT2727Q1ZW']));
        $this->nextMinute();
        $this->assertContains($this->actingAs($rival)->post(route('invoices.remind', $open), ['channel' => 'email'])->status(), [403, 404]);

        // A channel is required, and only the known ones.
        $this->nextMinute();
        $this->actingAs($this->owner)->post(route('invoices.remind', $open), [])->assertSessionHasErrors('channel');
        $this->nextMinute();
        $this->actingAs($this->owner)->post(route('invoices.remind', $open), ['channel' => 'telegram'])->assertSessionHasErrors('channel');

        Mail::assertNothingSent();
        $this->assertSame(0, InvoiceReminder::count());
    }

    // --------------------------------------------------- the daily job

    public function test_the_daily_job_sends_once_at_each_threshold_with_wording_that_matches_the_dates(): void
    {
        $invoice = $this->webIssued($this->owner, $this->customer);

        // Tue 10 Nov: not due yet, nothing goes.
        $this->runJobOn('2026-11-10');
        Mail::assertNothingSent();

        // Wed 11 Nov, the due date: one "due today" mail.
        $this->runJobOn('2026-11-11', 'Done. Sent: 1, Skipped: 0, Failed: 0');
        $sent = $this->mailsFor($invoice);
        $this->assertCount(1, $sent);
        $this->assertSame(0, $sent[0]->daysPastDue);
        $this->assertTrue($sent[0]->hasTo('accounts@patilstores.example'));
        $this->assertSame('Payment reminder: invoice INV-0001 is due today', $sent[0]->envelope()->subject);
        $this->assertStringContainsString('is due <strong>today (11 Nov 2026)</strong>', $sent[0]->render());
        $this->assertStringContainsString('Balance due ₹ 1,180', $this->flatten($sent[0]->render()));

        // The job runs again the same day (a retry, a second cron): no repeat.
        $this->runJobOn('2026-11-11', 'Done. Sent: 0, Skipped: 1, Failed: 0', '18:30:00');
        // Fri 13 Nov is 2 days late: below the 3-day step, nothing new.
        $this->runJobOn('2026-11-13');
        $this->assertCount(1, $this->mailsFor($invoice));

        // Each later step sends exactly one more, in words that match the date.
        $steps = [
            '2026-11-14' => 3,    // 11 Nov + 3
            '2026-11-18' => 7,    // 11 Nov + 7
            '2026-11-26' => 15,   // 11 Nov + 15
            '2026-12-11' => 30,   // 11 Nov + 30 (20 days left in Nov + 10 in Dec)
        ];
        $expected = 1;
        foreach ($steps as $day => $days) {
            $this->runJobOn($day);
            $this->runJobOn($day, null, '20:00:00');   // twice the same day
            $expected++;
            $sent = $this->mailsFor($invoice);
            $this->assertCount($expected, $sent, "one more reminder on {$day}, not two");
            $last = $sent->last();
            $this->assertSame($days, $last->daysPastDue);
            $this->assertSame("Payment reminder: invoice INV-0001 is {$days} days overdue", $last->envelope()->subject);
            $this->assertStringContainsString("was due on <strong>11 Nov 2026</strong> and is now <strong>{$days} days overdue</strong>", $last->render());
        }

        // After the last step (30 days) the job stops writing.
        $this->runJobOn('2027-01-12');
        $this->assertCount(5, $this->mailsFor($invoice));

        $rows = InvoiceReminder::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $this->assertSame([0, 3, 7, 15, 30], $rows->pluck('days_past_due')->map(fn ($d) => (int) $d)->all());
        $this->assertSame(['auto'], $rows->pluck('trigger')->unique()->values()->all());
        $this->assertSame(['sent'], $rows->pluck('status')->unique()->values()->all());
    }

    public function test_the_daily_job_never_chases_drafts_paid_or_cancelled_bills_or_customers_without_email(): void
    {
        $noEmail = $this->customerOf($this->biz, '27', ['name' => 'Walk-in Kirana', 'email' => null, 'phone' => '9876500002']);

        $unpaid = $this->webIssued($this->owner, $this->customer);                 // INV-0001, owes 1,180
        $partial = $this->webIssued($this->owner, $this->customer);                // INV-0002
        $this->webPay($this->owner, $partial, 500)->assertSessionHasNoErrors();    // owes 680
        $draft = $this->webDraft($this->owner, $this->customer);
        $paid = $this->webIssued($this->owner, $this->customer);                   // INV-0003
        $this->webPay($this->owner, $paid, 1180)->assertSessionHasNoErrors();
        $cancelled = $this->webIssued($this->owner, $this->customer);              // INV-0004
        $this->actingAs($this->owner)->post(route('invoices.cancel', $cancelled), ['cancellation_reason' => 'Order withdrawn by customer'])
            ->assertSessionHasNoErrors();
        $walkIn = $this->webIssued($this->owner, $noEmail);                        // INV-0005

        // Wed 11 Nov: every bill above is due today (threshold 0).
        $this->runJobOn('2026-11-11');

        $mails = Mail::sent(PaymentReminderMail::class);
        $this->assertCount(2, $mails, 'only the unpaid and the part-paid bill are chased');
        $byInvoice = $mails->keyBy(fn (PaymentReminderMail $m) => $m->invoice->id);
        $this->assertEqualsCanonicalizing([$unpaid->id, $partial->id], $byInvoice->keys()->all());
        $this->assertStringContainsString('Balance due ₹ 1,180', $this->flatten($byInvoice[$unpaid->id]->render()));
        $this->assertStringContainsString('Balance due ₹ 680', $this->flatten($byInvoice[$partial->id]->render()));
        foreach ([$draft, $paid, $cancelled, $walkIn] as $inv) {
            $this->assertCount(0, $this->mailsFor($inv), "invoice #{$inv->id} must not be chased");
        }
        foreach ([$draft, $paid, $cancelled] as $inv) {
            $this->assertSame(0, InvoiceReminder::where('invoice_id', $inv->id)->count());
        }
        // The walk-in customer has no email: nothing sent, and the attempt is
        // logged as failed with the reason, never as sent.
        $walkInRows = InvoiceReminder::where('invoice_id', $walkIn->id)->get();
        $this->assertSame(0, $walkInRows->where('status', 'sent')->count());
        $this->assertSame(['No email contact saved for this customer.'], $walkInRows->pluck('error')->unique()->values()->all());
    }

    public function test_an_invoice_already_overdue_when_first_seen_gets_one_reminder_not_a_burst(): void
    {
        // A bill dated 1 Sep, due 1 Oct, issued (late) today, 12 Oct: it is
        // already 11 days overdue the first time the job sees it (the same
        // happens after any missed run). The customer should get one email
        // saying "11 days overdue". runAutomaticFor() walks every threshold
        // already passed (0, 3, 7) and sends one email for each, in one go,
        // saying "due today", "3 days overdue" and "7 days overdue".
        $invoice = $this->webIssued($this->owner, $this->customer, null, [
            'invoice_date' => '2026-09-01', 'due_date' => '2026-10-01',
        ]);
        $this->assertSame('2026-10-01', $invoice->due_date->toDateString());

        $this->runJobOn('2026-10-12');

        $sent = $this->mailsFor($invoice);
        $this->assertCount(1, $sent, 'one reminder per run per invoice; got: '
            . $sent->map(fn ($m) => $m->envelope()->subject)->implode(' | '));
        $this->assertSame(11, $sent->first()->daysPastDue, '1 Oct to 12 Oct is 11 days');
        $this->assertSame('Payment reminder: invoice INV-0001 is 11 days overdue', $sent->first()->envelope()->subject);
    }

    public function test_the_dry_run_and_the_off_switch_send_nothing(): void
    {
        $invoice = $this->webIssued($this->owner, $this->customer);
        $this->travelTo(Carbon::parse('2026-11-11 08:00:00', 'Asia/Kolkata'));

        $this->artisan('invoices:send-reminders', ['--dry' => true])
            ->expectsOutputToContain('[dry] #INV-0001 — due today, balance ₹1,180')
            ->assertSuccessful();

        config(['reminders.enabled' => false]);
        $this->artisan('invoices:send-reminders')
            ->expectsOutputToContain('Reminders are disabled')
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, InvoiceReminder::where('invoice_id', $invoice->id)->count());
    }

    // ----------------------------------------------------------- the app

    public function test_the_app_reminder_sends_and_refuses_like_the_web(): void
    {
        $open = $this->webIssued($this->owner, $this->customer);
        $draft = $this->webDraft($this->owner, $this->customer);
        $paid = $this->webIssued($this->owner, $this->customer);
        $this->webPay($this->owner, $paid, 1180)->assertSessionHasNoErrors();
        $cancelled = $this->webIssued($this->owner, $this->customer);
        $this->actingAs($this->owner)->post(route('invoices.cancel', $cancelled), ['cancellation_reason' => 'Order withdrawn by customer'])
            ->assertSessionHasNoErrors();

        $this->nextMinute();
        $this->apiAs($this->owner)->postJson("/api/invoices/{$open->id}/remind", ['channel' => 'email'])
            ->assertOk()
            ->assertJson(['status' => 'sent', 'message' => 'Reminder sent via email to accounts@patilstores.example.']);
        Mail::assertSent(PaymentReminderMail::class, fn ($m) => $m->hasTo('accounts@patilstores.example') && $m->invoice->is($open));
        $this->assertSame('manual', InvoiceReminder::where('invoice_id', $open->id)->sole()->trigger);

        foreach ([$draft, $paid, $cancelled] as $inv) {
            $this->nextMinute();
            $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/remind", ['channel' => 'email'])
                ->assertStatus(422)
                ->assertJson(['message' => 'This invoice is not eligible for reminders.']);
        }

        $this->nextMinute();
        $this->apiAs($this->owner)->postJson("/api/invoices/{$open->id}/remind", [])
            ->assertStatus(422)->assertJsonValidationErrors('channel');

        $this->nextMinute();
        $this->apiAs($this->owner)->postJson("/api/invoices/{$open->id}/remind", ['channel' => 'whatsapp'])
            ->assertStatus(422)
            ->assertJson(['status' => 'failed', 'message' => 'Reminders by whatsapp are not set up.']);

        $rival = $this->ownerOf($this->makeBusiness(['name' => 'Rival Traders', 'gstin' => '27AAACT2727Q1ZW']));
        $this->nextMinute();
        $this->assertContains($this->apiAs($rival)->postJson("/api/invoices/{$open->id}/remind", ['channel' => 'email'])->status(), [403, 404]);

        Mail::assertSent(PaymentReminderMail::class, 1);
        $this->assertSame(1, InvoiceReminder::where('status', 'sent')->count());
    }

    public function test_the_app_reminder_respects_the_same_per_invoice_cap(): void
    {
        // Same promise as the web route: no more than 5 reminders to one
        // customer for one bill in a day. Only throttle:5,1 exists.
        $invoice = $this->webIssued($this->owner, $this->customer);
        for ($i = 1; $i <= 5; $i++) {
            $this->apiAs($this->owner)->postJson("/api/invoices/{$invoice->id}/remind", ['channel' => 'email'])->assertOk();
        }
        $this->travel(2)->minutes();
        $res = $this->apiAs($this->owner)->postJson("/api/invoices/{$invoice->id}/remind", ['channel' => 'email']);

        Mail::assertSent(PaymentReminderMail::class, 5);   // fails today: a 6th mail goes out
        $this->assertGreaterThanOrEqual(400, $res->status(), 'the 6th reminder on the same invoice the same day must be refused');
    }

    // ---------------------------------------------------------------- helpers

    /** @return Collection<int, PaymentReminderMail> in send order */
    private function mailsFor(Invoice $invoice): Collection
    {
        return Mail::sent(PaymentReminderMail::class, fn (PaymentReminderMail $m) => $m->invoice->id === $invoice->id)->values();
    }

    private function runJobOn(string $date, ?string $expectOutput = null, string $time = '08:00:00'): void
    {
        $this->travelTo(Carbon::parse("{$date} {$time}", 'Asia/Kolkata'));
        $cmd = $this->artisan('invoices:send-reminders');
        if ($expectOutput !== null) {
            $cmd->expectsOutputToContain($expectOutput);
        }
        $cmd->assertSuccessful();
        $cmd->run();
    }

    /** Step past the 5-a-minute throttle so each refusal is the app's own answer. */
    private function nextMinute(): void
    {
        $this->travel(61)->seconds();
    }

    /** @return array{0: string, 1: string} [wa.me number, decoded message] from the invoice page */
    private function waLinkOnPage(Invoice $invoice): array
    {
        $html = $this->actingAs($this->owner)->get(route('invoices.show', $invoice))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="https://wa\.me/(\d+)\?text=([^"]+)"#', $html, 'the invoice page offers a WhatsApp link');
        preg_match('#href="https://wa\.me/(\d+)\?text=([^"]+)"#', $html, $m);

        return [$m[1], rawurldecode(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5))];
    }
}
