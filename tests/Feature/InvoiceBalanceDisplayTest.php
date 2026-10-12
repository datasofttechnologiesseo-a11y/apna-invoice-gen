<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a customer reads on paper has to add up: the invoice lists every
 * amount that moved its balance, and a receipt shows the balance as it was
 * when that payment came in, not as it is today.
 */
class InvoiceBalanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $company = Company::factory()->recycle($this->user)->create(['state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now()]);
        $this->user->forceFill(['active_company_id' => $company->id])->save();
        $customer = Customer::factory()->recycle($this->user)->recycle($company)->create(['state_id' => $mh->id]);

        // 50,000 + 18% = 59,000
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => '2026-10-01',
            'items' => [['description' => 'Steel almirah', 'hsn_sac' => '94032010', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 50000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $this->invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $this->invoice))->assertRedirect();
    }

    private function pay(float $amount, string $on): void
    {
        $this->actingAs($this->user)->post(route('invoices.payments', $this->invoice), [
            'amount' => $amount, 'method' => 'upi', 'received_at' => $on,
        ])->assertSessionHasNoErrors();
    }

    private function receiptText(Payment $payment): string
    {
        $html = view('payments.receipt', ['payment' => $payment->fresh(['invoice.company.state', 'invoice.customer.state'])])->render();

        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)));
    }

    public function test_each_receipt_shows_the_balance_as_it_was_after_that_payment(): void
    {
        $this->pay(10000, '2026-10-02');
        $this->pay(20000, '2026-10-05');
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice->fresh()), [
            'credit_note_date' => '2026-10-06', 'amount' => 5000, 'reason' => array_key_first(config('credit_note_reasons')),
        ])->assertSessionHasNoErrors();

        [$first, $second] = Payment::orderBy('id')->get()->all();

        $text = $this->receiptText($first);
        $this->assertMatchesRegularExpression('/Previously paid\s*₹\s*0 /u', $text);
        $this->assertMatchesRegularExpression('/Balance after this payment\s*₹\s*49,000/u', $text);
        $this->assertStringNotContainsString('Less credit notes', $text, 'the credit note came later');
        $this->assertStringNotContainsString('PAID IN FULL', $text);

        $text = $this->receiptText($second);
        $this->assertMatchesRegularExpression('/Previously paid\s*₹\s*10,000/u', $text);
        $this->assertMatchesRegularExpression('/Balance after this payment\s*₹\s*29,000/u', $text);
    }

    public function test_the_invoice_lists_credit_notes_so_total_less_credits_and_payments_equals_the_balance(): void
    {
        $this->pay(30000, '2026-10-02');
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice->fresh()), [
            'credit_note_date' => '2026-10-06', 'amount' => 5000, 'reason' => array_key_first(config('credit_note_reasons')),
        ])->assertSessionHasNoErrors();

        // 59,000 - 5,000 - 30,000 = 24,000
        $this->assertEqualsWithDelta(24000, (float) $this->invoice->fresh()->balance, 0.001);
        $page = preg_replace('/\s+/', ' ', strip_tags($this->actingAs($this->user)->get(route('invoices.show', $this->invoice))->assertOk()->getContent()));
        $this->assertMatchesRegularExpression('/Less credit notes\s*₹5,000\s*Paid\s*₹30,000\s*Balance\s*₹24,000/u', $page);

        $invoice = $this->invoice->fresh(['items', 'customer.state', 'company.state']);
        $pdf = view('invoices.pdf', [
            'invoice' => $invoice, 'amountInWords' => 'Fifty-nine thousand rupees', 'style' => 'classic',
            'print' => true, 'copyLabels' => $invoice->copyLabels(1),
        ])->render();
        $pdf = preg_replace('/\s+/', ' ', strip_tags($pdf));
        $this->assertMatchesRegularExpression('/Less credit notes ₹\s?5,000/u', $pdf);
        $this->assertMatchesRegularExpression('/Balance due ₹\s?24,000/u', $pdf);
    }
}
