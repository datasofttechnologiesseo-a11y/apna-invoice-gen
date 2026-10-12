<?php

namespace Tests\Feature;

use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Services\BankStatementReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Bank reconciliation by statement upload: receipts, vendor payments and
 * expenses are found for the bank lines; overlapping statements don't
 * duplicate; nothing outside the books can be matched.
 */
class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-30 10:00'));
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $this->user->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'invoice_prefix' => 'INV', 'invoice_counter' => 0]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $customer = Customer::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $mh->id, 'name' => 'Patil Traders']);

        // Invoice 10,000 + 1,800; receipt 11,800 by NEFT on 10 Sep, UTR N123456789.
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => '2026-09-05',
            'items' => [['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18]],
        ]);
        $this->invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $this->invoice));
        $this->actingAs($this->user)->post(route('invoices.payments', $this->invoice), [
            'amount' => 11800, 'method' => 'neft', 'received_at' => '2026-09-10', 'reference_number' => 'N123456789',
        ])->assertSessionHasNoErrors();

        // Vendor bill 5,000 + 900 paid 5,900 on 12 Sep.
        $vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'Mumbai Metals', 'state_id' => $mh->id, 'gstin' => '27PQRST1234U1Z1']);
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'MM/1', 'bill_date' => '2026-09-11', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'paid_now' => 5900, 'paid_on' => '2026-09-12', 'payment_method' => 'neft',
            'items' => [['description' => 'Steel', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();

        // Rent 15,000 + 2,700 paid from the bank on 15 Sep.
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => '2026-09-15', 'category' => 'rent', 'description' => 'Office rent Sept', 'amount' => 15000, 'gst_amount' => 2700,
            'itc_eligible' => '1', 'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();
    }

    /** An HDFC-style export: account details on top, then the table. */
    private function hdfcXlsx(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bank') . '.xlsx';
        $w = new Writer();
        $w->openToFile($path);
        foreach ([
            ['HDFC BANK Ltd.'], ['Account No : 50200012345678'], ['Statement From : 01/09/2026 To : 30/09/2026'], [],
            ['Date', 'Narration', 'Chq./Ref.No.', 'Value Dt', 'Withdrawal Amt.', 'Deposit Amt.', 'Closing Balance'],
            ['********', '********', '********', '********', '********', '********', '********'],
            ['11/09/26', 'NEFT CR-HDFC0000123-PATIL TRADERS-N123456789', '0000N123456789', '11/09/26', '', '11,800.00', '1,11,800.00'],
            ['12/09/26', 'NEFT DR-MUMBAI METALS', '0000000000000077', '12/09/26', '5,900.00', '', '1,05,900.00'],
            ['15/09/26', 'IMPS-RENT-SHARMA PROPERTIES', '0000000000000088', '15/09/26', '17,700.00', '', '88,200.00'],
            ['20/09/26', 'CHRG: SMS ALERT CHARGES', '', '20/09/26', '17.70', '', '88,182.30'],
            ['25/09/26', 'UPI-UNKNOWN PAYER', '6123', '25/09/26', '', '2,500.00', '90,682.30'],
            [], ['STATEMENT SUMMARY :-'], ['Opening Balance', 'Dr Count', 'Cr Count'],
        ] as $cells) {
            $w->addRow(Row::fromValues($cells));
        }
        $w->close();

        return new UploadedFile($path, 'Acct_Statement.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_the_statement_is_read_and_obvious_lines_match_themselves(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame(5, BankStatementLine::count());
        $receipt = BankStatementLine::where('credit', 11800)->firstOrFail();
        $this->assertSame('2026-09-11', $receipt->txn_date->toDateString());
        $this->assertSame('matched', $receipt->status);
        $this->assertInstanceOf(Payment::class, $receipt->matched);

        $this->assertSame('matched', BankStatementLine::where('debit', 5900)->firstOrFail()->status, 'vendor payment');
        $rent = BankStatementLine::where('debit', 17700)->firstOrFail();
        $this->assertSame('matched', $rent->status);
        $this->assertInstanceOf(Expense::class, $rent->matched, 'rent + GST paid from the bank');

        $this->assertSame('unmatched', BankStatementLine::where('debit', 17.70)->firstOrFail()->status);
        $this->assertSame('unmatched', BankStatementLine::where('credit', 2500)->firstOrFail()->status);
    }

    public function test_uploading_an_overlapping_statement_does_not_duplicate(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Nothing new in this file. All 5 lines were uploaded before.'));
        $this->assertSame(5, BankStatementLine::count());
        $this->assertSame(1, \App\Models\BankStatement::count(), 'no empty statement left behind');
    }

    public function test_a_single_amount_column_with_dr_cr_is_understood(): void
    {
        $lines = app(BankStatementReader::class)->parse([
            ['Kotak Mahindra Bank'], [],
            ['Sl. No.', 'Transaction Date', 'Value Date', 'Description', 'Chq / Ref No.', 'Amount', 'Dr / Cr', 'Balance', 'Dr / Cr'],
            ['1', '11-09-2026', '11-09-2026', 'NEFT PATIL TRADERS', 'N123456789', '11,800.00', 'CR', '1,11,800.00', 'CR'],
            ['2', '12-09-2026', '12-09-2026', 'TO MUMBAI METALS', '77', '5,900.00', 'DR', '1,05,900.00', 'CR'],
        ]);
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta(11800.0, $lines[0]['credit'], 0.001);
        $this->assertEqualsWithDelta(0.0, $lines[0]['debit'], 0.001);
        $this->assertEqualsWithDelta(5900.0, $lines[1]['debit'], 0.001);
        $this->assertSame('2026-09-12', $lines[1]['date']);
    }

    public function test_manual_match_ignore_and_undo(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);
        $charge = BankStatementLine::where('debit', 17.70)->firstOrFail();
        $this->actingAs($this->user)->post(route('bank.lines.ignore', $charge))->assertRedirect();
        $this->assertSame('ignored', $charge->fresh()->status);

        $receipt = BankStatementLine::where('credit', 11800)->firstOrFail();
        $this->actingAs($this->user)->post(route('bank.lines.unmatch', $receipt));
        $this->assertSame('unmatched', $receipt->fresh()->status);

        // Only something offered for this line can be matched: not a payment of another amount.
        $this->actingAs($this->user)->post(route('bank.lines.match', $receipt), ['candidate' => (new Payment)->getMorphClass() . '#999999'])->assertStatus(422);

        $payment = Payment::firstOrFail();
        $this->actingAs($this->user)->post(route('bank.lines.match', $receipt), ['candidate' => $payment->getMorphClass() . '#' . $payment->id])->assertRedirect();
        $this->assertSame('matched', $receipt->fresh()->status);

        $this->actingAs($this->user)->get(route('bank.index', ['status' => 'all']))->assertOk()
            ->assertSee('Receipt ' . $payment->receipt_number)->assertSee('Ignored');
    }

    public function test_unmatched_lines_offer_the_next_step(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);
        $this->actingAs($this->user)->get(route('bank.index'))->assertOk()
            ->assertSee('+ Add as expense')
            ->assertSee('Find the invoice and record the receipt');

        // The expense form opens filled in from the bank line.
        $this->actingAs($this->user)->get(route('finance.expenses.create', ['amount' => 17.70, 'entry_date' => '2026-09-20', 'description' => 'CHRG: SMS ALERT CHARGES']))
            ->assertOk()->assertSee('17.7')->assertSee('CHRG: SMS ALERT CHARGES');
    }

    public function test_another_business_cannot_touch_these_lines(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);
        $line = BankStatementLine::firstOrFail();
        $stranger = User::factory()->create();
        $other = Company::factory()->create(['user_id' => $stranger->id]);
        $stranger->forceFill(['active_company_id' => $other->id])->save();

        $this->actingAs($stranger)->post(route('bank.lines.ignore', $line))->assertForbidden();
        $this->actingAs($stranger)->get(route('bank.index'))->assertOk()->assertDontSee('PATIL TRADERS');
    }

    public function test_dates_written_any_way_banks_write_them_are_read(): void
    {
        $reader = app(BankStatementReader::class);
        $lines = $reader->parse([
            ['Date', 'Narration', 'Debit', 'Credit'],
            ['05/10/2026', 'A', '10', ''], ['5/10/2026', 'B', '10', ''], ['05-OCT-2026', 'C', '10', ''],
            ['5-Oct-2026', 'D', '10', ''], ['11/09/26', 'E', '10', ''], ['31/02/2026', 'F', '10', ''],
        ]);
        $this->assertSame(['2026-10-05', '2026-10-05', '2026-10-05', '2026-10-05', '2026-09-11'], array_column($lines, 'date'));
        $this->assertSame(1, $reader->unreadable, '31/02 is not a date: reported, not guessed');
    }

    public function test_two_identical_payments_in_one_statement_are_both_kept_and_re_upload_skips_them(): void
    {
        $csv = function () {
            $path = tempnam(sys_get_temp_dir(), 'bank') . '.csv';
            file_put_contents($path, "Date,Narration,Debit,Credit
05/09/2026,UPI-TEA STALL,20.00,
05/09/2026,UPI-TEA STALL,20.00,
");

            return new UploadedFile($path, 'stmt.csv', 'text/csv', null, true);
        };
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'SBI', 'file' => $csv()])->assertSessionHasNoErrors();
        $this->assertSame(2, BankStatementLine::where('debit', 20)->count());
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'SBI', 'file' => $csv()]);
        $this->assertSame(2, BankStatementLine::where('debit', 20)->count());
    }

    public function test_a_match_is_released_when_the_receipt_is_deleted(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);
        $line = BankStatementLine::where('credit', 11800)->firstOrFail();
        $this->assertSame('matched', $line->status);

        Payment::firstOrFail()->delete();
        $this->actingAs($this->user)->get(route('bank.index'))->assertOk();
        $this->assertSame('unmatched', $line->fresh()->status);
    }

    public function test_two_payments_to_one_vendor_are_told_apart_by_date_and_amount(): void
    {
        // A second 5,900 payment to the same vendor a day later: both are
        // possible matches for the 12 Sep line, so it is left to the user.
        $bill = PurchaseBill::firstOrFail();
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $bill->vendor_id, 'bill_number' => 'MM/2', 'bill_date' => '2026-09-11', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'paid_now' => 5900, 'paid_on' => '2026-09-13', 'payment_method' => 'neft',
            'items' => [['description' => 'Steel', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);
        $this->assertSame('unmatched', BankStatementLine::where('debit', 5900)->firstOrFail()->status);

        $this->actingAs($this->user)->get(route('bank.index'))->assertOk()
            ->assertSee('Purchase invoice MM/1 · 12 Sep 2026 · ₹5,900')
            ->assertSee('Purchase invoice MM/2 · 13 Sep 2026 · ₹5,900');
    }

    public function test_matched_lines_name_the_party_and_the_document_and_show_paise(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);

        $this->actingAs($this->user)->get(route('bank.index', ['status' => 'all']))->assertOk()
            ->assertSee('Payment made · Mumbai Metals')
            ->assertSee('Purchase invoice MM/1')
            ->assertSee('Patil Traders')
            ->assertSee('Invoice ' . $this->invoice->fresh()->invoice_number)
            // Matching is to the paisa, so the screen shows paise.
            ->assertSee('−₹17.70')
            // Matched and still-to-review totals.
            ->assertSee('Matched · 3 lines')
            ->assertSee('To review · 2 lines');
    }

    public function test_add_as_expense_saves_it_matches_the_line_and_stays_on_the_bank_screen(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);
        $charge = BankStatementLine::where('debit', 17.70)->firstOrFail();

        // A bank-charge line offers the bank charges category.
        $this->actingAs($this->user)->get(route('bank.index'))->assertOk()
            ->assertSee('value="bank_charges" selected', false);

        $this->actingAs($this->user)->from(route('bank.index'))->post(route('bank.lines.match', $charge), [
            'candidate' => 'new-expense', 'line_id' => $charge->id,
            'category' => 'bank_charges', 'description' => 'CHRG: SMS ALERT CHARGES', 'vendor_name' => 'HDFC Bank',
        ])->assertRedirect(route('bank.index'))->assertSessionHas('status', 'Expense of ₹17.70 added and matched.');

        $expense = Expense::where('category', 'bank_charges')->firstOrFail();
        $this->assertEqualsWithDelta(17.70, (float) $expense->amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $expense->gst_amount, 0.001);
        $this->assertSame('2026-09-20', $expense->entry_date->toDateString());
        $this->assertSame('bank', $expense->payment_method);
        $charge->refresh();
        $this->assertSame('matched', $charge->status);
        $this->assertTrue($charge->matched->is($expense));
    }

    public function test_add_as_expense_checks_its_fields_the_line_and_the_books_lock(): void
    {
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC Current', 'file' => $this->hdfcXlsx()]);
        $charge = BankStatementLine::where('debit', 17.70)->firstOrFail();
        $before = Expense::count();

        $this->actingAs($this->user)->from(route('bank.index'))->post(route('bank.lines.match', $charge), [
            'candidate' => 'new-expense', 'line_id' => $charge->id, 'category' => 'bank_charges', 'description' => '',
        ])->assertSessionHasErrorsIn('expense', ['description']);

        // Money in is never an expense.
        $credit = BankStatementLine::where('credit', 2500)->firstOrFail();
        $this->actingAs($this->user)->from(route('bank.index'))->post(route('bank.lines.match', $credit), [
            'candidate' => 'new-expense', 'category' => 'misc', 'description' => 'x',
        ])->assertSessionHas('error');

        $this->company->forceFill(['books_locked_until' => '2026-09-30'])->save();
        $this->actingAs($this->user)->from(route('bank.index'))->post(route('bank.lines.match', $charge), [
            'candidate' => 'new-expense', 'line_id' => $charge->id, 'category' => 'bank_charges', 'description' => 'SMS charges',
        ])->assertSessionHasErrorsIn('expense', ['description']);

        $this->assertSame($before, Expense::count());
        $this->assertSame('unmatched', $charge->fresh()->status);
    }

    public function test_a_bad_file_gets_a_plain_message(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bank') . '.csv';
        file_put_contents($path, "Hello,World\n1,2\n");
        $this->actingAs($this->user)->post(route('bank.upload'), ['account_name' => 'HDFC', 'file' => new UploadedFile($path, 'x.csv', 'text/csv', null, true)])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, BankStatementLine::count());
    }
}
