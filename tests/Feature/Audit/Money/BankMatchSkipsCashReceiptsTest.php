<?php

namespace Tests\Feature\Audit\Money;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Services\BankReconciler;

/**
 * Money received in cash never reaches the bank statement. A cash receipt
 * of ₹1,180 used to be offered, and auto-matched, for an unrelated ₹1,180
 * NEFT credit: the credit looked explained while its own receipt was never
 * recorded or chased. Vendor payments and expenses already skipped cash.
 */
class BankMatchSkipsCashReceiptsTest extends MoneyAuditBase
{
    private function line(float $credit, string $date, string $narr): BankStatementLine
    {
        $st = BankStatement::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id, 'account_name' => 'HDFC current',
        ]);

        return BankStatementLine::create([
            'company_id' => $this->company->id, 'bank_statement_id' => $st->id, 'txn_date' => $date,
            'description' => $narr, 'credit' => $credit, 'debit' => 0, 'status' => 'unmatched',
            'line_hash' => sha1($narr . $credit . $date),
        ]);
    }

    private function invoiceOf1180(): \App\Models\Invoice
    {
        return $this->issue([
            ['description' => 'Repair', 'hsn_sac' => '998719', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ], '2026-09-05');
    }

    public function test_a_cash_receipt_is_never_offered_or_auto_matched_for_a_bank_credit(): void
    {
        $inv = $this->invoiceOf1180();
        $this->pay($inv, ['amount' => 1180, 'method' => 'cash', 'received_at' => '2026-09-10'])->assertSessionHasNoErrors();
        $cash = Payment::where('invoice_id', $inv->id)->sole();

        $neft = $this->line(1180, '2026-09-10', 'NEFT FROM MEHTA AGENCIES');

        $this->assertNotContains($cash->id, app(BankReconciler::class)->candidates($neft)->pluck('model.id')->all());

        app(BankReconciler::class)->autoMatch($this->company);
        $this->assertSame('unmatched', $neft->fresh()->status, 'the NEFT stays to review: its receipt is not recorded yet');
    }

    public function test_a_upi_receipt_of_the_same_amount_still_matches(): void
    {
        $inv = $this->invoiceOf1180();
        $this->pay($inv, ['amount' => 1180, 'method' => 'upi', 'reference_number' => '430912345678', 'received_at' => '2026-09-10'])->assertSessionHasNoErrors();
        $upi = Payment::where('invoice_id', $inv->id)->sole();

        $line = $this->line(1180, '2026-09-10', 'UPI 430912345678 MEHTA');

        app(BankReconciler::class)->autoMatch($this->company);
        $line->refresh();
        $this->assertSame('matched', $line->status);
        $this->assertSame($upi->id, (int) $line->matched_id);
    }
}
