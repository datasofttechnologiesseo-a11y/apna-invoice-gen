<?php

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FAQPage structured data, built from the question headings in a post.
 *
 * Google renders this as an expandable block in the results, which is a real
 * click-through difference on "can I / what is / how do I" queries - most of
 * this subject. It is also the kind of markup that earns a manual action when
 * it does not match visible content, so the rules matter more than the
 * feature: real questions only, real answers only, and nothing invented.
 */
class FaqSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $body): Post
    {
        $post = new Post;
        $post->user_id = User::factory()->create()->id;
        $post->title = 'GST questions';
        $post->slug = 'gst-questions';
        $post->status = 'published';
        $post->published_at = now();
        $post->excerpt = 'x';
        $post->body = $body;
        $post->save();

        return $post;
    }

    private function schemaTypes(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_values(array_filter(array_map(
            fn ($b) => json_decode(trim($b), true)['@type'] ?? null,
            $m[1]
        )));
    }

    public function test_question_headings_become_faq_pairs(): void
    {
        $post = $this->makePost(
            "## Can a GST invoice be sent on WhatsApp?\n\nYes. It can be shared as a PDF or a secure link, and the channel does not decide compliance.\n\n"
            ."## What if the customer has no GSTIN?\n\nIssue it to an unregistered buyer with their name and address; GSTIN is only mandatory when they are registered."
        );

        $pairs = $post->faqPairs();
        $this->assertCount(2, $pairs);
        $this->assertSame('Can a GST invoice be sent on WhatsApp?', $pairs[0]['question']);
        $this->assertStringContainsString('shared as a PDF', $pairs[0]['answer']);
    }

    public function test_a_question_never_swallows_the_headings_after_it(): void
    {
        // The first version matched ".*?\?" across the closing tag, so it ran
        // to the next question mark anywhere in the document and folded three
        // sections into one enormous "question".
        $post = $this->makePost(
            "## Can it be sent on WhatsApp?\n\nYes, as a PDF or a secure link, and the channel does not decide compliance.\n\n"
            ."## What must appear on it\n\nEvery mandatory field still applies, and leaving one out can invalidate the document.\n\n"
            ."## Do I need a paper copy?\n\nNo. An electronically issued invoice is valid on its own with no printed duplicate."
        );

        foreach ($post->faqPairs() as $pair) {
            $this->assertLessThan(80, mb_strlen($pair['question']),
                'a question ran past its own heading: '.$pair['question']);
            $this->assertStringNotContainsString("\n", $pair['question']);
        }
    }

    public function test_a_heading_that_is_not_a_question_is_ignored(): void
    {
        $post = $this->makePost("## What must appear on the invoice\n\nEvery mandatory field still applies to a digital copy of the document.");

        $this->assertSame([], $post->faqPairs());
    }

    public function test_a_question_with_a_thin_answer_is_left_out(): void
    {
        // Marking up an answer too short to be useful is what earns the
        // "invalid FAQ" warnings, so it is dropped rather than padded.
        $post = $this->makePost("## Is it free?\n\nYes.\n\n## Can I use it on mobile?\n\nYes, the whole app works on a phone browser without installing anything at all.");

        $pairs = $post->faqPairs();
        $this->assertCount(1, $pairs);
        $this->assertSame('Can I use it on mobile?', $pairs[0]['question']);
    }

    public function test_the_page_emits_faq_schema_when_there_are_two_or_more(): void
    {
        $post = $this->makePost(
            "## Can a GST invoice be sent on WhatsApp?\n\nYes. It can be shared as a PDF or a secure link, and the channel does not decide compliance.\n\n"
            ."## What if the customer has no GSTIN?\n\nIssue it to an unregistered buyer with their name and address; GSTIN is only mandatory when registered."
        );

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $this->assertContains('FAQPage', $this->schemaTypes($html));
        $this->assertContains('BlogPosting', $this->schemaTypes($html));
        $this->assertContains('BreadcrumbList', $this->schemaTypes($html));
    }

    public function test_a_single_question_does_not_emit_faq_schema(): void
    {
        // One Q&A is not a FAQ, and marking it up as one invites warnings.
        $post = $this->makePost("## Can a GST invoice be sent on WhatsApp?\n\nYes. It can be shared as a PDF or a secure link, and the channel does not decide compliance.");

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $this->assertNotContains('FAQPage', $this->schemaTypes($html));
    }

    public function test_every_marked_up_answer_appears_in_the_visible_page(): void
    {
        // Google's requirement: the markup must reflect content the reader can
        // actually see. Invented answers are a manual-action risk.
        $post = $this->makePost(
            "## Can it be sent on WhatsApp?\n\nYes, it can be shared as a PDF or a secure link to the buyer.\n\n"
            ."## Is a paper copy needed?\n\nNo, an electronically issued invoice stands on its own without a printed duplicate."
        );

        $visible = strip_tags($post->renderedBody());

        foreach ($post->faqPairs() as $pair) {
            $this->assertStringContainsString(mb_substr($pair['answer'], 0, 30), $visible);
        }
    }
}
