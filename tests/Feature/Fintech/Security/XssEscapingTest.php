<?php

namespace Tests\Feature\Fintech\Security;

use App\Http\Controllers\InvoiceShareController;
use App\Http\Controllers\QuotationShareController;
use App\Mail\InvoiceMail;
use App\Mail\PaymentReminderMail;
use App\Mail\QuotationMail;
use App\Mail\TeamInviteMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\Feature\Fintech\Security\Concerns\CapturingPdf;
use Tests\Feature\Fintech\Security\Concerns\SweepsRecordRoutes;
use Tests\TestCase;

/**
 * Stored XSS: names a customer, an item, the business, a vendor, a seller
 * or a team member can type are rendered on every screen, every PDF (dompdf
 * draws them from HTML), the public share pages, JSON-LD and emails. Each
 * payload must come out as text.
 *
 *  - `<script>alert(1)</script>`          plain tag injection
 *  - `"><img src=x onerror=alert(2)>`     attribute break-out
 *  - `{{7*7}}<svg onload=alert(3)>`       template syntax + SVG handler
 *  - `x'); alert(7); ('`                  JS-string break-out (inline handlers)
 */
class XssEscapingTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;
    use SweepsRecordRoutes;

    private const RAW = ['<script>alert(', '<img src=x onerror', '<svg onload', '<iframe src=javascript'];

    private array $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->a = $this->buildBusiness('A');
        $a = $this->a;

        $a['company']->forceFill(['name' => '{{7*7}}<svg onload=alert(3)>Firm', 'declaration' => '<script>alert(11)</script>',
            'default_terms' => '<iframe src=javascript:alert(12)>'])->save();
        $a['customer']->forceFill(['name' => '<script>alert(1)</script>Cust', 'address_line1' => '"><img src=x onerror=alert(13)>'])->save();
        $a['customerUnused']->forceFill(['name' => '"><img src=x onerror=alert(14)>Prospect'])->save();
        $a['product']->forceFill(['name' => '"><img src=x onerror=alert(2)>Item'])->save();
        $a['vendor']->forceFill(['name' => '<script>alert(4)</script>Vendor'])->save();
        $a['cashMemo']->forceFill(['seller_name' => '<script>alert(5)</script>Seller'])->save();
        $a['expense']->forceFill(['description' => '<svg onload=alert(6)>Rent', 'vendor_name' => '<script>alert(15)</script>'])->save();
        $a['quoteSent']->forceFill(['subject' => '</textarea><script>alert(8)</script>', 'notes' => '<iframe src=javascript:alert(9)>'])->save();
        $a['bankLine']->forceFill(['description' => '<script>alert(10)</script>Charges'])->save();
        $a['gstr2b']->forceFill(['supplier_name' => '<script>alert(16)</script>Supplier'])->save();
        // Issued invoices print the customer as frozen at issue.
        foreach (Invoice::where('company_id', $a['company']->id)->whereNotNull('customer_snapshot')->get() as $inv) {
            $snap = $inv->customer_snapshot;
            $snap['name'] = '<script>alert(1)</script>Cust';
            $inv->forceFill(['customer_snapshot' => $snap, 'notes' => '<iframe src=javascript:alert(17)>'])->save();
        }
        $a['owner']->forceFill(['name' => "Ravi'); alert(7); ('<b>"])->save();

        $this->app->bind('dompdf.wrapper', fn () => new CapturingPdf());
        CapturingPdf::$html = [];
    }

    private function rawIn(string $html): array
    {
        return array_values(array_filter(self::RAW, fn ($m) => stripos($html, $m) !== false));
    }

    public function test_every_signed_in_page_and_pdf_prints_the_payloads_as_text(): void
    {
        $bad = [];
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDef $r) => in_array('GET', $r->methods(), true))
            ->filter(fn (RouteDef $r) => collect($r->gatherMiddleware())->contains(fn ($m) => $m === 'auth' || str_starts_with((string) $m, 'auth:')))
            ->reject(fn (RouteDef $r) => in_array($r->uri(), ['logout', 'backup/download', 'api/backup/download'], true)
                || str_starts_with($r->uri(), 'admin') || array_intersect($r->parameterNames(), ['token', 'hash', 'path', 'slug', 'provider']) !== []);
        $count = 0;
        $pdfs = 0;

        foreach ($routes as $route) {
            $missing = [];
            $params = $route->parameterNames();
            if (array_intersect($params, ['type', 'report', 'format', 'template'])) {
                $url = '/' . strtr($route->uri(), ['{type}' => 'customers', '{report}' => 'sales-register', '{format}' => 'print', '{template}' => 'retail-shop']);
                $url = preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) $this->targetFor($this->a, $m[1], $route), $url);
            } else {
                $url = $this->urlFor($this->a, $route, $missing);
            }
            if ($url === null) {
                continue;
            }
            CapturingPdf::$html = [];
            app('cache')->store()->flush();
            if (str_starts_with($route->uri(), 'api/')) {
                Sanctum::actingAs($this->a['owner']->fresh());
                $res = $this->getJson($url);
            } else {
                $res = $this->actingAs($this->a['owner']->fresh())->get($url);
            }
            $count++;
            $type = (string) $res->headers->get('Content-Type');
            $docs = CapturingPdf::$html;
            $pdfs += count($docs);
            if (str_contains($type, 'text/html')) {
                $docs[] = (string) $res->getContent();
            }
            foreach ($docs as $doc) {
                foreach ($this->rawIn($doc) as $m) {
                    $bad[] = "{$url} renders raw {$m}";
                }
            }
        }

        $this->assertGreaterThan(100, $count);
        $this->assertGreaterThan(15, $pdfs, 'PDF HTML was captured for the PDF routes');
        $this->assertSame([], array_values(array_unique($bad)), implode("\n", array_unique($bad)));
    }

    public function test_the_public_share_pages_their_pdfs_and_json_ld_print_the_payloads_as_text(): void
    {
        auth()->logout();
        foreach ([InvoiceShareController::makePublicUrl($this->a['invoiceOpen']), QuotationShareController::makePublicUrl($this->a['quoteSent'])] as $url) {
            CapturingPdf::$html = [];
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame([], $this->rawIn($html), "share page {$url}");
            // Structured data blocks must not be closable from inside.
            preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $blocks);
            foreach ($blocks[1] as $json) {
                $this->assertFalse(stripos($json, '<') !== false, 'a raw < inside JSON-LD');
                $this->assertIsArray(json_decode($json, true), 'JSON-LD is still valid JSON');
            }
            preg_match('~href="([^"]+/pdf\?[^"]+)"~', $html, $m);
            $this->get(html_entity_decode($m[1]))->assertOk();
            foreach (CapturingPdf::$html as $doc) {
                $this->assertSame([], $this->rawIn($doc), 'public PDF');
            }
        }
    }

    public function test_emails_print_the_payloads_as_text(): void
    {
        $inv = $this->a['invoiceOpen']->fresh();
        $mails = [
            new InvoiceMail($inv, 'Invoice <script>alert(20)</script>', "Hello <script>alert(21)</script>\nThanks", InvoiceShareController::makePublicUrl($inv)),
            new PaymentReminderMail($inv, 5),
            new QuotationMail($this->a['quoteSent']->fresh(), 'Quote <svg onload=alert(22)>', 'Body <img src=x onerror=alert(23)>',
                QuotationShareController::makePublicUrl($this->a['quoteSent'])),
            new TeamInviteMail($this->a['memberPending']->fresh()->load('company'), '<script>alert(24)</script>Inviter'),
        ];
        foreach ($mails as $mail) {
            $html = $mail->render();
            $this->assertSame([], $this->rawIn($html), class_basename($mail) . ' renders a raw payload');
        }
    }

    /**
     * The super-admin user list builds `onsubmit="return confirm('Log in as
     * {{ $user->name }}?')"`. Blade's HTML escaping turns ' into &#039;,
     * but the browser decodes the attribute BEFORE running it as script, so
     * a name like  x'); alert(7); ('  closes the string and runs - in the
     * super admin's session, which can impersonate any user.
     */
    public function test_a_users_name_cannot_break_out_of_the_admin_panels_inline_scripts(): void
    {
        $admin = User::factory()->create(['email' => 'root@example.test']);
        $admin->forceFill(['is_super_admin' => true])->save();
        config(['admin.super_admin_emails' => []]);
        $victimName = "Ravi'); alert(7); ('<b>";

        foreach ([route('admin.users'), route('admin.users.show', $this->a['owner'])] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertSame([], $this->rawIn($html));
            preg_match_all('/\son(?:submit|click)="([^"]*)"/i', $html, $handlers);
            foreach ($handlers[1] as $attr) {
                $js = html_entity_decode($attr, ENT_QUOTES | ENT_HTML5);
                $this->assertFalse(str_contains($js, "Ravi'); alert(7);"), "{$url}: the name closes the JS string in an inline handler: {$js}");
            }
        }
        $this->assertTrue(str_contains($victimName, "'"));
    }
}
