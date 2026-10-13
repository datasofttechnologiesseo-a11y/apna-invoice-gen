<?php

namespace Tests\Feature\Audit\Gst;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GSTR-1 portal JSON / Table 12 / Table 13 repros.
 */
class Gstr1PortalFileTest extends TestCase
{
    use RefreshDatabase;
    use BuildsGstBooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooks('2026-09-01 10:00');
        $this->customer('PAT', 'MH', '27AAACP1234A1Z5', 'Patil Traders');
        $this->customer('WIP', 'MH', null, 'Walk-in Pune');
    }

    /**
     * Table 12 is one row per HSN + UQC + rate. A service (SAC 99...) always
     * goes to the portal with UQC "NA", so two invoices for the same SAC at
     * the same rate - one billed per "NOS", one per "HRS" - must be one HSN
     * row in the portal file, not two rows with the same HSN/UQC/rate key.
     */
    public function test_hsn_table_in_portal_json_has_one_row_per_hsn_uqc_rate_for_services(): void
    {
        $this->issue('A', 'PAT', '2026-09-05 11:00', [$this->l('Consulting', '998311', 1, 10000, 18, 'NOS')]);
        $this->issue('B', 'PAT', '2026-09-10 11:00', [$this->l('Consulting', '998311', 5, 2000, 18, 'HRS')]);

        $j = $this->gstr1Json('2026-09');
        $rows = $j['hsn']['hsn_b2b'] ?? [];
        $keys = array_map(fn ($r) => $r['hsn_sc'] . '|' . $r['uqc'] . '|' . $r['rt'], $rows);

        $this->assertSame(
            array_values(array_unique($keys)), $keys,
            'hsn_b2b carries duplicate HSN/UQC/rate rows: ' . json_encode($rows)
        );
    }

    /** Same for goods whose free-text units map to the same UQC ("KG" -> KGS). */
    public function test_hsn_table_in_portal_json_has_one_row_per_hsn_uqc_rate_for_goods_units(): void
    {
        $this->issue('A', 'WIP', '2026-09-05 11:00', [$this->l('Packaged tea', '09023020', 10, 400, 5, 'KGS')]);
        $this->issue('B', 'WIP', '2026-09-10 11:00', [$this->l('Packaged tea', '09023020', 5, 400, 5, 'kg')]);

        $j = $this->gstr1Json('2026-09');
        $rows = $j['hsn']['hsn_b2c'] ?? [];
        $keys = array_map(fn ($r) => $r['hsn_sc'] . '|' . $r['uqc'] . '|' . $r['rt'], $rows);

        $this->assertSame(
            array_values(array_unique($keys)), $keys,
            'hsn_b2c carries duplicate HSN/UQC/rate rows: ' . json_encode($rows)
        );
    }

    /**
     * CTN (cartons) and CMS (centimetres) are UQCs on the GSTN list (as are
     * BAL, BUN, SQY, THD, TGM, UGS, GYD, BKL, BOU). The portal file turns
     * them into OTH because Gstr1Json::uqcCode() / Gstr1Workbook::uqc() and
     * config/uqc_units.php carry only part of the list.
     */
    public function test_valid_gstn_uqc_codes_reach_the_portal_file_unchanged(): void
    {
        $this->issue('A', 'WIP', '2026-09-05 11:00', [
            $this->l('Biscuits', '19053100', 4, 500, 18, 'CTN'),
            $this->l('Ribbon', '58063200', 300, 2, 5, 'CMS'),
        ]);

        $rows = collect($this->gstr1Json('2026-09')['hsn']['hsn_b2c'])->keyBy('hsn_sc');
        $this->assertSame(['CTN', 'CMS'], [$rows['19053100']['uqc'], $rows['58063200']['uqc']]);
    }

    /**
     * Table 13 reports the serial range of credit notes issued. A note dated
     * earlier than one issued before it (the form allows any date from the
     * invoice date to today) must not turn the range upside down.
     */
    public function test_table_13_credit_note_range_runs_from_lowest_to_highest_number(): void
    {
        $this->issue('A', 'PAT', '2026-09-02 11:00', [$this->l('Steel', '73269099', 10, 1000, 18)]);
        // CRN-0001 issued 20 Sep dated 20 Sep; CRN-0002 issued 22 Sep but dated 15 Sep.
        $this->creditNote('c1', 'A', '2026-09-20 11:00', '2026-09-20', 1180);
        $this->creditNote('c2', 'A', '2026-09-22 11:00', '2026-09-15', 1180);
        $this->assertSame('CRN-0001', $this->cn['c1']->credit_note_number);
        $this->assertSame('CRN-0002', $this->cn['c2']->credit_note_number);

        $this->at('2026-10-05 10:00');
        $docs = collect($this->gstr1Page('2026-09')['docs'])->keyBy('nature');
        $cn = $docs['Credit Note'];
        $this->assertSame(['CRN-0001', 'CRN-0002', 2], [$cn['from'], $cn['to'], $cn['total']],
            'Table 13 credit note range: from ' . $cn['from'] . ' to ' . $cn['to']);

        $j = $this->gstr1Json('2026-09');
        $row = collect($j['doc_issue']['doc_det'])->firstWhere('doc_num', 5)['docs'][0];
        $this->assertSame(['CRN-0001', 'CRN-0002'], [$row['from'], $row['to']]);
    }
}
