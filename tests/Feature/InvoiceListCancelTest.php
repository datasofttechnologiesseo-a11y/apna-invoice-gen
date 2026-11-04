<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cancelling from the invoice list, and the GSTR-1 warning shown before
 * cancelling an invoice whose month's return was probably already filed.
 *
 * Each row offers the one correction GST allows: Delete for a draft, Cancel
 * for an issued invoice with no payment or credit note, Credit note once
 * money has moved. An issued invoice is never deleted.
 */
class InvoiceListCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 10, 0, 0, 'Asia/Kolkata'));
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create();
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function issued(array $attrs = []): Invoice
    {
        return Invoice::factory()->recycle($this->user)->finalized()->create(array_merge([
            'company_id' => $this->company->id,
            'invoice_date' => '2026-11-05',
            'grand_total' => 1180,
            'balance' => 1180,
            'paid_amount' => 0,
            'credited_amount' => 0,
        ], $attrs));
    }

    private function listHtml(): string
    {
        return $this->actingAs($this->user)->get(route('invoices.index'))->assertOk()->getContent();
    }

    public function test_an_unpaid_issued_invoice_can_be_cancelled_from_the_list(): void
    {
        $inv = $this->issued();

        $html = $this->listHtml();
        $this->assertStringContainsString('data-action="' . route('invoices.cancel', $inv) . '"', $html);
        $this->assertStringContainsString('id="cancel-invoice-modal"', $html);

        $this->actingAs($this->user)
            ->from(route('invoices.index', ['page' => 1]))
            ->post(route('invoices.cancel', $inv), ['cancellation_reason' => 'Wrong customer billed', 'return_to' => 'list'])
            ->assertRedirect(route('invoices.index', ['page' => 1]))
            ->assertSessionHas('status', fn ($s) => str_contains($s, $inv->invoice_number . ' cancelled'));

        $inv->refresh();
        $this->assertSame('cancelled', $inv->status);
        $this->assertSame('Wrong customer billed', $inv->cancellation_reason);
        $this->assertNotNull(Invoice::find($inv->id), 'a cancelled invoice stays on record');
    }

    public function test_a_short_reason_on_the_list_comes_back_to_the_list_with_the_dialog_reopened(): void
    {
        $inv = $this->issued();

        $this->actingAs($this->user)
            ->from(route('invoices.index'))
            ->post(route('invoices.cancel', $inv), ['cancellation_reason' => 'no', 'return_to' => 'list', 'cancel_invoice_id' => $inv->id])
            ->assertRedirect(route('invoices.index'))
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame('final', $inv->fresh()->status);

        // The next page load reopens the dialog for that row.
        $this->actingAs($this->user)
            ->withSession([
                '_old_input' => ['cancel_invoice_id' => (string) $inv->id],
                'errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag(['cancellation_reason' => ['Too short.']])),
            ])
            ->get(route('invoices.index'))->assertOk()
            ->assertSee('data-reopen="1"', false)
            ->assertSee('data-reopen-id="' . $inv->id . '"', false);
    }

    public function test_a_paid_invoice_offers_a_credit_note_not_cancel(): void
    {
        $inv = $this->issued(['paid_amount' => 1180, 'balance' => 0, 'status' => 'paid']);

        $html = $this->listHtml();
        $this->assertStringNotContainsString('data-action="' . route('invoices.cancel', $inv) . '"', $html);
        $this->assertStringContainsString(route('credit-notes.create', $inv), $html);

        // And the server refuses a cancel regardless of what the page shows.
        $this->actingAs($this->user)
            ->post(route('invoices.cancel', $inv), ['cancellation_reason' => 'Trying anyway', 'return_to' => 'list'])
            ->assertStatus(422);
    }

    public function test_drafts_offer_delete_and_cancelled_invoices_offer_neither(): void
    {
        $draft = Invoice::factory()->recycle($this->user)->create(['company_id' => $this->company->id, 'status' => 'draft']);
        $cancelled = $this->issued(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Duplicate bill']);

        $html = $this->listHtml();
        // The delete URL is the invoice URL (DELETE verb), so look for the confirm title.
        $this->assertStringContainsString("Delete draft #{$draft->id}?", $html);
        $this->assertStringNotContainsString("Delete draft #{$cancelled->id}?", $html);
        $this->assertStringNotContainsString(route('invoices.cancel', $cancelled), $html);
        $this->assertStringNotContainsString(route('credit-notes.create', $cancelled), $html);
    }

    public function test_list_totals_count_only_issued_invoices_as_billed_and_owed(): void
    {
        $this->issued(['grand_total' => 10000, 'balance' => 10000]);
        $this->issued(['grand_total' => 4000, 'balance' => 0, 'paid_amount' => 4000, 'status' => 'paid']);
        $this->issued(['grand_total' => 3000, 'balance' => 3000, 'status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Duplicate bill']);
        Invoice::factory()->recycle($this->user)->create(['company_id' => $this->company->id, 'status' => 'draft', 'grand_total' => 5000, 'balance' => 5000]);

        $text = fn (string $q = '') => preg_replace('/\s+/', ' ', strip_tags(
            $this->actingAs($this->user)->get(route('invoices.index') . $q)->assertOk()->getContent()));

        // All: 4 rows; billed 10,000 + 4,000; owed 10,000. The draft and the cancelled bill add nothing.
        $all = $text();
        $this->assertStringContainsString('4 invoices · Total ₹14,000 · To receive ₹10,000', $all);
        // Drafts: what they add up to, and nothing to receive.
        $drafts = $text('?status=draft');
        $this->assertStringContainsString('1 invoice · Total ₹5,000', $drafts);
        $this->assertStringNotContainsString('To receive', $drafts);
    }

    public function test_no_warning_before_the_months_gstr1_due_date(): void
    {
        // 5 Nov invoice, today 20 Nov: November's GSTR-1 is due 11 Dec.
        $this->assertNull($this->issued()->gstr1CancelWarning());

        // 31 Oct invoice on 11 Nov (the due date itself): still in time.
        Carbon::setTestNow(Carbon::create(2026, 11, 11, 18, 0, 0, 'Asia/Kolkata'));
        $this->assertNull($this->issued(['invoice_date' => '2026-10-31'])->gstr1CancelWarning());
    }

    public function test_warning_once_the_months_gstr1_due_date_has_passed(): void
    {
        $october = $this->issued(['invoice_date' => '2026-10-15']);

        $warning = $october->gstr1CancelWarning();
        $this->assertStringContainsString('GSTR-1 for October 2026 was due on 11 Nov 2026', $warning);
        $this->assertStringContainsString('issue a credit note instead (Section 34)', $warning);

        // Shown on the list row and on the invoice page, but cancelling still works.
        $this->assertStringContainsString('GSTR-1 for October 2026', $this->listHtml());
        $this->actingAs($this->user)->get(route('invoices.show', $october))
            ->assertOk()->assertSee('GSTR-1 for October 2026 was due on 11 Nov 2026');

        $this->actingAs($this->user)
            ->post(route('invoices.cancel', $october), ['cancellation_reason' => 'Not yet filed, billed twice'])
            ->assertRedirect(route('invoices.show', $october));
        $this->assertSame('cancelled', $october->fresh()->status);
    }

    public function test_drafts_never_carry_the_warning(): void
    {
        $draft = Invoice::factory()->recycle($this->user)->create([
            'company_id' => $this->company->id, 'status' => 'draft', 'invoice_date' => '2026-08-01',
        ]);

        $this->assertNull($draft->gstr1CancelWarning());
    }
}
