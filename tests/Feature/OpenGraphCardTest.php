<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Per-page Open Graph share cards.
 *
 * These are resolved by convention from the route name rather than declared on
 * each page, which is what makes adding one a matter of dropping in a file.
 * The risk of a convention is that it breaks silently: rename a route or move
 * a file and every share quietly reverts to the generic card, or worse points
 * at a 404. Nobody notices, because nothing errors.
 */
class OpenGraphCardTest extends TestCase
{
    use RefreshDatabase;

    private function ogImage(string $uri): string
    {
        $html = $this->get($uri)->assertOk()->getContent();
        preg_match('/property="og:image"\s+content="([^"]+)"/', $html, $m);

        return $m[1] ?? '';
    }

    public static function pagesWithOwnCard(): array
    {
        return [
            'calculator'  => ['/gst-calculator', 'og/gst-calculator.png'],
            'invoice fmt' => ['/free-gst-invoice-format', 'og/gst-invoice-format.png'],
            'software'    => ['/free-billing-software', 'og/billing-software.png'],
            'cash memo'   => ['/cash-memo-format', 'og/cash-memo-format.png'],
            'credit note' => ['/gst-credit-note-format', 'og/credit-note-format.png'],
            'pricing'     => ['/pricing', 'og/pricing.png'],
            'how to use'  => ['/how-to-use', 'og/how-to-use.png'],
        ];
    }

    #[DataProvider('pagesWithOwnCard')]
    public function test_the_page_advertises_its_own_card(string $uri, string $card): void
    {
        $this->assertStringContainsString($card, $this->ogImage($uri),
            "{$uri} should share its own card, not the generic one");
    }

    #[DataProvider('pagesWithOwnCard')]
    public function test_the_card_file_exists_at_the_right_size(string $uri, string $card): void
    {
        $path = public_path('brand/'.$card);
        $this->assertFileExists($path, 'a page points at a card that is not on disk');

        [$w, $h] = getimagesize($path);
        // Facebook, LinkedIn, WhatsApp and X all crop from 1200x630. Anything
        // else gets letterboxed or centre-cropped through the headline.
        $this->assertSame(1200, $w);
        $this->assertSame(630, $h);
    }

    public function test_pages_without_a_card_fall_back_to_the_default(): void
    {
        // The fallback is what makes the convention safe: a missing card must
        // give a generic image, never a broken one.
        foreach (['/features', '/faq', '/about', '/contact'] as $uri) {
            $this->assertStringContainsString('og-card.png', $this->ogImage($uri),
                "{$uri} should fall back to the default card");
        }
    }

    public function test_no_page_ever_points_at_a_card_that_is_missing(): void
    {
        $uris = ['/', '/features', '/pricing', '/how-to-use', '/faq', '/about', '/contact',
            '/gst-calculator', '/free-gst-invoice-format', '/free-billing-software',
            '/cash-memo-format', '/gst-credit-note-format', '/blog', '/partners'];

        foreach ($uris as $uri) {
            $img = $this->ogImage($uri);
            $this->assertNotSame('', $img, "{$uri} has no og:image at all");

            $relative = parse_url($img, PHP_URL_PATH);
            $this->assertFileExists(public_path(ltrim($relative, '/')),
                "{$uri} advertises {$relative}, which is not on disk");
        }
    }

    public function test_every_generated_card_is_actually_used_by_a_page(): void
    {
        // A card nobody references is dead weight in the repo and a sign a
        // route was renamed without the file following it.
        $cards = glob(public_path('brand/og/*.png')) ?: [];
        $this->assertNotEmpty($cards);

        $referenced = [];
        foreach (self::pagesWithOwnCard() as [$uri, $card]) {
            $referenced[] = basename($card);
        }

        foreach ($cards as $file) {
            $this->assertContains(basename($file), $referenced,
                basename($file).' is generated but no page points at it');
        }
    }
}
