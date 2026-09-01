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
 * End-to-end cover for the workflows that had no dedicated test: cash memos,
 * expenses, running more than one business in one login, backups and referral
 * codes. The multi-company cases matter most — a leak between two of a user's
 * own firms would mix their books.
 */
class CoreWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'name' => 'Sharma Exports',
            'state_id' => $this->state->id,
            'gstin' => '27ABCDE1234F1Z5',
            'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    // ---------------------------------------------------------------- cash memo

    public function test_cash_memo_can_be_created_and_downloaded(): void
    {
        $this->actingAs($this->user)->post(route('finance.cash-memos.store'), [
            'memo_date' => now()->toDateString(),
            'seller_name' => 'Local Wholesaler',
            'seller_state' => 'Maharashtra',
            'payment_mode' => 'cash',
            'gst_rate' => 18,
            'items' => [[
                'description' => 'Packing material', 'quantity' => 5, 'rate' => 200,
            ]],
        ])->assertRedirect();

        $memo = \App\Models\CashMemo::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $memo->subtotal, 0.01);

        $pdf = $this->actingAs($this->user)->get(route('finance.cash-memos.pdf', $memo));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    // ---------------------------------------------------------------- expenses

    public function test_expense_is_recorded_and_reaches_the_profit_and_loss(): void
    {
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(),
            'category' => 'rent',
            'description' => 'Office rent',
            'amount' => 15000,
            'gst_amount' => 2700,
            'itc_eligible' => '1',
        ])->assertRedirect();

        $expense = \App\Models\Expense::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(15000.0, (float) $expense->amount, 0.01);

        $finance = $this->actingAs($this->user)->get(route('finance.index'));
        $finance->assertOk();
        $this->assertEqualsWithDelta(15000.0, (float) $finance->viewData('expense')['taxable'], 0.01);
    }

    // ------------------------------------------------------------ multi-company

    public function test_a_second_business_keeps_its_own_books(): void
    {
        $second = Company::factory()->recycle($this->user)->create([
            'name' => 'Sharma Logistics',
            'state_id' => $this->state->id,
            'gstin' => '27FGHIJ5678K1Z3',
            'onboarded_at' => now(),
        ]);

        // An invoice under the first business only.
        $customer = Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $this->state->id]);
        Invoice::factory()->recycle($this->user)->recycle($this->company)->recycle($customer)
            ->create(['status' => 'final', 'finalized_at' => now()]);

        $this->assertSame(1, $this->company->invoices()->count());
        $this->assertSame(0, $second->invoices()->count());

        // Switching changes the active context.
        $this->actingAs($this->user)->post(route('companies.switch', $second))->assertRedirect();
        $this->assertSame($second->id, $this->user->fresh()->ensureCompany()->id);

        // The invoice list is scoped to the newly active business.
        $list = $this->actingAs($this->user->fresh())->get(route('invoices.index'));
        $list->assertOk();
        $this->assertCount(0, $list->viewData('invoices'),
            'invoices from another of the user\'s businesses must not appear');
    }

    public function test_a_user_cannot_switch_to_someone_elses_business(): void
    {
        $stranger = User::factory()->create();
        $theirs = Company::factory()->recycle($stranger)->create(['state_id' => $this->state->id]);

        $this->actingAs($this->user)
            ->post(route('companies.switch', $theirs))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- backup

    public function test_backup_downloads_a_zip(): void
    {
        $res = $this->actingAs($this->user)->get(route('backup.download'));
        $res->assertOk();
        $res->assertHeader('content-type', 'application/zip');

        $file = $res->baseResponse->getFile();
        $this->assertStringStartsWith('PK', file_get_contents($file->getPathname()),
            'a backup should be a ZIP archive');
    }

    // ---------------------------------------------------------------- referrals

    public function test_referral_code_is_generated_once_and_stays_stable(): void
    {
        $first = $this->user->ensureReferralCode();

        $this->assertNotEmpty($first);
        $this->assertSame($first, $this->user->fresh()->ensureReferralCode(),
            'a referral code must not change between calls');

        $this->actingAs($this->user)->get(route('referrals.index'))
            ->assertOk()
            ->assertSee($first);
    }
}
