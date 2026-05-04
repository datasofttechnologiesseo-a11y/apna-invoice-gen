<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CGST Section 34(2): a credit note must be declared no later than the
 * 30th day of November following the end of the FY in which the original
 * supply was made.
 *
 * Indian FY runs Apr 1 → Mar 31, so:
 *   • Invoice 15-Aug-2024 (FY 2024-25) → CN deadline 30-Nov-2025
 *   • Invoice 02-Apr-2024 (FY 2024-25) → CN deadline 30-Nov-2025
 *   • Invoice 31-Mar-2025 (FY 2024-25) → CN deadline 30-Nov-2025
 *   • Invoice 01-Apr-2025 (FY 2025-26) → CN deadline 30-Nov-2026
 */
class CreditNoteSection34Test extends TestCase
{
    use RefreshDatabase;

    public function test_deadline_is_30_november_of_next_fy_for_august_supply(): void
    {
        $invoice = Invoice::factory()->finalized()->create([
            'invoice_date' => '2024-08-15',  // FY 2024-25
        ]);
        $this->assertSame('2025-11-30', $invoice->creditNoteDeadline()->toDateString());
    }

    public function test_deadline_for_supply_in_first_quarter_of_fy(): void
    {
        $invoice = Invoice::factory()->finalized()->create([
            'invoice_date' => '2024-04-02',
        ]);
        $this->assertSame('2025-11-30', $invoice->creditNoteDeadline()->toDateString());
    }

    public function test_deadline_for_supply_on_last_day_of_fy(): void
    {
        $invoice = Invoice::factory()->finalized()->create([
            'invoice_date' => '2025-03-31',
        ]);
        $this->assertSame('2025-11-30', $invoice->creditNoteDeadline()->toDateString());
    }

    public function test_deadline_for_supply_on_first_day_of_new_fy(): void
    {
        $invoice = Invoice::factory()->finalized()->create([
            'invoice_date' => '2025-04-01',
        ]);
        $this->assertSame('2026-11-30', $invoice->creditNoteDeadline()->toDateString());
    }

    public function test_window_open_on_deadline_day(): void
    {
        $invoice = Invoice::factory()->finalized()->create(['invoice_date' => '2024-08-15']);
        Carbon::setTestNow('2025-11-30 12:00:00');
        $this->assertFalse($invoice->isCreditNoteWindowClosed());
        Carbon::setTestNow();
    }

    public function test_window_closed_one_day_after_deadline(): void
    {
        $invoice = Invoice::factory()->finalized()->create(['invoice_date' => '2024-08-15']);
        Carbon::setTestNow('2025-12-01 00:01:00');
        $this->assertTrue($invoice->isCreditNoteWindowClosed());
        Carbon::setTestNow();
    }

    public function test_create_route_blocks_when_window_closed(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create();
        $invoice = Invoice::factory()->recycle($user)->finalized()->create([
            'company_id' => $company->id,
            'invoice_date' => '2022-08-15',  // CN deadline was 30-Nov-2023; long past
        ]);

        $response = $this->actingAs($user)->get(route('credit-notes.create', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Section 34(2)', session('error'));
    }

    public function test_store_route_blocks_when_window_closed(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create();
        $invoice = Invoice::factory()->recycle($user)->finalized()->create([
            'company_id' => $company->id,
            'invoice_date' => '2022-08-15',
        ]);

        $response = $this->actingAs($user)->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'amount' => 100,
            'reason' => 'rate_correction',
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertDatabaseCount('credit_notes', 0);
    }

    public function test_create_route_allowed_when_window_still_open(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create();
        // Recent supply — window definitely open
        $invoice = Invoice::factory()->recycle($user)->finalized()->create([
            'company_id' => $company->id,
            'invoice_date' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($user)->get(route('credit-notes.create', $invoice))->assertOk();
    }
}
