<?php

namespace Tests\Feature\Audit\Sales;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shared set-up for the sales-document audit repros: a GST-registered
 * business in Maharashtra, one customer in the same state and one in
 * Karnataka. Runs on the in-memory SQLite test database only.
 */
abstract class SalesAuditCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected State $mh;
    protected State $ka;
    protected Customer $local;
    protected Customer $outside;

    protected function setUp(): void
    {
        // Never against the live local database: RefreshDatabase wipes
        // whatever the connection points at. Test databases are fine.
        $db = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        if (($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION')) !== 'sqlite' && $db === 'invoice_generator') {
            $this->markTestSkipped('Refusing to run against the live invoice_generator database.');
        }

        parent::setUp();

        $this->user = User::factory()->create();
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z5',
            'composition_dealer' => false, 'invoice_prefix' => 'INV', 'invoice_counter' => 0,
            'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->local = Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id,
            'state_id' => $this->mh->id, 'gstin' => '27FGHIJ5678K1Z3',
        ]);
        $this->outside = Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id,
            'state_id' => $this->ka->id, 'gstin' => '29FGHIJ5678K1Z3',
        ]);
    }

    /** Save a draft through the real web store endpoint. */
    protected function draft(array $items, ?Customer $customer = null, array $extra = []): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => ($customer ?? $this->local)->id,
            'invoice_date' => now()->toDateString(),
            'items' => $items,
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return Invoice::latest('id')->first()->fresh('items');
    }

    /** Press "Issue" on the draft through the real web endpoint. */
    protected function issue(Invoice $invoice): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice));

        return $invoice->fresh('items');
    }

    protected function node(): ?string
    {
        $out = [];
        $code = 1;
        exec('node --version 2>&1', $out, $code);

        return $code === 0 ? 'node' : null;
    }
}
