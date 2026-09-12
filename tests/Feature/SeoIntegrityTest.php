<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The parts of the SEO surface that fail silently.
 *
 * Titles, canonicals and share cards are easy to eyeball and easy to keep
 * right. What is not visible from a browser is a sitemap that lists a URL
 * nobody serves any more, a canonical that quietly follows whatever Host the
 * request arrived on, or structured data that stopped parsing because a post
 * title contained a character JSON-LD cannot carry inside a script tag. All
 * three cost rankings without ever looking broken.
 */
class SeoIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** @return string[] every <loc> in the sitemap */
    private function sitemapUrls(): array
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        preg_match_all('#<loc>(.*?)</loc>#', $xml, $matches);

        return $matches[1];
    }

    /** @return array<int, array<string, string>> the decoded JSON-LD blocks on a page */
    private function jsonLdBlocks(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $blocks = [];
        foreach ($matches[1] as $raw) {
            $decoded = json_decode($raw, true);
            $this->assertNotNull(
                $decoded,
                'a JSON-LD block did not parse: ' . json_last_error_msg() . ' — ' . mb_substr($raw, 0, 120)
            );
            $blocks[] = $decoded;
        }

        return $blocks;
    }

    public function test_a_closing_script_tag_in_a_post_title_cannot_break_the_structured_data(): void
    {
        // json_encode is called with JSON_UNESCAPED_SLASHES, which deliberately
        // leaves "/" alone - so "</script>" inside a title lands verbatim
        // inside the ld+json block and ends it early. Everything after is
        // parsed as HTML: the structured data is lost and the markup carries
        // whatever followed.
        $author = User::factory()->create();
        $post = Post::factory()->recycle($author)->published()->create([
            'title' => 'GST rates </script><script>alert(1)</script> explained',
            'slug' => 'gst-rates-script-title',
        ]);

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        $blocks = $this->jsonLdBlocks($html);
        $this->assertNotEmpty($blocks, 'the post should still emit structured data');

        $headlines = array_column($blocks, 'headline');
        $this->assertContains($post->title, $headlines, 'the real title should survive intact');
    }

    public function test_the_canonical_ignores_whatever_host_the_request_arrived_on(): void
    {
        // Every alias self-canonicalising is how indexing signals get split
        // across www, the apex and a hosting preview subdomain.
        $html = $this->get('/', ['HOST' => 'preview-7f2.staging.example.net'])->assertOk()->getContent();

        preg_match('#<link rel="canonical" href="([^"]+)"#', $html, $m);
        $this->assertNotEmpty($m, 'the home page should carry a canonical');
        $this->assertStringStartsWith(rtrim(config('app.url'), '/'), $m[1]);
        $this->assertStringNotContainsString('staging.example.net', $html);
    }

    public function test_every_url_the_sitemap_advertises_is_actually_served(): void
    {
        $author = User::factory()->create();
        Post::factory()->recycle($author)->published()->create(['slug' => 'a-real-published-post']);

        $urls = $this->sitemapUrls();
        $this->assertNotEmpty($urls, 'the sitemap should list something');

        $base = rtrim(config('app.url'), '/');
        foreach ($urls as $url) {
            $this->assertStringStartsWith($base, $url, "sitemap URL is off-host: {$url}");

            $path = '/' . ltrim(substr($url, strlen($base)), '/');
            $status = $this->get($path)->getStatusCode();

            $this->assertSame(200, $status, "sitemap advertises {$path} but it returns {$status}");
        }
    }

    public function test_the_sitemap_never_advertises_a_draft(): void
    {
        $author = User::factory()->create();
        Post::factory()->recycle($author)->create(['slug' => 'still-a-draft']);
        Post::factory()->recycle($author)->scheduled()->create(['slug' => 'not-out-yet']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('still-a-draft', $xml);
        $this->assertStringNotContainsString('not-out-yet', $xml);
    }

    public function test_every_marketing_page_carries_the_tags_a_crawler_and_a_share_need(): void
    {
        foreach ($this->sitemapUrls() as $url) {
            if (str_ends_with($url, '.pdf')) {
                continue;
            }

            $path = '/' . ltrim(substr($url, strlen(rtrim(config('app.url'), '/'))), '/');
            $html = $this->get($path)->assertOk()->getContent();

            foreach ([
                '#<title>[^<]+</title>#' => 'a <title>',
                '#<meta name="description" content="[^"]+"#' => 'a meta description',
                '#<link rel="canonical" href="https?://[^"]+"#' => 'an absolute canonical',
                '#<meta property="og:title" content="[^"]+"#' => 'an og:title',
                '#<meta property="og:image" content="https?://[^"]+"#' => 'an absolute og:image',
                '#<meta name="twitter:card" content="summary_large_image"#' => 'a twitter card',
            ] as $pattern => $what) {
                $this->assertMatchesRegularExpression($pattern, $html, "{$path} is missing {$what}");
            }
        }
    }

    public function test_the_signed_in_app_is_kept_out_of_the_index(): void
    {
        $user = User::factory()->create();
        $user->ensureCompany();

        $html = $this->actingAs($user)->get(route('invoices.index'))->assertOk()->getContent();
        $this->assertStringContainsString('noindex', $html);

        // And robots.txt should say the same thing, so a crawler that never
        // renders the page still stays out.
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /invoices');
    }

    public function test_no_public_page_links_to_a_page_that_does_not_exist(): void
    {
        // Internal links are the crawl paths between pages, and a dead one is
        // invisible from the page carrying it - the anchor still renders, still
        // looks clickable, and quietly wastes crawl budget while sending a
        // reader to a 404. Rendering each public page and following every
        // internal href is the only way to notice.
        $author = User::factory()->create();
        Post::factory()->recycle($author)->published()->create(['slug' => 'a-linked-post']);

        $base = rtrim(config('app.url'), '/');
        $broken = [];
        $checked = [];

        foreach ($this->sitemapUrls() as $url) {
            if (str_ends_with($url, '.pdf')) {
                continue;
            }

            $from = '/' . ltrim(substr($url, strlen($base)), '/');
            $html = $this->get($from)->assertOk()->getContent();

            // ~ delimiter: the pattern needs a literal # in a character class
            // to skip in-page anchors, and # cannot also be the delimiter.
            //
            // The lookbehind matters. Alpine binds links as :href="expr" and
            // x-bind:href="expr", and a plain href= match happily reads the
            // expression out of those - which is how a first run of this test
            // "found" /whatsappLink and /whatsAppShare() as broken pages. They
            // are bindings doing their job, not links.
            preg_match_all('~<a\s[^>]*(?<![:\w-])href="([^"#][^"]*)"~i', $html, $matches);

            foreach (array_unique($matches[1]) as $href) {
                // Off-site links, mail/tel/whatsapp and in-page anchors are not
                // this test's business.
                if (preg_match('#^(mailto:|tel:|javascript:|data:|https?://)#i', $href)
                    && ! str_starts_with($href, $base)) {
                    continue;
                }

                $target = str_starts_with($href, $base) ? substr($href, strlen($base)) : $href;
                $target = '/' . ltrim(strtok($target, '#'), '/');

                if ($target === '/' && $from === '/') {
                    continue;
                }
                if (isset($checked[$target])) {
                    continue;
                }
                $checked[$target] = true;

                // A real file under public/ is served by the web server, not by
                // a route. The test kernel has no route for it and would report
                // a 404 for a download that works perfectly in a browser.
                if (is_file(public_path(ltrim($target, '/')))) {
                    continue;
                }

                $status = $this->get($target)->getStatusCode();

                // 302 is a signed-out visitor being sent to login, which is the
                // link working correctly. Only genuinely dead ends count.
                if ($status >= 400) {
                    $broken[] = "{$from} links to {$target} ({$status})";
                }
            }
        }

        $this->assertNotEmpty($checked, 'the crawl should have followed some links');
        $this->assertSame([], $broken, "dead internal links:\n" . implode("\n", $broken));
    }

    public function test_the_blog_feed_is_parseable_and_lists_published_posts_only(): void
    {
        $author = User::factory()->create();
        Post::factory()->recycle($author)->published()->create(['slug' => 'in-the-feed', 'title' => 'In the feed']);
        Post::factory()->recycle($author)->create(['slug' => 'not-in-the-feed', 'title' => 'Not in the feed']);

        $xml = $this->get(route('blog.feed'))->assertOk()->getContent();

        $this->assertNotFalse(simplexml_load_string($xml), 'the feed should be well-formed XML');
        $this->assertStringContainsString('in-the-feed', $xml);
        $this->assertStringNotContainsString('not-in-the-feed', $xml);
    }
}
