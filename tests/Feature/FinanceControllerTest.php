<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_finance(): void
    {
        $this->get(route('finance.index'))->assertRedirect(route('login'));
    }

    public function test_user_sees_finance_overview(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('finance.index'));
        $response->assertStatus(200);
        $response->assertSee('Finance');
    }

    public function test_user_can_add_expense(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(),
            'category' => 'rent',
            'description' => 'July office rent',
            'amount' => 25000,
            'gst_amount' => 4500,
            'payment_method' => 'bank',
        ])->assertRedirect(route('finance.expenses'));

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'category' => 'rent',
            'amount' => 25000,
            'gst_amount' => 4500,
        ]);
    }

    public function test_expense_is_scoped_to_active_company(): void
    {
        $user = User::factory()->create();
        $activeCompany = $user->ensureCompany();

        $this->actingAs($user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(),
            'category' => 'marketing',
            'description' => 'Meta ads',
            'amount' => 5000,
        ]);

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'company_id' => $activeCompany->id,
            'amount' => 5000,
        ]);
    }

    public function test_user_cannot_edit_another_users_expense(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobExpense = Expense::factory()->recycle($bob)->create();

        $this->actingAs($alice)->get(route('finance.expenses.edit', $bobExpense))
            ->assertStatus(403);

        $this->actingAs($alice)->delete(route('finance.expenses.destroy', $bobExpense))
            ->assertStatus(403);

        $this->assertDatabaseHas('expenses', ['id' => $bobExpense->id]);
    }

    public function test_rejects_invalid_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(),
            'category' => 'not_a_real_category',
            'description' => 'x',
            'amount' => 100,
        ])->assertSessionHasErrors('category');
    }

    public function test_pnl_calculation_excludes_gst_from_revenue_and_expenses(): void
    {
        $user = User::factory()->create();
        $company = $user->ensureCompany();

        // One finalized invoice: ₹10,000 taxable + ₹1,800 GST = ₹11,800 total
        Invoice::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'status' => 'final',
            'invoice_date' => now()->toDateString(),
            'subtotal' => 10000,
            'total_cgst' => 900, 'total_sgst' => 900, 'total_igst' => 0, 'total_tax' => 1800,
            'grand_total' => 11800,
            'paid_amount' => 5000, 'balance' => 6800,
        ]);

        // One expense: ₹1,000 + ₹180 GST
        Expense::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'entry_date' => now()->toDateString(),
            'amount' => 1000,
            'gst_amount' => 180,
        ]);

        $response = $this->actingAs($user)->get(route('finance.index', ['period' => 'this_month']));

        // Accrual P&L: 10,000 − 1,000 = 9,000 net profit
        $response->assertSee('₹9,000');
    }
}
