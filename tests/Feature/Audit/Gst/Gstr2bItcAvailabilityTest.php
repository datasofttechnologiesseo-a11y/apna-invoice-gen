<?php

namespace Tests\Feature\Audit\Gst;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GSTR-2B marks each document "itcavl" Y/N. Credit on a document 2B shows as
 * ITC NOT available (e.g. place of supply in another state, s.16(4) time
 * bar) cannot be claimed (s.16(2)(aa), Rule 36(4)) - yet GSTR-3B's 2B check
 * counts it as confirmed credit.
 */
class Gstr2bItcAvailabilityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsGstBooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooks('2026-09-01 10:00');
        $this->vendor('V1', 'MH', '27PQRST1234U1Z1', 'Mumbai Metals');
        // One purchase invoice, 10,000 + CGST 900 + SGST 900, in the books for September.
        $this->bill('V1', '2026-09-05 11:00', 'INV/001', [$this->l('Goods', '72085100', 1, 10000, 18)]);
    }

    private function twoB(string $rtnprd, array $invoices): string
    {
        return json_encode(['chksum' => 'x', 'data' => [
            'gstin' => '27AAKCS1234M1Z5', 'rtnprd' => $rtnprd, 'version' => '1.0',
            'docdata' => ['b2b' => [['ctin' => '27PQRST1234U1Z1', 'trdnm' => 'Mumbai Metals', 'inv' => $invoices]]],
        ]]);
    }

    /**
     * The supplier files late: INV/001 (5 Sep) is not in September's 2B, so
     * September's 3B check says hold its 1,800. It appears in October's 2B -
     * from then the credit is available (s.16(2)(aa)) and is claimed in
     * October's 3B. October's 2B check must count it as confirmed; it counts
     * only bills booked in October, so the held credit never shows up again
     * and "cash if only 2B credit is claimed" is overstated by 1,800.
     */
    public function test_credit_held_for_a_late_supplier_is_confirmed_when_it_reaches_a_later_2b(): void
    {
        $item = ['num' => 1, 'rt' => 18, 'txval' => 10000, 'igst' => 0, 'cgst' => 900, 'sgst' => 900, 'cess' => 0];
        // October output tax so there is something to pay: 20,000 @18% intra.
        $this->customer('PAT', 'MH', '27AAACP1234A1Z5', 'Patil Traders');
        $this->issue('O1', 'PAT', '2026-10-05 11:00', [$this->l('Steel', '73269099', 20, 1000, 18)]);

        $this->at('2026-10-15 10:00');
        // September 2B: some other document, not INV/001.
        $this->uploadJson2b($this->twoB('092026', [
            ['inum' => 'X-9', 'typ' => 'R', 'dt' => '02-09-2026', 'val' => 1180, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y',
                'items' => [['num' => 1, 'rt' => 18, 'txval' => 1000, 'igst' => 0, 'cgst' => 90, 'sgst' => 90, 'cess' => 0]]],
        ]));
        $sep = $this->gstr3bPage('2026-09')['itc_2b'];
        $this->assertEqualsWithDelta(1800.0, (float) $sep['not_in_2b']['total'], 0.001, 'September: hold INV/001');

        $this->at('2026-11-15 10:00');
        // October 2B: INV/001 filed by the supplier in October.
        $this->uploadJson2b($this->twoB('102026', [
            ['inum' => 'INV-001', 'typ' => 'R', 'dt' => '05-09-2026', 'val' => 11800, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'items' => [$item]],
        ]));
        $oct = $this->gstr3bPage('2026-10');
        $check = $oct['itc_2b'];
        // Output CGST/SGST 1,800 each. 2B confirms 900 + 900 of credit for October.
        $this->assertEqualsWithDelta(1800.0, (float) $check['confirmed']['total'], 0.001,
            'October 2B confirms INV/001 (1,800) but the 3B check confirms ' . $check['confirmed']['total']);
        $this->assertEqualsWithDelta(1800.0, (float) $check['cash_if_2b_only']['total'], 0.001,
            'cash if only 2B credit is claimed: 3,600 output less 1,800 confirmed');
    }

    public function test_a_2b_document_marked_itc_not_available_is_not_confirmed_credit(): void
    {
        $item = ['num' => 1, 'rt' => 18, 'txval' => 10000, 'igst' => 0, 'cgst' => 900, 'sgst' => 900, 'cess' => 0];
        $json = json_encode(['chksum' => 'x', 'data' => [
            'gstin' => '27AAKCS1234M1Z5', 'rtnprd' => '092026', 'version' => '1.0', 'gendt' => '14-10-2026',
            'docdata' => ['b2b' => [
                ['ctin' => '27PQRST1234U1Z1', 'trdnm' => 'Mumbai Metals', 'inv' => [
                    // In 2B, but ITC NOT available (reason: POS / time limit).
                    ['inum' => 'INV-1', 'typ' => 'R', 'dt' => '05-09-2026', 'val' => 11800, 'pos' => '27', 'rev' => 'N',
                        'itcavl' => 'N', 'rsn' => 'P', 'items' => [$item]],
                ]],
            ]],
        ]]);
        $this->at('2026-10-15 10:00');
        $this->uploadJson2b($json);

        $d = $this->gstr3bPage('2026-09');
        $check = $d['itc_2b'];
        $this->assertNotNull($check);
        $this->assertEqualsWithDelta(1800.0, $d['itc']['total'], 0.001, 'books credit (unchanged)');
        // 2B says this credit is NOT available: none of it is confirmed.
        $this->assertEqualsWithDelta(0.0, (float) $check['in_2b']['total'], 0.001,
            'itc_2b.in_2b counts a 2B document with itcavl=N as confirmed credit');
        $this->assertEqualsWithDelta(0.0, (float) $check['confirmed']['total'], 0.001);
    }
}
