<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Gstr2bEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseBill;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Month end for September 2026 at Sharma Traders (27-Maharashtra,
 * GSTIN 27ABCDE1234F1Z0), filed in October, then the month is locked.
 *
 * Sales (all hand-worked; CGST = SGST = 9%, IGST = 18%):
 *   S1  05 Sep  B2B intra  Patil Stores 27AAACT2727Q1ZW   10 x 1,500 = 15,000; CGST 1,350 + SGST 1,350; value 17,700
 *   S2  12 Sep  B2B inter  Karnataka Kraft 29VWXYZ9876L1ZI  4 x 2,500 = 10,000; IGST 1,800;             value 11,800
 *   S3  20 Sep  B2C intra  walk-in (no GSTIN, MH)         3 x 450   =  1,350; CGST 121.50 + SGST 121.50; value 1,593
 *   D   28 Sep  draft, B2C intra 1 x 2,000 (never in a return while a draft)
 *
 * Purchases (ITC eligible):
 *   P1  08 Sep  Pune Paper Mart 27PQRST5678K1Z2 "PPM/0441"  20 x 400 = 8,000; CGST 720 + SGST 720; 9,440
 *   P2  25 Sep  Delhi Digital 07LMNOP4321Q1Z6 "DD-19"       5,000;            IGST 900;            5,900
 *
 * GSTR-2B (Sep): P1 as "PPM/441" (same invoice), plus SS-7 from Surat
 * Synthetics 24ABCDE1234F1Z6 (2,000 + IGST 360) that is not in the books.
 * P2 is not in 2B (supplier has not filed).
 *
 * GSTR-3B Sep:
 *   3.1(a)   taxable 15,000 + 10,000 + 1,350 = 26,350; IGST 1,800; CGST 1,350 + 121.50 = 1,471.50; SGST 1,471.50
 *   4(A)(5)  books: IGST 900, CGST 720, SGST 720 (2,340); in 2B only: CGST 720 + SGST 720 = 1,440
 *   6.1 cash (books):   IGST 1,800 - 900 = 900; CGST 1,471.50 - 720 = 751.50; SGST 751.50; total 2,403
 *   6.1 cash (2B only): IGST 1,800;             CGST 751.50;                  SGST 751.50; total 3,303
 */
class MonthEndGstJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        $this->biz = $this->makeBusiness();
        $this->owner = $this->ownerOf($this->biz);
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function paper(float $qty, float $rate): array
    {
        return $this->line($rate, 18, $qty, ['description' => 'A4 copier paper', 'hsn_sac' => '48025690', 'unit' => 'PAC']);
    }

    private function notebooks(float $qty, float $rate): array
    {
        return $this->line($rate, 18, $qty, ['description' => 'Long notebooks', 'hsn_sac' => '48201010', 'unit' => 'NOS']);
    }

    private function vendor(string $name, string $gstin, string $code): Vendor
    {
        return $this->biz->vendors()->create(['user_id' => $this->owner->id, 'name' => $name, 'gstin' => $gstin, 'state_id' => $this->state($code)->id]);
    }

    private function billPayload(Vendor $v, string $no, string $date, float $qty, float $rate, array $extra = []): array
    {
        return array_merge([
            'vendor_id' => $v->id, 'bill_number' => $no, 'bill_date' => $date, 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Paper reams', 'hsn_sac' => '48025690', 'quantity' => $qty, 'unit' => 'PAC', 'rate' => $rate, 'gst_rate' => 18]],
        ], $extra);
    }

    private function portal2bJson(): string
    {
        $item = fn ($tx, $i, $c, $s) => ['num' => 1, 'rt' => 18, 'txval' => $tx, 'igst' => $i, 'cgst' => $c, 'sgst' => $s, 'cess' => 0];

        return json_encode(['chksum' => 'abc', 'data' => [
            'gstin' => '27ABCDE1234F1Z0', 'rtnprd' => '092026', 'version' => '1.0', 'gendt' => '14-10-2026',
            'docdata' => ['b2b' => [
                ['ctin' => '27PQRST5678K1Z2', 'trdnm' => 'Pune Paper Mart', 'supfildt' => '11-10-2026', 'supprd' => '092026', 'inv' => [
                    ['inum' => 'PPM/441', 'typ' => 'R', 'dt' => '08-09-2026', 'val' => 9440, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'items' => [$item(8000, 0, 720, 720)]],
                ]],
                ['ctin' => '24ABCDE1234F1Z6', 'trdnm' => 'Surat Synthetics', 'supfildt' => '10-10-2026', 'supprd' => '092026', 'inv' => [
                    ['inum' => 'SS-7', 'typ' => 'R', 'dt' => '15-09-2026', 'val' => 2360, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'items' => [$item(2000, 360, 0, 0)]],
                ]],
            ]],
        ]]);
    }

    /** September's books, as the scenario above; returns [S1, S2, S3, draft, P1, P2]. */
    private function septemberBooks(): array
    {
        $patil = $this->customerOf($this->biz, '27', ['name' => 'Patil Stores', 'gstin' => '27AAACT2727Q1ZW']);
        $kraft = $this->customerOf($this->biz, '29', ['name' => 'Karnataka Kraft', 'gstin' => '29VWXYZ9876L1ZI', 'email' => 'ap@kraft.example']);
        $walkIn = $this->customerOf($this->biz, '27', ['name' => 'Ravi (walk-in)', 'email' => null]);

        // A draft made on 28 Sep and never issued (yet).
        $draft = $this->webDraft($this->owner, $walkIn, [$this->notebooks(1, 2000)], ['invoice_date' => '2026-09-28']);

        $s1 = $this->webIssued($this->owner, $patil, [$this->paper(10, 1500)], ['invoice_date' => '2026-09-05']);
        $s2 = $this->webIssued($this->owner, $kraft, [$this->paper(4, 2500)], ['invoice_date' => '2026-09-12']);
        $s3 = $this->webIssued($this->owner, $walkIn, [$this->notebooks(3, 450)], ['invoice_date' => '2026-09-20']);

        // The documents say what the scenario says.
        $this->assertMoney(15000, $s1->subtotal);
        $this->assertMoney(1350, $s1->total_cgst);
        $this->assertMoney(1350, $s1->total_sgst);
        $this->assertMoney(17700, $s1->grand_total);
        $this->assertTrue((bool) $s2->is_interstate);
        $this->assertMoney(1800, $s2->total_igst);
        $this->assertMoney(0, $s2->total_cgst);
        $this->assertMoney(11800, $s2->grand_total);
        $this->assertMoney(121.50, $s3->total_cgst);
        $this->assertMoney(121.50, $s3->total_sgst);
        $this->assertMoney(1593, $s3->grand_total);

        $ppm = $this->vendor('Pune Paper Mart', '27PQRST5678K1Z2', '27');
        $dd = $this->vendor('Delhi Digital', '07LMNOP4321Q1Z6', '07');
        $this->actingAs($this->owner)->post(route('purchases.store'), $this->billPayload($ppm, 'PPM/0441', '2026-09-08', 20, 400))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('purchases.store'), $this->billPayload($dd, 'DD-19', '2026-09-25', 1, 5000))->assertSessionHasNoErrors();
        $p1 = PurchaseBill::where('bill_number', 'PPM/0441')->firstOrFail();
        $p2 = PurchaseBill::where('bill_number', 'DD-19')->firstOrFail();
        $this->assertMoney(720, $p1->total_cgst);
        $this->assertMoney(720, $p1->total_sgst);
        $this->assertMoney(9440, $p1->grand_total);
        $this->assertTrue((bool) $p2->is_interstate, 'Delhi vendor: IGST');
        $this->assertMoney(900, $p2->total_igst);

        return [$s1, $s2, $s3, $draft, $p1, $p2];
    }

    private function upload2b(): void
    {
        $file = UploadedFile::fake()->createWithContent('returns_092026_R2B_27ABCDE1234F1Z0.json', $this->portal2bJson());
        $this->actingAs($this->owner)->post(route('finance.gstr2b.upload'), ['file' => $file])
            ->assertRedirect(route('finance.gstr2b', ['month' => '2026-09']))
            ->assertSessionHas('status', 'GSTR-2B for September 2026 read: 2 documents. The match is below.');
    }

    private function gstr1Json(string $month): array
    {
        $res = $this->actingAs($this->owner)->get(route('finance.gstr1.export.json', ['month' => $month]))->assertOk();
        $this->assertStringContainsString('GSTR1_27ABCDE1234F1Z0_' . substr($month, 5, 2) . substr($month, 0, 4) . '.json', (string) $res->headers->get('content-disposition'));

        return json_decode($this->body($res), true, 512, JSON_THROW_ON_ERROR);
    }

    private function lockSeptember(): void
    {
        $this->actingAs($this->owner)->patch(route('companies.update', $this->biz), ['section' => 'books_lock', 'books_locked_until' => '2026-09-30'])
            ->assertRedirect(route('companies.edit', ['company' => $this->biz->id]) . '#books-lock')
            ->assertSessionHas('status', 'Books locked up to 30 Sep 2026.');
        $this->assertSame('2026-09-30', $this->biz->fresh()->books_locked_until->toDateString());
    }

    // ------------------------------------------------------------------
    // The journey
    // ------------------------------------------------------------------

    public function test_september_is_returned_from_the_books_then_locked_and_october_carries_on(): void
    {
        [$s1, $s2, $s3, $draft, $p1, $p2] = $this->septemberBooks();

        // 1. GSTR-2B upload and match.
        $this->upload2b();
        $this->assertSame(2, Gstr2bEntry::where('company_id', $this->biz->id)->where('period', '2026-09')->count());
        $match = $this->actingAs($this->owner)->get(route('finance.gstr2b', ['month' => '2026-09']))->assertOk()
            ->assertSee('Surat Synthetics')->assertSee('DD-19')->viewData('result');
        $this->assertSame(['matched' => 1, 'mismatch' => 0, 'missing' => 1, 'not_in_2b' => 1, 'no_gstin' => 0, 'notes' => 0], $match['summary']['counts']);
        $this->assertSame('PPM/0441', $match['matched'][0]['bill']->bill_number, '"PPM/441" in 2B is "PPM/0441" in the books');
        $this->assertSame('DD-19', $match['not_in_2b'][0]['bill']->bill_number);
        // 2B credit: 720 + 720 (P1) + 360 (SS-7) = 1,800.
        $this->assertMoney(1800, $match['summary']['itc_in_2b']);

        // 2. GSTR-1 screen.
        $g1 = $this->actingAs($this->owner)->get(route('finance.gstr1', ['month' => '2026-09']))->assertOk()
            ->assertSee('27AAACT2727Q1ZW')->assertSee('29VWXYZ9876L1ZI')->assertSee('26,350');
        $sum = $g1->viewData('summary');
        $this->assertMoney(26350, $sum['taxable']);
        $this->assertMoney(1800, $sum['igst']);
        $this->assertMoney(1471.50, $sum['cgst']);
        $this->assertMoney(1471.50, $sum['sgst']);
        $this->assertSame(3, (int) $sum['invoices'], 'the 28 Sep draft is not a supply');
        $tables = $g1->viewData('tables');
        $this->assertSame(2, $tables['b2b']['records']);
        $this->assertMoney(25000, $tables['b2b']['taxable']);
        $this->assertSame(1, $tables['b2cs']['records']);
        $this->assertMoney(1350, $tables['b2cs']['taxable']);

        // 3. GSTR-1 JSON for the portal.
        $json = $this->gstr1Json('2026-09');
        $this->assertSame('27ABCDE1234F1Z0', $json['gstin']);
        $this->assertSame('092026', $json['fp']);
        $this->assertEquals([
            ['ctin' => '27AAACT2727Q1ZW', 'inv' => [[
                'inum' => $s1->invoice_number, 'idt' => '05-09-2026', 'val' => 17700, 'pos' => '27', 'rchrg' => 'N', 'inv_typ' => 'R',
                'itms' => [['num' => 1, 'itm_det' => ['txval' => 15000, 'rt' => 18, 'iamt' => 0, 'camt' => 1350, 'samt' => 1350, 'csamt' => 0]]],
            ]]],
            ['ctin' => '29VWXYZ9876L1ZI', 'inv' => [[
                'inum' => $s2->invoice_number, 'idt' => '12-09-2026', 'val' => 11800, 'pos' => '29', 'rchrg' => 'N', 'inv_typ' => 'R',
                'itms' => [['num' => 1, 'itm_det' => ['txval' => 10000, 'rt' => 18, 'iamt' => 1800, 'camt' => 0, 'samt' => 0, 'csamt' => 0]]],
            ]]],
        ], $json['b2b']);
        // B2C intra: one row by place of supply and rate; no IGST key for intra-state.
        $this->assertEquals([['sply_ty' => 'INTRA', 'pos' => '27', 'typ' => 'OE', 'txval' => 1350, 'rt' => 18, 'camt' => 121.5, 'samt' => 121.5, 'csamt' => 0]], $json['b2cs']);
        foreach (['b2cl', 'cdnr', 'cdnur', 'exp', 'nil'] as $empty) {
            $this->assertArrayNotHasKey($empty, $json, "{$empty} has nothing this month");
        }
        // Table 12: both B2B invoices are paper at 18%: 14 PAC, 25,000, IGST 1,800, CGST 1,350, SGST 1,350.
        $this->assertEquals([['num' => 1, 'hsn_sc' => '48025690', 'desc' => 'A4 copier paper', 'uqc' => 'PAC', 'qty' => 14, 'rt' => 18,
            'txval' => 25000, 'iamt' => 1800, 'camt' => 1350, 'samt' => 1350, 'csamt' => 0]], $json['hsn']['hsn_b2b']);
        $this->assertEquals([['num' => 1, 'hsn_sc' => '48201010', 'desc' => 'Long notebooks', 'uqc' => 'NOS', 'qty' => 3, 'rt' => 18,
            'txval' => 1350, 'iamt' => 0, 'camt' => 121.5, 'samt' => 121.5, 'csamt' => 0]], $json['hsn']['hsn_b2c']);
        // Table 13: three invoices issued, none cancelled.
        $this->assertEquals([['doc_num' => 1, 'doc_typ' => 'Invoices for outward supply', 'docs' => [[
            'num' => 1, 'from' => $s1->invoice_number, 'to' => $s3->invoice_number, 'totnum' => 3, 'cancel' => 0, 'net_issue' => 3,
        ]]]], $json['doc_issue']['doc_det']);
        $septemberFile = $this->body($this->actingAs($this->owner)->get(route('finance.gstr1.export.json', ['month' => '2026-09'])));

        // 4. GSTR-3B screen.
        $g3 = $this->actingAs($this->owner)->get(route('finance.gstr3b', ['month' => '2026-09']))->assertOk()->assertSee('26,350');
        $out = $g3->viewData('outward');
        $this->assertMoney(26350, $out['taxable']);
        $this->assertMoney(1800, $out['igst']);
        $this->assertMoney(1471.50, $out['cgst']);
        $this->assertMoney(1471.50, $out['sgst']);
        $books = $g3->viewData('itc_other');
        $this->assertMoney(900, $books['igst']);
        $this->assertMoney(720, $books['cgst']);
        $this->assertMoney(720, $books['sgst']);
        $this->assertMoney(0, $g3->viewData('itc_rcm')['igst'] + $g3->viewData('itc_rcm')['cgst'] + $g3->viewData('itc_rcm')['sgst']);
        $cash = $g3->viewData('netCash');
        $this->assertMoney(900, $cash['igst']);
        $this->assertMoney(751.50, $cash['cgst']);
        $this->assertMoney(751.50, $cash['sgst']);
        $this->assertMoney(2403, $cash['total']);
        // s.16(2)(aa): only what 2B shows can be claimed.
        $twoB = $g3->viewData('itc_2b');
        $this->assertNotNull($twoB, 'a 2B is uploaded for September');
        $this->assertMoney(0, $twoB['in_2b']['igst']);
        $this->assertMoney(720, $twoB['in_2b']['cgst']);
        $this->assertMoney(720, $twoB['in_2b']['sgst']);
        $this->assertMoney(1440, $twoB['in_2b']['total']);
        $this->assertMoney(900, $twoB['not_in_2b']['igst'], 'DD-19 held until the supplier files');
        $this->assertSame(1, $twoB['missing']['count'], 'SS-7 is in 2B, not in the books');
        $this->assertMoney(360, $twoB['missing']['tax']);
        $this->assertMoney(1800, $twoB['cash_if_2b_only']['igst']);
        $this->assertMoney(751.50, $twoB['cash_if_2b_only']['cgst']);
        $this->assertMoney(751.50, $twoB['cash_if_2b_only']['sgst']);
        $this->assertMoney(3303, $twoB['cash_if_2b_only']['total']);

        // 5. Lock the books up to 30 Sep 2026 (logged).
        $this->lockSeptember();
        $this->assertTrue(AuditLog::where('action', 'company.books_lock_changed')->exists());

        $counter = (int) $this->biz->fresh()->invoice_counter;
        $receipts = (int) $this->biz->fresh()->receipt_counter;
        $notes = (int) $this->biz->fresh()->credit_note_counter;

        // 6a. Issuing the September draft: refused, still a draft without a number.
        $this->actingAs($this->owner)->from(route('invoices.show', $draft))->post(route('invoices.finalize', $draft))
            ->assertRedirect(route('invoices.show', $draft))
            ->assertSessionHas('error', 'Books are locked up to 30 Sep 2026. Cannot issue an invoice dated on or before that.');
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->invoice_number);

        // 6b. A receipt dated in September: refused.
        $this->webPay($this->owner, $s2, 11800, ['received_at' => '2026-09-30'])
            ->assertSessionHasErrors(['received_at' => 'Books are locked up to 30 Sep 2026. Date the receipt after that.']);
        $this->assertSame(0, Payment::count());
        $this->assertMoney(11800, $s2->fresh()->balance);

        // 6c. A credit note dated in September: refused.
        $this->actingAs($this->owner)->from(route('credit-notes.create', $s1))->post(route('credit-notes.store', $s1), [
            'credit_note_date' => '2026-09-30', 'amount' => 1180, 'reason' => 'post_sale_discount',
        ])->assertRedirect(route('credit-notes.create', $s1))
            ->assertSessionHas('error', 'Books are locked up to 30 Sep 2026. Credit note date must be after that.');
        $this->assertSame(0, CreditNote::count());
        $this->assertMoney(0, $s1->fresh()->credited_amount);

        // 6d. Editing or deleting a September purchase invoice: refused.
        $this->actingAs($this->owner)->from(route('purchases.edit', $p1))
            ->put(route('purchases.update', $p1), $this->billPayload(Vendor::findOrFail($p1->vendor_id), 'PPM/0441', '2026-09-08', 25, 400))
            ->assertSessionHas('error', 'Books are locked up to 30 Sep 2026. This purchase invoice cannot be changed.');
        $this->assertMoney(8000, $p1->fresh()->subtotal);
        $this->assertMoney(20, $p1->fresh()->items()->value('quantity'));
        $this->actingAs($this->owner)->from(route('purchases.show', $p1))->delete(route('purchases.destroy', $p1))
            ->assertSessionHas('error', 'Books are locked up to 30 Sep 2026. This purchase invoice cannot be deleted.');
        $this->assertNotNull(PurchaseBill::find($p1->id));

        // 6e. Cancelling a September invoice: refused (a credit note is the way).
        $this->actingAs($this->owner)->from(route('invoices.show', $s3))->post(route('invoices.cancel', $s3), ['cancellation_reason' => 'Billed twice by mistake'])
            ->assertSessionHas('error', 'Books are locked up to 30 Sep 2026. This invoice cannot be cancelled. Issue a credit note instead.');
        $this->assertSame('final', $s3->fresh()->status);

        // Nothing moved: no number, receipt or note was used up, and September's return file is byte for byte the same.
        $this->assertSame($counter, (int) $this->biz->fresh()->invoice_counter);
        $this->assertSame($receipts, (int) $this->biz->fresh()->receipt_counter);
        $this->assertSame($notes, (int) $this->biz->fresh()->credit_note_counter);
        $this->assertSame($septemberFile, $this->body($this->actingAs($this->owner)->get(route('finance.gstr1.export.json', ['month' => '2026-09']))));

        // 7. October still works.
        // 7a. Re-date the draft to 1 Oct and issue it: next number in the series.
        $this->actingAs($this->owner)->put(route('invoices.update', $draft), [
            'customer_id' => $draft->customer_id, 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-31',
            'items' => [$this->notebooks(1, 2000)],
        ])->assertSessionHasNoErrors();
        $issued = $this->webIssue($this->owner, $draft->fresh());
        $this->assertSame($counter + 1, (int) $this->biz->fresh()->invoice_counter, 'the next number, none skipped');
        // 2,000 @18% intra: CGST 180 + SGST 180 = 2,360.
        $this->assertMoney(2360, $issued->grand_total);

        // 7b. Money received today against the September invoice: allowed (the lock is on the receipt date).
        $this->webPay($this->owner, $s2, 11800, ['received_at' => '2026-10-12'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $s2->fresh()->status);
        $this->assertMoney(0, $s2->fresh()->balance);

        // 7c. A discount credit note today on the September invoice: 1,180 of 17,700 = 1/15
        //     -> taxable 15,000/15 = 1,000; CGST 1,350/15 = 90; SGST 90.
        $this->actingAs($this->owner)->post(route('credit-notes.store', $s1), [
            'credit_note_date' => '2026-10-12', 'amount' => 1180, 'reason' => 'post_sale_discount',
        ])->assertRedirect(route('invoices.show', $s1))->assertSessionHasNoErrors();
        $cn = CreditNote::firstOrFail();
        $this->assertMoney(1000, $cn->taxable_value);
        $this->assertMoney(90, $cn->total_cgst);
        $this->assertMoney(90, $cn->total_sgst);
        // 17,700 - 1,180 = 16,520 still owed.
        $this->assertMoney(16520, $s1->fresh()->balance);

        // 7d. An October purchase invoice is recorded.
        $this->actingAs($this->owner)->post(route('purchases.store'), $this->billPayload(Vendor::findOrFail($p1->vendor_id), 'PPM/0502', '2026-10-05', 10, 400))
            ->assertSessionHasNoErrors();
        $this->assertNotNull(PurchaseBill::where('bill_number', 'PPM/0502')->first());

        // October's GSTR-1 carries the note (Table 9B, period of the note's date) and the re-dated invoice.
        $oct = $this->gstr1Json('2026-10');
        $this->assertSame('102026', $oct['fp']);
        $this->assertEquals([['ctin' => '27AAACT2727Q1ZW', 'nt' => [[
            'ntty' => 'C', 'nt_num' => $cn->credit_note_number, 'nt_dt' => '12-10-2026', 'val' => 1180, 'pos' => '27', 'rchrg' => 'N', 'inv_typ' => 'R',
            'itms' => [['num' => 1, 'itm_det' => ['txval' => 1000, 'rt' => 18, 'iamt' => 0, 'camt' => 90, 'samt' => 90, 'csamt' => 0]]],
        ]]]], $oct['cdnr']);
        $this->assertEquals([['sply_ty' => 'INTRA', 'pos' => '27', 'typ' => 'OE', 'txval' => 2000, 'rt' => 18, 'camt' => 180, 'samt' => 180, 'csamt' => 0]], $oct['b2cs']);
        // ...and September's file has not moved.
        $this->assertSame($septemberFile, $this->body($this->actingAs($this->owner)->get(route('finance.gstr1.export.json', ['month' => '2026-09']))));
    }

    // ------------------------------------------------------------------
    // Focused checks
    // ------------------------------------------------------------------

    /**
     * s.16(2)(aa): credit on a supplier invoice can be taken only once it is in
     * GSTR-2B. With September's 2B uploaded, Gstr3bBuilder works out that only
     * 1,440 (P1) is confirmed and 900 (P2, DD-19) must be held, and says so in
     * its own comment: "so nobody copies a books figure the portal will not
     * accept". The GSTR-3B page shows 4(A)(5) from the books (2,340 incl. the
     * 900 IGST) and must also show the 2B-confirmed credit and the cash payable
     * on it (3,303), or an owner copying the page over-claims ITC.
     */
    public function test_the_gstr3b_page_shows_how_much_of_the_credit_gstr2b_confirms(): void
    {
        $this->septemberBooks();
        $this->upload2b();

        $text = $this->flatten($this->actingAs($this->owner)->get(route('finance.gstr3b', ['month' => '2026-09']))->assertOk()->getContent());
        $this->assertStringContainsString('1,440', $text, 'the ITC GSTR-2B confirms (CGST 720 + SGST 720) is on the page');
        $this->assertStringContainsString('3,303', $text, 'the cash payable if only 2B credit is claimed is on the page');
    }

    /**
     * P3: the GSTR-3B counter strip says "Expenses in period". Gstr3bBuilder
     * calls it "standalone expenses only", and its ITC sum leaves out the
     * expense rows mirrored from purchase invoices (purchase_bill_id set) -
     * but the counter counts them, so two purchase invoices and no expenses
     * read "Expenses in period 2".
     */
    public function test_the_gstr3b_expense_counter_does_not_count_purchase_invoices(): void
    {
        $this->septemberBooks();

        $page = $this->actingAs($this->owner)->get(route('finance.gstr3b', ['month' => '2026-09']))->assertOk();
        $this->assertSame(2, (int) $page->viewData('purchaseBillCount'));
        $this->assertSame(0, (int) $page->viewData('expenseCount'), 'no expense was entered in September; the two rows are purchase invoices');
    }

    public function test_the_books_lock_form_is_only_for_the_owner_of_the_business(): void
    {
        $other = $this->makeBusiness(['name' => 'Bengaluru Furnishers', 'state_id' => $this->state('29')->id, 'gstin' => '29ABCDE1234F1ZW']);
        $this->actingAs($this->ownerOf($other))->patch(route('companies.update', $this->biz), ['section' => 'books_lock', 'books_locked_until' => '2026-09-30'])
            ->assertForbidden();
        $this->assertNull($this->biz->fresh()->books_locked_until);

        // A date that is not a date is refused.
        $this->actingAs($this->owner)->patch(route('companies.update', $this->biz), ['section' => 'books_lock', 'books_locked_until' => '31-31-2026'])
            ->assertSessionHasErrors('books_locked_until');
        $this->assertNull($this->biz->fresh()->books_locked_until);

        // Unlocking is a deliberate, logged step too.
        $this->lockSeptember();
        $this->actingAs($this->owner)->patch(route('companies.update', $this->biz), ['section' => 'books_lock', 'books_locked_until' => ''])
            ->assertSessionHas('status', 'Books unlocked. All entries can be edited.');
        $this->assertNull($this->biz->fresh()->books_locked_until);
        $this->assertSame(2, AuditLog::where('action', 'company.books_lock_changed')->count());
    }

    /**
     * After the lock, a receipt dated today (October) may be recorded against
     * a September invoice - recordPayment checks only the receipt date. If it
     * was entered wrongly, removing it must be allowed by the same rule: it
     * changes October's books only. deletePayment also refuses when the
     * INVOICE date is locked, so a mistaken October receipt can never be
     * corrected.
     */
    public function test_a_receipt_recorded_in_an_open_month_against_a_locked_month_invoice_can_be_reversed(): void
    {
        [, $s2] = $this->septemberBooks();
        $this->lockSeptember();

        // Typed 11,800 instead of the 1,800 actually received.
        $this->webPay($this->owner, $s2, 11800, ['received_at' => '2026-10-12'])->assertSessionHasNoErrors();
        $payment = Payment::firstOrFail();
        $this->assertSame('paid', $s2->fresh()->status);

        $this->actingAs($this->owner)->from(route('invoices.show', $s2))->delete(route('payments.destroy', $payment))
            ->assertSessionMissing('error');
        $this->assertNull(Payment::find($payment->id), 'an October receipt is reversible while October is open');
        $this->assertMoney(11800, $s2->fresh()->balance);
        $this->assertSame('final', $s2->fresh()->status);
    }
}
