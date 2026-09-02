<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Financial correctness, part two: the dashboard figures a business reads
 * first, the GST return totals it files, and the delivery paths - email and
 * WhatsApp - that carry the document to the customer.
 *
 * The dashboard matters more than its prominence suggests. It is the number
 * an owner glances at and trusts without checking, so a wrong one there is
 * believed longer than a wrong one anywhere else.
 */
class FinancialCorrectnessPartTwoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;
    private State $mh;
    private State $ka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);

        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $this->mh->id, 'email' => 'buyer@example.test']);
    }

    private function issued(float $rate, ?Customer $customer = null, ?string $date = null): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => ($customer ?? $this->customer)->id,
            'invoice_date' => $date ?? now()->toDateString(),
            'items' => [[
                'description' => 'Service', 'quantity' => 1, 'rate' => $rate,
                'gst_rate' => 18, 'hsn_sac' => '998314',
            ]],
        ])->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice))->assertRedirect();

        return $invoice->fresh();
    }

    // ========================================================== Dashboard

    public function test_the_dashboard_outstanding_equals_the_sum_of_unpaid_balances(): void
    {
        $a = $this->issued(10000);      // 11,800
        $b = $this->issued(20000);      // 23,600

        $this->actingAs($this->user)->post(route('invoices.payments', $b), [
            'amount' => 10000, 'received_at' => now()->toDateString(), 'method' => 'upi',
        ])->assertRedirect();

        $stats = $this->actingAs($this->user)->get('/dashboard')->assertOk()->viewData('stats');

        $expected = (float) Invoice::whereIn('status', ['final', 'partially_paid'])->sum('balance');

        $this->assertEqualsWithDelta($expected, (float) $stats['outstanding'], 0.01,
            'the headline outstanding figure must equal the unpaid balances behind it');
        $this->assertEqualsWithDelta(25400.0, (float) $stats['outstanding'], 0.01);
    }

    public function test_a_draft_is_not_counted_as_money_owed_to_the_business(): void
    {
        $this->issued(10000);

        // A draft carries a balance but is not a demand for payment.
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Draft', 'quantity' => 1, 'rate' => 50000,
                'gst_rate' => 18, 'hsn_sac' => '998314']],
        ])->assertRedirect();

        $stats = $this->actingAs($this->user)->get('/dashboard')->viewData('stats');

        $this->assertEqualsWithDelta(11800.0, (float) $stats['outstanding'], 0.01,
            'an unissued draft must not appear as a receivable');
    }

    public function test_a_cancelled_invoice_leaves_the_dashboard_outstanding(): void
    {
        $live = $this->issued(10000);
        $void = $this->issued(60000);

        $this->actingAs($this->user)->post(route('invoices.cancel', $void), [
            'cancellation_reason' => 'Issued in error before despatch.',
        ])->assertRedirect();

        $stats = $this->actingAs($this->user)->get('/dashboard')->viewData('stats');

        $this->assertEqualsWithDelta((float) $live->fresh()->balance,
            (float) $stats['outstanding'], 0.01);
    }

    // ========================================================== GST returns

    public function test_gstr3b_outward_tax_equals_the_tax_on_issued_invoices(): void
    {
        // Filed monthly for the PREVIOUS month, so the invoices have to sit in
        // that window to be in the return being prepared.
        $when = now()->subMonthNoOverflow()->startOfMonth()->addDays(10)->toDateString();

        $this->issued(100000, null, $when);                       // 18,000 tax, intra-state
        $this->issued(50000, Customer::factory()->recycle($this->user)
            ->recycle($this->company)->create(['state_id' => $this->ka->id]), $when);  // 9,000 IGST

        $res = $this->actingAs($this->user)->get(route('finance.gstr3b'))->assertOk();
        $outward = $res->viewData('outward');

        $this->assertEqualsWithDelta(150000.0, $outward['taxable'], 0.01);
        $this->assertEqualsWithDelta(9000.0, $outward['cgst'], 0.01, 'intra-state half of 18,000');
        $this->assertEqualsWithDelta(9000.0, $outward['sgst'], 0.01);
        $this->assertEqualsWithDelta(9000.0, $outward['igst'], 0.01, 'the inter-state sale');
    }

    public function test_a_credit_note_reduces_the_outward_supply_in_gstr3b(): void
    {
        // Section 34: a credit note reduces the liability already declared.
        $when = now()->subMonthNoOverflow()->startOfMonth()->addDays(10);
        $invoice = $this->issued(100000, null, $when->toDateString());

        $this->actingAs($this->user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => $when->copy()->addDays(2)->toDateString(),
            'amount' => 11800, 'reason' => 'rate_correction',
        ])->assertRedirect();

        $outward = $this->actingAs($this->user)
            ->get(route('finance.gstr3b'))->viewData('outward');

        $this->assertLessThan(100000.0, $outward['taxable'],
            'a credit note must reduce declared outward supply, not leave it unchanged');
    }

    public function test_a_cancelled_invoice_is_excluded_from_the_return_totals(): void
    {
        $when = now()->subMonthNoOverflow()->startOfMonth()->addDays(10)->toDateString();
        $keep = $this->issued(10000, null, $when);
        $void = $this->issued(90000, null, $when);

        $this->actingAs($this->user)->post(route('invoices.cancel', $void), [
            'cancellation_reason' => 'Issued in error before despatch.',
        ])->assertRedirect();

        $outward = $this->actingAs($this->user)
            ->get(route('finance.gstr3b'))->viewData('outward');

        $this->assertEqualsWithDelta(10000.0, $outward['taxable'], 0.01,
            'a cancelled invoice must never be declared as an outward supply');
    }

    public function test_the_gstr1_export_lists_issued_invoices_with_their_tax(): void
    {
        $invoice = $this->issued(10000);

        $csv = $this->actingAs($this->user)->get(route('invoices.gstr1'))->assertOk()->streamedContent();
        $flat = str_replace(',', '', $csv);

        $this->assertStringContainsString((string) $invoice->invoice_number, $csv);
        $this->assertStringContainsString('10000', $flat, 'the taxable value belongs in GSTR-1');
    }

    // ========================================================== Delivery

    public function test_emailing_an_invoice_sends_it_to_the_customer(): void
    {
        Mail::fake();
        $invoice = $this->issued(10000);

        $this->actingAs($this->user)->post(route('invoices.share.email', $invoice), [
            'to' => 'buyer@example.test',
            'subject' => 'Your invoice from Sharma Exports',
            'body' => 'Please find your GST invoice attached.',
        ])->assertRedirect();

        Mail::assertSent(\App\Mail\InvoiceMail::class, fn ($mail) => $mail->hasTo('buyer@example.test'));
    }

    public function test_a_draft_invoice_cannot_be_emailed_to_a_customer(): void
    {
        // Sending an unissued document with no number is a document the buyer
        // cannot book and the seller cannot account for.
        Mail::fake();

        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Draft', 'quantity' => 1, 'rate' => 1000,
                'gst_rate' => 18, 'hsn_sac' => '998314']],
        ])->assertRedirect();
        $draft = Invoice::latest('id')->firstOrFail();

        $this->actingAs($this->user)->post(route('invoices.share.email', $draft), [
            'to' => 'buyer@example.test',
            'subject' => 'Draft',
            'body' => 'Should never be sent.',
        ]);

        Mail::assertNothingSent();
    }

    public function test_the_share_link_is_signed_and_opens_without_logging_in(): void
    {
        // WhatsApp sharing sends this URL, so it has to work for someone who
        // has no account - and it must not be guessable from the invoice id.
        $invoice = $this->issued(10000);

        $res = $this->actingAs($this->user)->get(route('invoices.share.link', $invoice))->assertOk();
        $link = $res->json('url') ?? $res->json('link') ?? '';

        $this->assertNotEmpty($link, 'a share link should be returned');
        $this->assertStringContainsString('signature=', $link,
            'the public link must be signed, not a bare id');

        $this->post('/logout');
        $path = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);
        $this->get($path)->assertOk();
    }

    public function test_a_tampered_share_link_is_rejected(): void
    {
        $invoice = $this->issued(10000);
        $link = $this->actingAs($this->user)
            ->get(route('invoices.share.link', $invoice))->json('url') ?? '';

        $this->post('/logout');

        $tampered = preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('0', 64), $link);
        $path = parse_url($tampered, PHP_URL_PATH).'?'.parse_url($tampered, PHP_URL_QUERY);

        $this->get($path)->assertForbidden();
    }
}
