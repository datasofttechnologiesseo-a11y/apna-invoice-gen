<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workflow audit, part two: the journeys that touch more than one document.
 *
 * Credit notes, quotation conversion, the financial-year series reset,
 * expenses reaching the return, and isolation between two businesses run from
 * one login. Same standard as part one - assert the money and the state, not
 * that a page rendered.
 */
class WorkflowAuditPartTwoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $mh;
    private State $ka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);

        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'name' => 'Sharma Exports',
            'state_id' => $this->mh->id,
            'gstin' => '27ABCDE1234F1Z5',
            'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function customerIn(State $state, ?Company $company = null): Customer
    {
        return Customer::factory()->recycle($this->user)->recycle($company ?? $this->company)
            ->create(['state_id' => $state->id]);
    }

    private function issuedInvoice(float $rate = 10000, ?Customer $customer = null): Invoice
    {
        $customer ??= $this->customerIn($this->mh);

        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'description' => 'Service', 'quantity' => 1, 'rate' => $rate,
                'gst_rate' => 18, 'hsn_sac' => '998314',
            ]],
        ])->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice))->assertRedirect();

        return $invoice->fresh();
    }

    // ================================================ Credit notes (s.34)

    public function test_a_credit_note_reduces_the_balance_owed(): void
    {
        $invoice = $this->issuedInvoice(10000);          // 11,800 gross
        $this->assertEqualsWithDelta(11800.0, (float) $invoice->grand_total, 0.01);

        $this->actingAs($this->user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 1800,
            'reason' => 'rate_correction',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertEqualsWithDelta(1800.0, (float) $invoice->credited_amount, 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $invoice->balance, 0.01,
            'the balance owed must drop by the credited amount');
    }

    public function test_a_credit_note_cannot_exceed_what_is_still_creditable(): void
    {
        // Crediting more than the invoice is worth would create a negative
        // liability and a refund the business never agreed to.
        $invoice = $this->issuedInvoice(1000);           // 1,180 gross

        $this->actingAs($this->user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 5000,
            'reason' => 'rate_correction',
        ])->assertSessionHasErrors('amount');

        $this->assertEqualsWithDelta(0.0, (float) $invoice->fresh()->credited_amount, 0.01);
    }

    public function test_credit_notes_cannot_stack_past_the_invoice_value(): void
    {
        $invoice = $this->issuedInvoice(1000);           // 1,180 gross

        $this->actingAs($this->user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 1000, 'reason' => 'rate_correction',
        ])->assertRedirect();

        // only 180 left to credit
        $this->actingAs($this->user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 500, 'reason' => 'rate_correction',
        ])->assertSessionHasErrors('amount');

        $invoice->refresh();
        $this->assertLessThanOrEqual((float) $invoice->grand_total, (float) $invoice->credited_amount);
    }

    public function test_a_credit_note_cannot_predate_its_invoice(): void
    {
        $invoice = $this->issuedInvoice(1000);

        $this->actingAs($this->user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->subYear()->toDateString(),
            'amount' => 100, 'reason' => 'rate_correction',
        ])->assertSessionHasErrors('credit_note_date');
    }

    // ================================================ Quotation -> invoice

    public function test_converting_an_accepted_quotation_preserves_the_money(): void
    {
        $customer = $this->customerIn($this->mh);

        $this->actingAs($this->user)->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'INR',
            'style' => 'classic',
            'items' => [[
                'description' => 'Design retainer', 'hsn_sac' => '998314',
                'quantity' => 2, 'unit' => 'NOS', 'rate' => 7500,
                'discount' => 0, 'gst_rate' => 18,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::latest('id')->firstOrFail();
        $quotedTotal = (float) $quotation->grand_total;
        $this->assertEqualsWithDelta(17700.0, $quotedTotal, 0.01);

        $this->actingAs($this->user)->post(route('quotations.send', $quotation))->assertRedirect();
        $this->actingAs($this->user)->post(route('quotations.accept', $quotation))->assertRedirect();
        $this->assertSame('accepted', $quotation->fresh()->status);

        $this->actingAs($this->user)->post(route('quotations.convert', $quotation))->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta($quotedTotal, (float) $invoice->grand_total, 0.01,
            'the customer accepted a price - the invoice must charge exactly that');
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame($quotation->items()->count(), $invoice->items()->count());
    }

    public function test_a_quotation_cannot_be_converted_twice(): void
    {
        $customer = $this->customerIn($this->mh);
        $this->actingAs($this->user)->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'INR',
            'style' => 'classic',
            'items' => [['description' => 'X', 'hsn_sac' => '998314',
                'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000,
                'discount' => 0, 'gst_rate' => 18]],
        ]);
        $quotation = Quotation::latest('id')->firstOrFail();

        $this->actingAs($this->user)->post(route('quotations.send', $quotation));
        $this->actingAs($this->user)->post(route('quotations.accept', $quotation));
        $this->actingAs($this->user)->post(route('quotations.convert', $quotation));

        $countAfterFirst = Invoice::count();
        $this->assertSame(1, $countAfterFirst,
            'the first conversion must actually produce an invoice, or this test proves nothing');

        $this->actingAs($this->user)->post(route('quotations.convert', $quotation));

        $this->assertSame($countAfterFirst, Invoice::count(),
            'converting twice would bill the customer twice for one accepted quote');
    }

    // ================================================ Financial-year series

    public function test_the_series_resets_for_a_new_financial_year(): void
    {
        // An Indian FY runs 1 April to 31 March, and Rule 46(b) expects a
        // series unique to the year. A counter that carries over produces
        // duplicate numbers across years.
        $this->company->forceFill(['invoice_number_format' => 'INV/{FY}/'])->save();

        $customer = $this->customerIn($this->mh);

        $makeOn = function (string $date) use ($customer) {
            $this->actingAs($this->user)->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => $date,
                'items' => [['description' => 'X', 'quantity' => 1, 'rate' => 1000,
                    'gst_rate' => 18, 'hsn_sac' => '998314']],
            ])->assertRedirect();
            $inv = Invoice::latest('id')->firstOrFail();
            $this->actingAs($this->user)->post(route('invoices.finalize', $inv));

            return $inv->fresh()->invoice_number;
        };

        $marchNumber = $makeOn('2026-03-20');   // FY 2025-26
        $aprilNumber = $makeOn('2026-04-05');   // FY 2026-27

        $this->assertNotSame($marchNumber, $aprilNumber);
        $this->assertNotSame(
            preg_replace('/[^0-9]/', '', substr($marchNumber, 0, -4)),
            preg_replace('/[^0-9]/', '', substr($aprilNumber, 0, -4)),
            'invoices either side of 1 April must carry different FY segments'
        );
    }

    // ================================================ Expenses -> return

    public function test_an_expense_with_input_credit_reaches_gstr_3b(): void
    {
        // GSTR-3B is filed for the PREVIOUS month - August's return goes in
        // September - so the screen defaults to last month. An expense dated
        // today belongs to a return that is not due yet.
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(9);

        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => $lastMonth->toDateString(),
            'category' => 'rent',
            'description' => 'Office rent for the month',
            'amount' => 20000,
            'gst_amount' => 3600,
            'itc_eligible' => '1',
        ])->assertRedirect();

        $res = $this->actingAs($this->user)->get(route('finance.gstr3b'));
        $res->assertOk();

        // 3,600 of input tax on an intra-state purchase splits 50/50 into
        // CGST and SGST per s.9(1), so the screen shows 1,800 twice.
        $this->assertStringContainsString('1,800', $res->getContent(),
            'an ITC-eligible expense should show as claimable credit in GSTR-3B');
    }

    public function test_ineligible_input_credit_is_left_out_of_the_return(): void
    {
        // s.17(5) blocks credit on things like staff catering and motor
        // vehicles. Claiming it would overstate the refund and invite a notice.
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(9);

        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => $lastMonth->toDateString(),
            'category' => 'food',
            'description' => 'Team lunch',
            'amount' => 20000,
            'gst_amount' => 3600,
            'itc_eligible' => '0',
        ])->assertRedirect();

        $body = $this->actingAs($this->user)->get(route('finance.gstr3b'))->getContent();
        $this->assertStringNotContainsString('1,800', $body,
            'blocked credit under s.17(5) must not be claimed');
    }

    // ================================================ Two businesses, one login

    public function test_one_businesss_invoices_never_appear_in_anothers_books(): void
    {
        // A user running two firms from one login must never see the books
        // mixed - that is a bigger problem than any UI bug.
        $second = Company::factory()->recycle($this->user)->create([
            'name' => 'Second Venture', 'state_id' => $this->mh->id, 'onboarded_at' => now(),
        ]);

        $firstInvoice = $this->issuedInvoice(13579);

        $this->user->forceFill(['active_company_id' => $second->id])->save();

        $body = $this->actingAs($this->user)->get(route('invoices.index'))->getContent();
        $this->assertStringNotContainsString('13,579', $body,
            "the other firm's invoice must not be listed here");
        // NOT asserting on the number itself - each firm starts its own series
        // at 1, so INV-0001 belongs to both and proves nothing either way.
        $this->assertSame(0, $second->invoices()->count(),
            'the newly created firm should have no invoices of its own yet');

        $gstr3b = $this->actingAs($this->user)->get(route('finance.gstr3b'))->getContent();
        $this->assertStringNotContainsString('13,579', str_replace(' ', '', $gstr3b),
            "the other firm's turnover must not reach this firm's return");
    }

    public function test_a_second_company_starts_its_own_number_series(): void
    {
        $first = $this->issuedInvoice(1000);

        $second = Company::factory()->recycle($this->user)->create([
            'name' => 'Second Venture', 'state_id' => $this->mh->id, 'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $second->id])->save();

        $secondInvoice = $this->issuedInvoice(1000, $this->customerIn($this->mh, $second));

        $this->assertNotSame($first->company_id, $secondInvoice->company_id);
        $this->assertNotNull($secondInvoice->invoice_number);
        // Each company keeps its own counter; the second firm starts at one.
        $trailing = (int) preg_replace('/[^0-9]/', '', substr($secondInvoice->invoice_number, -4));
        $this->assertSame(1, $trailing, 'a new firm should begin its own series at 1');
    }

    // ================================================ Ageing

    public function test_an_overdue_invoice_is_reported_as_money_owed(): void
    {
        $customer = $this->customerIn($this->mh);

        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->subDays(75)->toDateString(),
            'due_date' => now()->subDays(45)->toDateString(),
            'items' => [['description' => 'Overdue work', 'quantity' => 1, 'rate' => 24680,
                'gst_rate' => 18, 'hsn_sac' => '998314']],
        ])->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice));

        $res = $this->actingAs($this->user)->get(route('finance.aging'));
        $res->assertOk();
        // The report shows what is still OWED, so the gross balance is the
        // figure to look for, not the taxable value.
        $this->assertEqualsWithDelta(29122.0, (float) $invoice->fresh()->balance, 1.0);
        $this->assertStringContainsString('29,122', $res->getContent(),
            'an invoice 45 days past its due date must appear in the ageing report');
    }
}
