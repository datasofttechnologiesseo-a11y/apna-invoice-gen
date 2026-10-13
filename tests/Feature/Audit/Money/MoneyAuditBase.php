<?php

namespace Tests\Feature\Audit\Money;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Shared setup for the money-in audit repros: one Maharashtra business, one customer. */
abstract class MoneyAuditBase extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-30 10:00', 'Asia/Kolkata'));
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5',
            'composition_dealer' => false, 'invoice_prefix' => 'INV', 'invoice_counter' => 0,
        ]);
        $this->company->forceFill(['upi_id' => 'shop@okaxis'])->save();
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id,
            'state_id' => $mh->id, 'gstin' => '27FGHIJ5678K1Z3', 'name' => 'Patil Traders',
            'phone' => '9876500001', 'email' => 'patil@example.com',
        ]);
    }

    /** Draft through the web form, then issue it. */
    protected function issue(array $items, string $date = '2026-09-05', ?string $due = null, array $extra = []): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'invoice_date' => $date,
            'due_date' => $due,
            'items' => $items,
        ] + $extra)->assertSessionHasNoErrors();
        $inv = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasNoErrors();

        return $inv->fresh();
    }

    protected function pay(Invoice $inv, array $data)
    {
        return $this->actingAs($this->user)->post(route('invoices.payments', $inv), $data + ['method' => 'neft']);
    }

    protected function creditNote(Invoice $inv, array $data)
    {
        return $this->actingAs($this->user)->post(route('credit-notes.store', $inv), $data + [
            'credit_note_date' => now()->toDateString(), 'reason' => 'rate_correction',
        ]);
    }
}
