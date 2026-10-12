<?php

namespace Tests\Feature\Fintech\Security;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Customer;
use App\Models\Gstr2bEntry;
use App\Services\Gstr2bReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * Every file a user can upload: logo and signature (company settings,
 * new business, onboarding), spreadsheet imports, bank statements and the
 * GSTR-2B. Hostile files are refused with a message (never a 500), and a
 * stored file can only ever be an image with an image extension under a
 * random name - nothing a browser would run as HTML or a server as PHP.
 */
class UploadSafetyTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    private array $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->a = $this->buildBusiness('A');
        Storage::fake('public');
    }

    private function owner()
    {
        return $this->a['owner']->fresh();
    }

    private function file(string $name, string $bytes, string $mime = 'application/octet-stream'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    private function png(int $w = 40, int $h = 20): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 5, 5, $w - 5, $h - 5, imagecolorallocate($img, 20, 120, 140));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private function settings(array $extra = []): array
    {
        $c = $this->a['company'];

        return array_merge(['name' => $c->name, 'state_id' => $c->state_id, 'country' => 'India', 'default_currency' => 'INR',
            'invoice_prefix' => 'INV', 'invoice_number_padding' => 4, 'bank_ifsc' => $c->bank_ifsc, 'upi_id' => $c->upi_id], $extra);
    }

    private function storedFiles(): array
    {
        return Storage::disk('public')->allFiles();
    }

    // ------------------------------------------------------------------ logo / signature

    public function test_hostile_logo_and_signature_files_are_refused_and_nothing_is_stored(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(document.cookie)</script></svg>';
        $cases = [
            // (Kept harmless on purpose: a real web-shell string gets the temp file
            // quarantined by Windows Defender before the app can read it.)
            'php renamed .png' => $this->file('logo.png', "<?php echo 'owned'; ?>", 'image/png'),
            'svg with script' => $this->file('logo.svg', $svg, 'image/svg+xml'),
            'svg renamed .png' => $this->file('logo.png', $svg, 'image/png'),
            'html renamed .jpg' => $this->file('logo.jpg', '<html><script>alert(1)</script></html>', 'image/jpeg'),
            'oversized png (2.5 MB)' => $this->file('logo.png', $this->png() . str_repeat("\0", 2600 * 1024), 'image/png'),
        ];
        foreach ($cases as $label => $file) {
            foreach (['logo', 'signature'] as $field) {
                $res = $this->actingAs($this->owner())->patch(route('companies.update', $this->a['company']), $this->settings([$field => $file]));
                $this->assertNotSame(500, $res->getStatusCode(), "{$label} as {$field} crashed");
                $res->assertSessionHasErrors($field);
            }
        }
        $this->assertSame([], $this->storedFiles());
        $this->assertNull($this->a['company']->fresh()->logo_path);

        // The same rules guard the other two forms that take a logo.
        $this->actingAs($this->owner())->post(route('onboarding.business.save'), $this->settings(['logo' => $this->file('l.png', $svg, 'image/png')]))
            ->assertSessionHasErrors('logo');
        $this->actingAs($this->owner())->post(route('companies.store'), $this->settings(['name' => 'Second', 'logo' => $this->file('l.png', '<?php echo 1;', 'image/png')]))
            ->assertSessionHasErrors('logo');
        $this->assertSame([], $this->storedFiles());
    }

    public function test_a_real_logo_with_a_hostile_name_is_stored_under_a_random_image_name(): void
    {
        $this->actingAs($this->owner())->patch(route('companies.update', $this->a['company']), $this->settings([
            'logo' => $this->file('../../../public/index.php.png', $this->png(), 'image/png'),
            'signature' => $this->file('..\\..\\evil.phtml.png', $this->png(), 'image/png'),
        ]))->assertSessionHasNoErrors();

        $c = $this->a['company']->fresh();
        foreach (['logos/' => $c->logo_path, 'signatures/' => $c->signature_path] as $dir => $path) {
            $this->assertStringStartsWith($dir, $path);
            $this->assertStringNotContainsString('..', $path);
            $this->assertStringNotContainsString('php', strtolower($path));
            $this->assertMatchesRegularExpression('~^[a-z]+/[A-Za-z0-9]{20,}\.(png|jpe?g|webp)$~', $path);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_a_png_carrying_php_after_the_image_is_stored_without_it(): void
    {
        $this->actingAs($this->owner())->patch(route('companies.update', $this->a['company']), $this->settings([
            'logo' => $this->file('logo.png', $this->png() . "<?php echo 'owned'; ?>", 'image/png'),
        ]))->assertSessionHasNoErrors();

        $path = $this->a['company']->fresh()->logo_path;
        $this->assertMatchesRegularExpression('~\.(png|jpe?g|webp)$~', $path);
        $this->assertStringNotContainsString('<?php', Storage::disk('public')->get($path), 'the stored logo still carries the PHP tail');
    }

    // ------------------------------------------------------------------ imports

    public function test_an_import_file_that_is_not_a_spreadsheet_too_big_or_too_long_is_refused(): void
    {
        $customers = Customer::count();
        $php = $this->file('customers.csv', "<?php system('id'); ?>\nName,State\n", 'text/csv');
        $res = $this->actingAs($this->owner())->post(route('imports.preview', 'customers'), ['file' => $php]);
        $this->assertNotSame(500, $res->getStatusCode());

        $big = $this->file('customers.csv', "Name,State,Country\n" . str_repeat("X,Maharashtra,India\n", 300000), 'text/csv'); // ~5.7 MB
        $this->actingAs($this->owner())->post(route('imports.preview', 'customers'), ['file' => $big])->assertSessionHasErrors('file');

        $rows = "Name,State,Country\n";
        for ($i = 1; $i <= 10001; $i++) {
            $rows .= "Bulk {$i},Maharashtra,India\n";
        }
        $res = $this->actingAs($this->owner())->post(route('imports.preview', 'customers'), ['file' => $this->file('../../bulk.csv', $rows, 'text/csv')]);
        $res->assertSessionHasErrors('file');
        $this->assertStringContainsString('2,000', session('errors')->first('file'));

        $this->actingAs($this->owner())->post(route('imports.preview', 'customers'), ['file' => $this->file('evil.php', "Name,State\nX,Maharashtra\n", 'text/csv')])
            ->assertSessionHasErrors('file');
        $this->assertSame($customers, Customer::count());
    }

    public function test_an_import_preview_prints_a_hostile_file_name_and_cells_as_text(): void
    {
        $csv = "Name,State,Country\n\"<script>alert(1)</script>\",Maharashtra,India\n";
        $html = $this->actingAs($this->owner())->post(route('imports.preview', 'customers'), [
            'file' => $this->file('<img src=x onerror=alert(2)>.csv', $csv, 'text/csv'),
        ])->assertOk()->getContent();
        $this->assertFalse(str_contains($html, '<script>alert(1)'), 'a cell rendered as HTML');
        $this->assertFalse(str_contains($html, '<img src=x onerror'), 'the file name rendered as HTML');
    }

    // ------------------------------------------------------------------ bank statements

    public function test_a_hostile_or_oversized_bank_statement_gets_a_message_not_a_crash(): void
    {
        $lines = BankStatementLine::count();
        $cases = [
            'php as csv' => $this->file('statement.csv', "<?php system('id'); ?>", 'text/csv'),
            'php file' => $this->file('statement.php', "<?php system('id'); ?>", 'application/x-php'),
            'html as xlsx' => $this->file('statement.xlsx', '<html><body>x</body></html>', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'traversal name' => $this->file('../../../../storage/app/evil.csv', "garbage\n", 'text/csv'),
            'over 10 MB' => $this->file('big.csv', str_repeat("x,y\n", 2_800_000), 'text/csv'),
        ];
        foreach ($cases as $label => $file) {
            $res = $this->actingAs($this->owner())->post(route('bank.upload'), ['account_name' => 'HDFC', 'file' => $file]);
            $this->assertNotSame(500, $res->getStatusCode(), "{$label} crashed");
            $res->assertSessionHasErrors('file');
        }
        $this->assertSame($lines, BankStatementLine::count());
    }

    public function test_a_statement_with_more_lines_than_the_limit_is_refused_whole(): void
    {
        // 5,001 transactions, one more than BankStatementReader::MAX_LINES.
        $csv = "Date,Narration,Chq./Ref.No.,Value Dt,Withdrawal Amt.,Deposit Amt.,Closing Balance\n";
        for ($i = 1; $i <= 5001; $i++) {
            $csv .= "01/09/26,UPI-{$i},{$i},01/09/26,1.00,,1000.00\n";
        }
        $before = BankStatementLine::count();
        $this->actingAs($this->owner())->post(route('bank.upload'), ['account_name' => 'Bulk', 'file' => $this->file('bulk.csv', $csv, 'text/csv')])
            ->assertSessionHasErrors('file');
        $this->assertSame($before, BankStatementLine::count());
        $this->assertSame(0, BankStatement::where('account_name', 'Bulk')->count());
    }

    public function test_a_hostile_statement_file_name_is_kept_as_a_label_only(): void
    {
        $file = $this->bankStatementFile('A2');
        $hostile = new UploadedFile($file->getRealPath(), '../../<script>alert(1)</script>.xlsx', $file->getMimeType(), null, true);
        $this->actingAs($this->owner())->post(route('bank.upload'), ['account_name' => '<b>HDFC</b>', 'file' => $hostile])->assertSessionHasNoErrors();
        $html = $this->actingAs($this->owner())->get(route('bank.index', ['status' => 'all']))->assertOk()->getContent();
        $this->assertFalse(str_contains($html, '<script>alert(1)'));
        $this->assertFalse(str_contains($html, '<b>HDFC</b>'));
        $this->assertSame([], $this->storedFiles(), 'statements are read, never stored');
    }

    // ------------------------------------------------------------------ GSTR-2B

    private function twoBJson(int $invoices = 1): string
    {
        $inv = [];
        for ($i = 1; $i <= $invoices; $i++) {
            $inv[] = ['inum' => "K-{$i}", 'typ' => 'R', 'dt' => '12-09-2026', 'val' => 118, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y',
                'items' => [['num' => 1, 'rt' => 18, 'txval' => 100, 'igst' => 0, 'cgst' => 9, 'sgst' => 9, 'cess' => 0]]];
        }

        return json_encode(['data' => ['gstin' => null, 'rtnprd' => '092026', 'docdata' => ['b2b' => [
            ['ctin' => '27KLMNO9876P1Z3', 'trdnm' => 'Kolhapur Kraft', 'inv' => $inv],
        ]]]]);
    }

    public function test_a_hostile_or_oversized_2b_file_gets_a_message_not_a_crash(): void
    {
        $entries = Gstr2bEntry::count();
        $cases = [
            'php file' => $this->file('2b.php', "<?php system('id'); ?>", 'application/x-php'),
            'php as json' => $this->file('2b.json', "<?php system('id'); ?>", 'application/json'),
            'not a zip' => $this->file('2b.zip', 'PK-not-really', 'application/zip'),
            'zip without json' => $this->zip(['readme.txt' => 'hello']),
            'over 20 MB' => $this->file('2b.json', str_repeat(' ', 21 * 1024 * 1024), 'application/json'),
        ];
        foreach ($cases as $label => $file) {
            $res = $this->actingAs($this->owner())->post(route('finance.gstr2b.upload'), ['file' => $file, 'month' => '2026-09']);
            $this->assertNotSame(500, $res->getStatusCode(), "{$label} crashed");
            $res->assertSessionHasErrors('file');
        }
        $this->assertSame($entries, Gstr2bEntry::count());
    }

    public function test_a_2b_with_more_documents_than_the_limit_is_refused(): void
    {
        $entries = Gstr2bEntry::count();
        $res = $this->actingAs($this->owner())->post(route('finance.gstr2b.upload'), [
            'file' => $this->file('2b.json', $this->twoBJson(Gstr2bReader::MAX_ENTRIES + 1), 'application/json'), 'month' => '2026-09',
        ]);
        $res->assertSessionHasErrors('file');
        $this->assertSame($entries, Gstr2bEntry::count());
    }

    /**
     * The .zip branch reads the JSON with ZipArchive::getFromIndex(), which
     * inflates the whole entry into memory with no size check. A 20 MB
     * upload (the validation cap) can inflate to many GB and exhaust PHP's
     * memory - a fatal error no try/catch can turn into a message. Here a
     * 64 MB entry (compressed to about 64 KB) must be refused BEFORE it is
     * inflated; a real 2B JSON for 20,000 documents is ~10 MB.
     */
    public function test_a_2b_zip_that_inflates_far_beyond_a_real_return_is_refused_before_reading(): void
    {
        $json = $this->twoBJson();
        $bomb = $this->zip(['returns_092026_R2B.json' => str_repeat(' ', 64 * 1024 * 1024) . $json]);
        $this->assertLessThan(1024 * 1024, filesize($bomb->getRealPath()), 'fixture: the upload itself is small');

        $res = $this->actingAs($this->owner())->post(route('finance.gstr2b.upload'), ['file' => $bomb, 'month' => '2026-09']);
        $this->assertNotSame(500, $res->getStatusCode());
        $res->assertSessionHasErrors('file');
        $this->assertSame(0, Gstr2bEntry::where('document_number', 'K-1')->count());
    }

    private function zip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zp') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, '2b.zip', 'application/zip', null, true);
    }

    // ------------------------------------------------------------------ serving

    public function test_the_private_file_route_serves_nothing_without_a_signature_and_never_escapes_its_folder(): void
    {
        Storage::disk('local')->put('secret.txt', 'PRIVATE-CONTENT');
        foreach (['/storage/secret.txt', '/storage/..%2F..%2F.env', '/storage/../../.env', '/storage/%2e%2e/%2e%2e/.env'] as $url) {
            $res = $this->get($url);
            $this->assertContains($res->getStatusCode(), [403, 404], "{$url} answered {$res->getStatusCode()}");
            $this->assertFalse(str_contains((string) $res->getContent(), 'PRIVATE-CONTENT'));
            $this->assertFalse(str_contains((string) $res->getContent(), 'APP_KEY'));
        }
    }
}
