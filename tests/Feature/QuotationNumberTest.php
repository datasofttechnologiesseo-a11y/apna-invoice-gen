<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A quotation is a commercial price offer, not a statutory document, so unlike
 * a GST tax invoice its number is the user's to choose — even when the business
 * is GST-registered.
 */
class QuotationNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $state = State::factory()->create(['gst_code' => '27']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'state_id' => $state->id,
            'gstin' => '27ABCDE1234F1Z5',   // registered — still editable
            'quote_prefix' => 'QT',
            'quote_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $state->id]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'quote_date' => now()->toDateString(),
            'items' => [[
                'description' => 'Consulting', 'hsn_sac' => '998311',
                'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18,
            ]],
        ], $override);
    }

    public function test_a_custom_quote_number_is_saved_on_the_draft(): void
    {
        $this->actingAs($this->user)
            ->post(route('quotations.store'), $this->payload(['quote_number' => 'PROP-2026-014']))
            ->assertRedirect();

        $this->assertSame('PROP-2026-014', Quotation::latest('id')->first()->quote_number);
    }

    public function test_a_custom_number_survives_sending_and_does_not_burn_the_counter(): void
    {
        $this->actingAs($this->user)
            ->post(route('quotations.store'), $this->payload(['quote_number' => 'PROP-2026-014']));

        $quote = Quotation::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('quotations.send', $quote))->assertRedirect();

        $quote->refresh();
        $this->assertSame('PROP-2026-014', $quote->quote_number);
        $this->assertSame('sent', $quote->status);
        $this->assertSame(0, (int) $this->company->fresh()->quote_counter,
            'a user-chosen number must not consume one from the auto series');
    }

    public function test_leaving_it_blank_still_auto_assigns_on_send(): void
    {
        $this->actingAs($this->user)->post(route('quotations.store'), $this->payload(['quote_number' => '']));

        $quote = Quotation::latest('id')->firstOrFail();
        $this->assertNull($quote->quote_number);

        $this->actingAs($this->user)->post(route('quotations.send', $quote));

        $quote->refresh();
        $this->assertNotEmpty($quote->quote_number);
        $this->assertSame(1, (int) $this->company->fresh()->quote_counter);
    }

    public function test_the_number_can_be_changed_while_it_is_still_a_draft(): void
    {
        $this->actingAs($this->user)->post(route('quotations.store'), $this->payload(['quote_number' => 'FIRST-1']));
        $quote = Quotation::latest('id')->firstOrFail();

        $this->actingAs($this->user)
            ->put(route('quotations.update', $quote), $this->payload(['quote_number' => 'SECOND-2']))
            ->assertRedirect();

        $this->assertSame('SECOND-2', $quote->fresh()->quote_number);
    }

    public function test_two_quotations_cannot_share_a_number(): void
    {
        $this->actingAs($this->user)->post(route('quotations.store'), $this->payload(['quote_number' => 'DUP-1']));

        $this->actingAs($this->user)
            ->post(route('quotations.store'), $this->payload(['quote_number' => 'DUP-1']))
            ->assertSessionHasErrors('quote_number');

        $this->assertSame(1, Quotation::where('quote_number', 'DUP-1')->count());
    }

    public function test_another_business_may_reuse_the_same_number(): void
    {
        $this->actingAs($this->user)->post(route('quotations.store'), $this->payload(['quote_number' => 'SHARED-1']));

        // A different user's company keeps its own numbering space.
        $other = User::factory()->create();
        $otherCompany = Company::factory()->recycle($other)->create([
            'state_id' => $this->company->state_id,
        ]);
        $other->forceFill(['active_company_id' => $otherCompany->id])->save();
        $otherCustomer = Customer::factory()->recycle($other)->recycle($otherCompany)
            ->create(['state_id' => $this->company->state_id]);

        $this->actingAs($other)->post(route('quotations.store'), [
            'customer_id' => $otherCustomer->id,
            'quote_date' => now()->toDateString(),
            'quote_number' => 'SHARED-1',
            'items' => [[
                'description' => 'Design', 'hsn_sac' => '998311',
                'quantity' => 1, 'rate' => 5000, 'gst_rate' => 18,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Quotation::where('quote_number', 'SHARED-1')->count());
    }
}
