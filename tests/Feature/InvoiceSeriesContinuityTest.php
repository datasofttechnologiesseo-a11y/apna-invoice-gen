<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A business without a GSTIN may type its own invoice number, so a shop moving
 * over from a paper bill book can carry on from where it left off.
 *
 * The number they type has to carry the series forward. Before this, typing
 * 5464 left the counter at 0 and the very next bill came out as INV-0001 —
 * the sequence jumped backwards, which breaks the books.
 */
class InvoiceSeriesContinuityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    private function setUpCompany(array $overrides = []): void
    {
        $state = State::factory()->create(['gst_code' => '27']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create(array_merge([
            'state_id' => $state->id,
            'gstin' => null,                  // no GSTIN: custom numbers allowed
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'invoice_number_format' => null,
        ], $overrides));
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $state->id]);
    }

    private function issue(?string $number = null): Invoice
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'description' => 'Item', 'hsn_sac' => '998311',
                'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18,
            ]],
        ];
        if ($number !== null) {
            $payload['invoice_number'] = $number;
        }

        $this->actingAs($this->user)->post(route('invoices.store'), $payload)->assertRedirect();
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice));

        return $invoice->fresh();
    }

    public function test_a_bare_number_continues_from_where_the_bill_book_left_off(): void
    {
        $this->setUpCompany();

        $this->assertSame('5464', $this->issue('5464')->invoice_number);

        // The whole point: the next bill is 5465, not INV-0001.
        $this->assertSame('5465', $this->issue()->invoice_number);
        $this->assertSame('5466', $this->issue()->invoice_number);
    }

    public function test_a_prefixed_number_keeps_its_prefix_and_padding(): void
    {
        $this->setUpCompany();

        $this->assertSame('SHOP-0120', $this->issue('SHOP-0120')->invoice_number);
        $this->assertSame('SHOP-0121', $this->issue()->invoice_number);
    }

    public function test_the_series_never_moves_backwards(): void
    {
        $this->setUpCompany();

        $this->issue('5464');
        $this->assertSame(5464, (int) $this->company->fresh()->invoice_counter);

        // Re-using an older number must not rewind the counter.
        $this->issue('120');
        $this->assertSame(5464, (int) $this->company->fresh()->invoice_counter);
        $this->assertSame('5465', $this->issue()->invoice_number);
    }

    public function test_a_deliberate_format_is_never_overwritten(): void
    {
        $this->setUpCompany(['invoice_number_format' => 'BILL/{FY}/{N}', 'invoice_number_padding' => 4]);

        $this->issue('9001');

        $company = $this->company->fresh();
        $this->assertSame('BILL/{FY}/{N}', $company->invoice_number_format,
            'a format the user chose must survive a hand-typed number');
        $this->assertSame(9001, (int) $company->invoice_counter);
    }

    public function test_a_number_with_no_digits_is_left_alone(): void
    {
        $this->setUpCompany();

        $this->issue('OPENING');

        $company = $this->company->fresh();
        $this->assertSame(0, (int) $company->invoice_counter);
        $this->assertNull($company->invoice_number_format);
    }

    public function test_a_gst_registered_business_continues_the_series_it_arrived_with(): void
    {
        // Deliberate policy change. This previously asserted that a typed
        // number was ignored for a GST-registered supplier, on the reading that
        // Rule 46(b) demands a consecutive series.
        //
        // It does, and the supplier still owns that duty. But the commonest
        // real case is a firm moving over mid-year at bill 5464: ignoring the
        // number restarts them at 0001, so their books show two invoices for
        // the same period numbered from different series - which is the very
        // thing Rule 46 is trying to prevent. Continuing from 5464 is both
        // what they need and the more compliant outcome.
        $this->setUpCompany([
            'gstin' => '27ABCDE1234F1Z5',
            'invoice_number_format' => 'INV/{FY}/{N}',
            'invoice_number_padding' => 4,
        ]);

        $first = $this->issue('5464');
        $this->assertSame('5464', $first->invoice_number, 'the number they typed is kept');

        // And the series carries on from there rather than restarting.
        $next = $this->issue(null);
        $this->assertStringEndsWith('5465', $next->invoice_number,
            'the next auto-numbered bill must continue the series it was given');
    }
}
