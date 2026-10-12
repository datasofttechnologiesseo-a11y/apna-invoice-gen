<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared set-up for the document-lifecycle probes: one GST-registered
 * business in Maharashtra, one unregistered customer in the same state, and
 * helpers that drive the real web routes the way the owner's browser does.
 *
 * The standard line is 1 x Rs 1,000 at 18%, intra-state:
 *   taxable 1,000.00; CGST 9% = 90.00; SGST 9% = 90.00; grand total 1,180.00.
 */
trait IntegrityFixtures
{
    protected User $user;
    protected Company $company;
    protected State $mh;
    protected State $ka;
    protected Customer $customer;

    /** Today for every test unless it travels: Monday 12 Oct 2026, noon IST (FY 2026-27). */
    protected function freezeToday(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 12, 12, 0, 0, 'Asia/Kolkata'));
    }

    protected function setUpBusiness(array $company = []): void
    {
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Sharma Traders',
            'state_id' => $this->mh->id,
            'gstin' => '27ABCDE1234F1Z5',
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'invoice_number_padding' => 4,
            'receipt_prefix' => 'RCPT',
            'receipt_counter' => 0,
            'receipt_number_padding' => 4,
        ], $company));
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = $this->customerFor($this->company, ['name' => 'Patil Stores']);
    }

    protected function customerFor(Company $company, array $attrs = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'user_id' => $company->user_id,
            'company_id' => $company->id,
            'state_id' => $company->state_id,
            'gstin' => null,
            'email' => 'accounts@patilstores.example',
            'phone' => '9876500001',
        ], $attrs));
    }

    /** The one-line Rs 1,000 @ 18% invoice body. */
    protected function line(float $rate = 1000, float $gst = 18, float $qty = 1, array $extra = []): array
    {
        return array_merge(['description' => 'Steel almirah', 'hsn_sac' => '94031010', 'quantity' => $qty, 'unit' => 'NOS', 'rate' => $rate, 'gst_rate' => $gst], $extra);
    }

    /** Save a draft through the web form, for the active company. */
    protected function draft(array $over = [], ?array $items = null): Invoice
    {
        $before = Invoice::max('id') ?? 0;
        $date = $over['invoice_date'] ?? now()->toDateString();
        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => $this->customer->id,
            'invoice_date' => $date,
            'due_date' => $date,
            'items' => $items ?? [$this->line()],
        ], $over))->assertSessionHasNoErrors();

        $invoice = Invoice::where('id', '>', $before)->latest('id')->firstOrFail();
        $this->assertSame('draft', $invoice->status);

        return $invoice;
    }

    protected function issue(Invoice $invoice): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice))->assertSessionHasNoErrors();
        $fresh = $invoice->fresh();
        $this->assertNotSame('draft', $fresh->status, 'the draft should have been issued: ' . (string) session('error'));

        return $fresh;
    }

    protected function issued(array $over = [], ?array $items = null): Invoice
    {
        return $this->issue($this->draft($over, $items));
    }

    protected function pay(Invoice $invoice, float $amount, array $extra = []): Payment
    {
        $before = Payment::max('id') ?? 0;
        $this->actingAs($this->user)->post(route('invoices.payments', $invoice), array_merge([
            'amount' => $amount, 'method' => 'upi', 'received_at' => now()->toDateString(),
        ], $extra))->assertSessionHasNoErrors();

        return Payment::where('id', '>', $before)->latest('id')->firstOrFail();
    }

    protected function credit(Invoice $invoice, float $amount, array $extra = []): CreditNote
    {
        $before = CreditNote::max('id') ?? 0;
        $this->actingAs($this->user)->post(route('credit-notes.store', $invoice), array_merge([
            'credit_note_date' => now()->toDateString(), 'amount' => $amount, 'reason' => 'rate_correction',
        ], $extra))->assertSessionHasNoErrors();

        return CreditNote::where('id', '>', $before)->latest('id')->firstOrFail();
    }

    protected function cancel(Invoice $invoice): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post(route('invoices.cancel', $invoice), ['cancellation_reason' => 'Billed to the wrong customer']);
    }

    /** A request as a second browser tab or a client retry would send it, for calling a controller with a stale model. */
    protected function requestAs(array $data, string $method = 'POST'): Request
    {
        $r = Request::create('/x', $method, $data);
        $r->setUserResolver(fn () => $this->user);
        $r->setLaravelSession(app('session.store'));

        return $r;
    }

    protected function assertMoney(float $expected, $actual, string $message = ''): void
    {
        $this->assertEqualsWithDelta($expected, (float) $actual, 0.001, $message);
    }
}
