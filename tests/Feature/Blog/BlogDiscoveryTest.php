<?php

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How readers and crawlers move around the blog.
 *
 * The posts themselves are well covered - publishing, drafts, markdown safety,
 * the sitemap. What is not covered is the navigation between them: which posts
 * a reader is offered at the end of an article, and which of the many URLs the
 * index can produce are the ones we actually want indexed.
 *
 * Both are the kind of thing that looks fine on screen and quietly wastes the
 * whole point of having an archive.
 */
class BlogDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $slug, string $keywords = '', int $daysAgo = 1): Post
    {
        return Post::factory()->recycle(User::factory())->published()->create([
            'slug' => $slug,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'meta_keywords' => $keywords,
            'published_at' => now()->subDays($daysAgo),
        ]);
    }

    private function canonicalOf(string $url): string
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match('#<link rel="canonical" href="([^"]+)"#', $html, $m);
        $this->assertNotEmpty($m, "no canonical on {$url}");

        return $m[1];
    }

    public function test_the_end_of_an_article_offers_posts_on_the_same_topic(): void
    {
        // Every article used to end with the three newest posts, whatever it
        // was about. Two costs: a reader finishing a piece on e-way bills was
        // offered whatever shipped last week, and the three newest posts
        // collected an internal link from every other post while the rest of
        // the archive collected none.
        $subject = $this->makePost('eway-bill-rules', 'e-way bill, transport, GST', 30);

        $this->makePost('eway-bill-distance-limits', 'e-way bill, transport', 29);
        $this->makePost('transport-documents', 'transport, logistics', 28);
        $this->makePost('choosing-a-gst-consultant', 'consultant, advice', 1);
        $this->makePost('office-rent-deductions', 'rent, deductions', 2);
        $this->makePost('festive-season-billing', 'festival, sales', 3);

        $html = $this->get(route('blog.show', $subject->slug))->assertOk()->getContent();
        $tail = substr($html, strpos($html, 'More blogs'));

        $this->assertStringContainsString('eway-bill-distance-limits', $tail, 'the closest match should be offered');
        $this->assertStringContainsString('transport-documents', $tail, 'a partial topic match should be offered');
    }

    public function test_an_article_with_no_topic_still_gets_somewhere_to_go(): void
    {
        $subject = $this->makePost('an-untagged-post', '', 10);
        $this->makePost('something-recent', 'gst', 1);
        $this->makePost('something-older', 'gst', 2);

        $html = $this->get(route('blog.show', $subject->slug))->assertOk()->getContent();

        $this->assertStringContainsString('More blogs', $html);
        $this->assertStringContainsString('something-recent', $html);
    }

    public function test_an_article_never_offers_itself(): void
    {
        $subject = $this->makePost('gst-basics', 'gst, basics', 5);
        $this->makePost('gst-advanced', 'gst, advanced', 4);

        $tail = substr(
            $this->get(route('blog.show', $subject->slug))->assertOk()->getContent(),
            strpos($this->get(route('blog.show', $subject->slug))->getContent(), 'More blogs')
        );

        $this->assertStringNotContainsString('/blog/gst-basics"', $tail);
    }

    public function test_a_filtered_list_is_not_offered_to_the_index_on_any_page(): void
    {
        // The index can generate an unbounded number of URLs from the search
        // box. Page 1 of a search canonicalised to /blog, which is right - but
        // page 2 self-canonicalised, so the same filter was de-duplicated on
        // one page and advertised as its own on the next. Whichever answer is
        // correct, it cannot be both.
        foreach (range(1, 20) as $i) {
            $this->makePost("gst-post-{$i}", 'gst', $i);
        }

        $searchPageOne = $this->canonicalOf('/blog?search=gst');
        $searchPageTwo = $this->canonicalOf('/blog?search=gst&page=2');

        $this->assertStringNotContainsString('search=', $searchPageOne);
        $this->assertStringNotContainsString('search=', $searchPageTwo);
    }

    public function test_a_filtered_list_is_crawled_but_not_indexed(): void
    {
        foreach (range(1, 3) as $i) {
            $this->makePost("gst-post-{$i}", 'gst', $i);
        }

        $html = $this->get('/blog?search=gst')->assertOk()->getContent();

        // follow, so the crawler still reaches the posts through it; noindex,
        // so a search-results page never competes with the posts themselves.
        // Exact, because "nofollow" contains "follow" and a loose match would
        // pass on precisely the wrong answer.
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
    }

    public function test_unfiltered_pagination_still_stands_on_its_own(): void
    {
        foreach (range(1, 20) as $i) {
            $this->makePost("post-{$i}", 'gst', $i);
        }

        $this->assertSame(rtrim(config('app.url'), '/') . '/blog', $this->canonicalOf('/blog'));
        $this->assertStringContainsString('page=2', $this->canonicalOf('/blog?page=2'));

        $html = $this->get('/blog?page=2')->assertOk()->getContent();
        $this->assertStringContainsString('content="index, follow', $html);
    }
}
