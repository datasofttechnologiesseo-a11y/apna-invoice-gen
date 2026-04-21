<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function finalizedInvoice(User $user, float $total = 1180): Invoice
    {
        $company = Company::factory()->recycle($user)->create([
            'invoice_prefix' => 'INV',
            'credit_note_prefix' => 'CRN',
            'credit_note_counter' => 0,
            'credit_note_number_padding' => 4,
        ]);
        return Invoice::factory()->recycle($user)->finalized()->create([
            'company_id' => $company->id,
            'grand_total' => $total,
            'balance' => $total,
            'paid_amount' => 0,
            'credited_amount' => 0,
        ]);
    }

    public function test_issuing_credit_note_reduces_invoice_balance(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user, 1180);

        $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 500,
            'reason' => 'rate_correction',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(500.0, (float) $invoice->credited_amount);
        $this->assertSame(680.0, (float) $invoice->balance);

        $cn = $invoice->creditNotes()->first();
        $this->assertNotNull($cn);
        $this->assertSame('CRN-0001', $cn->credit_note_number);
        $this->assertSame('rate_correction', $cn->reason);
    }

    public function test_full_credit_flips_invoice_to_paid(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user, 1180);

        $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 1180,
            'reason' => 'sales_return',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_cannot_credit_more_than_invoice_total(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user, 1180);

        $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 2000,
            'reason' => 'other',
        ])->assertSessionHasErrors('amount');
    }

    public function test_draft_invoice_refuses_credit_note(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create();
        $invoice = Invoice::factory()->recycle($user)->create([
            'company_id' => $company->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 100,
            'reason' => 'other',
        ])->assertStatus(422);
    }

    public function test_cancelled_invoice_refuses_credit_note(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);
        $invoice->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'wrong',
        ])->save();

        $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 100,
            'reason' => 'other',
        ])->assertStatus(422);
    }

    public function test_reversing_a_credit_note_restores_balance(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user, 1180);

        $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 500,
            'reason' => 'sales_return',
        ]);

        $cn = $invoice->fresh()->creditNotes()->first();

        $this->actingAs($user)->delete(route('credit-notes.destroy', $cn))
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->credited_amount);
        $this->assertSame(1180.0, (float) $invoice->balance);
        $this->assertSame('final', $invoice->status);
    }

    public function test_credit_note_numbers_are_sequential_per_company(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user, 10000);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
                'credit_note_date' => now()->toDateString(),
                'amount' => 100,
                'reason' => 'other',
            ]);
        }

        $numbers = CreditNote::where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->pluck('credit_note_number')
            ->all();

        $this->assertSame(['CRN-0001', 'CRN-0002', 'CRN-0003'], $numbers);
    }

    public function test_another_user_cannot_credit_or_reverse(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $invoice = $this->finalizedInvoice($alice);

        $this->actingAs($bob)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 100,
            'reason' => 'other',
        ])->assertStatus(403);
    }

    public function test_credit_note_pdf_downloads(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 100,
            'reason' => 'other',
        ]);
        $cn = $invoice->fresh()->creditNotes()->first();

        $response = $this->actingAs($user)->get(route('credit-notes.pdf', $cn));
        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }
}
