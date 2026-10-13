<?php

namespace Tests\Feature\Audit\Purchases;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\State;
use App\Models\User;
use App\Services\BankReconciler;
use App\Services\BankStatementReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Audit repro: bank statement reading and matching. */
class BankReconAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $mh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-30 10:00'));
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $this->user->id, 'state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z5', 'invoice_prefix' => 'INV', 'invoice_counter' => 0]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function line(string $date, float $debit, float $credit, string $desc = 'NEFT'): BankStatementLine
    {
        $st = BankStatement::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id, 'account_name' => 'HDFC Current',
            'file_name' => 'x.csv', 'period_from' => $date, 'period_to' => $date,
        ]);

        return BankStatementLine::create([
            'company_id' => $this->company->id, 'bank_statement_id' => $st->id, 'txn_date' => $date,
            'description' => $desc, 'reference' => null, 'debit' => $debit, 'credit' => $credit, 'balance' => null,
            'line_hash' => sha1(uniqid('', true)),
        ]);
    }

    /**
     * A receipt with TDS: the customer pays 11,800 less 1,000 TDS, so the
     * bank shows 10,800 (Payment::netReceived(), and the P&L's "received in
     * bank"). The reconciler compares the bank credit with the GROSS
     * Payment.amount, so the receipt is never offered, cannot be matched by
     * hand either (match() only accepts offered candidates), and the line
     * stays unreconciled for good.
     */
    public function test_a_receipt_with_tds_is_offered_for_its_net_bank_credit(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $this->mh->id, 'name' => 'Patil Traders']);
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => '2026-09-05',
            'items' => [['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18]],
        ]);
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice));
        $this->actingAs($this->user)->post(route('invoices.payments', $invoice), [
            'amount' => 11800, 'tds_amount' => 1000, 'tds_section' => '194C', 'method' => 'neft', 'received_at' => '2026-09-10', 'reference_number' => 'N123456789',
        ])->assertSessionHasNoErrors();
        $payment = Payment::firstOrFail();
        $this->assertEqualsWithDelta(10800.0, $payment->netReceived(), 0.001);

        $line = $this->line('2026-09-11', 0, 10800.00, 'NEFT CR-PATIL TRADERS-N123456789');

        $candidates = app(BankReconciler::class)->candidates($line);
        $this->assertTrue($candidates->contains(fn ($c) => $c['model'] instanceof Payment && $c['model']->is($payment)),
            'The 10,800 bank credit (11,800 receipt less 1,000 TDS) is not matched to its receipt; candidates: ' . $candidates->count());
    }

    /**
     * A vendor paid in cash never passes through the bank. Expenses paid in
     * cash are kept out of the candidates, but a cash PurchasePayment is
     * offered - and auto-matched when it is the only one - against an
     * unrelated bank debit of the same amount.
     */
    public function test_a_cash_vendor_payment_is_not_matched_to_a_bank_debit(): void
    {
        $vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'Mumbai Metals', 'state_id' => $this->mh->id, 'gstin' => '27PQRST1234U1Z1']);
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'MM/1', 'bill_date' => '2026-09-11', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'paid_now' => 5900, 'paid_on' => '2026-09-12', 'payment_method' => 'cash',
            'items' => [['description' => 'Steel', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('cash', PurchasePayment::firstOrFail()->method);

        // Same amount leaves the bank that day for something else (a cheque to the landlord).
        $line = $this->line('2026-09-12', 5900.00, 0, 'CHQ PAID 000451 SHARMA PROPERTIES');
        $matched = app(BankReconciler::class)->autoMatch($this->company);

        $this->assertSame(0, $matched, 'A bank debit was auto-matched to a vendor payment made in CASH: ' . $line->fresh()->matched_type);
    }

    /**
     * A cash memo paid by UPI: the seller is paid the memo's grand total
     * (rounded to the rupee). The memo's expense row holds taxable + GST
     * without the round-off, and the reconciler compares that, so a memo with
     * any round-off is never offered for its own bank debit.
     */
    public function test_a_cash_memo_paid_by_upi_is_offered_for_its_bank_debit(): void
    {
        $this->actingAs($this->user)->post(route('finance.cash-memos.store'), [
            'memo_date' => '2026-09-12', 'seller_name' => 'Ganesh Hardware', 'payment_mode' => 'upi', 'reference_number' => 'UPI998877',
            'gst_rate' => 18, 'itc_eligible' => 1, 'expense_category' => 'repairs',
            'items' => [['description' => 'Fittings', 'quantity' => 1, 'rate' => 999.50]],
        ])->assertSessionHasNoErrors();
        $memo = \App\Models\CashMemo::firstOrFail();
        // 999.50 + 179.91 = 1,179.41, rounded to 1,179.00 paid.
        $this->assertEqualsWithDelta(1179.0, (float) $memo->grand_total, 0.001);

        $line = $this->line('2026-09-12', 1179.00, 0, 'UPI/GANESH HARDWARE');
        $candidates = app(BankReconciler::class)->candidates($line);

        $this->assertTrue($candidates->isNotEmpty(),
            'The 1,179 UPI debit for cash memo ' . $memo->memo_number . ' (grand total 1,179) finds no candidate');
    }

    /**
     * One "Amount" column with a Dr/Cr column. A Dr/Cr column holding "C" /
     * "D" (or "Deposit"/"Withdrawal") is read as debit for every line: only
     * the letters "CR" mark a credit, anything else silently becomes money out.
     */
    public function test_a_dr_cr_column_with_single_letter_c_and_d_keeps_the_sign(): void
    {
        $lines = app(BankStatementReader::class)->parse([
            ['Txn Date', 'Description', 'Amount', 'Dr/Cr', 'Balance'],
            ['05/09/2026', 'NEFT FROM PATIL TRADERS', '11,800.00', 'C', '1,11,800.00'],
            ['06/09/2026', 'NEFT TO MUMBAI METALS', '5,900.00', 'D', '1,05,900.00'],
        ]);

        $this->assertSame([11800.0, 0.0], [$lines[0]['credit'], $lines[0]['debit']],
            'An 11,800 deposit marked "C" was read as a withdrawal');
    }

    /**
     * A single-amount statement headed "Amount in INR" (or "Amount in Rs")
     * with a Dr/Cr column: the header contains "amount in", which the
     * reader takes for a CREDIT column, drops the Dr/Cr column, and every
     * withdrawal is read as money in.
     */
    public function test_an_amount_in_inr_header_with_dr_cr_does_not_turn_withdrawals_into_deposits(): void
    {
        $lines = app(BankStatementReader::class)->parse([
            ['Date', 'Particulars', 'Amount in INR', 'Dr/Cr', 'Balance'],
            ['05/09/2026', 'NEFT FROM PATIL TRADERS', '11,800.00', 'CR', '1,11,800.00'],
            ['06/09/2026', 'NEFT TO MUMBAI METALS', '5,900.00', 'DR', '1,05,900.00'],
        ]);

        $this->assertSame([5900.0, 0.0], [$lines[1]['debit'], $lines[1]['credit']],
            'A 5,900 withdrawal marked DR was read as a deposit');
    }
}
