<?php

namespace Tests\Feature;

use App\Models\CashMemo;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the two convenience features added to close functional gaps:
 *   - Invoice "Duplicate" → fresh editable draft (repeat/recurring billing)
 *   - Cash-memo edit/update (previously create/delete only), incl. fractional
 *     quantities and keeping the linked Expense entry in sync.
 */
class InvoiceDuplicateCashMemoEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithCompany(): array
    {
        $user = User::factory()->create();
        $state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'state_id' => $state->id,
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
        ]);
        $user->forceFill(['active_company_id' => $company->id])->save();

        return [$user, $company, $state];
    }

    public function test_duplicate_creates_a_fresh_draft_with_copied_items(): void
    {
        [$user, $company, $state] = $this->makeUserWithCompany();
        $customer = Customer::factory()->create([
            'user_id' => $user->id, 'company_id' => $company->id, 'state_id' => $state->id,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id, 'company_id' => $company->id, 'customer_id' => $customer->id,
            'status' => 'paid', 'invoice_number' => 'INV-0001', 'finalized_at' => now(),
            'subtotal' => 1000, 'total_cgst' => 90, 'total_sgst' => 90, 'total_igst' => 0,
            'total_tax' => 180, 'grand_total' => 1180, 'paid_amount' => 1180, 'balance' => 0,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Service A', 'hsn_sac' => '998314',
            'quantity' => 10, 'unit' => 'NOS', 'rate' => 100, 'discount' => 0, 'gst_rate' => 18,
            'amount' => 1000, 'cgst_amount' => 90, 'sgst_amount' => 90, 'igst_amount' => 0, 'total' => 1180,
        ]);

        $before = Invoice::count();
        $resp = $this->actingAs($user)->post(route('invoices.duplicate', $invoice));

        $this->assertSame($before + 1, Invoice::count(), 'A new invoice should be created');
        $new = Invoice::latest('id')->first();

        $resp->assertRedirect(route('invoices.edit', $new));
        $this->assertNotSame($invoice->id, $new->id);
        $this->assertSame('draft', $new->status);
        $this->assertNull($new->invoice_number, 'Copy starts unnumbered');
        $this->assertNull($new->finalized_at);
        $this->assertSame($customer->id, $new->customer_id);
        $this->assertEquals(0.0, (float) $new->paid_amount, 'Payment history is NOT carried over');
        $this->assertEquals((float) $invoice->grand_total, (float) $new->balance);

        $new->load('items');
        $this->assertCount(1, $new->items);
        $this->assertSame('Service A', $new->items->first()->description);
        $this->assertEquals(10.0, (float) $new->items->first()->quantity);
    }

    public function test_user_cannot_duplicate_another_users_invoice(): void
    {
        [$owner, $company, $state] = $this->makeUserWithCompany();
        $customer = Customer::factory()->create(['user_id' => $owner->id, 'company_id' => $company->id, 'state_id' => $state->id]);
        $invoice = Invoice::factory()->create(['user_id' => $owner->id, 'company_id' => $company->id, 'customer_id' => $customer->id]);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('invoices.duplicate', $invoice))->assertForbidden();
    }

    public function test_cash_memo_can_be_edited_with_fractional_qty_and_expense_syncs(): void
    {
        [$user] = $this->makeUserWithCompany();

        // Create (also proves fractional quantity 1.5 is accepted now).
        $this->actingAs($user)->post(route('finance.cash-memos.store'), [
            'memo_date' => now()->toDateString(),
            'seller_name' => 'Sharma Stationery',
            'payment_mode' => 'cash',
            'expense_category' => 'misc',
            'gst_rate' => 0,
            'items' => [['description' => 'Cloth', 'quantity' => 1.5, 'rate' => 100]],
        ])->assertRedirect();

        $memo = CashMemo::latest('id')->first();
        $this->assertEquals(150.0, (float) $memo->grand_total, '1.5 × 100 = 150 (fractional qty)');
        $expenseId = $memo->expense_id;
        $this->assertNotNull($expenseId);

        // Edit page renders.
        $this->actingAs($user)->get(route('finance.cash-memos.edit', $memo))->assertOk();

        // Update — change seller + items.
        $this->actingAs($user)->put(route('finance.cash-memos.update', $memo), [
            'memo_date' => now()->toDateString(),
            'memo_number' => $memo->memo_number,
            'seller_name' => 'Verma Traders',
            'payment_mode' => 'upi',
            'expense_category' => 'misc',
            'gst_rate' => 0,
            'items' => [['description' => 'Notebooks', 'quantity' => 2.5, 'rate' => 40]],
        ])->assertRedirect(route('finance.cash-memos.show', $memo));

        $memo->refresh()->load('items');
        $this->assertSame('Verma Traders', $memo->seller_name);
        $this->assertEquals(100.0, (float) $memo->grand_total, '2.5 × 40 = 100');
        $this->assertCount(1, $memo->items);
        $this->assertSame('Notebooks', $memo->items->first()->description);

        // Linked expense kept in sync.
        $expense = Expense::find($expenseId);
        $this->assertSame('Verma Traders', $expense->vendor_name);
        $this->assertEquals(100.0, (float) $expense->amount);
    }

    public function test_user_cannot_edit_another_users_cash_memo(): void
    {
        [$user] = $this->makeUserWithCompany();
        $this->actingAs($user)->post(route('finance.cash-memos.store'), [
            'memo_date' => now()->toDateString(),
            'seller_name' => 'Sharma Stationery',
            'payment_mode' => 'cash',
            'gst_rate' => 0,
            'items' => [['description' => 'Pens', 'quantity' => 2, 'rate' => 50]],
        ]);
        $memo = CashMemo::latest('id')->first();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('finance.cash-memos.edit', $memo))->assertForbidden();
    }
}
