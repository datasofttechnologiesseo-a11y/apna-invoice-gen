<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function finalizedInvoice(User $user, array $overrides = []): Invoice
    {
        $company = Company::factory()->recycle($user)->create([
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'receipt_prefix' => 'RCPT',
            'receipt_counter' => 0,
            'receipt_number_padding' => 4,
        ]);

        return Invoice::factory()->recycle($user)->finalized()->create(array_merge([
            'company_id' => $company->id,
            'grand_total' => 1180,
            'balance' => 1180,
            'paid_amount' => 0,
        ], $overrides));
    }

    public function test_recording_payment_creates_receipt_and_updates_balance(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.payments', $invoice), [
            'amount' => 500,
            'method' => 'upi',
            'received_at' => now()->toDateString(),
            'reference_number' => 'UPI123456',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(500.0, (float) $invoice->paid_amount);
        $this->assertSame(680.0, (float) $invoice->balance);
        $this->assertSame('partially_paid', $invoice->status);

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);
        $this->assertSame('RCPT-0001', $payment->receipt_number);
        $this->assertSame('upi', $payment->method);
        $this->assertSame('UPI123456', $payment->reference_number);
    }

    public function test_full_payment_flips_invoice_to_paid(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.payments', $invoice), [
            'amount' => 1180,
            'method' => 'cash',
            'received_at' => now()->toDateString(),
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_draft_invoice_cannot_accept_payment(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create();
        $invoice = Invoice::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user)->post(route('invoices.payments', $invoice), [
            'amount' => 100,
            'method' => 'cash',
            'received_at' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_cannot_overpay_invoice(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.payments', $invoice), [
            'amount' => 9999999,
            'method' => 'upi',
            'received_at' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');
    }

    public function test_reversing_a_payment_restores_balance_and_status(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.payments', $invoice), [
            'amount' => 1180,
            'method' => 'cash',
            'received_at' => now()->toDateString(),
        ]);

        $payment = $invoice->fresh()->payments()->first();
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->actingAs($user)->delete(route('payments.destroy', $payment))
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->paid_amount);
        $this->assertSame(1180.0, (float) $invoice->balance);
        $this->assertSame('final', $invoice->status);
    }

    public function test_receipt_pdf_returns_for_owner(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.payments', $invoice), [
            'amount' => 500,
            'method' => 'cash',
            'received_at' => now()->toDateString(),
        ]);

        $payment = $invoice->fresh()->payments()->first();

        $response = $this->actingAs($user)->get(route('payments.receipt', $payment));
        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_user_cannot_access_another_users_receipt(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $invoice = $this->finalizedInvoice($alice);

        $this->actingAs($alice)->post(route('invoices.payments', $invoice), [
            'amount' => 500,
            'method' => 'cash',
            'received_at' => now()->toDateString(),
        ]);

        $payment = $invoice->fresh()->payments()->first();

        $this->actingAs($bob)->get(route('payments.receipt', $payment))->assertStatus(403);
        $this->actingAs($bob)->delete(route('payments.destroy', $payment))->assertStatus(403);
    }

    public function test_receipt_numbers_are_sequential_per_company(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->post(route('invoices.payments', $invoice), [
                'amount' => 100,
                'method' => 'cash',
                'received_at' => now()->toDateString(),
            ]);
        }

        $numbers = Payment::where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->pluck('receipt_number')
            ->all();

        $this->assertSame(['RCPT-0001', 'RCPT-0002', 'RCPT-0003'], $numbers);
    }
}
