<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payee name and account type on the company's bank block.
 *
 * The bank block is the one part of an invoice a customer acts on with their
 * own money, so what it prints has to be what the owner entered — nothing
 * inferred. The payee in particular: a proprietor billing under a trade name
 * often collects into an account held in a different name, and guessing it
 * from the company name would put the wrong payee on a cheque.
 */
class CompanyBankDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function state(): State
    {
        return State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
    }

    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'SLS IT Solutions',
            'state_id' => $this->state()->id,
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'INV/',
            'invoice_number_padding' => 4,
        ], $overrides);
    }

    public function test_payee_name_and_account_type_are_saved_from_company_settings(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'state_id' => $this->state()->id,
            'onboarded_at' => now(),
        ]);

        $this->actingAs($user)->patch(route('companies.update', $company), $this->companyPayload([
            'bank_name' => 'SVC Cooperative Bank Ltd',
            'bank_account_name' => 'M/s SLS IT Solutions',
            'bank_account_number' => '125204180000070',
            'bank_account_type' => 'current',
            'bank_ifsc' => 'SVCB0000252',
            'bank_branch' => 'Neelam Bata Road, Faridabad',
        ]))->assertRedirect();

        $company->refresh();
        $this->assertSame('M/s SLS IT Solutions', $company->bank_account_name);
        $this->assertSame('current', $company->bank_account_type);
        $this->assertSame('Current', $company->bankAccountTypeLabel());
    }

    public function test_account_type_is_restricted_to_savings_or_current(): void
    {
        // The value is printed verbatim on the invoice and read by an accounts
        // team, so the column holds one of two known words rather than
        // whatever a client posts.
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'state_id' => $this->state()->id,
            'onboarded_at' => now(),
        ]);

        $this->actingAs($user)->patch(route('companies.update', $company),
            $this->companyPayload(['bank_account_type' => 'nre']))
            ->assertSessionHasErrors('bank_account_type');

        $this->assertNull($company->fresh()->bank_account_type);
    }

    public function test_the_invoice_prints_the_payee_name_and_account_type(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'name' => 'SLS IT Solutions',
            'state_id' => $this->state()->id,
            'bank_name' => 'SVC Cooperative Bank Ltd',
            'bank_account_name' => 'Arbaz Khan',
            'bank_account_number' => '125204180000070',
            'bank_account_type' => 'savings',
            'bank_ifsc' => 'SVCB0000252',
        ]);
        $invoice = Invoice::factory()->recycle($user)->recycle($company)->finalized()->create();

        $this->actingAs($user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Arbaz Khan')
            ->assertSee('(Savings)');
    }

    public function test_a_blank_payee_name_prints_nothing_rather_than_the_company_name(): void
    {
        // The account may be in a name the business never told us. Printing
        // the trade name as a fallback would be an invented instruction to
        // the buyer's bank.
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'name' => 'SLS IT Solutions',
            'state_id' => $this->state()->id,
            'bank_name' => 'SVC Cooperative Bank Ltd',
            'bank_account_number' => '125204180000070',
            'bank_ifsc' => 'SVCB0000252',
            'bank_account_name' => null,
            'bank_account_type' => null,
        ]);
        $invoice = Invoice::factory()->recycle($user)->recycle($company)->finalized()->create();

        $this->actingAs($user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('125204180000070')
            ->assertDontSee('Payee:')
            ->assertDontSee('(Current)')
            ->assertDontSee('(Savings)');
    }

    public function test_both_forms_that_write_the_bank_block_render_the_new_controls(): void
    {
        // Company settings and the setup wizard write the same columns. A
        // field present on one and missing from the other is how a value
        // gets entered once and silently lost on the next save from the
        // other screen.
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'state_id' => $this->state()->id,
            'bank_account_type' => 'current',
        ]);
        $user->switchCompany($company);

        $settings = $this->actingAs($user)->get(route('companies.edit', $company))->assertOk();
        $settings->assertSee('name="bank_account_name"', false);
        $settings->assertSee('<option value="current" selected>Current</option>', false);

        // ?edit=1 — the wizard skips this step once the business is complete.
        $setup = $this->actingAs($user)->get(route('onboarding.business', ['edit' => 1]))->assertOk();
        $setup->assertSee('name="bank_account_name"', false);
        $setup->assertSee('name="bank_account_type"', false);
    }

    public function test_the_api_accepts_and_returns_both_fields(): void
    {
        // Web and mobile write the same company row; a field the app cannot
        // read back is a field the app silently drops on the next save.
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'state_id' => $this->state()->id,
            'onboarded_at' => now(),
        ]);

        $this->actingAs($user)
            ->putJson("/api/companies/{$company->id}", $this->companyPayload([
                'bank_account_name' => 'M/s SLS IT Solutions',
                'bank_account_type' => 'current',
            ]))
            ->assertOk()
            ->assertJsonPath('data.bank_account_name', 'M/s SLS IT Solutions')
            ->assertJsonPath('data.bank_account_type', 'current');
    }
}
