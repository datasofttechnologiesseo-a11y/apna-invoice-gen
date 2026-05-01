<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Books-locked-until enforcement.
 *
 * Once a company sets `books_locked_until` (typically 31 March after FY close),
 * the app must refuse any mutation against documents dated on or before that
 * date — invoices, payments, expenses, cash memos, credit notes. This test
 * file is the regression guard that proves the protection is wired across
 * every relevant controller path.
 *
 * Why it matters: this is a Section 128 (Companies Act 2013) audit-trail
 * commitment. If a future refactor silently removes one of the guards, the
 * audit defensibility story breaks. Read-only access to the locked period is
 * always allowed — only mutations are blocked.
 */
class BooksLockedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Carbon $lockDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->lockDate = Carbon::create(2026, 3, 31)->endOfDay();
        $this->company = Company::factory()->recycle($this->user)->create([
            'books_locked_until' => $this->lockDate->toDateString(),
        ]);
    }

    /** Date inside the locked period (1 Mar 2026, before 31 Mar). */
    private function lockedDate(): string
    {
        return '2026-03-01';
    }

    /** Date safely after the lock (1 May 2026). */
    private function unlockedDate(): string
    {
        return '2026-05-01';
    }

    // ─── INVOICES: finalize, delete-draft, cancel ───────────────────────

    public function test_cannot_finalize_invoice_dated_inside_locked_period(): void
    {
        $invoice = Invoice::factory()->recycle($this->user)->create([
            'company_id' => $this->company->id,
            'invoice_date' => $this->lockedDate(),
            'status' => 'draft',
        ]);
        // Need at least one line item or finalize fails for a different reason.
        InvoiceItem::factory()->recycle($invoice)->create();

        $this->actingAs($this->user)
            ->post(route('invoices.finalize', $invoice))
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Books are locked'));

        // Status unchanged.
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_cannot_delete_draft_invoice_dated_inside_locked_period(): void
    {
        $invoice = Invoice::factory()->recycle($this->user)->create([
            'company_id' => $this->company->id,
            'invoice_date' => $this->lockedDate(),
            'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->delete(route('invoices.destroy', $invoice))
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Books are locked'));

        $this->assertNotNull(Invoice::find($invoice->id), 'Invoice should still exist');
    }

    public function test_cannot_cancel_finalized_invoice_dated_inside_locked_period(): void
    {
        $invoice = Invoice::factory()->recycle($this->user)->finalized()->create([
            'company_id' => $this->company->id,
            'invoice_date' => $this->lockedDate(),
        ]);

        $this->actingAs($this->user)
            ->post(route('invoices.cancel', $invoice), [
                'cancellation_reason' => 'Customer disputes the bill.',
            ])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Books are locked'));

        $this->assertSame('final', $invoice->fresh()->status);
    }

    public function test_cannot_reverse_payment_dated_inside_locked_period(): void
    {
        $invoice = Invoice::factory()->recycle($this->user)->finalized()->create([
            'company_id' => $this->company->id,
            'invoice_date' => $this->lockedDate(),
            'paid_amount' => 1180,
            'balance' => 0,
            'status' => 'paid',
        ]);
        $payment = Payment::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-0001',
            'received_at' => $this->lockedDate(),
            'amount' => 1180,
            'method' => 'upi',
        ]);

        $this->actingAs($this->user)
            ->delete(route('payments.destroy', $payment))
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Books are locked'));

        $this->assertNotNull(Payment::find($payment->id), 'Payment should still exist');
    }

    // ─── EXPENSES: create, update, delete ──────────────────────────────

    public function test_cannot_create_expense_dated_inside_locked_period(): void
    {
        $this->actingAs($this->user)
            ->post(route('finance.expenses.store'), [
                'entry_date' => $this->lockedDate(),
                'category' => array_key_first(config('expense_categories')),
                'description' => 'Backdated test',
                'amount' => 500,
                'gst_amount' => 0,
            ])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Books are locked'));

        $this->assertSame(0, Expense::count());
    }

    public function test_cannot_edit_expense_dated_inside_locked_period(): void
    {
        $expense = Expense::factory()->recycle($this->user)->create([
            'company_id' => $this->company->id,
            'entry_date' => $this->lockedDate(),
        ]);

        $this->actingAs($this->user)
            ->patch(route('finance.expenses.update', $expense), [
                'entry_date' => $this->lockedDate(),
                'category' => $expense->category,
                'description' => 'Edited',
                'amount' => 999,
                'gst_amount' => 0,
            ])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Books are locked'));

        $this->assertSame((float) $expense->amount, (float) $expense->fresh()->amount);
    }

    public function test_cannot_move_expense_into_locked_period(): void
    {
        // Expense currently dated AFTER the lock — user attempts to backdate it INTO the lock.
        $expense = Expense::factory()->recycle($this->user)->create([
            'company_id' => $this->company->id,
            'entry_date' => $this->unlockedDate(),
            'description' => 'Original',
        ]);

        $this->actingAs($this->user)
            ->patch(route('finance.expenses.update', $expense), [
                'entry_date' => $this->lockedDate(),
                'category' => $expense->category,
                'description' => 'Backdated',
                'amount' => $expense->amount,
                'gst_amount' => 0,
            ])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'locked period'));

        $this->assertSame('Original', $expense->fresh()->description);
    }

    public function test_cannot_delete_expense_dated_inside_locked_period(): void
    {
        $expense = Expense::factory()->recycle($this->user)->create([
            'company_id' => $this->company->id,
            'entry_date' => $this->lockedDate(),
        ]);

        $this->actingAs($this->user)
            ->delete(route('finance.expenses.destroy', $expense))
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Books are locked'));

        $this->assertNotNull(Expense::find($expense->id));
    }

    // ─── POSITIVE CONTROL: dates AFTER lock still work ─────────────────

    public function test_can_create_expense_dated_after_lock(): void
    {
        $this->actingAs($this->user)
            ->post(route('finance.expenses.store'), [
                'entry_date' => $this->unlockedDate(),
                'category' => array_key_first(config('expense_categories')),
                'description' => 'Current period expense',
                'amount' => 500,
                'gst_amount' => 0,
            ])
            ->assertRedirect(route('finance.expenses'));

        $this->assertSame(1, Expense::count());
    }

    public function test_can_finalize_invoice_dated_after_lock(): void
    {
        $invoice = Invoice::factory()->recycle($this->user)->create([
            'company_id' => $this->company->id,
            'invoice_date' => $this->unlockedDate(),
            'status' => 'draft',
        ]);
        InvoiceItem::factory()->recycle($invoice)->create();

        $this->actingAs($this->user)
            ->post(route('invoices.finalize', $invoice))
            ->assertSessionMissing('error');

        $this->assertSame('final', $invoice->fresh()->status);
    }

    // ─── READ ACCESS: viewing locked-period data is always allowed ────

    public function test_can_view_locked_period_invoice_for_audit(): void
    {
        $invoice = Invoice::factory()->recycle($this->user)->finalized()->create([
            'company_id' => $this->company->id,
            'invoice_date' => $this->lockedDate(),
        ]);

        $this->actingAs($this->user)
            ->get(route('invoices.show', $invoice))
            ->assertOk();
    }

    public function test_can_view_locked_period_expense_for_audit(): void
    {
        $expense = Expense::factory()->recycle($this->user)->create([
            'company_id' => $this->company->id,
            'entry_date' => $this->lockedDate(),
        ]);

        $this->actingAs($this->user)
            ->get(route('finance.expenses.edit', $expense))
            ->assertOk();
    }
}
