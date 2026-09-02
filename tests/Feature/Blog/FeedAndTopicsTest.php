<?php

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The RSS feed and topic navigation.
 *
 * A feed is one of the few things whose absence reads as "this blog is not
 * really maintained", and it is the kind of endpoint that breaks silently:
 * malformed XML still returns 200, so only a parser notices.
 */
class FeedAndTopicsTest extends TestCase
{
    use RefreshDatabase;

    private function publish(string $slug, string $title, ?string $keywords = null): Post
    {
        $post = new Post;
        $post->user_id = User::factory()->create(['name' => 'Priya Sharma'])->id;
        $post->title = $title;
        $post->slug = $slug;
        $post->status = 'published';
        $post->published_at = now()->subDay();
        $post->excerpt = 'A short summary of the article for readers and feeds.';
        $post->meta_keywords = $keywords;
        $post->body = "## A heading\n\nSome body copy long enough to read as a real paragraph of prose.";
        $post->save();

        return $post;
    }

    public function test_the_feed_is_well_formed_xml(): void
    {
        $this->publish('first-post', 'First Post');

        $res = $this->get('/blog/feed.xml')->assertOk();
        $this->assertStringContainsString('application/rss+xml', $res->headers->get('Content-Type'));

        // 200 with malformed XML is the failure mode that hides, so parse it.
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($res->getContent());
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($xml, 'the feed is not parseable XML');
        $this->assertSame([], $errors);
        $this->assertSame('2.0', (string) $xml['version']);
    }

    public function test_published_posts_appear_as_items(): void
    {
        $this->publish('first-post', 'First Post');
        $this->publish('second-post', 'Second Post');

        $xml = simplexml_load_string($this->get('/blog/feed.xml')->getContent());

        $titles = [];
        foreach ($xml->channel->item as $item) {
            $titles[] = (string) $item->title;
        }

        $this->assertContains('First Post', $titles);
        $this->assertContains('Second Post', $titles);
    }

    public function test_a_draft_never_reaches_the_feed(): void
    {
        $draft = $this->publish('draft-post', 'Draft Post');
        $draft->forceFill(['status' => 'draft'])->save();

        $this->assertStringNotContainsString('Draft Post', $this->get('/blog/feed.xml')->getContent());
    }

    public function test_the_feed_url_is_not_captured_by_the_post_slug_route(): void
    {
        // /blog/{slug} would happily match "feed.xml" and 404 as a missing post.
        $this->publish('first-post', 'First Post');

        $this->get('/blog/feed.xml')->assertOk();
    }

    public function test_the_feed_declares_itself_and_carries_categories(): void
    {
        $this->publish('tagged-post', 'Tagged Post', 'GST invoice, HSN SAC');

        $body = $this->get('/blog/feed.xml')->getContent();

        $this->assertStringContainsString('rel="self"', $body);
        $this->assertStringContainsString('<category>GST invoice</category>', $body);
        $this->assertStringContainsString('<category>HSN SAC</category>', $body);
    }

    public function test_the_blog_pages_advertise_the_feed(): void
    {
        $post = $this->publish('first-post', 'First Post');

        foreach (['/blog', '/blog/'.$post->slug] as $uri) {
            $this->assertStringContainsString('application/rss+xml',
                $this->get($uri)->assertOk()->getContent(),
                "{$uri} should link the feed so readers can discover it");
        }
    }

    public function test_topic_tags_link_into_the_index_search(): void
    {
        $post = $this->publish('tagged-post', 'Tagged Post', 'GST invoice, HSN SAC');

        $html = $this->get('/blog/'.$post->slug)->assertOk()->getContent();

        // A chip that looks clickable and is not is worse than no chip.
        // Asserted encoding-agnostically: %20 and + are both valid here.
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/blog\?search=GST(\+|%20)invoice"/', $html);
    }

    public function test_a_topic_search_returns_the_matching_post(): void
    {
        $this->publish('tagged-post', 'Tagged Post', 'GST invoice, HSN SAC');
        $this->publish('other-post', 'Unrelated Post', 'payroll');

        $html = $this->get('/blog?search='.urlencode('HSN SAC'))->assertOk()->getContent();

        $this->assertStringContainsString('Tagged Post', $html);
        $this->assertStringNotContainsString('Unrelated Post', $html);
    }
}
