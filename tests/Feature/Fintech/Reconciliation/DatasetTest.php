<?php

namespace Tests\Feature\Fintech\Reconciliation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The year of books itself, as SeedsFy2026Books describes it: if one of these
 * fails, every reconciliation built on the data is suspect, so it is checked
 * first and on its own.
 */
class DatasetTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFy2026Books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBooks();
    }

    public function test_every_issued_invoice_carries_the_hand_worked_totals_and_number(): void
    {
        // [number, taxable, cgst, sgst, igst, round off, grand total, status]
        $expected = [
            'X0' => ['SAH/25-26/0001', 2000, 180, 180, 0, 0, 2360, 'paid'],
            'A1' => ['SAH/26-27/0001', 1000, 90, 90, 0, 0, 1180, 'paid'],
            'A2' => ['SAH/26-27/0002', 18000, 0, 0, 2070, 0, 20070, 'paid'],
            'A4' => ['SAH/26-27/0003', 150000, 0, 0, 27000, 0, 177000, 'paid'],
            'A5' => ['SAH/26-27/0004', 2000, 90, 90, 0, 0, 2180, 'partially_paid'],
            'M1' => ['SAH/26-27/0005', 9999.99, 900, 900, 0, 0.01, 11800, 'partially_paid'],
            'M2' => ['SAH/26-27/0006', 200000, 0, 0, 0, 0, 200000, 'paid'],
            'M3' => ['SAH/26-27/0007', 20000, 0, 0, 3600, 0, 23600, 'final'],
            'M4' => ['SAH/26-27/0008', 5000, 450, 450, 0, 0, 5900, 'cancelled'],
            'M5' => ['SAH/26-27/0009', 50000, 0, 0, 0, 0, 50000, 'paid'],
            'J1' => ['SAH/26-27/0010', 40000, 0, 0, 2000, 0, 42000, 'paid'],
            'J2' => ['SAH/26-27/0011', 84745.76, 0, 0, 15254.24, 0, 100000, 'final'],
            'J3' => ['SAH/26-27/0012', 95239.05, 0, 0, 4761.95, 0, 100001, 'paid'],
            'J4' => ['SAH/26-27/0013', 5000, 450, 450, 0, 0, 5900, 'paid'],
            'L1' => ['SAH/26-27/0014', 4000, 100, 100, 0, 0, 4200, 'paid'],
            'L2' => ['SAH/26-27/0015', 10777.77, 0, 0, 1940, 0.23, 12718, 'paid'],
            'L3' => ['SAH/26-27/0016', 50000, 0, 0, 9000, 0, 59000, 'final'],
            'G1' => ['SAH/26-27/0017', 15000, 862.50, 862.50, 0, 0, 16725, 'final'],
            'G2' => ['SAH/26-27/0018', 2500, 225, 225, 0, 0, 2950, 'paid'],
            'S1' => ['SAH/26-27/0019', 75000, 0, 0, 0, 0, 75000, 'paid'],
            'S2' => ['SAH/26-27/0020', 1500, 0, 0, 0, 0, 1500, 'paid'],
            'S4' => ['SAH/26-27/0021', 25000, 0, 0, 4500, 0, 29500, 'final'],
            'O1' => ['SAH/26-27/0022', 6000, 150, 150, 0, 0, 6300, 'paid'],
            'O2' => ['SAH/26-27/0023', 1200, 240, 240, 0, 0, 1680, 'paid'],
            'O3' => ['SAH/26-27/0024', 3000, 0, 0, 540, 0, 3540, 'cancelled'],
            'N1' => ['SAH/26-27/0025', 30000, 0, 0, 5400, 0, 35400, 'paid'],
            'N2' => ['SAH/26-27/0026', 40000, 0, 0, 0, 0, 40000, 'paid'],
            'N3' => ['SAH/26-27/0027', 999.95, 90, 90, 0, 0.05, 1180, 'paid'],
            'D1' => ['SAH/26-27/0028', 100000, 0, 0, 18000, 0, 118000, 'paid'],
            'D2' => ['SAH/26-27/0029', 10000, 0, 0, 500, 0, 10500, 'final'],
            'Ja1' => ['SAH/26-27/0030', 12000, 1080, 1080, 0, 0, 14160, 'final'],
            'Ja2' => ['SAH/26-27/0031', 30000, 0, 0, 5400, 0, 35400, 'final'],
            'F1' => ['SAH/26-27/0032', 4500, 230, 230, 0, 0, 4960, 'paid'],
            'F2' => ['SAH/26-27/0033', 50000, 0, 0, 9000, 0, 59000, 'partially_paid'],
            'F3' => ['SAH/26-27/0034', 100000, 0, 0, 18000, 0, 118000, 'final'],
            'Mr1' => ['SAH/26-27/0035', 20000, 1800, 1800, 0, 0, 23600, 'paid'],
            'Mr2' => ['SAH/26-27/0036', 1000, 90, 90, 0, 0, 1180, 'paid'],
            'Y1' => ['SAH/27-28/0001', 500, 45, 45, 0, 0, 590, 'final'],
        ];
        $failures = [];
        foreach ($expected as $k => [$no, $taxable, $c, $s, $i, $ro, $grand, $status]) {
            $inv = $this->inv[$k];
            if ($inv->invoice_number !== $no) {
                $failures[] = "{$k}: number {$inv->invoice_number}, expected {$no}";
            }
            if ($inv->status !== $status) {
                $failures[] = "{$k}: status {$inv->status}, expected {$status}";
            }
            foreach (['subtotal' => $taxable, 'total_cgst' => $c, 'total_sgst' => $s, 'total_igst' => $i, 'round_off' => $ro, 'grand_total' => $grand] as $f => $v) {
                $this->check($failures, "{$k} {$f}", $v, (float) $inv->{$f});
            }
        }
        $this->assertSame([], $failures);
        $this->assertSame('draft', $this->inv['A6']->status);
        $this->assertNull($this->inv['A6']->invoice_number);
        $this->assertSame('draft', $this->inv['G3']->status);
    }

    public function test_every_credit_note_carries_the_hand_worked_split(): void
    {
        // [number, amount, taxable, cgst, sgst, igst]
        $expected = [
            'cnX0' => ['CRN-0001', 236, 200, 18, 18, 0],
            'cnJ4' => ['CRN-0002', 1180, 1000, 90, 90, 0],
            'cnM1' => ['CRN-0003', 1180, 1000, 90, 90, 0],
            'cnG1' => ['CRN-0004', 1000, 896.86, 51.57, 51.57, 0],
            'cnO1' => ['CRN-0005', 2100, 2000, 50, 50, 0],
            'cnL3' => ['CRN-0006', 5900, 5000, 0, 0, 900],
            'cnJa2' => ['CRN-0007', 3540, 3000, 0, 0, 540],
            // 100 x 4,500/4,960 = 90.7258: 90.72 or 90.73 here; which one is right is the add-up test's job
            'cnF1' => ['CRN-0008', 100, 90.7258, 4.64, 4.64, 0],
            'cnF3' => ['CRN-0009', 11800, 10000, 0, 0, 1800],
        ];
        $failures = [];
        foreach ($expected as $k => [$no, $amount, $taxable, $c, $s, $i]) {
            $cn = $this->cn[$k]->fresh();
            if ($cn->credit_note_number !== $no) {
                $failures[] = "{$k}: number {$cn->credit_note_number}, expected {$no}";
            }
            foreach (['amount' => $amount, 'taxable_value' => $taxable, 'total_cgst' => $c, 'total_sgst' => $s, 'total_igst' => $i] as $f => $v) {
                $this->check($failures, "{$k} {$f}", $v, (float) $cn->{$f}, $k === 'cnF1' && $f === 'taxable_value' ? 0.006 : 0.001);
            }
        }
        $this->assertSame([], $failures);
    }

    public function test_every_credit_note_adds_up_to_its_own_amount(): void
    {
        // A credit note has no round-off line: its taxable value plus its tax is
        // the amount it credits, or the customer ledger (which moves by the
        // amount) and the GST returns (which move by taxable + tax) part ways.
        // cnF1: ₹100 goodwill on F1 (4,500 + 460 tax): pro rata 90.7258 / 4.6371
        // / 4.6371 must land on 90.72 + 4.64 + 4.64 = 100.00 (the remainder goes
        // to the largest head), not 90.73 + 4.64 + 4.64 = 100.01.
        $failures = [];
        foreach ($this->cn as $k => $cn) {
            $cn->refresh();
            $this->check($failures, "{$k} taxable + tax = amount",
                (float) $cn->amount, round((float) $cn->taxable_value + (float) $cn->total_cgst + (float) $cn->total_sgst + (float) $cn->total_igst, 2));
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_every_purchase_bill_carries_the_hand_worked_totals(): void
    {
        // [taxable, cgst, sgst, igst, grand total (owed to vendor), paid, balance]
        $expected = [
            'B1' => [60000, 5400, 5400, 0, 70800, 70800, 0],
            'B2' => [60000, 0, 0, 3000, 63000, 31500, 31500],
            'B3' => [10000, 250, 250, 0, 10000, 10000, 0],
            'B4' => [10000, 250, 250, 0, 10500, 10500, 0],
            'B5' => [2000, 0, 0, 0, 2000, 2000, 0],
            'B6' => [25000, 2250, 2250, 0, 25000, 25000, 0],
            'B7' => [33333.33, 3000, 3000, 0, 39333, 39333, 0],
            'B8' => [30000, 0, 0, 1500, 31500, 0, 31500],
            'B9' => [30000, 2700, 2700, 0, 35400, 20000, 15400],
            'B10' => [8000, 200, 200, 0, 8000, 8000, 0],
            'B11' => [100000, 0, 0, 18000, 118000, 0, 118000],
            'B12' => [20000, 1800, 1800, 0, 23600, 23600, 0],
            'B13' => [60000, 5400, 5400, 0, 70800, 0, 70800],
            'B14' => [15000, 1350, 1350, 0, 15000, 15000, 0],
            'B15' => [15000, 0, 0, 750, 15750, 15750, 0],
            'B16' => [5000, 450, 450, 0, 5900, 0, 5900],
            'B17' => [1500, 0, 0, 0, 1500, 1500, 0],
            'B18' => [50000, 0, 0, 9000, 59000, 9000, 50000],
            'B19' => [12400, 0, 0, 620, 13020, 0, 13020],
            'B20' => [6000, 150, 150, 0, 6000, 6000, 0],
        ];
        $failures = [];
        foreach ($expected as $k => [$taxable, $c, $s, $i, $grand, $paid, $balance]) {
            $b = $this->bill[$k]->fresh();
            foreach (['subtotal' => $taxable, 'total_cgst' => $c, 'total_sgst' => $s, 'total_igst' => $i,
                'grand_total' => $grand, 'paid_amount' => $paid, 'balance' => $balance] as $f => $v) {
                $this->check($failures, "{$k} {$f}", $v, (float) $b->{$f});
            }
        }
        $this->assertSame([], $failures);
    }
}
