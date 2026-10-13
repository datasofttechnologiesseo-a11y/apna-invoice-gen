<?php

namespace Tests\Feature\Fintech\Security;

use App\Http\Controllers\InvoiceShareController;
use App\Http\Controllers\QuotationShareController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * The public links a business sends its customer: /i/{invoice} and
 * /q/{quotation} (and their /pdf children). Anyone holding the link may
 * open it without signing in, so the signature and expiry ARE the access
 * control. Links are minted with a 30-day TTL (SHARE_TTL_DAYS).
 */
class SignedShareLinkTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->a = $this->buildBusiness('A');
        $this->b = $this->buildBusiness('B');
        auth()->logout();
    }

    /** @return array{0:string,1:array<string,string>} path and query */
    private function parts(string $url): array
    {
        $u = parse_url($url);
        parse_str($u['query'] ?? '', $q);

        return [$u['path'], $q];
    }

    private function build(string $path, array $query): string
    {
        return $path . '?' . http_build_query($query);
    }

    public function test_a_fresh_invoice_link_opens_for_anyone_and_its_pdf_child_too(): void
    {
        $url = InvoiceShareController::makePublicUrl($this->a['invoiceOpen']);
        $page = $this->get($url)->assertOk();
        $page->assertSee('SECRET-FIRM-A')->assertSee('INV-0001');

        preg_match('~href="([^"]+/i/\d+/pdf\?[^"]+)"~', $page->getContent(), $m);
        $this->assertNotEmpty($m, 'the page links its PDF');
        $this->get(html_entity_decode($m[1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_links_expire_after_thirty_days(): void
    {
        $invoiceUrl = InvoiceShareController::makePublicUrl($this->a['invoiceOpen']);
        $quoteUrl = QuotationShareController::makePublicUrl($this->a['quoteSent']);

        $this->travel(29)->days();
        $this->get($invoiceUrl)->assertOk();
        $this->get($quoteUrl)->assertOk();

        $this->travel(2)->days(); // day 31
        $this->get($invoiceUrl)->assertForbidden();
        $this->get($quoteUrl)->assertForbidden();
    }

    public function test_a_tampered_signature_id_or_expiry_is_refused(): void
    {
        foreach ([
            [InvoiceShareController::makePublicUrl($this->a['invoiceOpen']), '/i/' . $this->b['invoiceOpen']->id],
            [QuotationShareController::makePublicUrl($this->a['quoteSent']), '/q/' . $this->b['quoteSent']->id],
        ] as [$url, $otherPath]) {
            [$path, $q] = $this->parts($url);

            // One flipped hex digit in the signature.
            $bad = $q;
            $bad['signature'] = substr($q['signature'], 0, -1) . ($q['signature'][-1] === 'a' ? 'b' : 'a');
            $this->get($this->build($path, $bad))->assertForbidden();

            // A's valid signature on B's document.
            $this->get($this->build($otherPath, $q))->assertForbidden();

            // Pushing the expiry out by a year.
            $later = $q;
            $later['expires'] = (string) ((int) $q['expires'] + 365 * 86400);
            $this->get($this->build($path, $later))->assertForbidden();

            // Dropping the expiry altogether.
            $this->get($this->build($path, ['signature' => $q['signature']]))->assertForbidden();

            // No signature: the bare, sequential id is not enough.
            $this->get($path)->assertForbidden();
            $this->get($path . '/pdf')->assertForbidden();
        }
    }

    public function test_a_signature_is_an_unguessable_keyed_hash(): void
    {
        [, $q] = $this->parts(InvoiceShareController::makePublicUrl($this->a['invoiceOpen']));
        // HMAC-SHA256 as hex: 64 characters = 256 bits.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $q['signature']);

        // The same link signed with another key does not verify here.
        [$path, $q2] = $this->parts(URL::signedRoute('invoices.public', ['invoice' => $this->a['invoiceOpen']->id], now()->addDays(30)));
        $forged = hash_hmac('sha256', url($path) . '?expires=' . $q2['expires'], 'not-the-app-key');
        $this->get($this->build($path, ['expires' => $q2['expires'], 'signature' => $forged]))->assertForbidden();
    }

    public function test_a_cancelled_invoice_is_gone_from_its_link_and_its_pdf(): void
    {
        $url = InvoiceShareController::makePublicUrl($this->a['invoiceCancelled']);
        $this->get($url)->assertStatus(410);
        $pdf = URL::signedRoute('invoices.public.pdf', ['invoice' => $this->a['invoiceCancelled']->id], now()->addDays(30));
        $this->get($pdf)->assertStatus(410);
    }

    public function test_a_link_cancelled_after_sharing_stops_working(): void
    {
        $url = InvoiceShareController::makePublicUrl($this->a['invoiceOpen']);
        $this->get($url)->assertOk();
        $this->actingAs($this->a['owner'])->post(route('invoices.cancel', $this->a['invoiceOpen']), ['cancellation_reason' => 'Wrong buyer'])
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->get($url)->assertStatus(410);
    }

    public function test_the_public_page_shows_only_this_invoice(): void
    {
        // A's owner also runs a second business with its own customers.
        $a2 = $this->buildBusiness('A2', $this->a['owner']);
        auth()->logout();

        $html = $this->get(InvoiceShareController::makePublicUrl($this->a['invoiceOpen']))->assertOk()->getContent();
        foreach ([
            'SECRET-CUSTOMER-B', 'SECRET-FIRM-B', 'SECRET-ACCT-B',       // another user
            'SECRET-FIRM-A2', 'SECRET-CUSTOMER-A2', 'SECRET-ACCT-A2',    // the seller's other business
            'SECRET-PROSPECT-A', 'SECRET-VENDOR-A', 'SECRET-RENT-A',     // the seller's other records
            $this->a['owner']->email,                                    // the seller's login
            $this->a['invoicePaid']->invoice_number,                     // the seller's other invoices (INV-0002)
        ] as $marker) {
            $this->assertFalse(str_contains($html, $marker), "the public page shows {$marker}");
        }
        // No signed-in chrome: no app navigation, no CSRF-bearing forms.
        $this->assertFalse(str_contains($html, 'name="_token"'), 'the public page carries an app form');
        $this->assertFalse(str_contains($html, route('dashboard')), 'the public page links into the app');
    }

    public function test_a_declined_quotation_link_is_gone(): void
    {
        $url = QuotationShareController::makePublicUrl($this->a['quoteSent']);
        $this->get($url)->assertOk();
        $this->actingAs($this->a['owner'])->post(route('quotations.decline', $this->a['quoteSent']), ['decline_reason' => 'Too costly']);
        auth()->logout();
        $this->get($url)->assertStatus(410);
    }

    /**
     * Drafts "have no shareable identity yet" (Invoice::whatsappMessage),
     * and a draft is not a tax invoice: it can still change or be deleted.
     * The owner's share-link endpoint nevertheless signs one, and the public
     * route then shows the draft to anyone holding it.
     */
    public function test_a_draft_invoice_cannot_be_opened_through_a_public_link(): void
    {
        $link = $this->actingAs($this->a['owner'])->getJson(route('invoices.share.link', $this->a['invoiceDraft']))->json('url');
        auth()->logout();
        $url = $link ?: InvoiceShareController::makePublicUrl($this->a['invoiceDraft']);

        $status = $this->get($url)->getStatusCode();
        $this->assertContains($status, [403, 404, 410], "a draft opened publicly ({$status})");
    }

    public function test_the_public_pages_cannot_be_framed(): void
    {
        foreach ([InvoiceShareController::makePublicUrl($this->a['invoiceOpen']), QuotationShareController::makePublicUrl($this->a['quoteSent'])] as $url) {
            $res = $this->get($url)->assertOk();
            $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $this->assertStringContainsString("frame-ancestors 'self'", (string) $res->headers->get('Content-Security-Policy'));
            $res->assertHeader('X-Content-Type-Options', 'nosniff');
            $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $this->assertStringContainsString('noindex', $res->getContent());
        }
    }
}
