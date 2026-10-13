<?php

namespace Tests\Feature\Audit\Money;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Services\BankReconciler;

/**
 * A receipt with TDS: the invoice is settled by the gross amount, but the
 * bank only ever sees amount - TDS (Payment::netReceived). BankReconciler
 * looks for receipts whose gross amount equals the bank credit, so the real
 * bank line is never offered (and BankReconciliationController::match only
 * accepts what candidates() offers), while an unrelated credit equal to the
 * gross is.
 */
class BankMatchIgnoresTdsTest extends MoneyAuditBase
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

    public function test_tds_receipt_is_offered_for_the_net_amount_that_reached_the_bank(): void
    {
        // 1,00,000 + 18% = 1,18,000. Customer deducts 10% TDS u/s 194J on
        // the taxable 1,00,000 and transfers 1,08,000.
        $inv = $this->issue([
            ['description' => 'Consulting', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18],
        ], '2026-09-05');
        $this->pay($inv, [
            'amount' => 118000, 'tds_amount' => 10000, 'tds_section' => '194J', 'tds_rate' => 10,
            'received_at' => '2026-09-10', 'reference_number' => 'N998877',
        ])->assertSessionHasNoErrors();
        $p = Payment::where('invoice_id', $inv->id)->sole();
        $this->assertEqualsWithDelta(108000.0, $p->netReceived(), 0.001);
        $this->assertSame('paid', $inv->fresh()->status);

        $bank = $this->line(108000, '2026-09-10', 'NEFT N998877 PATIL TRADERS');

        $offered = app(BankReconciler::class)->candidates($bank)->pluck('model.id')->all();
        $this->assertContains($p->id, $offered, 'The receipt whose net (1,08,000) reached the bank is not offered for that bank line.');

        // And the user cannot match it by hand either.
        $this->actingAs($this->user)->post(route('bank.lines.match', $bank), [
            'candidate' => $p->getMorphClass() . '#' . $p->id,
        ])->assertRedirect();
        $this->assertSame('matched', $bank->fresh()->status);
    }

    public function test_tds_receipt_is_not_auto_matched_to_a_credit_equal_to_its_gross(): void
    {
        $inv = $this->issue([
            ['description' => 'Consulting', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18],
        ], '2026-09-05');
        $this->pay($inv, [
            'amount' => 118000, 'tds_amount' => 10000, 'tds_section' => '194J', 'tds_rate' => 10,
            'received_at' => '2026-09-10',
        ])->assertSessionHasNoErrors();
        $p = Payment::where('invoice_id', $inv->id)->sole();

        // Someone else's 1,18,000 comes in two days later.
        $other = $this->line(118000, '2026-09-12', 'IMPS FROM SHARMA ENTERPRISES');
        app(BankReconciler::class)->autoMatch($this->company);

        $other->refresh();
        $this->assertFalse(
            $other->status === 'matched' && (int) $other->matched_id === $p->id,
            'An unrelated 1,18,000 credit was auto-matched to the TDS receipt that only brought 1,08,000 into the bank.'
        );
    }
}
