<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Gstr2bEntry;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Gstr2bMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * GSTR-2B upload and match. Books for September 2026:
 *   P1 Mumbai Metals  INV/001  10,000 + 1,800   - in 2B as "INV-1"        -> matched
 *   P2 Mysore Motors  MM-77    20,000 + 3,600   - in 2B at 20,000 + 3,960 -> amounts differ
 *   P3 Mumbai Metals  INV/002   5,000 +   900   - not in 2B               -> not in 2B yet
 *   P4 Local shop (no GSTIN)    1,000 +   180                             -> no GSTIN
 * 2B also has Kolhapur Kraft K-9 (4,000 + 720), not in the books -> missing,
 * and a credit note from Mumbai Metals.
 */
class Gstr2bMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 10:00'));
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $this->user->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5']);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();

        $metals = $this->vendor('Mumbai Metals', '27PQRST1234U1Z1', $mh);
        $motors = $this->vendor('Mysore Motors', '29VWXYZ1234A1Z2', $ka);
        $shop = $this->vendor('Local shop', null, $mh);
        $this->bill($metals, 'INV/001', '2026-09-05', 10000);
        $this->bill($motors, 'MM-77', '2026-09-08', 20000);
        $this->bill($metals, 'INV/002', '2026-09-20', 5000);
        $this->bill($shop, 'S-1', '2026-09-21', 1000);
    }

    private function vendor(string $name, ?string $gstin, State $state): Vendor
    {
        return $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => $name, 'gstin' => $gstin, 'state_id' => $state->id]);
    }

    private function bill(Vendor $v, string $no, string $date, float $rate): void
    {
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $v->id, 'bill_number' => $no, 'bill_date' => $date, 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => $rate, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
    }

    private function portalJson(string $gstin = '27ABCDE1234F1Z5'): string
    {
        $item = fn ($tx, $i, $c, $s) => ['num' => 1, 'rt' => 18, 'txval' => $tx, 'igst' => $i, 'cgst' => $c, 'sgst' => $s, 'cess' => 0];

        return json_encode(['chksum' => 'x', 'data' => [
            'gstin' => $gstin, 'rtnprd' => '092026', 'version' => '1.0', 'gendt' => '14-10-2026',
            'docdata' => [
                'b2b' => [
                    ['ctin' => '27PQRST1234U1Z1', 'trdnm' => 'Mumbai Metals', 'supfildt' => '11-10-2026', 'supprd' => '092026', 'inv' => [
                        ['inum' => 'INV-1', 'typ' => 'R', 'dt' => '05-09-2026', 'val' => 11800, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'rsn' => '', 'diffprcnt' => 1, 'srctyp' => '', 'items' => [$item(10000, 0, 900, 900)]],
                    ]],
                    ['ctin' => '29VWXYZ1234A1Z2', 'trdnm' => 'Mysore Motors', 'inv' => [
                        ['inum' => 'MM-77', 'typ' => 'R', 'dt' => '08-09-2026', 'val' => 23960, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'items' => [$item(20000, 3960, 0, 0)]],
                    ]],
                    ['ctin' => '27KLMNO9876P1Z3', 'trdnm' => 'Kolhapur Kraft', 'inv' => [
                        ['inum' => 'K-9', 'typ' => 'R', 'dt' => '12-09-2026', 'val' => 4720, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'items' => [$item(4000, 0, 360, 360)]],
                    ]],
                ],
                'cdnr' => [
                    ['ctin' => '27PQRST1234U1Z1', 'trdnm' => 'Mumbai Metals', 'nt' => [
                        ['ntnum' => 'CN-3', 'typ' => 'C', 'suptyp' => 'R', 'dt' => '25-09-2026', 'val' => 1180, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'items' => [$item(1000, 0, 90, 90)]],
                    ]],
                ],
            ],
        ]]);
    }

    private function upload(UploadedFile $file, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post(route('finance.gstr2b.upload'), ['file' => $file] + $extra);
    }

    private function jsonFile(?string $content = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), '2b') . '.json';
        file_put_contents($path, $content ?? $this->portalJson());

        return new UploadedFile($path, 'returns_092026_R2B_27ABCDE1234F1Z5.json', 'application/json', null, true);
    }

    public function test_uploading_the_portal_json_matches_every_kind_of_line(): void
    {
        $this->upload($this->jsonFile())->assertRedirect(route('finance.gstr2b', ['month' => '2026-09']));
        $this->assertSame(4, Gstr2bEntry::count());

        $r = app(Gstr2bMatcher::class)->match($this->company, '2026-09');
        $this->assertSame(['matched' => 1, 'mismatch' => 1, 'missing' => 1, 'not_in_2b' => 1, 'no_gstin' => 1, 'notes' => 1], $r['summary']['counts']);
        $this->assertSame('INV/001', $r['matched'][0]['bill']->bill_number, '"INV-1" in 2B is "INV/001" in the books');
        $this->assertEqualsWithDelta(360.0, $r['mismatch'][0]['tax_diff'], 0.001);
        $this->assertSame('K-9', $r['missing'][0]['entry']->document_number);
        $this->assertSame('INV/002', $r['not_in_2b'][0]['bill']->bill_number);
        $this->assertEqualsWithDelta(1800.0 + 3960.0 + 720.0, $r['summary']['itc_in_2b'], 0.001);

        $this->actingAs($this->user)->get(route('finance.gstr2b', ['month' => '2026-09']))->assertOk()
            ->assertSee('Missing in your books')->assertSee('Kolhapur Kraft')->assertSee('+ Add purchase invoice')
            ->assertSee('Not in 2B yet')->assertSee('Amounts differ');

        $csv = $this->actingAs($this->user)->get(route('finance.gstr2b.export', ['month' => '2026-09']))->streamedContent();
        $this->assertStringContainsString('In 2B, missing in books', $csv);
        $this->assertStringContainsString('K-9', $csv);
    }

    public function test_gstr3b_says_how_much_of_the_books_credit_2b_confirms(): void
    {
        $from = \Illuminate\Support\Carbon::parse('2026-09-01');
        $to = \Illuminate\Support\Carbon::parse('2026-09-30');
        $this->assertNull(app(\App\Services\Gstr3bBuilder::class)->build($this->company, $from, $to)['itc_2b'], 'no 2B uploaded: nothing to compare');

        $this->upload($this->jsonFile());
        $r = app(\App\Services\Gstr3bBuilder::class)->build($this->company, $from, $to);
        $check = $r['itc_2b'];

        // Books: P1 900+900, P2 IGST 3,600, P3 450+450, P4 (no GSTIN) 90+90 = 6,480.
        $this->assertEqualsWithDelta(6480.0, $r['itc']['total'], 0.001, 'the books figure is unchanged');
        // 2B confirms P1 in full and P2 up to the books' 3,600 (2B shows 3,960).
        $this->assertEqualsWithDelta(5400.0, $check['in_2b']['total'], 0.001);
        $this->assertEqualsWithDelta(3600.0, $check['in_2b']['igst'], 0.001);
        // P3 is not in 2B yet and P4 never will be: hold 1,080.
        $this->assertEqualsWithDelta(1080.0, $check['not_in_2b']['total'], 0.001);
        $this->assertSame(1, $check['missing']['count'], 'K-9 is in 2B but not in the books');
        $this->assertEqualsWithDelta(720.0, $check['missing']['tax'], 0.001);
        $this->assertSame(['2026-09'], $check['uploaded']);
    }

    public function test_invoice_numbers_match_however_they_are_written(): void
    {
        $m = app(Gstr2bMatcher::class);
        foreach ([['DC/2026/0117', 'DC-2026-117'], ['INV/001', 'inv-1'], ['INV001', 'INV-1'], ['0045', '45'], ['A 12/B', 'a12b']] as [$books, $portal]) {
            $this->assertSame($m->key('27AAPFU0939F1ZV', $books), $m->key('27aapfu0939f1zv', $portal), "{$books} = {$portal}");
        }
        $this->assertNotSame($m->key('27AAPFU0939F1ZV', 'DC/2026/0117'), $m->key('27AAPFU0939F1ZV', 'DC/2026/0118'));
        $this->assertNotSame($m->key('27AAPFU0939F1ZV', 'INV/10'), $m->key('27AAPFU0939F1ZV', 'INV/1'));
    }

    public function test_a_second_upload_replaces_the_first_and_a_zip_works(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), '2b') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('returns_092026_R2B.json', $this->portalJson());
        $zip->close();

        $this->upload($this->jsonFile());
        $this->upload(new UploadedFile($zipPath, 'R2B.zip', 'application/zip', null, true))->assertSessionHasNoErrors();
        $this->assertSame(4, Gstr2bEntry::count(), 'replaced, not doubled');
    }

    public function test_another_businesses_2b_is_refused(): void
    {
        $this->upload($this->jsonFile($this->portalJson('29KLMNO1234P1Z9')))->assertSessionHasErrors('file');
        $this->upload($this->jsonFile('not json'))->assertSessionHasErrors('file');
        $this->assertSame(0, Gstr2bEntry::count());
    }

    public function test_the_portal_excel_is_read_too(): void
    {
        $path = tempnam(sys_get_temp_dir(), '2b') . '.xlsx';
        $w = new Writer();
        $w->openToFile($path);
        $w->getCurrentSheet()->setName('Read me');
        $w->addRow(Row::fromValues(['Goods and Services Tax - GSTR-2B']));
        $sheet = $w->addNewSheetAndMakeItCurrent();
        $sheet->setName('B2B');
        $w->addRow(Row::fromValues(['Taxable inward supplies received from registered persons']));
        $w->addRow(Row::fromValues(['GSTIN of supplier', 'Trade/Legal name', 'Invoice Details', '', '', '', 'Place of supply', 'Supply Attract Reverse Charge', 'Taxable Value (₹)', 'Tax Amount', '', '', '', 'GSTR-1/IFF/GSTR-5 Period', 'GSTR-1/IFF/GSTR-5 Filing Date', 'ITC Availability']));
        $w->addRow(Row::fromValues(['', '', 'Invoice number', 'Invoice type', 'Invoice Date', 'Invoice Value(₹)', '', '', '', 'Integrated Tax(₹)', 'Central Tax(₹)', 'State/UT Tax(₹)', 'Cess(₹)', '', '', '']));
        $w->addRow(Row::fromValues(['27PQRST1234U1Z1', 'Mumbai Metals', 'INV-1', 'Regular', '05-09-2026', 11800, 'Maharashtra', 'No', 10000, 0, 900, 900, 0, 'Sep-26', '11-10-2026', 'Yes']));
        $w->addRow(Row::fromValues(['27KLMNO9876P1Z3', 'Kolhapur Kraft', 'K-9', 'Regular', '12-09-2026', 4720, 'Maharashtra', 'No', 4000, 0, 360, 360, 0, 'Sep-26', '11-10-2026', 'Yes']));
        $w->close();

        $this->upload(new UploadedFile($path, 'GSTR2B.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), ['month' => '2026-09'])
            ->assertSessionHasNoErrors();

        $r = app(Gstr2bMatcher::class)->match($this->company, '2026-09');
        $this->assertSame(1, $r['summary']['counts']['matched']);
        $this->assertSame(1, $r['summary']['counts']['missing']);
        $this->assertSame('2026-09-12', $r['missing'][0]['entry']->document_date->toDateString());
        $this->assertEqualsWithDelta(720.0, $r['missing'][0]['entry']->tax(), 0.001);
    }

    public function test_add_purchase_invoice_from_a_missing_line_is_prefilled(): void
    {
        $this->actingAs($this->user)->get(route('purchases.create', [
            'bill_number' => 'K-9', 'bill_date' => '2026-09-12', 'vendor_gstin' => '27PQRST1234U1Z1', 'vendor_name' => 'Mumbai Metals',
        ]))->assertOk()->assertSee('value="K-9"', false)->assertSee('2026-09-12');
    }
}
