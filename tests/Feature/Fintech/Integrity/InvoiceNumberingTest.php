<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CGST Rule 46(b): a tax invoice carries "a consecutive serial number not
 * exceeding sixteen characters ... unique for a financial year". These probes
 * drive the real routes and check that drafts never consume the series, that
 * issued and cancelled numbers are never reused, that the FY boundary is read
 * in IST from the invoice date, and that the database itself refuses a
 * duplicate if the application logic ever slips.
 */
class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();
    }

    private function useFyFormat(): void
    {
        // "INV/2026-27/0001" = 3 + 1 + 7 + 1 + 4 = 16 characters: the longest Rule 46(b) allows.
        $this->company->forceFill(['invoice_number_format' => 'INV/{FY}/{N}'])->save();
    }

    public function test_drafts_take_no_number_and_the_order_of_issue_decides_the_number(): void
    {
        $a = $this->draft(['invoice_date' => '2026-10-01']);
        $b = $this->draft(['invoice_date' => '2026-10-02']);
        $c = $this->draft(['invoice_date' => '2026-10-03']);

        foreach ([$a, $b, $c] as $d) {
            $this->assertNull($d->invoice_number, 'a draft has no number');
        }
        $this->assertSame(0, (int) $this->company->fresh()->invoice_counter, 'saving drafts never moves the counter');

        // Issued B, then A, then C: numbers follow the order of issue, gap-free.
        $this->assertSame('INV-0001', $this->issue($b)->invoice_number);
        $this->assertSame('INV-0002', $this->issue($a)->invoice_number);
        $this->assertSame('INV-0003', $this->issue($c)->invoice_number);
        $this->assertSame(3, (int) $this->company->fresh()->invoice_counter);
    }

    public function test_a_deleted_draft_never_burns_a_number(): void
    {
        $this->assertSame('INV-0001', $this->issued()->invoice_number);

        $doomed = $this->draft();
        $this->actingAs($this->user)->delete(route('invoices.destroy', $doomed))->assertRedirect(route('invoices.index'));
        $this->assertNull(Invoice::find($doomed->id));

        // A draft deleted over the app API burns nothing either.
        $doomedToo = $this->draft();
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/invoices/{$doomedToo->id}")->assertOk();

        $this->assertSame('INV-0002', $this->issued()->invoice_number, 'the series continues 0001 -> 0002 with no gap');
        $this->assertSame(2, (int) $this->company->fresh()->invoice_counter);
    }

    public function test_a_cancelled_invoice_keeps_its_number_and_the_series_never_reuses_it(): void
    {
        $first = $this->issued();
        $this->cancel($first)->assertRedirect();

        $first->refresh();
        $this->assertSame('cancelled', $first->status);
        $this->assertSame('INV-0001', $first->invoice_number, 'the cancelled invoice keeps its number (GSTR-1 Table 13 reports it as cancelled)');

        $this->assertSame('INV-0002', $this->issued()->invoice_number, 'INV-0001 is never handed out again');
        $this->assertSame(1, Invoice::where('invoice_number', 'INV-0001')->count());
    }

    public function test_an_fy_series_restarts_at_midnight_ist_on_1_april(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'), 'the FY boundary is midnight in India, so the app must run on IST');
        $this->useFyFormat();

        // 31 Mar 2026 23:59 IST is still FY 2025-26.
        $this->travelTo(Carbon::create(2026, 3, 31, 23, 59, 0, 'Asia/Kolkata'));
        $this->assertSame('2026-03-31', now()->toDateString());
        $this->assertSame('INV/2025-26/0001', $this->issued()->invoice_number);
        $this->assertSame('INV/2025-26/0002', $this->issued()->invoice_number);
        $lateMarchDraft = $this->draft(); // saved on 31 Mar, issued after midnight

        // Two minutes later it is 1 Apr 2026, FY 2026-27: the {FY} series restarts at 0001.
        $this->travelTo(Carbon::create(2026, 4, 1, 0, 1, 0, 'Asia/Kolkata'));
        $this->assertSame('2026-04-01', now()->toDateString());
        $april = $this->issued();
        $this->assertSame('INV/2026-27/0001', $april->invoice_number);
        $this->assertLessThanOrEqual(16, strlen($april->invoice_number));

        // The invoice DATE decides the year: the 31 Mar draft issued on 1 Apr
        // continues FY 2025-26, and the new year's series is not disturbed.
        $this->assertSame('INV/2025-26/0003', $this->issue($lateMarchDraft)->invoice_number);
        $this->assertSame('INV/2026-27/0002', $this->issued()->invoice_number);
    }

    public function test_a_plain_prefix_series_carries_on_across_the_fy_boundary_without_reusing_a_number(): void
    {
        // No {FY} token: "INV-0001" in April would be a second INV-0001 in the
        // same register, so the plain series must carry on instead.
        $this->travelTo(Carbon::create(2026, 3, 31, 23, 59, 0, 'Asia/Kolkata'));
        $this->assertSame('INV-0001', $this->issued()->invoice_number);
        $this->assertSame('INV-0002', $this->issued()->invoice_number);

        $this->travelTo(Carbon::create(2026, 4, 1, 0, 1, 0, 'Asia/Kolkata'));
        $this->assertSame('INV-0003', $this->issued()->invoice_number);
        $this->assertSame('INV-0004', $this->issued()->invoice_number);
        $this->assertSame(4, Invoice::whereNotNull('invoice_number')->distinct()->count('invoice_number'));
    }

    public function test_backdating_into_the_previous_fy_continues_that_years_series_without_a_gap(): void
    {
        $this->useFyFormat();

        // FY 2025-26 closed with two invoices: INV/2025-26/0001 and 0002.
        $this->travelTo(Carbon::create(2026, 3, 20, 11, 0, 0, 'Asia/Kolkata'));
        $this->issued();
        $this->assertSame('INV/2025-26/0002', $this->issued()->invoice_number);

        // FY 2026-27 is at 0004 by October.
        $this->freezeToday();
        foreach (range(1, 4) as $n) {
            $this->assertSame('INV/2026-27/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT), $this->issued()->invoice_number);
        }

        // A sale of 25 Mar 2026 billed late (books not locked). Rule 46(b):
        // consecutive within its FY -> INV/2025-26/0003. Anything higher
        // leaves a hole in last year's series that GSTR-1 Table 13 shows.
        $late = $this->issued(['invoice_date' => '2026-03-25']);
        $this->assertSame('INV/2025-26/0003', $late->invoice_number, 'the backdated invoice must take the next number of FY 2025-26, not jump ahead with this year\'s counter');

        // And this year's series carries on from 0004.
        $this->assertSame('INV/2026-27/0005', $this->issued()->invoice_number);
    }

    public function test_a_next_year_dated_draft_never_disturbs_this_years_series(): void
    {
        $this->useFyFormat();
        foreach (range(1, 3) as $n) {
            $this->issued();
        }

        // Whatever the app decides for a draft dated in the NEXT financial year
        // (refuse it, or number it in FY 2027-28), this year's series must
        // carry on at 0004 and next year's must start consecutively.
        $future = $this->draft(['invoice_date' => '2027-04-05']);
        $this->actingAs($this->user)->post(route('invoices.finalize', $future));

        $this->assertSame('INV/2026-27/0004', $this->issued()->invoice_number);

        $this->travelTo(Carbon::create(2027, 4, 5, 10, 0, 0, 'Asia/Kolkata'));
        $alreadyThere = Invoice::where('invoice_number', 'like', 'INV/2027-28/%')->count();
        $this->assertSame('INV/2027-28/' . str_pad((string) ($alreadyThere + 1), 4, '0', STR_PAD_LEFT), $this->issued()->invoice_number);
    }

    public function test_a_numbering_format_longer_than_16_characters_is_refused_in_settings(): void
    {
        // "SHARMA/TRADERS/2026-27/0001" = 6+1+7+1+7+1+4 = 27 characters > 16.
        $this->actingAs($this->user)->patch(route('companies.update', $this->company), $this->companyPayload([
            'invoice_number_format' => 'SHARMA/TRADERS/{FY}/{N}',
        ]))->assertSessionHasErrors('invoice_number_format');

        $this->assertNull($this->company->fresh()->invoice_number_format);
    }

    public function test_a_numbering_format_longer_than_16_characters_is_refused_over_the_app_api(): void
    {
        Sanctum::actingAs($this->user);
        $payload = $this->companyPayload(['invoice_number_format' => 'SHARMA/TRADERS/{FY}/{N}']);
        unset($payload['gstin']); // leave the GSTIN out so only the numbering is judged

        $this->putJson("/api/companies/{$this->company->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice_number_format');

        $this->assertNull($this->company->fresh()->invoice_number_format, 'a 27-character series would print invoice numbers GSTR-1 rejects');
    }

    public function test_two_businesses_keep_independent_series_even_with_the_same_prefix(): void
    {
        $b = Company::factory()->create([
            'user_id' => $this->user->id, 'name' => 'Sharma Hardware', 'state_id' => $this->mh->id,
            'invoice_prefix' => 'INV', 'invoice_counter' => 0, 'invoice_number_padding' => 4,
        ]);
        $customerB = $this->customerFor($b, ['name' => 'Kulkarni & Sons']);

        $a1 = $this->draft();
        $a2 = $this->draft();
        $this->assertSame('INV-0001', $this->issue($a1)->invoice_number);

        $this->actingAs($this->user)->post(route('companies.switch', $b))->assertRedirect();
        $b1 = $this->draft(['customer_id' => $customerB->id]);
        $this->assertSame($b->id, $b1->company_id);
        $this->assertSame('INV-0001', $this->issue($b1)->invoice_number, 'business B starts its own series');

        // Issuing A's draft while B is the active business uses A's series.
        $this->assertSame('INV-0002', $this->issue($a2)->invoice_number);
        $this->assertSame('INV-0002', $this->issue($this->draft(['customer_id' => $customerB->id]))->invoice_number);

        $this->assertSame(2, (int) $this->company->fresh()->invoice_counter);
        $this->assertSame(2, (int) $b->fresh()->invoice_counter);
    }

    public function test_the_database_refuses_a_second_invoice_with_the_same_number_in_one_business(): void
    {
        $this->issued(); // INV-0001
        $other = Company::factory()->create(['user_id' => $this->user->id, 'state_id' => $this->mh->id]);
        $otherCustomer = $this->customerFor($other);

        // The same number in ANOTHER business is fine.
        Invoice::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $other->id, 'customer_id' => $otherCustomer->id,
            'invoice_number' => 'INV-0001', 'status' => 'final', 'finalized_at' => now(),
        ]);

        // A second INV-0001 in this business is refused by the unique index itself.
        try {
            Invoice::factory()->create([
                'user_id' => $this->user->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
                'invoice_number' => 'INV-0001', 'status' => 'final', 'finalized_at' => now(),
            ]);
            $this->fail('the database accepted a duplicate invoice number in one business');
        } catch (QueryException) {
            $this->assertSame(1, Invoice::where('company_id', $this->company->id)->where('invoice_number', 'INV-0001')->count());
        }
    }

    public function test_a_counter_left_behind_by_a_racing_request_cannot_hand_out_a_number_twice(): void
    {
        foreach (range(1, 3) as $n) {
            $this->issued();
        }

        // Simulate the lost update of a race: another request read the counter
        // before our save and wrote back an older value.
        Company::whereKey($this->company->id)->update(['invoice_counter' => 1]);

        $next = $this->issued();
        $this->assertSame('INV-0004', $next->invoice_number, 'the issue must skip 0002 and 0003, which are taken');
        $this->assertSame(4, (int) $this->company->fresh()->invoice_counter);

        // The model method alone is not race-proof: a stale copy of the company
        // still hands out a number that is taken. The unique index is what
        // stops it reaching the register.
        $stale = Company::find($this->company->id);
        $this->issued(); // INV-0005 through the real route
        $dup = $stale->bumpCounterForFinalize(now()->toDateString());
        $this->assertSame('INV-0005', $dup);
        $draft = $this->draft();
        try {
            $draft->forceFill(['invoice_number' => $dup, 'status' => 'final', 'finalized_at' => now()])->save();
            $this->fail('a duplicate number reached the invoices table');
        } catch (QueryException) {
            $this->assertSame(1, Invoice::where('invoice_number', 'INV-0005')->count());
        }
    }

    public function test_issuing_reads_the_counter_under_a_row_lock_on_the_company(): void
    {
        $draft = $this->draft();
        $baseline = DB::transactionLevel();
        $log = [];
        DB::listen(function (QueryExecuted $e) use (&$log) {
            $log[] = ['sql' => strtolower($e->sql), 'level' => $e->connection->transactionLevel()];
        });

        $this->issue($draft);

        $inner = array_values(array_filter($log, fn ($q) => $q['level'] > $baseline));
        $lock = null;
        $bump = null;
        foreach ($inner as $i => $q) {
            if ($lock === null && str_starts_with($q['sql'], 'select') && preg_match('/from [`"]?companies[`"]?/', $q['sql'])) {
                $lock = $i;
            }
            if ($bump === null && str_starts_with($q['sql'], 'update') && str_contains($q['sql'], 'companies') && str_contains($q['sql'], 'invoice_counter')) {
                $bump = $i;
            }
        }
        $this->assertNotNull($lock, 'the company row is read inside the issue transaction');
        $this->assertNotNull($bump, 'the counter is written inside the same transaction');
        $this->assertLessThan($bump, $lock, 'the company is read (locked) before the counter is written');
        if (DB::getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', $inner[$lock]['sql'], 'on MySQL the read must be SELECT ... FOR UPDATE');
        }
    }

    private function companyPayload(array $over = []): array
    {
        return array_merge([
            'name' => $this->company->name,
            'gstin' => $this->company->gstin,
            'state_id' => $this->company->state_id,
            'address_line1' => '12 MG Road',
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'INV',
            'invoice_number_padding' => 4,
        ], $over);
    }
}
