<?php

namespace Tests\Feature\Fintech\Reconciliation;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identity 5: money received. The receipts register, the payments table and
 * the "Payments received" list agree for every month, TDS adds up, and every
 * rupee received sits against an invoice.
 */
class ReceiptsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    /** Receipts by month [gross amount, TDS], by hand from the ledger. */
    private const BY_MONTH = [
        '2026-04' => [181540.00, 0.00],      // X0 2,360 (3 Apr) + A1 1,180 (1 Apr 00:01) + A4 1,77,000 + A5 1,000 (30 Apr)
        '2026-05' => [225070.00, 360.00],    // A2 20,070 (TDS 360) + M1 5,000 + M2 2,00,000
        '2026-06' => [192001.00, 40.00],     // M5 50,000 + J3 1,00,001 + J1 42,000 (TDS 40)
        '2026-07' => [21638.00, 155.56],     // L1 4,200 + J4 4,720 + L2 12,718 (TDS 155.56)
        '2026-08' => [2950.00, 0.00],
        '2026-09' => [76500.00, 0.00],       // S2 1,500 + S1 75,000
        '2026-10' => [5880.00, 0.00],        // O1 4,200 + O2 1,680
        '2026-11' => [41180.00, 0.00],       // N3 1,180 + N2 40,000
        '2026-12' => [153400.00, 30.00],     // N1 35,400 (TDS 30) + D1 1,18,000
        '2027-01' => [0.00, 0.00],
        '2027-02' => [54860.00, 0.00],       // F1 4,860 + F2 50,000
        '2027-03' => [0.00, 0.00],
    ];

    public function test_receipts_register_payments_table_and_payments_received_list_agree_every_month(): void
    {
        $failures = [];
        foreach ($this->months() as $ym) {
            [$from, $to] = $this->monthRange($ym);
            [$amount, $tds] = self::BY_MONTH[$ym];
            // "Payments received" on screen is the receipts register.
            $screen = $this->report('receipts', ['from' => $from, 'to' => $to])['totals'];
            $table = Payment::where('company_id', $this->company->id)->get()
                ->filter(fn ($p) => $p->received_at->format('Y-m-d') >= $from && $p->received_at->format('Y-m-d') <= $to);
            $this->check($failures, "{$ym} receipts (hand)", $amount, (float) $screen['amount']);
            $this->check($failures, "{$ym} TDS (hand)", $tds, (float) $screen['tds']);
            $this->check($failures, "{$ym} register = payments table", round((float) $table->sum('amount'), 2), (float) $screen['amount']);
            $this->check($failures, "{$ym} register TDS = payments table", round((float) $table->sum('tds_amount'), 2), (float) $screen['tds']);
        }
        // The year: 9,55,019.00 received, TDS 360 + 40 + 155.56 + 30 = 585.56.
        $fy = $this->report('receipts', ['from' => '2026-04-01', 'to' => '2027-03-31'])['totals'];
        $this->check($failures, 'FY receipts (hand)', 955019.00, (float) $fy['amount']);
        $this->check($failures, 'FY TDS (hand)', 585.56, (float) $fy['tds']);
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_the_dashboard_counts_the_months_receipts_by_their_date(): void
    {
        // Read on the evening of 30 June: June's receipts are M5 50,000 + J3 1,00,001 + J1 42,000.
        $this->at('2026-06-30 20:00');
        $stats = $this->actingAs($this->user)->get(route('dashboard'))->viewData('stats');
        $this->assertEqualsWithDelta(192001.00, (float) $stats['received_this_month'], 0.001);
        $this->assertSame(3, (int) $stats['received_count_this_month']);
    }

    public function test_every_receipt_is_gross_of_tds_and_sits_against_its_invoice(): void
    {
        $rows = collect($this->report('receipts', ['from' => '2026-04-01', 'to' => '2027-03-31'])['rows']);
        $tds = $rows->filter(fn ($r) => $r['tds'] > 0)->map(fn ($r) => [$r['invoice'], $r['amount'], $r['tds'], $r['tds_section']])->values()->all();
        $this->assertSame([
            [$this->inv['A2']->invoice_number, 20070.0, 360.0, '194C'],
            [$this->inv['J1']->invoice_number, 42000.0, 40.0, '194Q'],
            [$this->inv['L2']->invoice_number, 12718.0, 155.56, '194J'],
            [$this->inv['N1']->invoice_number, 35400.0, 30.0, '194Q'],
        ], $tds);
        // Net to bank on those: 19,710 + 41,960 + 12,562.44 + 35,370.
        $net = Payment::where('company_id', $this->company->id)->where('tds_amount', '>', 0)->get()->sum(fn ($p) => $p->netReceived());
        $this->assertEqualsWithDelta(109602.44, $net, 0.001);

        // All receipts ever (FY 9,55,019 + April 2027 24,780) = every invoice's paid amount.
        $all = (float) Payment::where('company_id', $this->company->id)->sum('amount');
        $this->assertEqualsWithDelta(979799.00, $all, 0.001);
        $this->assertEqualsWithDelta($all, (float) Invoice::where('company_id', $this->company->id)->sum('paid_amount'), 0.001);
        // No receipt on a draft or cancelled invoice.
        $this->assertSame(0, Payment::whereIn('invoice_id', [$this->inv['M4']->id, $this->inv['O3']->id, $this->inv['A6']->id, $this->inv['G3']->id])->count());
    }
}
