<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 80mm thermal receipt has constraints an A4 page does not: the print head
 * only reaches ~72mm of the 80mm roll, and it prints one bit per dot, so grey
 * dithers into something faint and patchy. These lock in both.
 */
class ThermalReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function receiptHtml(): string
    {
        $state = State::factory()->create(['gst_code' => '27']);
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create([
            'name' => 'Sharma General Store', 'state_id' => $state->id,
            'gstin' => '27ABCDE1234F1Z5',
        ]);
        $customer = Customer::factory()->recycle($user)->recycle($company)
            ->create(['state_id' => $state->id]);

        $invoice = Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)
            ->create(['status' => 'final', 'finalized_at' => now(), 'invoice_number' => 'INV/2026-27/0001']);
        InvoiceItem::factory()->recycle($invoice)->create();

        $res = $this->actingAs($user)->get(route('invoices.print', $invoice).'?format=thermal');
        $res->assertOk();

        return $res->getContent();
    }

    public function test_page_is_declared_as_80mm_paper(): void
    {
        $html = $this->receiptHtml();

        $this->assertStringContainsString('size: 80mm auto', $html);
        $this->assertStringContainsString('margin: 0', $html);
    }

    public function test_content_stays_inside_the_printable_area(): void
    {
        $html = $this->receiptHtml();

        // border-box is what makes the declared width the true outer width.
        // Without it, 76mm + 2mm padding each side measured 80mm and the
        // print head clipped both edges.
        $this->assertMatchesRegularExpression(
            '/\.receipt\s*\{[^}]*box-sizing:\s*border-box/s',
            $html,
            'the receipt must use border-box or its real width exceeds the printable area'
        );
        $this->assertMatchesRegularExpression('/\.receipt\s*\{[^}]*width:\s*76mm/s', $html);
    }

    public function test_nothing_prints_in_grey(): void
    {
        $html = $this->receiptHtml();

        // Pull just the stylesheet, then look for any non-black text colour.
        preg_match('/<style>(.*?)<\/style>/s', $html, $m);
        $css = $m[1] ?? '';

        preg_match_all('/color:\s*(#[0-9a-fA-F]{3,6})/', $css, $colours);
        foreach ($colours[1] as $hex) {
            $hex = strtolower($hex);
            // #fff is fine (screen-only chrome); greys are not.
            $this->assertNotContains(
                $hex,
                ['#333', '#333333', '#444', '#555', '#666', '#777', '#888', '#999'],
                "thermal output must be pure black, found {$hex}"
            );
        }
    }

    public function test_type_never_drops_below_the_legible_floor(): void
    {
        $html = $this->receiptHtml();
        preg_match('/<style>(.*?)<\/style>/s', $html, $m);
        $css = $m[1] ?? '';

        // Screen-only chrome lives inside @media screen; strip it before checking.
        $printCss = preg_replace('/@media screen\s*\{.*?\n        \}/s', '', $css);

        preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $printCss, $sizes);
        foreach ($sizes[1] as $px) {
            $this->assertGreaterThanOrEqual(
                10,
                (float) $px,
                "type below 10px is unreadable on receipt paper, found {$px}px"
            );
        }
    }

    public function test_screen_zoom_never_reaches_the_paper(): void
    {
        $html = $this->receiptHtml();

        // The preview is scaled up for proof-reading; the print block must undo it.
        $this->assertMatchesRegularExpression(
            '/@media print\s*\{.*?\.receipt\s*\{[^}]*transform:\s*none/s',
            $html,
            'the on-screen zoom must be reset for print'
        );
    }

    public function test_dividers_survive_printing(): void
    {
        $html = $this->receiptHtml();

        $this->assertStringContainsString('print-color-adjust: exact', $html);
    }
}
