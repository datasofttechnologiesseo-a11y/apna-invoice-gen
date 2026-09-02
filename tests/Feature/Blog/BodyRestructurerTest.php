<?php

namespace Tests\Feature\Blog;

use App\Services\Blog\BodyRestructurer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rebuilding structure in posts that were pasted as plain text.
 *
 * The risk here is not missing a heading - it is inventing one. A stray "## "
 * in the middle of a sentence is worse than a flat paragraph, and this runs
 * over real published articles. So most of these tests are about what the
 * restructurer must NOT do.
 */
class BodyRestructurerTest extends TestCase
{
    use RefreshDatabase;

    private function restructure(string $body, ?string $title = null): string
    {
        return (new BodyRestructurer)->restructure($body, $title)['body'];
    }

    /** The shape a Google Docs article has after being pasted into a textarea. */
    private function flattenedArticle(): string
    {
        return <<<TXT
        GST Invoice Rules for 2026

        Every registered business in India must issue a tax invoice under Rule 46 of the CGST Rules. Getting the format wrong is one of the most common reasons input credit gets rejected.

        What must appear on the invoice

        The law is specific about this, and most of the fields are not optional. Leaving one out can invalidate the document for your buyer.

        • Your GSTIN and the buyer's GSTIN
        • HSN or SAC codes for every line
        • Place of supply, which decides CGST/SGST versus IGST

        When to issue it

        Timing is separate from format, and it trips up service businesses in particular because the rule differs from the one for goods.

        1) Goods: on or before removal
        2) Services: within 30 days of supply
        TXT;
    }

    public function test_a_flattened_article_regains_its_headings(): void
    {
        $out = $this->restructure($this->flattenedArticle());

        $this->assertStringContainsString('## What must appear on the invoice', $out);
        $this->assertStringContainsString('## When to issue it', $out);
    }

    public function test_bullet_glyphs_become_markdown_lists(): void
    {
        $out = $this->restructure($this->flattenedArticle());

        $this->assertStringContainsString("- Your GSTIN and the buyer's GSTIN", $out);
        $this->assertStringContainsString('- HSN or SAC codes for every line', $out);
        $this->assertStringNotContainsString('•', $out);
    }

    public function test_paren_numbered_lines_become_an_ordered_list(): void
    {
        $out = $this->restructure($this->flattenedArticle());

        $this->assertStringContainsString('1. Goods: on or before removal', $out);
        $this->assertStringContainsString('2. Services: within 30 days of supply', $out);
    }

    public function test_a_repeated_title_at_the_top_is_removed(): void
    {
        // The post title is already the page's H1; repeating it in the body
        // gives the same words twice at the top of the article.
        $out = $this->restructure($this->flattenedArticle(), 'GST Invoice Rules for 2026');

        $this->assertStringNotContainsString('GST Invoice Rules for 2026', $out);
        $this->assertStringStartsWith('Every registered business', $out);
    }

    public function test_prose_is_never_turned_into_a_heading(): void
    {
        // The failure that would matter: a short sentence inside a paragraph
        // becoming a section heading.
        $body = "This is a normal paragraph.\nIt runs across two lines and should stay prose.\n\nSo should this one, which is short.";

        $out = $this->restructure($body);

        $this->assertStringNotContainsString('##', $out);
    }

    public function test_a_line_ending_in_a_full_stop_is_not_a_heading(): void
    {
        $body = "Short line here.\n\nA following paragraph with enough words in it to look like real body copy.";

        $this->assertStringNotContainsString('##', $this->restructure($body));
    }

    public function test_a_question_can_be_a_heading(): void
    {
        // "What is a GST invoice?" is the single most common heading shape in
        // this subject area, so the full-stop rule must not swallow it.
        $body = "What is a GST invoice?\n\nA tax invoice is the document a registered supplier issues for a taxable supply, and it carries specific mandatory fields.";

        $this->assertStringContainsString('## What is a GST invoice?', $this->restructure($body));
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $once = $this->restructure($this->flattenedArticle());
        $twice = $this->restructure($once);

        $this->assertSame($once, $twice, 'the pass must be safe to re-run');
    }

    public function test_a_post_already_written_in_markdown_is_left_alone(): void
    {
        $body = "## Already a heading\n\nSome body copy that is long enough to read as a real paragraph of prose.\n\n- an existing bullet\n- another one\n";

        $out = $this->restructure($body);

        $this->assertSame(1, substr_count($out, '## Already a heading'));
        $this->assertStringNotContainsString('## - an existing bullet', $out);
    }

    public function test_fenced_code_is_never_touched(): void
    {
        $body = "```\nSome Config Line\n• not a bullet in here\n```\n\nA paragraph after the fence with enough words to be prose.";

        $out = $this->restructure($body);

        $this->assertStringContainsString("• not a bullet in here", $out);
        $this->assertStringNotContainsString('## Some Config Line', $out);
    }

    public function test_consecutive_short_lines_are_not_all_made_headings(): void
    {
        // A run of short lines is a list someone forgot to bullet, not five
        // headings in a row.
        $body = "Delhi\n\nMumbai\n\nBengaluru\n\nChennai";

        $out = $this->restructure($body);

        $this->assertStringNotContainsString('##', $out);
    }

    public function test_headings_end_up_with_blank_lines_around_them(): void
    {
        // Without air above and below, the Markdown renderer runs a heading
        // into the paragraph before it and it stops being a heading at all.
        $out = $this->restructure($this->flattenedArticle());

        $this->assertMatchesRegularExpression('/\n\n## What must appear on the invoice\n\n/', $out);
    }

    public function test_non_breaking_spaces_from_docs_are_normalised(): void
    {
        $body = "Heading With Nbsp\u{00A0}Here\n\nA paragraph following it that is long enough to count as body prose.";

        $out = $this->restructure($body);

        $this->assertStringNotContainsString("\u{00A0}", $out);
    }

    public function test_an_empty_body_is_handled(): void
    {
        $this->assertSame("\n", $this->restructure(''));
    }
}
