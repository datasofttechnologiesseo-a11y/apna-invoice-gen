<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function finalizedInvoice(User $user): Invoice
    {
        $company = Company::factory()->recycle($user)->create();
        return Invoice::factory()->recycle($user)->finalized()->create([
            'company_id' => $company->id,
            'balance' => 1180,
        ]);
    }

    public function test_owner_can_cancel_a_finalized_invoice_with_a_reason(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.cancel', $invoice), [
            'cancellation_reason' => 'Customer changed their mind after issue.',
        ])->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status);
        $this->assertNotNull($invoice->cancelled_at);
        $this->assertSame('Customer changed their mind after issue.', $invoice->cancellation_reason);
    }

    public function test_cancellation_requires_a_reason(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.cancel', $invoice), [
            'cancellation_reason' => '',
        ])->assertSessionHasErrors('cancellation_reason');

        $this->assertSame('final', $invoice->fresh()->status);
    }

    public function test_reason_must_be_at_least_five_characters(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.cancel', $invoice), [
            'cancellation_reason' => 'oops',
        ])->assertSessionHasErrors('cancellation_reason');
    }

    public function test_draft_invoice_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->recycle($user)->create(['status' => 'draft']);

        $this->actingAs($user)->post(route('invoices.cancel', $invoice), [
            'cancellation_reason' => 'Draft should be deleted not cancelled',
        ])->assertStatus(422);
    }

    public function test_cancelled_invoice_cannot_be_cancelled_again(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);
        $invoice->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'already cancelled',
        ])->save();

        $this->actingAs($user)->post(route('invoices.cancel', $invoice), [
            'cancellation_reason' => 'Try again',
        ])->assertStatus(422);
    }

    public function test_cancelled_invoice_refuses_new_payments(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);
        $invoice->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Wrong amount',
        ])->save();

        $this->actingAs($user)->post(route('invoices.payments', $invoice), [
            'amount' => 100,
            'method' => 'cash',
            'received_at' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_user_cannot_cancel_another_users_invoice(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobInvoice = $this->finalizedInvoice($bob);

        $this->actingAs($alice)->post(route('invoices.cancel', $bobInvoice), [
            'cancellation_reason' => 'should not work',
        ])->assertStatus(403);

        $this->assertSame('final', $bobInvoice->fresh()->status);
    }

    public function test_cancelled_invoice_still_viewable_to_owner_for_audit(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);
        $invoice->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Audit trail preserved',
        ])->save();

        $this->actingAs($user)->get(route('invoices.show', $invoice))
            ->assertStatus(200)
            ->assertSee('Audit trail preserved');
    }
}
