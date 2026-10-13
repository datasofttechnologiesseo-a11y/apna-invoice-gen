<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Bank reconciliation, end to end, with an HDFC-style CSV statement for
 * 3-9 October 2026 against the books of Sharma Traders (MH):
 *
 *   Books
 *     INV A  10,000 + 18% = 11,800, receipt 03 Oct NEFT, ref UTR778899
 *     INV B   5,000 + 18% =  5,900, receipt 06 Oct UPI
 *     INV C   5,000 + 18% =  5,900, receipt 07 Oct UPI
 *     Bill MM/55 from Mumbai Metals 20,000 + 18% = 23,600 (paid 04 Oct, entered late)
 *
 *   Statement
 *     03/10  NEFT CR ... UTR778899       credit 11,800  -> receipt A (only candidate, ref in narration)
 *     04/10  NEFT DR-MUMBAI METALS 000077 debit 23,600  -> nothing until the payment is entered
 *     07/10  UPI CR-PATIL STORES          credit  5,900 -> B or C: two candidates, left to the user
 *     09/10  POS ... AMAZON PAY           debit  1,250  -> nothing: added as an expense from the line
 */
class BankJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;
    private Customer $customer;

    private const CSV = "Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.,Closing Balance\n"
        . "03/10/2026,NEFT CR-HDFC0000123-PATIL STORES-UTR778899,UTR778899,,11800.00,111800.00\n"
        . "04/10/2026,NEFT DR-MUMBAI METALS,000077,23600.00,,88200.00\n"
        . "07/10/2026,UPI CR-PATIL STORES,612345,,5900.00,94100.00\n"
        . "09/10/2026,POS 4521XXXX AMAZON PAY,4521,1250.00,,92850.00\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        $this->biz = $this->makeBusiness();
        $this->owner = $this->ownerOf($this->biz);
        $this->customer = $this->customerOf($this->biz);
    }

    private function statement(string $content = self::CSV, string $name = 'Acct_Statement_Oct.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function upload(User $who, UploadedFile $file, string $account = 'HDFC Current')
    {
        return $this->actingAs($who)->from(route('bank.index'))->post(route('bank.upload'), ['account_name' => $account, 'file' => $file]);
    }

    /** INV A, B, C issued and paid; bill MM/55 recorded, unpaid. */
    private function books(): array
    {
        // A: 10,000 @18% intra = CGST 900 + SGST 900 = 11,800.
        $a = $this->webIssued($this->owner, $this->customer, [$this->sheetLine(10000)], ['invoice_date' => '2026-10-01']);
        $this->webPay($this->owner, $a, 11800, ['method' => 'neft', 'received_at' => '2026-10-03', 'reference_number' => 'UTR778899'])->assertSessionHasNoErrors();
        // B and C: 5,000 @18% = 5,900 each.
        $b = $this->webIssued($this->owner, $this->customer, [$this->sheetLine(5000)], ['invoice_date' => '2026-10-02']);
        $this->webPay($this->owner, $b, 5900, ['received_at' => '2026-10-06'])->assertSessionHasNoErrors();
        $c = $this->webIssued($this->owner, $this->customer, [$this->sheetLine(5000)], ['invoice_date' => '2026-10-02']);
        $this->webPay($this->owner, $c, 5900, ['received_at' => '2026-10-07'])->assertSessionHasNoErrors();
        foreach ([$a, $b, $c] as $inv) {
            $this->assertSame('paid', $inv->fresh()->status);
        }

        $vendor = $this->biz->vendors()->create(['user_id' => $this->owner->id, 'name' => 'Mumbai Metals', 'gstin' => '27PQRST5678K1Z2', 'state_id' => $this->biz->state_id]);
        // 20,000 @18% intra = CGST 1,800 + SGST 1,800 = 23,600.
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'MM/55', 'bill_date' => '2026-10-02', 'itc_eligible' => 1,
            'items' => [['description' => 'MS sheets', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 20000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $bill = PurchaseBill::where('bill_number', 'MM/55')->firstOrFail();
        $this->assertMoney(23600, $bill->grand_total);

        return [$a, $b, $c, $bill];
    }

    private function sheetLine(float $rate = 1000, float $gst = 18, float $qty = 1, array $extra = []): array
    {
        return array_merge(['description' => 'Steel sheets', 'hsn_sac' => '72085100', 'quantity' => $qty, 'unit' => 'NOS', 'rate' => $rate, 'gst_rate' => $gst], $extra);
    }

    private function bankLine(float $debit, float $credit): BankStatementLine
    {
        return BankStatementLine::where('debit', $debit)->where('credit', $credit)->latest('id')->firstOrFail();
    }

    /** What reconciliation must never touch: the documents and their money. */
    private function booksSnapshot(): array
    {
        return [
            'payments' => [Payment::count(), round((float) Payment::sum('amount'), 2)],
            'vendor_payments' => [PurchasePayment::count(), round((float) PurchasePayment::sum('amount'), 2)],
            'expenses' => [Expense::count(), round((float) Expense::sum('amount'), 2), round((float) Expense::sum('gst_amount'), 2)],
            'invoices' => Invoice::orderBy('id')->get(['id', 'status', 'paid_amount', 'balance'])
                ->map(fn ($i) => [$i->id, $i->status, round((float) $i->paid_amount, 2), round((float) $i->balance, 2)])->all(),
            'bills' => PurchaseBill::orderBy('id')->get(['id', 'status', 'balance'])->map(fn ($b) => [$b->id, $b->status, round((float) $b->balance, 2)])->all(),
        ];
    }

    // ------------------------------------------------------------------

    public function test_upload_auto_match_manual_match_add_expense_undo_and_remove_the_statement(): void
    {
        [$a, $b, $c, $bill] = $this->books();
        $payA = Payment::where('invoice_id', $a->id)->firstOrFail();
        $payC = Payment::where('invoice_id', $c->id)->firstOrFail();

        // 1. Upload: 4 lines read, the receipt with its UTR matches itself.
        $this->upload($this->owner, $this->statement())
            ->assertRedirect(route('bank.index', ['account' => 'HDFC Current']))
            ->assertSessionHas('status', '4 new lines read. 1 matched automatically. 3 lines to review below.');
        $statement = BankStatement::firstOrFail();
        $this->assertSame(4, (int) $statement->lines_count);
        $this->assertSame('2026-10-03', $statement->period_from->toDateString());
        $this->assertSame('2026-10-09', $statement->period_to->toDateString());

        $lineA = $this->bankLine(0, 11800);
        $this->assertSame('matched', $lineA->status);
        $this->assertTrue($lineA->matched->is($payA));
        $lineVendor = $this->bankLine(23600, 0);
        $lineBC = $this->bankLine(0, 5900);
        $lineAmazon = $this->bankLine(1250, 0);
        $this->assertSame('unmatched', $lineVendor->status, 'the vendor payment is not in the books yet');
        $this->assertSame('unmatched', $lineBC->status, 'two receipts of 5,900: not guessed');
        $this->assertSame('unmatched', $lineAmazon->status);
        $this->assertSame('2026-10-09', $lineAmazon->txn_date->toDateString(), 'dd/mm/yyyy read day first');

        // 2. The forgotten vendor payment is entered, then "Match again" finds it.
        $this->actingAs($this->owner)->from(route('purchases.show', $bill))->post(route('purchases.payments.store', $bill), [
            'amount' => 23600, 'method' => 'neft', 'paid_on' => '2026-10-04', 'reference_number' => '000077',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->from(route('bank.index'))->post(route('bank.auto-match'))
            ->assertRedirect(route('bank.index'))
            ->assertSessionHas('status', fn ($s) => str_starts_with((string) $s, '1 more line'));
        $lineVendor->refresh();
        $this->assertSame('matched', $lineVendor->status);
        $this->assertTrue($lineVendor->matched->is(PurchasePayment::firstOrFail()));

        // 3. The 5,900: the user picks receipt C (7 Oct, same day as the bank).
        //    A receipt already matched elsewhere (A's 11,800) is not on offer.
        $this->actingAs($this->owner)->from(route('bank.index'))->post(route('bank.lines.match', $lineBC), [
            'candidate' => $payA->getMorphClass() . '#' . $payA->id,
        ])->assertStatus(422);
        $this->actingAs($this->owner)->from(route('bank.index'))->post(route('bank.lines.match', $lineBC), [
            'candidate' => $payC->getMorphClass() . '#' . $payC->id,
        ])->assertRedirect(route('bank.index'))->assertSessionHas('status', 'Matched.');
        $this->assertTrue($lineBC->fresh()->matched->is($payC));

        // 4. The Amazon debit becomes an expense from the line: its date, its exact amount, paid from the bank.
        $this->actingAs($this->owner)->from(route('bank.index'))->post(route('bank.lines.match', $lineAmazon), [
            'candidate' => 'new-expense', 'category' => 'misc', 'description' => 'Printer cartridges', 'vendor_name' => 'Amazon',
        ])->assertRedirect(route('bank.index'))->assertSessionHas('status', 'Expense of ₹1,250 added and matched.');
        $expense = Expense::whereNull('purchase_bill_id')->firstOrFail();
        $this->assertMoney(1250, $expense->amount);
        $this->assertMoney(0, $expense->gst_amount);
        $this->assertSame('2026-10-09', $expense->entry_date->toDateString());
        $this->assertSame('bank', $expense->payment_method);
        $this->assertSame('Amazon', $expense->vendor_name);
        $this->assertSame('4521', $expense->reference_number);
        $this->assertTrue($lineAmazon->fresh()->matched->is($expense));

        // 5. Undo the 5,900 match: the line is back to review, the receipt untouched.
        $this->actingAs($this->owner)->from(route('bank.index'))->post(route('bank.lines.unmatch', $lineBC))
            ->assertRedirect(route('bank.index'))->assertSessionHas('status', 'Match removed. The line is back in the list to review.');
        $lineBC->refresh();
        $this->assertSame('unmatched', $lineBC->status);
        $this->assertNull($lineBC->matched_id);
        $this->assertMoney(5900, $payC->fresh()->amount);
        $this->assertSame('paid', $c->fresh()->status);

        $this->actingAs($this->owner)->get(route('bank.index', ['status' => 'all']))->assertOk()
            ->assertSee('Matched · 3 lines')->assertSee('To review · 1 line')
            ->assertSee('Receipt ' . $payA->receipt_number)->assertSee('Payment made · Mumbai Metals');

        // 6. Remove the statement. Lines go; receipts, the vendor payment, the
        //    expense and every balance stay exactly as they were.
        // Payments 11,800 + 5,900 + 5,900 = 23,600; vendor 23,600; expenses: the bill's 20,000 (+3,600 GST) and 1,250.
        $before = $this->booksSnapshot();
        $this->assertSame([3, 23600.0], $before['payments']);
        $this->assertSame([1, 23600.0], $before['vendor_payments']);
        $this->assertSame([2, 21250.0, 3600.0], $before['expenses']);

        $this->actingAs($this->owner)->from(route('bank.index'))->delete(route('bank.statements.destroy', $statement))
            ->assertRedirect(route('bank.index'))->assertSessionHas('status', 'Statement removed. Receipts and payments are not affected.');
        $this->assertSame(0, BankStatement::count());
        $this->assertSame(0, BankStatementLine::count(), 'its lines are deleted with it');
        $this->assertSame($before, $this->booksSnapshot());
        $this->assertSame('paid', $bill->fresh()->status);
        $this->actingAs($this->owner)->get(route('bank.index', ['status' => 'all']))->assertOk()
            ->assertDontSee('AMAZON PAY')->assertSee('No statements uploaded yet.');

        // 7. The same file again: everything that is in the books matches itself,
        //    the expense from step 4 included; the 5,900 still waits for the user.
        $this->upload($this->owner, $this->statement())
            ->assertSessionHas('status', '4 new lines read. 3 matched automatically. 1 line to review below.');
        $this->assertTrue($this->bankLine(1250, 0)->matched->is($expense));
        $this->assertSame('unmatched', $this->bankLine(0, 5900)->status);
        $this->assertSame($before, $this->booksSnapshot());
    }

    public function test_another_business_cannot_match_unmatch_or_remove_these_lines(): void
    {
        $this->books();
        $this->upload($this->owner, $this->statement())->assertSessionHasNoErrors();
        $statement = BankStatement::firstOrFail();
        $lineA = $this->bankLine(0, 11800);
        $lineBC = $this->bankLine(0, 5900);

        $other = $this->makeBusiness(['name' => 'Bengaluru Furnishers', 'state_id' => $this->state('29')->id, 'gstin' => '29ABCDE1234F1ZW']);
        $stranger = $this->ownerOf($other);
        // The stranger's own 5,900 receipt on 7 Oct (5,000 @18% intra KA).
        $theirCustomer = $this->customerOf($other, '29');
        $theirs = $this->webIssued($stranger, $theirCustomer, [$this->sheetLine(5000)], ['invoice_date' => '2026-10-02']);
        $this->webPay($stranger, $theirs, 5900, ['received_at' => '2026-10-07'])->assertSessionHasNoErrors();
        $theirPayment = Payment::where('invoice_id', $theirs->id)->firstOrFail();

        $this->actingAs($stranger)->post(route('bank.lines.match', $lineBC), ['candidate' => $theirPayment->getMorphClass() . '#' . $theirPayment->id])->assertForbidden();
        $this->actingAs($stranger)->post(route('bank.lines.unmatch', $lineA))->assertForbidden();
        $this->actingAs($stranger)->delete(route('bank.statements.destroy', $statement))->assertForbidden();

        // Nor can the owner tie their bank line to someone else's receipt of the same amount and date.
        $this->actingAs($this->owner)->post(route('bank.lines.match', $lineBC), ['candidate' => $theirPayment->getMorphClass() . '#' . $theirPayment->id])->assertStatus(422);

        $this->assertSame('matched', $lineA->fresh()->status);
        $this->assertSame('unmatched', $lineBC->fresh()->status);
        $this->assertNotNull(BankStatement::find($statement->id));
        $this->assertSame(4, BankStatementLine::where('company_id', $this->biz->id)->count());
        $this->assertSame(0, BankStatementLine::where('company_id', $other->id)->count());
    }

    public function test_a_statement_that_cannot_be_read_is_refused_with_a_plain_message(): void
    {
        // Dates but no money columns.
        $this->upload($this->owner, $this->statement("Date,Narration,Remarks\n03/10/2026,NEFT CR,abc\n"))
            ->assertRedirect(route('bank.index'))
            ->assertSessionHasErrors(['file' => 'Transactions table not found. The statement needs a Date column and Debit/Credit (or Withdrawal/Deposit) columns.']);
        // The right columns, but not one readable transaction under them.
        $this->upload($this->owner, $this->statement("Date,Narration,Debit,Credit\nOpening balance,,,\n"))
            ->assertSessionHasErrors(['file' => 'No transactions were found below the column headings.']);
        // Not a spreadsheet at all.
        $this->upload($this->owner, UploadedFile::fake()->createWithContent('statement.pdf', "%PDF-1.4\n"))
            ->assertSessionHasErrors(['file' => 'Upload the statement as an Excel (.xlsx) or CSV file.']);
        // Old Excel.
        $this->upload($this->owner, UploadedFile::fake()->createWithContent('statement.xls', 'binary'))
            ->assertSessionHasErrors(['file' => 'This is an old Excel (.xls) file. Open it in Excel and "Save As" .xlsx, or download the statement as CSV.']);
        // No account name.
        $this->actingAs($this->owner)->post(route('bank.upload'), ['account_name' => '', 'file' => $this->statement()])
            ->assertSessionHasErrors('account_name');

        $this->assertSame(0, BankStatement::count());
        $this->assertSame(0, BankStatementLine::count());
    }

    /** P3 copy: one line is "1 more line matched", as the upload message already says "1 line". */
    public function test_match_again_reports_a_single_line_in_the_singular(): void
    {
        [, , , $bill] = $this->books();
        $this->upload($this->owner, $this->statement())->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('purchases.payments.store', $bill), [
            'amount' => 23600, 'method' => 'neft', 'paid_on' => '2026-10-04', 'reference_number' => '000077',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->from(route('bank.index'))->post(route('bank.auto-match'))
            ->assertSessionHas('status', '1 more line matched.');
    }
}
