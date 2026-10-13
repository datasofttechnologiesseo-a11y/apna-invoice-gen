<?php

namespace Tests\Feature\Fintech\Reconciliation;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Identity 3: what customers owe, read on 5 Apr 2027, is one number whichever
 * screen it comes from: the invoices' own balances, Receivables aging (web,
 * report, app), the dashboard, the customer list and the customer ledgers.
 */
class ReceivablesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    public function test_each_invoice_balance_is_its_total_less_gross_receipts_less_credit_notes(): void
    {
        // Balance by hand = grand total - receipts (gross, TDS included) - credit notes.
        $hand = [
            'X0' => -236.00,          // 2,360 - 2,360 - 236: refund owed to Patil
            'A2' => 0.00,             // 20,070 - 20,070 (TDS 360 inside) - 0
            'A5' => 1180.00,          // 2,180 - 1,000
            'M1' => 5620.00,          // 11,800 - 5,000 - 1,180
            'M3' => 23600.00, 'J2' => 100000.00,
            'J4' => 0.00,             // 5,900 - 4,720 - 1,180
            'L3' => 53100.00,         // 59,000 - 5,900
            'G1' => 15725.00,         // 16,725 - 1,000
            'S4' => 29500.00,
            'O1' => 0.00,             // 6,300 - 4,200 - 2,100 (sales return)
            'D2' => 10500.00, 'Ja1' => 14160.00,
            'Ja2' => 31860.00,        // 35,400 - 3,540
            'F1' => 0.00,             // 4,960 - 4,860 - 100
            'F2' => 9000.00,          // 59,000 - 50,000
            'F3' => 106200.00,        // 1,18,000 - 11,800
            'Mr1' => 0.00, 'Mr2' => 0.00, 'Y1' => 590.00,
        ];
        $failures = [];
        foreach ($this->inv as $k => $inv) {
            if (in_array($inv->status, ['draft', 'cancelled'], true)) {
                continue;
            }
            $paid = round((float) $inv->payments()->sum('amount'), 2);
            $credited = round((float) $inv->creditNotes()->reorder()->sum('amount'), 2);
            $this->check($failures, "{$k} paid_amount = receipts", $paid, (float) $inv->paid_amount);
            $this->check($failures, "{$k} credited_amount = credit notes", $credited, (float) $inv->credited_amount);
            $this->check($failures, "{$k} balance = total - receipts - notes", round((float) $inv->grand_total - $paid - $credited, 2), (float) $inv->balance);
            if (array_key_exists($k, $hand)) {
                $this->check($failures, "{$k} balance (hand)", $hand[$k], (float) $inv->balance);
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_open_balances_aging_dashboard_and_customer_list_are_one_number(): void
    {
        // Open (> 0) on 5 Apr 2027, days past due -> bucket:
        //  0-30   F2 9,000 (20 d) + F3 1,06,200 (6 d) + Y1 590 (4 d)                 = 1,15,790
        //  31-60  Ja1 14,160 (55 d) + Ja2 31,860 (45 d)                              =   46,020
        //  61-90  D2 10,500 (65 d)                                                   =   10,500
        //  90+    A5 1,180 + M1 5,620 + M3 23,600 + J2 1,00,000 + L3 53,100
        //         + G1 15,725 + S4 29,500                                             = 2,28,725
        //  total 4,01,035
        $total = 401035.00;
        $failures = [];

        $open = (float) Invoice::where('company_id', $this->company->id)->whereIn('status', ['final', 'partially_paid'])->where('balance', '>', 0)->sum('balance');
        $this->check($failures, 'sum of open invoice balances', $total, $open);

        $aging = $this->actingAs($this->user)->get(route('finance.aging'))->viewData('summary');
        foreach (['total' => $total, 'current' => 115790.00, 'b30_60' => 46020.00, 'b60_90' => 10500.00, 'b90_plus' => 228725.00] as $k => $v) {
            $this->check($failures, "Receivables aging page {$k}", $v, (float) $aging[$k]);
        }
        $report = $this->report('receivables-aging')['totals'];
        foreach (['total' => $total, 'b0_30' => 115790.00, 'b31_60' => 46020.00, 'b61_90' => 10500.00, 'b90' => 228725.00] as $k => $v) {
            $this->check($failures, "Receivables aging report {$k}", $v, (float) $report[$k]);
        }
        Sanctum::actingAs($this->user);
        $this->check($failures, 'app aging total', $total, (float) $this->getJson('/api/finance/aging')->assertOk()->json('summary.total'));

        $stats = $this->actingAs($this->user)->get(route('dashboard'))->viewData('stats');
        $this->check($failures, 'dashboard outstanding', $total, (float) $stats['outstanding']);
        $this->check($failures, 'customer list report outstanding', $total, (float) $this->report('customers')['totals']['outstanding']);
        $index = $this->actingAs($this->user)->get(route('customers.index'))->viewData('customers');
        $this->check($failures, 'customers screen outstanding', $total, (float) collect($index->items())->sum('outstanding'));
        $pnl = $this->actingAs($this->user)->get(route('finance.index', ['period' => 'this_fy']))->viewData('revenue');
        $this->check($failures, 'P&L outstanding receivables', $total, (float) $pnl['outstanding']);

        // Per customer, the aging rows by hand.
        $rows = collect($this->report('receivables-aging')['rows'])->keyBy('party');
        foreach (['Walk-in Pune' => 1770.00, 'Patil Traders' => 35505.00, 'Rao Industries' => 38500.00, 'Shah SEZ Unit' => 55460.00,
            'Acme Inc' => 53100.00, 'Kumar Retail Bengaluru' => 216700.00] as $name => $v) {
            $this->check($failures, "aging row {$name}", $v, (float) ($rows[$name]['total'] ?? 0));
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_customer_ledgers_close_on_what_the_receivables_report_shows(): void
    {
        // Ledger closing = invoices - receipts - credit notes, by hand:
        //  Patil: X0 2,360 + J4 5,900 + M1 11,800 + G1 16,725 + S2 1,500 + O2 1,680 + Ja1 14,160 + Mr1 23,600
        //         = 77,725; receipts 2,360 + 5,000 + 4,720 + 1,500 + 1,680 + 23,600 = 38,860;
        //         notes 236 + 1,180 + 1,180 + 1,000 = 3,596  ->  35,269
        //         (35,505 still open on M1/G1/Ja1, less the 236 refund owed on X0).
        //  The other customers have no credit balance, so ledger = aging row.
        $ledgerHand = ['WIP' => 1770.00, 'PAT' => 35269.00, 'RAO' => 38500.00, 'SEZ' => 55460.00, 'ACME' => 53100.00, 'BLR' => 216700.00, 'DES' => 0.00];
        $aging = collect($this->report('receivables-aging')['rows'])->keyBy('party');
        $failures = [];
        $sumLedgers = 0.0;
        foreach ($ledgerHand as $key => $closing) {
            $c = $this->cust[$key];
            $res = $this->actingAs($this->user)->get(route('customers.ledger', $c));
            $totals = $res->viewData('totals');
            $entries = $res->viewData('entries');
            $this->check($failures, "{$key} ledger closing (hand)", $closing, (float) $totals['outstanding']);
            $this->check($failures, "{$key} ledger running balance ends at the closing", (float) $totals['outstanding'], (float) ($entries->last()['balance'] ?? 0));
            Sanctum::actingAs($this->user);
            $this->check($failures, "{$key} app ledger closing", (float) $totals['outstanding'], (float) $this->getJson("/api/customers/{$c->id}/ledger")->assertOk()->json('totals.outstanding'));
            // The report ages open invoices (Patil 35,505) and shows the refund
            // owed back (-236) beside them; open less refund is the ledger.
            $this->check($failures, "{$key} receivables aging net vs ledger", (float) $totals['outstanding'], (float) ($aging[$c->name]['net'] ?? 0));
            $sumLedgers += (float) $totals['outstanding'];
        }
        $this->check($failures, 'sum of customer ledgers vs receivables net', $sumLedgers, (float) $this->report('receivables-aging')['totals']['net']);
        $this->check($failures, 'refund owed to Patil on the report', -236.00, (float) ($aging['Patil Traders']['refund_due'] ?? 0));
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
