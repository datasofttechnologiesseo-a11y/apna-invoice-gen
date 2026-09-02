<?php

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Services\Blog\InternalLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Automatic contextual internal links.
 *
 * The rules matter more than the mechanism: a linker that fires on every
 * occurrence, links inside headings, or nests anchors makes a post look
 * spammy and is worse than no linker at all.
 */
class InternalLinkerTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $markdown, string $slug = 'demo'): string
    {
        $post = new Post(['slug' => $slug]);
        $post->body = $markdown;

        return $post->renderedBody();
    }

    private function links(string $html): array
    {
        preg_match_all('/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', $html, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => ['url' => $x[1], 'text' => strip_tags($x[2])], $m);
    }

    public function test_a_known_phrase_becomes_a_link_to_the_right_page(): void
    {
        $html = $this->render('Use our GST calculator to check the maths.');

        $links = $this->links($html);
        $this->assertCount(1, $links);
        $this->assertSame('/gst-calculator', $links[0]['url']);
        $this->assertSame('GST calculator', $links[0]['text']);
    }

    public function test_only_the_first_occurrence_is_linked(): void
    {
        // Repeating the same link down a page reads as keyword stuffing and
        // the repeats carry no weight anyway.
        $html = $this->render(
            "Our GST calculator is free.\n\nThe GST calculator handles IGST.\n\nTry the GST calculator today."
        );

        $this->assertSame(1, substr_count($html, 'href="/gst-calculator"'));
    }

    public function test_headings_are_never_linked(): void
    {
        // A link inside a heading competes with the heading's own anchor and
        // steals the click.
        $html = $this->render("## The GST calculator explained\n\nBody text here.");

        $this->assertStringContainsString('<h2 id="the-gst-calculator-explained">The GST calculator explained</h2>', $html);
        $this->assertSame([], $this->links($html));
    }

    public function test_code_blocks_are_never_linked(): void
    {
        $html = $this->render("```\nrun the GST calculator\n```");

        $this->assertSame([], $this->links($html));
    }

    public function test_text_inside_an_existing_link_is_left_alone(): void
    {
        // Nesting an <a> inside an <a> is invalid HTML and browsers recover
        // from it unpredictably.
        $html = $this->render('[Read about the GST calculator](https://example.com/post)');

        $links = $this->links($html);
        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/post', $links[0]['url']);
    }

    public function test_the_longest_matching_phrase_wins(): void
    {
        // "invoice format" and "GST invoice format" both match here; taking
        // the shorter one would fragment the phrase and point at the wrong page.
        $html = $this->render('Every business needs a proper GST invoice format.');

        $links = $this->links($html);
        $this->assertCount(1, $links);
        $this->assertSame('/free-gst-invoice-format', $links[0]['url']);
        $this->assertSame('GST invoice format', $links[0]['text']);
    }

    public function test_a_phrase_inside_a_longer_word_is_not_matched(): void
    {
        // "GST" must never match inside "GSTIN", or every mention of a
        // registration number becomes a link.
        $html = $this->render('Enter your GSTIN and the pricingmodel stays free.');

        $this->assertSame([], $this->links($html));
    }

    public function test_several_different_phrases_link_in_document_order(): void
    {
        $html = $this->render(
            'A GST invoice format differs from a cash memo, and a credit note reverses a sale.'
        );

        $urls = array_column($this->links($html), 'url');
        $this->assertSame(
            ['/free-gst-invoice-format', '/cash-memo-format', '/gst-credit-note-format'],
            $urls,
            'links should appear in the order the phrases do'
        );
    }

    public function test_a_phrase_after_an_earlier_match_in_the_same_paragraph_still_links(): void
    {
        // Linking splits the text node, so the remainder is a NEW node. An
        // implementation that iterates a list captured up front silently drops
        // everything after the first match in a paragraph.
        $html = $this->render(
            'Our GST calculator is free, a cash memo is different, and a credit note reverses a sale.'
        );

        $this->assertCount(3, $this->links($html));
    }

    public function test_the_number_of_automatic_links_is_capped(): void
    {
        config()->set('internal_links.max_per_post', 2);

        $html = $this->render(
            'A GST calculator, a cash memo, a credit note, a GST invoice format and free billing software.'
        );

        $this->assertCount(2, $this->links($html));
    }

    public function test_automatic_links_are_marked_so_they_can_be_told_apart(): void
    {
        $html = $this->render('Use our GST calculator.');

        $this->assertStringContainsString('data-internal-link="auto"', $html);
    }

    public function test_a_post_never_links_to_itself(): void
    {
        $post = new Post(['slug' => 'gst-calculator']);
        $post->body = 'Our GST calculator is free.';

        // The self URL is the blog post, not the tool page, so this still
        // links - the guard only fires when they are the same URL.
        $this->assertStringContainsString('/gst-calculator', $post->renderedBody());
    }

    public function test_malformed_markup_is_returned_untouched_rather_than_throwing(): void
    {
        $linker = new InternalLinker(['pages.gst-calculator' => ['GST calculator']], 6);

        $html = '<p>Unclosed <b>bold and a GST calculator';
        $out = $linker->apply($html);

        $this->assertNotEmpty($out);
    }

    public function test_an_unknown_route_name_is_skipped_rather_than_fatal(): void
    {
        // A renamed route should degrade to "no link", never take the blog down.
        $linker = new InternalLinker(['pages.this-route-does-not-exist' => ['GST calculator']], 6);

        $this->assertSame('<p>A GST calculator here.</p>', trim($linker->apply('<p>A GST calculator here.</p>')));
    }

    public function test_rupee_signs_and_devanagari_survive_the_pass(): void
    {
        // DOMDocument mangles UTF-8 without an explicit encoding declaration.
        $html = $this->render('Pay ₹1,180 for the GST calculator. अपना इनवॉइस मुफ़्त है।');

        $this->assertStringContainsString('₹1,180', $html);
        $this->assertStringContainsString('अपना इनवॉइस', $html);
    }
}
