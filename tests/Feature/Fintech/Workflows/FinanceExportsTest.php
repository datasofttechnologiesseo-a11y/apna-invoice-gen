<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\CashMemo;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The file a CA receives must say exactly what the owner saw on screen.
 * Each money report is built here from hand-worked documents and read four
 * ways: the web screen, its CSV, its PDF (the data the PDF was drawn from,
 * re-rendered to text) and the mobile API. All four must agree to the paisa.
 *
 * "Today" is Monday 12 Oct 2026 (WorkflowFixtures). Home business: Sharma
 * Traders, Maharashtra (27).
 */
class FinanceExportsTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;
    private Customer $patil;
    private Customer $kaveri;

    /** @var array<string, array> data each PDF view was rendered with */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        $this->biz = $this->makeBusiness();
        $this->owner = $this->ownerOf($this->biz);
        $this->patil = $this->customerOf($this->biz);
        $this->kaveri = $this->customerOf($this->biz, '29', [
            'name' => 'Kaveri Agencies', 'email' => 'kaveri@example.test', 'phone' => '9876500002',
        ]);
    }

    // ---- helpers ----------------------------------------------------------

    /** Remember the data a (PDF) view is rendered with. */
    private function capture(string $view): void
    {
        View::composer($view, function ($v) use ($view) {
            $this->captured[$view] = $v->getData();
        });
    }

    private function captured(string $view): array
    {
        $this->assertArrayHasKey($view, $this->captured, "the PDF was not drawn from {$view}");

        return $this->captured[$view];
    }

    /** The PDF's own view, rendered again from the same data, as plain text. */
    private function pdfText(string $view): string
    {
        $data = Arr::except($this->captured($view), ['__env', 'app', 'errors']);

        return $this->flatten(view($view, $data)->render());
    }

    /** Rows of the CSV table whose header row starts with $firstHeader, up to the next blank line. */
    private function table(array $rows, string $firstHeader): array
    {
        $start = null;
        foreach ($rows as $i => $r) {
            if (($r[0] ?? null) === $firstHeader) {
                $start = $i;
                break;
            }
        }
        $this->assertNotNull($start, "no table headed '{$firstHeader}' in the CSV");
        $out = [];
        for ($j = $start + 1; $j < count($rows); $j++) {
            if (count(array_filter($rows[$j], fn ($c) => $c !== null && $c !== '')) === 0) {
                break;
            }
            $out[] = $rows[$j];
        }

        return $out;
    }

    /** The CSV row whose first non-empty cell is $label. */
    private function rowLabelled(array $rows, string $label): array
    {
        foreach ($rows as $r) {
            $cells = array_values(array_filter($r, fn ($c) => $c !== null && $c !== ''));
            if (($cells[0] ?? null) === $label) {
                return $r;
            }
        }
        $this->fail("no row labelled '{$label}' in the CSV");
    }

    private function memberOf(Company $company, string $role): User
    {
        $member = User::factory()->create(['name' => 'Member ' . $role]);
        CompanyMember::create([
            'company_id' => $company->id, 'user_id' => $member->id, 'email' => $member->email,
            'role' => $role, 'token' => CompanyMember::newToken(), 'invited_by' => $company->user_id,
            'accepted_at' => now(),
        ]);
        $member->forceFill(['active_company_id' => $company->id])->save();

        return $member->fresh();
    }

    private function webExpense(array $data)
    {
        return $this->actingAs($this->owner)->post(route('finance.expenses.store'), array_merge([
            'payment_method' => 'bank',
        ], $data));
    }

    /**
     * Four open invoices, one in each aging bucket, plus documents that must
     * stay out of the report. Issued in date order, so INV-0001 is the oldest.
     */
    private function seedReceivables(): void
    {
        // INV-0001 to Kaveri (Karnataka, inter-state), 1 Jun, due 1 Jun:
        // 1 x 2,000 @ 18% IGST = 2,000 + 360 = 2,360. Credit note 236 today
        // (10%: taxable 200, IGST 36) -> balance 2,124. 133 days overdue -> 90+.
        $a = $this->webIssued($this->owner, $this->kaveri, [$this->line(2000, 18, 1)], ['invoice_date' => '2026-06-01', 'due_date' => '2026-06-01']);
        $this->actingAs($this->owner)->post(route('credit-notes.store', $a), [
            'credit_note_date' => now()->toDateString(), 'amount' => 236, 'reason' => 'post_sale_discount',
        ])->assertSessionHasNoErrors();
        // INV-0002 to Patil, 1 Jul, due 31 Jul: 2 x 500 @ 5% = 1,000 + 25 + 25 = 1,050.
        // 31 Jul -> 12 Oct = 31 (Aug) + 30 (Sep) + 12 = 73 days -> 61-90.
        $this->webIssued($this->owner, $this->patil, [$this->line(500, 5, 2)], ['invoice_date' => '2026-07-01', 'due_date' => '2026-07-31']);
        // INV-0003 to Kaveri, 20 Aug, due 1 Sep: 1 x 500 @ 18% IGST = 590.
        // 1 Sep -> 12 Oct = 30 + 11 = 41 days -> 31-60.
        $this->webIssued($this->owner, $this->kaveri, [$this->line(500, 18, 1)], ['invoice_date' => '2026-08-20', 'due_date' => '2026-09-01']);
        // INV-0004 to Patil, today, due 11 Nov: 1,180; 180 received -> 1,000 open, not yet due -> current.
        $d = $this->webIssued($this->owner, $this->patil, [$this->line(1000, 18, 1)]);
        $this->webPay($this->owner, $d, 180)->assertSessionHasNoErrors();
        // Out of the report: a fully paid invoice, a draft, a cancelled invoice,
        // and another business's open invoice.
        $paid = $this->webIssued($this->owner, $this->patil, [$this->line(100, 18, 1)]);
        $this->webPay($this->owner, $paid, 118)->assertSessionHasNoErrors();
        $this->webDraft($this->owner, $this->patil, [$this->line(77777, 18, 1)]);
        $cancelled = $this->webIssued($this->owner, $this->patil, [$this->line(3000, 18, 1)]);
        $this->actingAs($this->owner)->post(route('invoices.cancel', $cancelled), ['cancellation_reason' => 'Raised twice'])->assertSessionHasNoErrors();
        $rival = $this->makeBusiness(['name' => 'Rival Traders', 'gstin' => '29ABCDE1234F1ZW', 'state_id' => $this->state('29')->id]);
        $this->webIssued($this->ownerOf($rival), $this->customerOf($rival, '29', ['name' => 'Rival Customer']), [$this->line(9000, 18, 1)]);
    }

    // ---- receivables aging -------------------------------------------------

    public function test_receivables_aging_screen_csv_pdf_and_api_agree_to_the_paisa(): void
    {
        $this->seedReceivables();

        // Hand-worked summary: current 1,000; 31-60 590; 61-90 1,050; 90+ 2,124;
        // total 1,000 + 590 + 1,050 + 2,124 = 4,764 over 4 invoices, 2 customers.
        // Kaveri 590 + 2,124 = 2,714; Patil 1,000 + 1,050 = 2,050.
        $expected = ['customers' => 2, 'invoices' => 4, 'total' => 4764.0, 'current' => 1000.0,
            'b30_60' => 590.0, 'b60_90' => 1050.0, 'b90_plus' => 2124.0];

        // Screen
        $screen = $this->actingAs($this->owner)->get(route('finance.aging'))->assertOk();
        $summary = $screen->viewData('summary');
        foreach ($expected as $k => $v) {
            $this->assertMoney($v, $summary[$k], "screen summary {$k}");
        }
        $text = $this->flatten($screen->getContent());
        $this->assertStringContainsString('₹2,124 is overdue by more than 90 days.', $text);
        $this->assertStringContainsString('₹4,764', $text);
        $this->assertStringContainsString('₹2,714', $text);
        $this->assertStringContainsString('₹2,050', $text);
        $this->assertStringNotContainsString('Rival Customer', $text);

        // CSV, invoice by invoice (oldest due date first)
        $csv = $this->actingAs($this->owner)->get(route('finance.aging.export.csv'));
        $csv->assertOk();
        $this->assertStringContainsString('receivables-aging-20261012.csv', (string) $csv->headers->get('content-disposition'));
        $rows = $this->csvRows($csv);
        $this->assertSame(['As on', '12-Oct-2026'], $rows[2]);
        $lines = $this->table($rows, 'S.No.');
        $this->assertCount(4, $lines);
        $want = [
            // invoice, customer, days, bucket, grand, paid, credited, balance
            ['INV-0001', 'Kaveri Agencies', '133', '90+', 2360, 0, 236, 2124],
            ['INV-0002', 'Patil Stores', '73', '60-90', 1050, 0, 0, 1050],
            ['INV-0003', 'Kaveri Agencies', '41', '30-60', 590, 0, 0, 590],
            ['INV-0004', 'Patil Stores', '0', 'current', 1180, 180, 0, 1000],
        ];
        foreach ($want as $i => [$no, $cust, $days, $bucket, $grand, $paid, $cred, $bal]) {
            $r = $lines[$i];
            $this->assertSame($no, $r[3], "row {$i} invoice");
            $this->assertSame($cust, $r[1], "row {$i} customer");
            $this->assertSame($days, (string) $r[6], "row {$i} days overdue");
            $this->assertSame($bucket, $r[7], "row {$i} bucket");
            $this->assertMoney($grand, $r[8], "row {$i} grand total");
            $this->assertMoney($paid, $r[9], "row {$i} paid");
            $this->assertMoney($cred, $r[10], "row {$i} credit notes");
            $this->assertMoney($bal, $r[11], "row {$i} balance");
            $this->assertMoney((float) $r[8] - (float) $r[9] - (float) $r[10], $r[11], "row {$i} adds up");
        }
        $total = end($rows);
        $this->assertSame('TOTAL', $total[7]);
        $this->assertSame('4764.00', $total[11], 'CSV total = screen total');
        $this->assertStringNotContainsString('Rival', $this->body($csv));

        // PDF: drawn from the same summary, printed in paise.
        $this->capture('finance.aging-pdf');
        $pdf = $this->actingAs($this->owner)->get(route('finance.aging.export.pdf'));
        $this->assertIsPdf($pdf);
        $pdfSummary = $this->captured('finance.aging-pdf')['summary'];
        foreach ($expected as $k => $v) {
            $this->assertMoney($v, $pdfSummary[$k], "PDF summary {$k}");
        }
        $pdfText = $this->pdfText('finance.aging-pdf');
        $this->assertStringContainsString('Rs. 4,764', $pdfText);
        $this->assertStringContainsString('Rs. 2,124', $pdfText);

        // API: the same summary and per-customer split.
        $api = $this->apiAs($this->owner)->getJson('/api/finance/aging')->assertOk();
        $api->assertJsonPath('as_on', '2026-10-12');
        foreach ($expected as $k => $v) {
            $this->assertMoney($v, $api->json("summary.{$k}"), "API summary {$k}");
        }
        $this->assertSame('Kaveri Agencies', $api->json('by_customer.0.name'));
        $this->assertMoney(2714, $api->json('by_customer.0.total'));
        $this->assertMoney(2124, $api->json('by_customer.0.b90_plus'));
        $this->assertMoney(590, $api->json('by_customer.0.b30_60'));
        $this->assertSame('Patil Stores', $api->json('by_customer.1.name'));
        $this->assertMoney(2050, $api->json('by_customer.1.total'));
        $this->assertMoney(1000, $api->json('by_customer.1.current'));
        $this->assertMoney(1050, $api->json('by_customer.1.b60_90'));

        // The API's files are the web files, byte for byte.
        $apiCsv = $this->apiAs($this->owner)->get('/api/finance/aging/export/csv')->assertOk();
        $this->assertSame($this->body($csv), $this->body($apiCsv));
        $this->assertIsPdf($this->apiAs($this->owner)->get('/api/finance/aging/export/pdf'));
    }

    // ---- expenses ----------------------------------------------------------

    public function test_expense_statement_screen_csv_pdf_and_api_agree(): void
    {
        $this->webExpense(['entry_date' => '2026-10-05', 'category' => 'rent', 'vendor_name' => 'Shree Estates',
            'description' => 'October rent', 'amount' => 10000, 'gst_amount' => 1800, 'itc_eligible' => 1])
            ->assertRedirect(route('finance.expenses'))
            ->assertSessionHas('status', 'Expense of ₹10,000 added.');
        $this->apiAs($this->owner)->postJson('/api/expenses', [
            'entry_date' => '2026-10-07', 'category' => 'travel', 'description' => 'Train tickets to Mumbai',
            'amount' => 2500, 'payment_method' => 'upi',
        ])->assertCreated()->assertJsonPath('data.total', 2500);
        // 2 x 250 = 500; 5% = 25 -> CGST 12.50 + SGST 12.50; memo 525 (writes a 500 + 25 expense).
        $this->actingAs($this->owner)->post(route('finance.cash-memos.store'), [
            'memo_date' => '2026-10-09', 'seller_name' => 'Ganesh Hardware', 'payment_mode' => 'cash',
            'gst_rate' => 5, 'itc_eligible' => 1, 'expense_category' => 'purchases',
            'items' => [['description' => 'Paint brush', 'quantity' => 2, 'rate' => 250]],
        ])->assertSessionHasNoErrors();
        // Blocked credit (s.17(5)): GST paid but not claimable.
        $this->webExpense(['entry_date' => '2026-10-10', 'category' => 'professional', 'vendor_name' => 'Joshi & Co',
            'description' => 'CA fees', 'amount' => 5000, 'gst_amount' => 900, 'itc_eligible' => 0])->assertSessionHasNoErrors();
        // Last month: outside "this month".
        $this->webExpense(['entry_date' => '2026-09-28', 'category' => 'utilities', 'description' => 'Electricity September',
            'amount' => 700, 'gst_amount' => 126, 'itc_eligible' => 1])->assertSessionHasNoErrors();

        // October by hand: taxable 10,000 + 2,500 + 500 + 5,000 = 18,000;
        // GST 1,800 + 0 + 25 + 900 = 2,725; paid out 20,725; claimable ITC
        // 1,800 + 25 = 1,825 (the CA fee GST is blocked); 4 entries.
        $screen = $this->actingAs($this->owner)->get(route('finance.expenses'))->assertOk();
        $s = $screen->viewData('summary');
        $this->assertMoney(18000, $s['taxable']);
        $this->assertMoney(2725, $s['gst']);
        $this->assertMoney(20725, $s['cash_out']);
        $this->assertMoney(1825, $s['itc_claimable']);
        $this->assertSame(4, (int) $s['count']);
        $text = $this->flatten($screen->getContent());
        foreach (['₹18,000', '₹2,725', '₹20,725'] as $shown) {
            $this->assertStringContainsString($shown, $text);
        }
        $this->assertStringNotContainsString('Electricity September', $text);

        $csv = $this->actingAs($this->owner)->get(route('finance.expenses.export.csv'));
        $csv->assertOk();
        $this->assertStringContainsString('expenses-20261001-20261031.csv', (string) $csv->headers->get('content-disposition'));
        $rows = $this->csvRows($csv);
        $lines = $this->table($rows, 'S.No.');
        $this->assertCount(4, $lines);
        // Date order: rent 11,800; travel 2,500; paint brushes 525; CA fee 5,900.
        $this->assertSame(['05-Oct-2026', 'Rent', 'Shree Estates', 'October rent'], array_slice($lines[0], 1, 4));
        foreach ([11800, 2500, 525, 5900] as $i => $rowTotal) {
            $this->assertMoney($rowTotal, $lines[$i][7], "row {$i} total incl. GST");
            $this->assertMoney((float) $lines[$i][5] + (float) $lines[$i][6], $lines[$i][7], "row {$i} adds up");
        }
        $this->assertSame('Purchase of goods (stock)', $lines[2][2], 'the cash memo shows under its category');
        $total = end($rows);
        $this->assertSame(['TOTAL', '18000.00', '2725.00', '20725.00'], array_slice($total, 4, 4), 'CSV totals = screen totals');

        $this->capture('finance.expenses-report');
        $this->assertIsPdf($this->actingAs($this->owner)->get(route('finance.expenses.export.pdf')));
        $ps = $this->captured('finance.expenses-report')['summary'];
        $this->assertMoney(18000, $ps['taxable']);
        $this->assertMoney(2725, $ps['gst']);
        $this->assertMoney(20725, $ps['cash_out']);
        $pdfText = $this->pdfText('finance.expenses-report');
        $this->assertStringContainsString('Rs. 18,000', $pdfText);
        $this->assertStringContainsString('Rs. 20,725', $pdfText);
        $this->assertStringContainsString('ITC claimable Rs. 1,825', $pdfText);

        // API: same files, and the list totals match for the same dates.
        $this->assertSame($this->body($csv), $this->body($this->apiAs($this->owner)->get('/api/finance/expenses/export/csv')->assertOk()));
        $this->assertIsPdf($this->apiAs($this->owner)->get('/api/finance/expenses/export/pdf'));
        $list = $this->apiAs($this->owner)->getJson('/api/expenses?from=2026-10-01')->assertOk();
        $this->assertSame(4, (int) $list->json('meta.totals.count'));
        $this->assertMoney(18000, $list->json('meta.totals.amount'));
        $this->assertMoney(2725, $list->json('meta.totals.gst'));
        $this->assertMoney(20725, $list->json('meta.totals.outflow'));
        $this->assertSame('CA fees', $list->json('data.0.description'), 'newest first');

        // A single voucher: its own PDF; a cash-memo row sends you to the memo.
        $rent = Expense::where('description', 'October rent')->firstOrFail();
        $this->assertIsPdf($this->actingAs($this->owner)->get(route('finance.expenses.pdf', $rent)));
        $memoRow = Expense::whereNotNull('cash_memo_id')->firstOrFail();
        $this->actingAs($this->owner)->get(route('finance.expenses.pdf', $memoRow))
            ->assertRedirect(route('finance.cash-memos.pdf', $memoRow->cash_memo_id));
    }

    // ---- cash memos --------------------------------------------------------

    public function test_cash_memo_register_screen_csv_pdf_and_api_agree(): void
    {
        $create = $this->actingAs($this->owner)->get(route('finance.cash-memos.create'))->assertOk();
        $this->assertSame('CM/26-27/0001', $create->viewData('nextMemoNumber'));

        // M1 (web, intra): 2 x 250 = 500; 5% = 25 -> CGST 12.50 + SGST 12.50; grand 525.
        $this->actingAs($this->owner)->post(route('finance.cash-memos.store'), [
            'memo_date' => '2026-10-09', 'seller_name' => 'Ganesh Hardware', 'payment_mode' => 'cash',
            'gst_rate' => 5, 'itc_eligible' => 1,
            'items' => [['description' => 'Paint brush', 'quantity' => 2, 'rate' => 250]],
        ])->assertSessionHas('status', 'Cash memo CM/26-27/0001 created and added to expenses.');
        $m1 = CashMemo::where('memo_number', 'CM/26-27/0001')->firstOrFail();
        $this->assertMoney(525, $m1->grand_total);

        // M2 (phone, inter-state): 1 x 1,000.50 @ 18% IGST = 180.09; 1,180.59 -> grand 1,181, round off +0.41.
        $m2 = $this->apiAs($this->owner)->postJson('/api/cash-memos', [
            'memo_date' => '2026-10-11', 'seller_name' => 'Surat Textiles', 'payment_mode' => 'upi',
            'gst_rate' => 18, 'is_interstate' => true, 'reference_number' => 'UPI-4455',
            'items' => [['description' => 'Cotton cloth', 'hsn_sac' => '5208', 'quantity' => 1, 'rate' => 1000.50]],
        ])->assertCreated();
        $m2->assertJsonPath('data.memo_number', 'CM/26-27/0002');
        $this->assertMoney(1000.50, $m2->json('data.taxable_value'));
        $this->assertMoney(180.09, $m2->json('data.total_igst'));
        $this->assertMoney(0.41, $m2->json('data.round_off'));
        $this->assertMoney(1181, $m2->json('data.grand_total'));
        $mirror = Expense::where('cash_memo_id', $m2->json('data.id'))->firstOrFail();
        $this->assertMoney(1000.50, $mirror->amount);
        $this->assertMoney(180.09, $mirror->gst_amount);

        // M3 (phone) last month, no GST: 100. Outside this month's statement.
        $this->apiAs($this->owner)->postJson('/api/cash-memos', [
            'memo_date' => '2026-09-25', 'seller_name' => 'Chaiwala', 'payment_mode' => 'cash',
            'items' => [['description' => 'Tea for staff', 'quantity' => 10, 'rate' => 10]],
        ])->assertCreated()->assertJsonPath('data.memo_number', 'CM/26-27/0003');

        // Screen lists all three: 525 + 1,181 + 100 = 1,806 on the page.
        $index = $this->actingAs($this->owner)->get(route('finance.cash-memos.index'))->assertOk();
        $index->assertSee('CM/26-27/0001')->assertSee('CM/26-27/0002')->assertSee('CM/26-27/0003');
        $this->assertStringContainsString('₹1,806', $this->flatten($index->getContent()));

        // CSV for this month: M1 + M2. Taxable 500 + 1,000.50 = 1,500.50;
        // CGST 12.50; SGST 12.50; IGST 180.09; grand 525 + 1,181 = 1,706.
        $csv = $this->actingAs($this->owner)->get(route('finance.cash-memos.export.csv'))->assertOk();
        $rows = $this->csvRows($csv);
        $lines = $this->table($rows, 'S.No.');
        $this->assertCount(2, $lines);
        $this->assertSame('CM/26-27/0001', $lines[0][2]);
        $this->assertSame('CM/26-27/0002', $lines[1][2]);
        foreach ($lines as $i => $l) {
            // taxable + CGST + SGST + IGST + round off = grand total
            $this->assertMoney((float) $l[7] + (float) $l[8] + (float) $l[9] + (float) $l[10] + (float) $l[11], $l[12], "memo row {$i} adds up");
        }
        $total = end($rows);
        $this->assertSame(['TOTAL', '1500.50', '12.50', '12.50', '180.09', '', '1706.00'], array_slice($total, 6, 7));

        $this->capture('finance.cash-memos.report');
        $this->assertIsPdf($this->actingAs($this->owner)->get(route('finance.cash-memos.export.pdf')));
        $ps = $this->captured('finance.cash-memos.report')['summary'];
        $this->assertSame(2, (int) $ps['count']);
        $this->assertMoney(1500.50, $ps['taxable']);
        $this->assertMoney(205.09, $ps['total_tax']); // 12.50 + 12.50 + 180.09
        $this->assertMoney(1706, $ps['grand_total']);
        $this->assertStringContainsString('₹1,706', $this->pdfText('finance.cash-memos.report'));

        // API list (newest first) and one memo in full.
        $list = $this->apiAs($this->owner)->getJson('/api/cash-memos')->assertOk();
        $this->assertSame(['CM/26-27/0002', 'CM/26-27/0001', 'CM/26-27/0003'], collect($list->json('data'))->pluck('memo_number')->all());
        $one = $this->apiAs($this->owner)->getJson('/api/cash-memos/' . $m2->json('data.id'))->assertOk();
        $one->assertJsonPath('data.is_interstate', true)
            ->assertJsonPath('data.seller_name', 'Surat Textiles')
            ->assertJsonCount(1, 'data.items');
        $this->assertMoney(1181, $one->json('data.grand_total'));
    }

    // ---- GST return exports over the API -----------------------------------

    public function test_gst_return_exports_over_the_api_match_the_web_files(): void
    {
        // October: Patil (intra) 1 x 1,000 @ 18% = 1,000 + CGST 90 + SGST 90 = 1,180;
        // Kaveri (inter) 1 x 2,000 @ 18% = 2,000 + IGST 360 = 2,360.
        $this->webIssued($this->owner, $this->patil, [$this->line(1000, 18, 1)]);
        $this->webIssued($this->owner, $this->kaveri, [$this->line(2000, 18, 1)]);
        // Office supplies 500 + GST 90 intra, ITC eligible: CGST 45 + SGST 45 credit.
        $this->webExpense(['entry_date' => '2026-10-06', 'category' => 'office_supplies', 'description' => 'Printer paper',
            'amount' => 500, 'gst_amount' => 90, 'itc_eligible' => 1])->assertSessionHasNoErrors();

        // GSTR-1 invoice-wise CSV: totals 3,000 taxable, CGST 90, SGST 90, IGST 360, 3,540.
        $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];
        $web1 = $this->actingAs($this->owner)->get(route('invoices.gstr1', $period))->assertOk();
        $api1 = $this->apiAs($this->owner)->get('/api/finance/gstr1/export/csv?' . http_build_query($period))->assertOk();
        $this->assertSame($this->body($web1), $this->body($api1), 'the phone gets the same GSTR-1 file');
        $rows = $this->csvRows($api1);
        $total = $this->rowLabelled($rows, 'TOTAL');
        $this->assertSame(['3000.00', '90.00', '90.00', '360.00', '0.00', '', '3540.00'], array_slice($total, 9, 7));
        $this->assertSame(['Total Invoices', '2'], $this->rowLabelled($rows, 'Total Invoices'));

        // GSTR-3B: liability IGST 360 / CGST 90 / SGST 90; ITC CGST 45 / SGST 45
        // set off against their own heads (s.49(5)); cash 360 + 45 + 45 = 450.
        $web3 = $this->actingAs($this->owner)->get(route('finance.gstr3b.export.csv', ['month' => '2026-10']))->assertOk();
        $api3 = $this->apiAs($this->owner)->get('/api/finance/gstr3b/export/csv?month=2026-10')->assertOk();
        $this->assertSame($this->body($web3), $this->body($api3), 'the phone gets the same GSTR-3B file');
        $this->assertStringContainsString('gstr3b-202610.csv', (string) $api3->headers->get('content-disposition'));
        $rows = $this->csvRows($api3);
        $this->assertSame(['3000.00', '360.00', '90.00', '90.00', '0.00'],
            array_slice($this->rowLabelled($rows, '(a) Outward taxable supplies (other than zero rated, nil rated and exempted)'), 1, 5));
        $this->assertSame(['0.00', '45.00', '45.00', '0.00'], array_slice($this->rowLabelled($rows, '(A)(5) All other ITC'), 1, 4));
        $this->assertSame(['360.00', '45.00', '45.00'], array_slice($this->rowLabelled($rows, 'Net cash payable'), 1, 3));
        $this->assertSame('450.00', $this->rowLabelled($rows, 'Total cash to deposit')[1]);

        // The 3B screen, the 3B PDF and the API JSON say the same.
        $screen = $this->actingAs($this->owner)->get(route('finance.gstr3b', ['month' => '2026-10']))->assertOk();
        $this->assertMoney(450, $screen->viewData('netCash')['total']);
        $this->capture('finance.gstr3b-pdf');
        $this->assertIsPdf($this->apiAs($this->owner)->get('/api/finance/gstr3b/export/pdf?month=2026-10'));
        $pdf = $this->captured('finance.gstr3b-pdf');
        $this->assertMoney(450, $pdf['netCash']['total']);
        $this->assertMoney(3000, $pdf['outward']['taxable']);
        $json = $this->apiAs($this->owner)->getJson('/api/finance/gstr3b?month=2026-10')->assertOk();
        $this->assertMoney(450, $json->json('net_cash.total'));
        $this->assertMoney(360, $json->json('outward.igst'));
    }

    // ---- reports hub -------------------------------------------------------

    public function test_the_reports_hub_links_every_finance_statement(): void
    {
        $page = $this->actingAs($this->owner)->get(route('reports.index'))->assertOk();
        $page->assertSee('All reports')
            ->assertSee(route('finance.aging'), false)
            ->assertSee(route('finance.expenses'), false)
            ->assertSee(route('finance.cash-memos.index'), false)
            ->assertSee(route('finance.gstr3b'), false)
            // Last month's GSTR-1 CSV: 1-30 Sep 2026.
            ->assertSee(e(route('invoices.gstr1', ['from' => '2026-09-01', 'to' => '2026-09-30'])), false);

        // Billing staff do not get the reports hub.
        $staff = $this->memberOf($this->biz, 'staff');
        $this->actingAs($staff)->get(route('reports.index'))->assertRedirect(route('invoices.index'));
    }

    public function test_the_reports_hub_does_not_offer_an_accountant_the_backup(): void
    {
        // An accountant's role excludes backups (CompanyMember::RULES). The hub
        // must not offer a card that only bounces them back with an error.
        $accountant = $this->memberOf($this->biz, 'accountant');
        $page = $this->actingAs($accountant)->get(route('reports.index'))->assertOk();
        $page->assertSee(route('finance.expenses'), false);
        $this->assertFalse(str_contains((string) $page->getContent(), 'href="' . route('backup.index') . '"'),
            'the reports hub offers an accountant a Backups card that their role cannot open');
        // (the sidebar and the settings page already hide it via Team::can)
        $this->actingAs($accountant)->get(route('backup.index'))->assertRedirect(route('dashboard'));
    }

    // ---- refusals ----------------------------------------------------------

    public function test_billing_staff_cannot_pull_finance_figures_over_the_api(): void
    {
        $memo = $this->apiAs($this->owner)->postJson('/api/cash-memos', [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Ganesh Hardware', 'payment_mode' => 'cash',
            'items' => [['description' => 'Nails', 'quantity' => 1, 'rate' => 50]],
        ])->assertCreated()->json('data.id');
        $staff = $this->memberOf($this->biz, 'staff');

        foreach ([
            ['GET', '/api/finance/aging'],
            ['GET', '/api/finance/aging/export/csv'],
            ['GET', '/api/finance/aging/export/pdf'],
            ['GET', '/api/finance/expenses/export/csv'],
            ['GET', '/api/finance/expenses/export/pdf'],
            ['GET', '/api/finance/gstr1/export/csv'],
            ['GET', '/api/finance/gstr3b/export/csv'],
            ['GET', '/api/finance/gstr3b/export/pdf'],
            ['GET', '/api/expenses'],
            ['POST', '/api/expenses'],
            ['GET', '/api/cash-memos'],
            ['POST', '/api/cash-memos'],
            ['GET', "/api/cash-memos/{$memo}"],
        ] as [$verb, $uri]) {
            $this->apiAs($staff)->json($verb, $uri, $verb === 'POST' ? ['seller_name' => 'x', 'description' => 'x'] : [])
                ->assertForbidden();
        }
        $this->assertSame(0, Expense::where('description', 'x')->count());
        $this->assertSame(1, CashMemo::count());

        // On the web the staff member is sent back to the invoices.
        $this->actingAs($staff)->get(route('finance.aging'))->assertRedirect(route('invoices.index'));
        $this->actingAs($staff)->get(route('finance.expenses.export.csv'))->assertRedirect(route('invoices.index'));

        // An accountant may: the same report opens for them.
        $accountant = $this->memberOf($this->biz, 'accountant');
        $this->apiAs($accountant)->getJson('/api/finance/aging')->assertOk()->assertJsonPath('summary.invoices', 0);
    }

    public function test_another_business_expense_and_cash_memo_are_out_of_reach(): void
    {
        $rival = $this->makeBusiness(['name' => 'Rival Traders', 'gstin' => '29ABCDE1234F1ZW', 'state_id' => $this->state('29')->id]);
        $rivalOwner = $this->ownerOf($rival);
        $this->actingAs($rivalOwner)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'rent', 'description' => 'Rival rent', 'amount' => 50000,
        ])->assertSessionHasNoErrors();
        $rivalExpense = Expense::where('description', 'Rival rent')->firstOrFail();
        $rivalMemo = $this->apiAs($rivalOwner)->postJson('/api/cash-memos', [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Rival Seller', 'payment_mode' => 'cash',
            'items' => [['description' => 'Rival goods', 'quantity' => 1, 'rate' => 999]],
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->owner)->get(route('finance.expenses.pdf', $rivalExpense))->assertForbidden();
        $this->actingAs($this->owner)->get(route('finance.cash-memos.show', $rivalMemo))->assertForbidden();
        $this->apiAs($this->owner)->getJson("/api/cash-memos/{$rivalMemo}")->assertForbidden();

        // Nor does it leak into this business's lists and files.
        $this->apiAs($this->owner)->getJson('/api/expenses')->assertOk()->assertJsonPath('meta.totals.count', 0);
        $this->apiAs($this->owner)->getJson('/api/cash-memos')->assertOk()->assertJsonCount(0, 'data');
        $csv = $this->actingAs($this->owner)->get(route('finance.expenses.export.csv'));
        $this->assertStringNotContainsString('Rival rent', $this->body($csv));
    }

    public function test_bad_expense_and_cash_memo_input_is_refused(): void
    {
        // Missing description / unknown category.
        $this->apiAs($this->owner)->postJson('/api/expenses', ['entry_date' => now()->toDateString(), 'category' => 'rent', 'amount' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('description');
        $this->apiAs($this->owner)->postJson('/api/expenses', ['entry_date' => now()->toDateString(), 'category' => 'party', 'description' => 'x', 'amount' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('category');
        // GST above the top 40% slab on the amount: 1,000 x 40% = 400 (+0.50 slack); 401 is refused.
        $this->apiAs($this->owner)->postJson('/api/expenses', [
            'entry_date' => now()->toDateString(), 'category' => 'rent', 'description' => 'Rent with GST typed in', 'amount' => 1000, 'gst_amount' => 401,
        ])->assertStatus(422)->assertJsonValidationErrors('gst_amount');
        $this->webExpense(['entry_date' => now()->toDateString(), 'category' => 'rent', 'description' => 'Rent', 'amount' => 1000, 'gst_amount' => 401])
            ->assertSessionHasErrors('gst_amount');
        // Inside closed books.
        $this->biz->forceFill(['books_locked_until' => '2026-09-30'])->save();
        $this->apiAs($this->owner)->postJson('/api/expenses', [
            'entry_date' => '2026-09-15', 'category' => 'rent', 'description' => 'Backdated rent', 'amount' => 1000,
        ])->assertStatus(422)->assertJsonPath('message', 'Books are locked up to 30 Sep 2026. Date the expense after that.');
        $this->assertSame(0, Expense::count());

        // Cash memo: no items, unknown payment mode, a number already used, closed books.
        $memo = ['memo_date' => now()->toDateString(), 'seller_name' => 'Ganesh Hardware', 'payment_mode' => 'cash',
            'items' => [['description' => 'Nails', 'quantity' => 1, 'rate' => 50]]];
        $this->apiAs($this->owner)->postJson('/api/cash-memos', array_merge($memo, ['items' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('items');
        $this->apiAs($this->owner)->postJson('/api/cash-memos', array_merge($memo, ['payment_mode' => 'barter']))
            ->assertStatus(422)->assertJsonValidationErrors('payment_mode');
        $this->apiAs($this->owner)->postJson('/api/cash-memos', array_merge($memo, ['memo_number' => 'CM-77']))->assertCreated();
        $this->apiAs($this->owner)->postJson('/api/cash-memos', array_merge($memo, ['memo_number' => 'CM-77']))
            ->assertStatus(422)->assertJsonValidationErrors('memo_number');
        $this->apiAs($this->owner)->postJson('/api/cash-memos', array_merge($memo, ['memo_date' => '2026-09-20']))
            ->assertStatus(422)->assertJsonPath('message', 'Books are locked up to 30 Sep 2026. Cannot create a cash memo dated on or before that.');
        $this->assertSame(1, CashMemo::count());
        $this->assertSame(1, Expense::count(), 'only the one good memo wrote an expense');
    }
}
