<?php

namespace Tests\Feature\Fintech\Money;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\State;
use App\Models\User;
use App\Support\NumberToWords;
use Database\Seeders\StateSeeder;
use Illuminate\Support\Carbon;

/**
 * Shared set-up for the money and tax maths probes.
 *
 * The real state master (StateSeeder) is loaded so UT/legislature flags and
 * GST codes are the ones production uses. Every document is created through
 * the same HTTP routes the web app posts to. Expected values in the tests are
 * worked out by hand and written next to each assertion; nothing here calls
 * the app's own calculator to produce an expectation.
 */
trait MoneyFixtures
{
    protected User $user;

    protected Company $company;

    /** Fixed "today" so dates, the FY and the s.34 window never drift. */
    protected function bootMoney(string $homeGstCode = '27', ?string $gstin = '27ABCDE1234F1Z5'): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 11:00:00'));
        $this->seed(StateSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'state_id' => $this->state($homeGstCode)->id,
            'gstin' => $gstin,
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    protected function state(string $gstCode): State
    {
        return State::where('gst_code', $gstCode)->firstOrFail();
    }

    protected function customerIn(string $gstCode, array $extra = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'state_id' => $this->state($gstCode)->id,
            'gstin' => null,
            'country' => $gstCode === '96' ? 'United States' : 'India',
        ], $extra));
    }

    /** One invoice line as the web form posts it. */
    protected function line(float $rate, float $gst, float $qty = 1, array $extra = []): array
    {
        return array_merge([
            'description' => 'Item ' . $rate,
            'hsn_sac' => '998314',
            'quantity' => $qty,
            'unit' => 'NOS',
            'rate' => $rate,
            'gst_rate' => $gst,
        ], $extra);
    }

    protected function draft(array $items, Customer $customer, array $extra = []): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => $items,
        ], $extra))->assertSessionHasNoErrors();

        return Invoice::latest('id')->firstOrFail();
    }

    protected function issue(array $items, Customer $customer, array $extra = []): Invoice
    {
        $inv = $this->draft($items, $customer, $extra);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasNoErrors();
        $inv = $inv->fresh(['items']);
        $this->assertFalse($inv->isDraft(), 'the invoice should have been issued');

        return $inv;
    }

    protected function pay(Invoice $inv, float $amount, array $extra = [])
    {
        return $this->actingAs($this->user)->post(route('invoices.payments', $inv), array_merge([
            'amount' => $amount,
            'method' => 'upi',
            'received_at' => now()->toDateString(),
        ], $extra));
    }

    protected function credit(Invoice $inv, ?float $amount, array $extra = [])
    {
        return $this->actingAs($this->user)->post(route('credit-notes.store', $inv), array_merge([
            'credit_note_date' => now()->toDateString(),
            'amount' => $amount,
            'reason' => 'rate_correction',
        ], $extra));
    }

    protected function latestNote(): CreditNote
    {
        return CreditNote::latest('id')->firstOrFail();
    }

    /** Exact to the paisa (a sub-paisa delta only absorbs float noise). */
    protected function assertMoney(float $expected, mixed $actual, string $message = ''): void
    {
        $this->assertEqualsWithDelta($expected, (float) $actual, 0.001, $message);
    }

    /**
     * The invariants every stored invoice must satisfy, whatever its lines:
     * heads add to the tax, lines add to the header, grand total is taxable
     * plus tax plus round off, and round off is at most 50 paise.
     */
    protected function assertInvoiceAddsUp(Invoice $inv): void
    {
        $inv = $inv->fresh(['items']);
        $items = $inv->items;

        $this->assertMoney(round($items->sum(fn ($i) => (float) $i->amount), 2), $inv->subtotal, 'lines add to the taxable value');
        $this->assertMoney(round($items->sum(fn ($i) => (float) $i->cgst_amount), 2), $inv->total_cgst, 'line CGST adds to the header');
        $this->assertMoney(round($items->sum(fn ($i) => (float) $i->sgst_amount), 2), $inv->total_sgst, 'line SGST adds to the header');
        $this->assertMoney(round($items->sum(fn ($i) => (float) $i->igst_amount), 2), $inv->total_igst, 'line IGST adds to the header');
        $this->assertMoney(round((float) $inv->total_cgst + (float) $inv->total_sgst + (float) $inv->total_igst, 2), $inv->total_tax, 'heads add to the tax');
        $this->assertMoney(round((float) $inv->subtotal + (float) $inv->total_tax + (float) $inv->round_off, 2), $inv->grand_total, 'grand = taxable + tax + round off');
        $this->assertLessThanOrEqual(0.5, abs((float) $inv->round_off), 'round off is at most 50 paise');
        $this->assertMoney(round((float) $inv->grand_total), $inv->grand_total, 'grand total is in whole rupees');
        foreach ($items as $i) {
            $this->assertMoney(round((float) $i->amount + (float) $i->cgst_amount + (float) $i->sgst_amount + (float) $i->igst_amount, 2), $i->total, 'a line total is its value plus its tax');
            if (! $inv->is_interstate) {
                $this->assertMoney((float) $i->cgst_amount, $i->sgst_amount, 'CGST equals SGST on an intra-state line');
            }
        }
    }

    /** Plain text of the invoice PDF as the PDF controller renders it. */
    protected function invoicePdfText(Invoice $inv): string
    {
        $invoice = $inv->fresh(['items.product', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'amountInWords' => NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency),
            'style' => 'classic',
            'print' => true,
            'copyLabels' => $invoice->copyLabels(1),
        ])->render();

        return $this->flatten($html);
    }

    protected function receiptText(Payment $payment): string
    {
        $html = view('payments.receipt', ['payment' => $payment->fresh(['invoice.company.state', 'invoice.customer.state'])])->render();

        return $this->flatten($html);
    }

    protected function flatten(string $html): string
    {
        // Drop <style> blocks so CSS never matches a money pattern.
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);

        // Every tag becomes a space so adjacent table cells never run together.
        $text = preg_replace('/<[^>]*>/', ' ', $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
