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
 * Period filters on the GST reports must include both edges of the range.
 *
 * The reports used to bound the range with bare date strings
 * (`[$from->toDateString(), $to->toDateString()]`). Wherever the stored value
 * carries a time component that makes the upper bound exclusive — so an
 * invoice raised on the last day of the month vanished from GSTR-1, which is
 * exactly the day most businesses bill on. Bounds are now startOfDay/endOfDay,
 * which is correct regardless of how the driver stores the column.
 */
class GstrPeriodBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'state_id' => $state->id,
            'gstin' => '27ABCDE1234F1Z5',
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();

        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'state_id' => $state->id,
            'gstin' => '27FGHIJ5678K1Z3',
        ]);
    }

    private function issueInvoiceOn(string $date, float $rate): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => $date,
            'due_date' => $date,
            'items' => [[
                'description' => 'Service', 'hsn_sac' => '998311',
                'quantity' => 1, 'rate' => $rate, 'gst_rate' => 18,
            ]],
        ])->assertRedirect();

        $this->actingAs($this->user)->post(route('invoices.finalize', Invoice::latest('id')->first()));
    }

    private function gstr1For(string $from, string $to): string
    {
        return $this->actingAs($this->user)
            ->get(route('invoices.gstr1', ['from' => $from, 'to' => $to]))
            ->streamedContent();
    }

    public function test_invoice_on_the_last_day_of_the_period_is_included(): void
    {
        $this->issueInvoiceOn('2026-08-31', 10000);

        $csv = $this->gstr1For('2026-08-01', '2026-08-31');

        $this->assertStringContainsString('10000.00', $csv,
            'an invoice raised on the final day of the period must appear in GSTR-1');
    }

    public function test_invoice_on_the_first_day_of_the_period_is_included(): void
    {
        $this->issueInvoiceOn('2026-08-01', 12000);

        $this->assertStringContainsString('12000.00', $this->gstr1For('2026-08-01', '2026-08-31'));
    }

    public function test_invoices_outside_the_period_are_excluded(): void
    {
        $this->issueInvoiceOn('2026-07-31', 44000);   // day before the window
        $this->issueInvoiceOn('2026-09-01', 55000);   // day after the window

        $csv = $this->gstr1For('2026-08-01', '2026-08-31');

        $this->assertStringNotContainsString('44000.00', $csv);
        $this->assertStringNotContainsString('55000.00', $csv);
    }

    public function test_both_edges_are_counted_in_the_same_period(): void
    {
        $this->issueInvoiceOn('2026-08-01', 10000);
        $this->issueInvoiceOn('2026-08-31', 20000);

        $csv = $this->gstr1For('2026-08-01', '2026-08-31');

        // 30,000 taxable · CGST 2,700 · SGST 2,700 · 35,400 gross.
        $this->assertStringContainsString('30000.00', $csv);
        $this->assertStringContainsString('35400.00', $csv);
    }
}
