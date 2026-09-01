<?php

namespace Tests\Feature\Api;

use App\Models\CashMemo;
use App\Models\Company;
use App\Models\Expense;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The remaining DELETE routes, plus the company edit form.
 *
 * A delete that skips its ownership check destroys someone else's records
 * with no way back, so these are worth a test even though each one is small.
 */
class DestructiveRouteGuardTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithCompany(): array
    {
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'state_id' => $state->id, 'onboarded_at' => now(),
        ]);

        return [$user, $company, $state];
    }

    public function test_expense_delete_and_update_are_scoped_to_the_owner(): void
    {
        [$owner, $ownerCo] = $this->ownerWithCompany();
        [$intruder] = $this->ownerWithCompany();

        $expense = Expense::factory()->recycle($owner)->recycle($ownerCo)->create();

        Sanctum::actingAs($intruder);
        $this->deleteJson("/api/expenses/{$expense->id}")->assertForbidden();
        $this->putJson("/api/expenses/{$expense->id}", ['amount' => 1])->assertForbidden();

        $this->assertNotNull(Expense::find($expense->id));

        // and the owner can still do it, so the guard is not simply "deny all"
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/expenses/{$expense->id}")->assertSuccessful();
        $this->assertNull(Expense::find($expense->id));
    }

    public function test_cash_memo_delete_is_scoped_to_the_owner(): void
    {
        [$owner, $ownerCo, $state] = $this->ownerWithCompany();
        [$intruder] = $this->ownerWithCompany();
        // There is no CashMemoFactory, so the row is built from the columns
        // the schema actually requires.
        $memo = CashMemo::create([
            'user_id' => $owner->id,
            'company_id' => $ownerCo->id,
            'memo_number' => 'CM/0001',
            'memo_date' => now()->toDateString(),
            'seller_name' => 'Local Supplier',
            'grand_total' => 1180,
        ]);

        Sanctum::actingAs($intruder);
        $this->deleteJson("/api/cash-memos/{$memo->id}")->assertForbidden();
        $this->assertNotNull(CashMemo::find($memo->id));

        $this->actingAs($intruder)
            ->delete(route('finance.cash-memos.destroy', $memo))
            ->assertForbidden();
        $this->assertNotNull(CashMemo::find($memo->id));
    }

    public function test_the_company_edit_form_saves_and_is_owner_scoped(): void
    {
        [$owner, $company, $state] = $this->ownerWithCompany();

        $payload = [
            'name' => 'Renamed Business', 'state_id' => $state->id, 'country' => 'India',
            'default_currency' => 'INR', 'invoice_prefix' => 'INV/', 'invoice_number_padding' => 4,
        ];

        $this->actingAs($owner)->patch(route('company.update'), $payload)->assertRedirect();
        $this->assertSame('Renamed Business', $company->fresh()->name);

        // A second user editing "their" company must not touch this one.
        [$other] = $this->ownerWithCompany();
        $this->actingAs($other)->patch(route('company.update'),
            array_merge($payload, ['name' => 'Hijacked']))->assertRedirect();

        $this->assertSame('Renamed Business', $company->fresh()->name,
            'company.update must act on the caller\'s own company, never a shared one');
    }

    public function test_quick_create_product_belongs_to_the_caller(): void
    {
        [$user] = $this->ownerWithCompany();

        $res = $this->actingAs($user)->postJson(route('products.quick-create'), [
            'name' => 'Quick Item', 'rate' => 500, 'gst_rate' => 18,
            'hsn_sac' => '998314', 'unit' => 'NOS', 'kind' => 'service',
        ]);

        $res->assertSuccessful();
        $product = \App\Models\Product::where('name', 'Quick Item')->first();
        $this->assertNotNull($product);
        $this->assertSame($user->id, $product->user_id);
    }
}
