<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Gstr1Builder;
use App\Services\Gstr3bBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * One month of a Maharashtra business, every GSTR-1 table and GSTR-3B row
 * worked out by hand and asserted to the paisa - through the real HTTP
 * endpoints, so what the user downloads is what is checked.
 *
 * Sales, September 2026 (company in 27-Maharashtra):
 *   S1  B2B intra   10 x 1,000 @18% + 2 x 500 @0%          taxable 10,000 + nil 1,000; CGST 900, SGST 900
 *   S2  B2B inter   20,000 @18% + 5,000 @5%, discount 2,500 -> 18,000 + 4,500; IGST 3,240 + 225 = 3,465
 *   S3  B2CL        unregistered KA, 1,50,000 @18%          IGST 27,000 (value 1,77,000 > 1 lakh)
 *   S4  B2CS inter  unregistered KA, 50,000 @5%             IGST 2,500
 *   S5  B2CS intra  unregistered MH, 3 x 333.33 @18%        999.99; CGST 90, SGST 90
 *   S6  B2B RCM     8,000 @5%, tax paid by the recipient    nothing payable by us
 *   S7  issued then cancelled (2,000 @18%)                 Table 13 only
 *   S8  draft (99,999 @18%)                                 nowhere
 *   A1  B2B inter dated 20 Aug 2026, 10,000 @18%            August - only its September credit note counts
 *
 * Credit notes, September:
 *   CN1 half of S1 (6,400)    -> 5,000 @18% (CGST/SGST 450) to cdnr, 500 nil off Table 8
 *   CN2 10% of S3 (17,700)    -> 15,000 @18%, IGST 2,700 to cdnur (B2CL)
 *   CN3 all of S5 (1,180)     -> nets the B2CS line to nothing
 *   CN4 1,000 of S6 (RCM)     -> cdnr with reverse charge Y; no effect on 3B
 *   CN5 half of A1 (5,900)    -> 5,000 @18%, IGST 900 to cdnr
 *
 * Purchases, September:
 *   P1 intra 10,000 @18% eligible (CGST/SGST 900)   P2 inter 20,000 @18% eligible (IGST 3,600)
 *   P3 RCM GTA 10,000 @5% eligible (CGST/SGST 250)  P4 intra 10,000 @5% blocked
 *   E1 expense intra GST 180 eligible               E2 expense inter GST 360 eligible
 *   E3 expense GST 500 blocked                      M1 cash memo intra 2,000 @18% eligible (CGST/SGST 180)
 */
class GstReturnsReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $mh;
    private State $ka;
    private array $inv = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-28 11:00:00'));

        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'state_id' => $this->mh->id,
            'gstin' => '27ABCDE1234F1Z5',
            'composition_dealer' => false,
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();

        $b2bMh = $this->customer($this->mh, '27FGHIJ5678K1Z3', 'Patil Traders');
        $b2bKa = $this->customer($this->ka, '29KLMNO1234P1Z9', 'Rao Industries');
        $urKa = $this->customer($this->ka, null, 'Walk-in Bengaluru');
        $urMh = $this->customer($this->mh, null, 'Walk-in Pune');

        $this->inv['A1'] = $this->issue($b2bKa, '2026-08-20', [$this->line('Audit', '998221', 1, 10000, 18)]);
        $this->inv['S1'] = $this->issue($b2bMh, '2026-09-02', [
            $this->line('Steel brackets', '73269099', 10, 1000, 18),
            $this->line('Fresh vegetables', '07099990', 2, 500, 0),
        ]);
        $this->inv['S2'] = $this->issue($b2bKa, '2026-09-05', [
            $this->line('CNC machining', '998873', 1, 20000, 18),
            $this->line('Packaged tea', '09023020', 1, 5000, 5),
        ], ['invoice_discount' => 2500]);
        $this->inv['S3'] = $this->issue($urKa, '2026-09-08', [$this->line('Generator', '85023100', 1, 150000, 18)]);
        $this->inv['S4'] = $this->issue($urKa, '2026-09-10', [$this->line('Sugar', '17019990', 1, 50000, 5)]);
        $this->inv['S5'] = $this->issue($urMh, '2026-09-12', [$this->line('Cable', '85444999', 3, 333.33, 18)]);
        $this->inv['S6'] = $this->issue($b2bMh, '2026-09-14', [$this->line('Goods transport', '996511', 1, 8000, 5)], ['reverse_charge' => 1]);
        $this->inv['S7'] = $this->issue($urMh, '2026-09-15', [$this->line('Switch', '85365090', 1, 2000, 18)]);
        $this->actingAs($this->user)->post(route('invoices.cancel', $this->inv['S7']), ['cancellation_reason' => 'Raised to the wrong party'])->assertRedirect();
        $this->draft($b2bMh, '2026-09-16', [$this->line('Big order', '73269099', 1, 99999, 18)]);

        $this->creditNote('S1', '2026-09-20', 6400);
        $this->creditNote('S3', '2026-09-21', 17700);
        $this->creditNote('S5', '2026-09-22', (float) $this->inv['S5']->fresh()->grand_total);
        $this->creditNote('S6', '2026-09-23', 1000);
        $this->creditNote('A1', '2026-09-24', 5900);

        $mhVendor = $this->vendor($this->mh, 'Mumbai Metals', '27PQRST1234U1Z1');
        $kaVendor = $this->vendor($this->ka, 'Mysore Motors', '29VWXYZ1234A1Z2');
        $gta = $this->vendor($this->mh, 'Fast Freight GTA', null);
        $this->bill($mhVendor, 'P1', [$this->line('Sheet metal', '72085100', 1, 10000, 18)]);
        $this->bill($kaVendor, 'P2', [$this->line('Spindle motor', '85015210', 1, 20000, 18)]);
        $this->bill($gta, 'P3', [$this->line('Freight', '996511', 1, 10000, 5)], ['reverse_charge' => 1]);
        $this->bill($mhVendor, 'P4', [$this->line('Staff lunch', '996331', 1, 10000, 5)], ['itc_eligible' => 0]);

        $this->expense(1000, 180, false, true);
        $this->expense(2000, 360, true, true);
        $this->expense(5000, 500, false, false);
        $this->actingAs($this->user)->post(route('finance.cash-memos.store'), [
            'memo_date' => '2026-09-18', 'seller_name' => 'Local hardware', 'gst_rate' => 18,
            'is_interstate' => 0, 'itc_eligible' => 1, 'payment_mode' => 'cash',
            'items' => [['description' => 'Tools', 'quantity' => 1, 'rate' => 2000]],
        ])->assertRedirect();
    }

    // ------------------------------------------------------------------

    private function customer(State $state, ?string $gstin, string $name): Customer
    {
        return Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id,
            'state_id' => $state->id, 'gstin' => $gstin, 'name' => $name,
        ]);
    }

    private function line(string $d, string $hsn, float $q, float $rate, float $gst): array
    {
        return ['description' => $d, 'hsn_sac' => $hsn, 'quantity' => $q, 'unit' => 'NOS', 'rate' => $rate, 'gst_rate' => $gst];
    }

    private function draft(Customer $c, string $date, array $items, array $extra = []): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => $c->id, 'invoice_date' => $date, 'due_date' => $date, 'items' => $items,
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return Invoice::latest('id')->firstOrFail();
    }

    private function issue(Customer $c, string $date, array $items, array $extra = []): Invoice
    {
        $inv = $this->draft($c, $date, $items, $extra);
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasNoErrors()->assertRedirect();

        return $inv->fresh(['items']);
    }

    private function creditNote(string $key, string $date, float $amount): void
    {
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->inv[$key]), [
            'credit_note_date' => $date, 'amount' => $amount, 'reason' => 'rate_correction',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function vendor(State $state, string $name, ?string $gstin): Vendor
    {
        return $this->company->vendors()->create([
            'user_id' => $this->user->id, 'name' => $name, 'state_id' => $state->id, 'gstin' => $gstin,
        ]);
    }

    private function bill(Vendor $v, string $no, array $items, array $extra = []): void
    {
        $this->actingAs($this->user)->post(route('purchases.store'), array_merge([
            'vendor_id' => $v->id, 'bill_number' => $no, 'bill_date' => '2026-09-17', 'due_date' => '2026-10-17',
            'itc_eligible' => 1, 'expense_category' => 'office_supplies', 'payment_method' => 'neft', 'items' => $items,
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();
    }

    private function expense(float $amount, float $gst, bool $inter, bool $eligible): void
    {
        $this->actingAs($this->user)->post(route('finance.expenses.store'), [
            'entry_date' => '2026-09-19', 'category' => 'rent', 'description' => 'Expense ' . $gst,
            'amount' => $amount, 'gst_amount' => $gst, 'is_interstate' => $inter ? '1' : '0', 'itc_eligible' => $eligible ? '1' : '0',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function gstr1(): array
    {
        return app(Gstr1Builder::class)->build($this->company, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
    }

    private function gstr3b(): array
    {
        return app(Gstr3bBuilder::class)->build($this->company, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
    }

    private function assertHeads(array $expected, array $actual, string $what): void
    {
        foreach ($expected as $k => $v) {
            $this->assertEqualsWithDelta($v, (float) $actual[$k], 0.001, "{$what}: {$k}");
        }
    }

    // ------------------------------------------------------------------

    public function test_the_invoices_themselves_are_what_the_scenario_says(): void
    {
        $s2 = $this->inv['S2'];
        $this->assertHeads(['subtotal' => 22500, 'total_igst' => 3465, 'total_cgst' => 0], $s2->toArray(), 'S2');
        $s5 = $this->inv['S5'];
        $this->assertHeads(['subtotal' => 999.99, 'total_cgst' => 90, 'total_sgst' => 90], $s5->toArray(), 'S5');
        $this->assertHeads(['subtotal' => 8000, 'total_cgst' => 0, 'total_sgst' => 0, 'grand_total' => 8000], $this->inv['S6']->toArray(), 'S6 RCM');
        $this->assertSame('cancelled', $this->inv['S7']->fresh()->status);
    }

    public function test_gstr1_b2b_table_has_one_row_per_invoice_and_rate(): void
    {
        $rows = collect($this->gstr1()['b2b'])->map(fn ($r) => [$r['number'], $r['rate'], $r['taxable'], $r['rcm']])->all();

        $this->assertEqualsCanonicalizing([
            [$this->inv['S1']->invoice_number, '18', 10000.0, 'N'],
            [$this->inv['S2']->invoice_number, '18', 18000.0, 'N'],
            [$this->inv['S2']->invoice_number, '5', 4500.0, 'N'],
            [$this->inv['S6']->invoice_number, '5', 8000.0, 'Y'],
        ], $rows);

        $s2 = collect($this->gstr1()['b2b'])->firstWhere('number', $this->inv['S2']->invoice_number);
        $this->assertSame('29-Karnataka', $s2['pos']);
        $this->assertEqualsWithDelta(25965.0, $s2['value'], 0.001, 'invoice value is the whole invoice');
    }

    public function test_gstr1_b2c_tables_split_on_the_one_lakh_inter_state_limit(): void
    {
        $g = $this->gstr1();

        $this->assertCount(1, $g['b2cl']);
        $this->assertSame($this->inv['S3']->invoice_number, $g['b2cl'][0]['number']);
        $this->assertHeads(['taxable' => 150000, 'igst' => 27000, 'value' => 177000], $g['b2cl'][0], 'B2CL');

        // S5's line is wholly reversed by CN3 and drops out; S4 stays.
        $this->assertCount(1, $g['b2cs']);
        $this->assertSame('29-Karnataka', $g['b2cs'][0]['pos']);
        $this->assertHeads(['rate' => 5, 'taxable' => 50000, 'igst' => 2500, 'cgst' => 0], $g['b2cs'][0], 'B2CS');
    }

    public function test_gstr1_credit_notes_land_in_the_right_tables(): void
    {
        $g = $this->gstr1();
        $cdnr = collect($g['cdnr'])->map(fn ($r) => [$r['invoice'], $r['rate'], $r['taxable'], $r['rcm']])->all();

        $this->assertEqualsCanonicalizing([
            [$this->inv['S1']->invoice_number, '18', 5000.0, 'N'],
            [$this->inv['S6']->invoice_number, '5', 1000.0, 'Y'],
            [$this->inv['A1']->invoice_number, '18', 5000.0, 'N'],
        ], $cdnr, 'the 0% half of CN1 is not a cdnr row');

        $this->assertCount(1, $g['cdnur']);
        $this->assertSame('B2CL', $g['cdnur'][0]['ur_type']);
        $this->assertHeads(['taxable' => 15000, 'igst' => 2700], $g['cdnur'][0], 'CDNUR');
    }

    public function test_gstr1_nil_rated_and_documents(): void
    {
        $g = $this->gstr1();
        $exemp = collect($g['exemp'])->keyBy('description');
        $this->assertEqualsWithDelta(500.0, $exemp['Intra-State supplies to registered persons']['nil'], 0.001);
        $this->assertEqualsWithDelta(500.0, array_sum(array_column($g['exemp'], 'nil')), 0.001);

        $docs = collect($g['docs'])->keyBy('nature');
        $this->assertSame(7, $docs['Invoices for outward supply']['total'], 'S1-S7 (A1 is August, the draft has no number)');
        $this->assertSame(1, $docs['Invoices for outward supply']['cancelled']);
        $this->assertSame(5, $docs['Credit Note']['total']);
    }

    public function test_gstr1_hsn_summary_is_split_b2b_and_b2c_and_net_of_credit_notes(): void
    {
        $hsn = collect($this->gstr1()['hsn']);
        $brackets = $hsn->first(fn ($r) => $r['tab'] === 'B2B' && $r['hsn'] === '73269099');
        $this->assertEqualsWithDelta(10.0, $brackets['qty'], 0.001, 'credit note does not move quantity');
        $this->assertEqualsWithDelta(5000.0, $brackets['taxable'], 0.001);
        $this->assertEqualsWithDelta(450.0, $brackets['cgst'], 0.001);

        $generator = $hsn->first(fn ($r) => $r['tab'] === 'B2C' && $r['hsn'] === '85023100');
        $this->assertEqualsWithDelta(135000.0, $generator['taxable'], 0.001);
        $this->assertEqualsWithDelta(24300.0, $generator['igst'], 0.001);

        $this->assertNull($hsn->first(fn ($r) => $r['hsn'] === '73269099' && $r['taxable'] > 90000), 'the draft is not in HSN');
        $this->assertNull($hsn->first(fn ($r) => $r['hsn'] === '85365090' && $r['taxable'] > 0), 'the cancelled invoice is not in HSN');
    }

    public function test_gstr3b_outward_rows_net_of_credit_notes(): void
    {
        $d = $this->gstr3b();

        // 10,000 + 22,500 + 1,50,000 + 50,000 + 999.99 - (5,000 + 15,000 + 999.99 + 5,000)
        $this->assertHeads(['taxable' => 207500.00, 'igst' => 29365.00, 'cgst' => 450.00, 'sgst' => 450.00], $d['outward'], '3.1(a)');
        $this->assertEqualsWithDelta(500.0, $d['outward_nil'], 0.001, '3.1(c)');
        $this->assertHeads(['taxable' => 10000, 'igst' => 0, 'cgst' => 250, 'sgst' => 250], $d['rcm_inward'], '3.1(d)');
    }

    public function test_gstr3b_input_credit_by_row(): void
    {
        $d = $this->gstr3b();

        // P1 900/900, P2 IGST 3,600, P3 RCM 250/250, E1 90/90, E2 IGST 360, M1 180/180. P4, E3 blocked.
        $this->assertHeads(['igst' => 3960, 'cgst' => 1420, 'sgst' => 1420, 'total' => 6800], $d['itc'], 'ITC total');
        $this->assertHeads(['igst' => 0, 'cgst' => 250, 'sgst' => 250], $d['itc_rcm'], '4(A)(3)');
        $this->assertHeads(['igst' => 3960, 'cgst' => 1170, 'sgst' => 1170], $d['itc_other'], '4(A)(5)');
    }

    public function test_gstr3b_cash_after_set_off_in_the_legal_order_plus_reverse_charge_in_cash(): void
    {
        $d = $this->gstr3b();

        // IGST 29,365 - IGST credit 3,960 = 25,405. CGST 450 - 450 (970 left -> IGST),
        // SGST 450 - 450 (970 left -> IGST): IGST cash 25,405 - 970 - 970 = 23,465.
        // Reverse charge 250 + 250 is paid in cash on top.
        $this->assertHeads(['igst' => 23465, 'cgst' => 250, 'sgst' => 250, 'total' => 23965], $d['netCash'], '6.1 cash');
        $this->assertHeads(['igst' => 0, 'cgst' => 0, 'sgst' => 0, 'total' => 0], $d['itc_carried'], 'carried');
        // Credit used against each liability head: IGST 3,960 + 970 + 970.
        $this->assertHeads(['igst' => 5900, 'cgst' => 450, 'sgst' => 450], $d['itc_used'], 'ITC used');
    }

    public function test_gstr1_tables_tie_to_gstr3b(): void
    {
        $t = $this->gstr1()['tables'];
        $d = $this->gstr3b();

        $taxable = $t['b2b']['taxable'] + $t['b2cl']['taxable'] + $t['b2cs']['taxable'] - $t['cdnur']['taxable']
            - collect($this->gstr1()['cdnr'])->where('rcm', 'N')->sum('taxable');
        $this->assertEqualsWithDelta($d['outward']['taxable'], $taxable, 0.001);
        $this->assertEqualsWithDelta($d['outward_nil'], $t['exemp']['taxable'], 0.001);
        $this->assertSame(2, $t['b2b']['records']);
        $this->assertSame(1, $t['b2b_rcm']['records']);
    }

    public function test_the_pages_and_exports_show_the_same_figures(): void
    {
        $q = ['month' => '2026-09'];

        $this->actingAs($this->user)->get(route('finance.gstr3b', $q))->assertOk()
            ->assertSeeInOrder(['(a)', '2,07,500', '29,365', '450', '450'])
            ->assertSeeInOrder(['(c)', '500'])
            ->assertSee('23,965');

        $this->actingAs($this->user)->get(route('finance.gstr1', $q))->assertOk()
            ->assertSeeInOrder(['3.1(a)', '2,07,500', '29,365']);

        $csv = $this->actingAs($this->user)->get(route('finance.gstr3b.export.csv', $q))->streamedContent();
        $this->assertStringContainsString('"(a) Outward taxable supplies (other than zero rated, nil rated and exempted)",207500.00,29365.00,450.00,450.00', $csv);
        $this->assertStringContainsString('"Net cash payable",23465.00,250.00,250.00', $csv);

        $this->actingAs($this->user)->get(route('finance.gstr3b.export.pdf', $q))->assertOk();

        $g1csv = $this->actingAs($this->user)->get(route('invoices.gstr1', ['from' => '2026-09-01', 'to' => '2026-09-30']))->streamedContent();
        $this->assertStringContainsString('B2CL (5)', $g1csv);
        $this->assertStringContainsString('CDNUR (9B)', $g1csv);
        $this->assertStringContainsString('Netted in B2CS (7)', $g1csv);
        $this->assertStringContainsString('207500.00,29365.00,450.00,450.00', $g1csv);
    }

    public function test_the_offline_tool_workbook(): void
    {
        $res = $this->actingAs($this->user)->get(route('finance.gstr1.export.xlsx', ['month' => '2026-09']));
        $res->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'g1') . '.xlsx';
        file_put_contents($path, $res->streamedContent());

        $sheets = [];
        $reader = new Reader();
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        @unlink($path);

        $this->assertSame(['b2b', 'b2cl', 'b2cs', 'cdnr', 'cdnur', 'exp', 'exemp', 'hsn(b2b)', 'hsn(b2c)', 'docs', 'Read me'], array_keys($sheets));
        $this->assertSame('GSTIN/UIN of Recipient', $sheets['b2b'][3][0], 'headings on row 4');
        $this->assertCount(4 + 4, $sheets['b2b'], 'four b2b rows from row 5');
        $s2 = collect(array_slice($sheets['b2b'], 4))->first(fn ($r) => $r[2] === $this->inv['S2']->invoice_number && (float) $r[10] === 18.0);
        $this->assertSame('05-Sep-2026', $s2[3]);
        $this->assertSame('29-Karnataka', $s2[5]);
        $this->assertEqualsWithDelta(18000.0, (float) $s2[11], 0.001);
        $this->assertEqualsWithDelta(12800.0 + 25965.0 + 8000.0, (float) $sheets['b2b'][2][4], 0.001, 'summary invoice value counts S1, S2, S6 once each');
        $this->assertSame('C', $sheets['cdnr'][4][4]);
        $this->assertSame('B2CL', $sheets['cdnur'][4][0]);
        $services = collect(array_slice($sheets['hsn(b2b)'], 4))->first(fn ($r) => $r[0] === '998873');
        $this->assertSame('NA', $services[2], 'services report UQC NA');
    }

    public function test_the_portal_upload_json(): void
    {
        $res = $this->actingAs($this->user)->get(route('finance.gstr1.export.json', ['month' => '2026-09']));
        $res->assertOk();
        $j = json_decode($res->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('27ABCDE1234F1Z5', $j['gstin']);
        $this->assertSame('092026', $j['fp']);

        $b2b = collect($j['b2b'])->keyBy('ctin');
        $this->assertEqualsCanonicalizing(['27FGHIJ5678K1Z3', '29KLMNO1234P1Z9'], $b2b->keys()->all());
        $s2 = collect($b2b['29KLMNO1234P1Z9']['inv'])->firstWhere('inum', $this->inv['S2']->invoice_number);
        $this->assertSame('05-09-2026', $s2['idt']);
        $this->assertSame('29', $s2['pos']);
        $this->assertSame('N', $s2['rchrg']);
        $this->assertSame('R', $s2['inv_typ']);
        $this->assertEqualsWithDelta(25965.0, $s2['val'], 0.001);
        $this->assertEqualsCanonicalizing([[18000.0, 18, 3240.0], [4500.0, 5, 225.0]],
            array_map(fn ($i) => [$i['itm_det']['txval'], $i['itm_det']['rt'], $i['itm_det']['iamt']], $s2['itms']));
        $s6 = collect($b2b['27FGHIJ5678K1Z3']['inv'])->firstWhere('inum', $this->inv['S6']->invoice_number);
        $this->assertSame('Y', $s6['rchrg']);

        $this->assertSame('29', $j['b2cl'][0]['pos']);
        $this->assertEqualsWithDelta(27000.0, $j['b2cl'][0]['inv'][0]['itms'][0]['itm_det']['iamt'], 0.001);
        $this->assertArrayNotHasKey('camt', $j['b2cl'][0]['inv'][0]['itms'][0]['itm_det']);

        $this->assertEquals([['sply_ty' => 'INTER', 'pos' => '29', 'typ' => 'OE', 'txval' => 50000, 'rt' => 5, 'iamt' => 2500, 'csamt' => 0]], $j['b2cs']);

        $cdnr = collect($j['cdnr'])->keyBy('ctin');
        $this->assertCount(2, $cdnr['27FGHIJ5678K1Z3']['nt']);
        $this->assertSame('C', $cdnr['29KLMNO1234P1Z9']['nt'][0]['ntty']);
        $this->assertEqualsWithDelta(900.0, $cdnr['29KLMNO1234P1Z9']['nt'][0]['itms'][0]['itm_det']['iamt'], 0.001);
        $this->assertSame('B2CL', $j['cdnur'][0]['typ']);
        $this->assertEqualsWithDelta(15000.0, $j['cdnur'][0]['itms'][0]['itm_det']['txval'], 0.001);

        $this->assertEquals([['sply_ty' => 'INTRAB2B', 'expt_amt' => 0, 'nil_amt' => 500, 'ngsup_amt' => 0]], $j['nil']['inv']);

        $svc = collect($j['hsn']['hsn_b2b'])->firstWhere('hsn_sc', '998873');
        $this->assertSame('NA', $svc['uqc']);
        $this->assertEquals(0, $svc['qty']);
        $this->assertArrayNotHasKey('val', $svc, 'Table 12 has no total value since April 2021');
        $this->assertEqualsWithDelta(135000.0, collect($j['hsn']['hsn_b2c'])->firstWhere('hsn_sc', '85023100')['txval'], 0.001);

        $docs = collect($j['doc_issue']['doc_det'])->keyBy('doc_num');
        $this->assertSame(['num' => 1, 'from' => $docs[1]['docs'][0]['from'], 'to' => $docs[1]['docs'][0]['to'], 'totnum' => 7, 'cancel' => 1, 'net_issue' => 6], $docs[1]['docs'][0]);
        $this->assertSame(5, $docs[5]['docs'][0]['totnum']);
    }

    public function test_the_json_needs_a_gstin(): void
    {
        $this->company->update(['gstin' => null]);
        $this->actingAs($this->user)->get(route('finance.gstr1.export.json', ['month' => '2026-09']))
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_quarterly_filers_get_the_whole_quarter(): void
    {
        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => '2026-09', 'frequency' => 'quarterly']))->assertOk()
            ->assertSee('Jul-Sep 2026 (Q2 FY 2026-27)')
            ->assertSee('22 Oct 2026'); // Maharashtra: 22nd

        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $this->getJson('/api/finance/gstr3b?month=2026-08&frequency=quarterly')->assertOk()
            ->assertJsonPath('period.start', '2026-07-01')
            ->assertJsonPath('period.end', '2026-09-30')
            ->assertJsonPath('period.due_date', '2026-10-22');
        // Monthly API figures equal the web build.
        $api = $this->getJson('/api/finance/gstr3b?month=2026-09')->assertOk();
        $this->assertEqualsWithDelta(207500.0, (float) $api->json('outward.taxable'), 0.001);
        $this->assertEqualsWithDelta(23965.0, (float) $api->json('net_cash.total'), 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $api->json('outward_nil'), 0.001);
        $g1 = $this->getJson('/api/finance/gstr1?from=2026-09-01&to=2026-09-30')->assertOk();
        $this->assertEqualsWithDelta(207500.0, (float) $g1->json('gstr3b_outward.taxable'), 0.001);
    }

    public function test_a_bad_month_falls_back_instead_of_crashing(): void
    {
        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => '2026-13']))->assertOk();
        $this->actingAs($this->user)->get(route('finance.gstr1', ['month' => 'garbage']))->assertOk();
    }
}
