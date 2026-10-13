<?php

namespace Tests\Feature\Audit\Gst;

use App\Models\CreditNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GSTR-1 tables that must agree with each other (Table 12 vs 8/9B) and
 * Table 13 for credit notes.
 */
class Gstr1TablesAgreeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsGstBooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooks('2026-09-01 10:00');
        $this->customer('RAO', 'KA', '29AAACR5678B1Z2', 'Rao Industries');
    }

    /**
     * B1 (Sep): RAO, inter-state, steel 3 x 1,000 @18% (IGST 540) + vegetables
     * 2 x 500 @0% = 4,540. Credit note (Oct, no lines) for 1,000: taxable
     * 881.06, IGST 118.94. Spread pro rata: 18% part 660.795, nil part 220.265.
     * Table 9B (cdnr) is settled to the note: 660.79 + 8: 220.27 = 881.06.
     * Table 12 (B2B tab) must carry the same 881.06 off - it carries 881.07
     * (660.80 + 220.27) because the HSN rows skip the settle-to-note step.
     */
    public function test_table_12_takes_off_exactly_what_tables_9b_and_8_take_off_for_a_credit_note(): void
    {
        $this->issue('B1', 'RAO', '2026-09-09 11:00', [
            $this->l('Steel', '73269099', 3, 1000, 18),
            $this->l('Vegetables', '07099990', 2, 500, 0, 'KGS'),
        ]);
        $cn = $this->creditNote('n1', 'B1', '2026-10-09 11:00', '2026-10-09', 1000);
        $this->assertEqualsWithDelta(881.06, (float) $cn->taxable_value, 0.001);
        $this->assertEqualsWithDelta(118.94, (float) $cn->total_igst, 0.001);

        $this->at('2026-11-02 10:00');
        $g = $this->gstr1Page('2026-10');
        $t = $g['tables'];
        $nilReg = array_sum(array_map(fn ($r) => str_contains($r['description'], 'unregistered') ? 0 : $r['nil'], $g['exemp']));

        // 9B + 8 = the note.
        $this->assertEqualsWithDelta(881.06, round($t['cdnr']['taxable'] - $nilReg, 2), 0.001, 'cdnr + Table 8 = the note');
        // Table 12 B2B tab = -(9B + 8).
        $this->assertEqualsWithDelta(-881.06, (float) $t['hsn_b2b']['taxable'], 0.001,
            'Table 12 (B2B) taxable is ' . $t['hsn_b2b']['taxable'] . ' against a credit note of 881.06');

        // The steel HSN row and the 18% cdnr row are the same supply.
        $steel = collect($g['hsn'])->firstWhere('hsn', '73269099');
        $this->assertEqualsWithDelta(-(float) $g['cdnr'][0]['taxable'], (float) $steel['taxable'], 0.001,
            'HSN 73269099 row ' . $steel['taxable'] . ' vs cdnr 18% row ' . $g['cdnr'][0]['taxable']);
    }

    /**
     * Table 13 is reported series by series. Company settings allow the
     * invoice number format to change at any time (CompanyController only
     * checks the 16-character limit); after a change mid-month the month has
     * two series, but GSTR-1 reports one row "AUD/26-27/0001 to SHP/26-27/0004".
     */
    public function test_table_13_reports_each_invoice_series_on_its_own_row(): void
    {
        $this->issue('A1', 'RAO', '2026-09-02 11:00', [$this->l('Steel', '73269099', 1, 1000, 18)]);
        $this->issue('A2', 'RAO', '2026-09-03 11:00', [$this->l('Steel', '73269099', 1, 1000, 18)]);
        // Owner switches to a new series in Company settings.
        $this->company->forceFill(['invoice_prefix' => 'SHP', 'invoice_number_format' => 'SHP/{FY_SHORT}/{N}'])->save();
        $this->issue('B1', 'RAO', '2026-09-10 11:00', [$this->l('Steel', '73269099', 1, 1000, 18)]);
        $this->issue('B2', 'RAO', '2026-09-11 11:00', [$this->l('Steel', '73269099', 1, 1000, 18)]);
        $numbers = array_map(fn ($k) => $this->inv[$k]->invoice_number, ['A1', 'A2', 'B1', 'B2']);
        $this->assertStringStartsWith('AUD/', $numbers[0]);
        $this->assertStringStartsWith('SHP/', $numbers[2]);

        $this->at('2026-10-05 10:00');
        $rows = collect($this->gstr1Page('2026-09')['docs'])->where('nature', 'Invoices for outward supply')->values();
        $this->assertCount(2, $rows, 'Table 13 rows for invoices: ' . json_encode($rows->all()));
    }

    /**
     * A credit note issued and then reversed (deleted) leaves its number used:
     * the counter never gives it out again. Table 13 must still account for
     * it - the series CRN-0001..CRN-0003 is three documents, one cancelled -
     * otherwise the range and the count disagree and the gap is unexplained.
     */
    public function test_table_13_counts_a_reversed_credit_note_as_cancelled(): void
    {
        $this->issue('B1', 'RAO', '2026-09-02 11:00', [$this->l('Steel', '73269099', 10, 1000, 18)]);
        $this->creditNote('c1', 'B1', '2026-09-10 11:00', '2026-09-10', 1180);
        $this->creditNote('c2', 'B1', '2026-09-11 11:00', '2026-09-11', 1180);
        $this->creditNote('c3', 'B1', '2026-09-12 11:00', '2026-09-12', 1180);
        $this->assertSame(['CRN-0001', 'CRN-0002', 'CRN-0003'], [$this->cn['c1']->credit_note_number, $this->cn['c2']->credit_note_number, $this->cn['c3']->credit_note_number]);

        // CRN-0002 is reversed.
        $this->at('2026-09-13 11:00');
        $this->actingAs($this->user)->delete(route('credit-notes.destroy', $this->cn['c2']))->assertRedirect();
        $this->assertSame(2, CreditNote::count());

        $this->at('2026-10-05 10:00');
        $row = collect($this->gstr1Page('2026-09')['docs'])->firstWhere('nature', 'Credit Note');
        $this->assertSame(['CRN-0001', 'CRN-0003'], [$row['from'], $row['to']]);
        $this->assertSame([3, 1], [$row['total'], $row['cancelled']],
            "Table 13 says CRN-0001 to CRN-0003 is {$row['total']} notes, {$row['cancelled']} cancelled");
    }
}
