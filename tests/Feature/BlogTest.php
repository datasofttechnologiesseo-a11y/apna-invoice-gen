<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    // ─── Public visibility ───────────────────────────────────────────────

    public function test_blog_index_is_publicly_accessible(): void
    {
        $this->get('/blog')->assertOk()->assertSee('Apna Invoice Blog', false);
    }

    public function test_body_h1_is_demoted_to_h2_for_single_h1_seo(): void
    {
        // The post title is the page's one <h1>; a "# Heading" in the body
        // must render as <h2> — both for SEO and because nothing styles a
        // body <h1> (it displayed as plain 16px text).
        $post = new Post(['body' => "# Hash Heading\n\nPara with **bold**.\n\n## Real H2"]);
        $html = $post->renderedBody();

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString('<h2>Hash Heading</h2>', $html);
        $this->assertStringContainsString('<h2>Real H2</h2>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_blog_index_lists_only_published_posts(): void
    {
        Post::factory()->published()->create(['title' => 'Live post']);
        Post::factory()->create(['title' => 'Draft post']); // status=draft default

        $response = $this->get('/blog');
        $response->assertOk()
            ->assertSee('Live post')
            ->assertDontSee('Draft post');
    }

    public function test_blog_index_excludes_scheduled_future_posts(): void
    {
        Post::factory()->scheduled()->create(['title' => 'Future post']);

        $this->get('/blog')->assertOk()->assertDontSee('Future post');
    }

    public function test_blog_show_renders_published_post_with_seo_meta(): void
    {
        $post = Post::factory()->published()->create([
            'title' => 'How GST works',
            'slug' => 'how-gst-works',
            'meta_description' => 'A no-jargon guide to India GST for small businesses and freelancers.',
            'meta_keywords' => 'GST, India, MSME',
            'body' => "## Intro\n\nThis post explains GST clearly. CGST and SGST split.\n",
        ]);

        $response = $this->get('/blog/how-gst-works');
        $response->assertOk()
            ->assertSee('How GST works', false)
            ->assertSee('A no-jargon guide to India GST', false)
            ->assertSee('"@type":"BlogPosting"', false)            // JSON-LD article schema (json_encode default has no spaces)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_blog_show_404s_for_draft_post(): void
    {
        Post::factory()->create(['slug' => 'draft-post']);  // draft default
        $this->get('/blog/draft-post')->assertNotFound();
    }

    public function test_blog_show_404s_for_scheduled_post(): void
    {
        Post::factory()->scheduled()->create(['slug' => 'future-post']);
        $this->get('/blog/future-post')->assertNotFound();
    }

    public function test_view_count_increments_on_show(): void
    {
        $post = Post::factory()->published()->create(['view_count' => 0]);

        $this->get('/blog/' . $post->slug)->assertOk();
        $this->get('/blog/' . $post->slug)->assertOk();

        $this->assertSame(2, $post->fresh()->view_count);
    }

    public function test_markdown_body_renders_to_safe_html(): void
    {
        $post = Post::factory()->published()->create([
            'slug' => 'mark-down',
            'body' => "## Heading\n\n**Bold** text and a [link](https://example.com).\n\n<script>alert('xss')</script>",
        ]);

        $response = $this->get('/blog/mark-down');
        $response->assertOk()
            ->assertSee('<h2>Heading</h2>', false)
            ->assertSee('<strong>Bold</strong>', false)
            // <script> is escaped, not rendered
            ->assertDontSee('<script>alert', false);
    }

    // ─── Admin protection ────────────────────────────────────────────────

    public function test_admin_blog_routes_require_super_admin(): void
    {
        $regular = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($regular)->get('/admin/blog')->assertStatus(403);
        $this->actingAs($regular)->get('/admin/blog/create')->assertStatus(403);
    }

    public function test_anonymous_blocked_from_admin_blog(): void
    {
        $this->get('/admin/blog')->assertRedirect('/login');
    }

    public function test_super_admin_can_view_blog_index(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get('/admin/blog')->assertOk();
    }

    // ─── CRUD ────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_a_post(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/admin/blog', [
            'title' => 'Filing GSTR-1 in 5 minutes',
            'slug' => '',
            'body' => "## Intro\n\nHere's how.",
            'status' => 'published',
            'meta_description' => 'A practical 5-minute walkthrough of filing GSTR-1 from your invoice list.',
        ])->assertRedirect();

        $this->assertDatabaseCount('posts', 1);
        $post = Post::first();
        $this->assertSame('filing-gstr-1-in-5-minutes', $post->slug);
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame($admin->id, $post->user_id);
        $this->assertSame(1, $post->reading_minutes);
    }

    public function test_slug_is_unique_with_auto_suffix(): void
    {
        $admin = $this->superAdmin();
        Post::factory()->create(['slug' => 'gst-tips']);

        $slug = Post::generateSlug('GST Tips');
        $this->assertSame('gst-tips-2', $slug);
    }

    public function test_super_admin_can_update_a_post(): void
    {
        $admin = $this->superAdmin();
        $post = Post::factory()->create(['user_id' => $admin->id, 'title' => 'Old title']);

        $this->actingAs($admin)->patch('/admin/blog/' . $post->id, [
            'title' => 'New title',
            'slug' => $post->slug,
            'body' => 'Updated body content here.',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertSame('New title', $post->fresh()->title);
    }

    public function test_super_admin_can_delete_a_post(): void
    {
        $admin = $this->superAdmin();
        $post = Post::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)->delete('/admin/blog/' . $post->id)->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_toggle_publish_endpoint(): void
    {
        $admin = $this->superAdmin();
        $post = Post::factory()->create(['user_id' => $admin->id, 'status' => 'draft']);

        $this->actingAs($admin)->post('/admin/blog/' . $post->id . '/toggle')->assertRedirect();
        $this->assertSame('published', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);

        $this->actingAs($admin)->post('/admin/blog/' . $post->id . '/toggle')->assertRedirect();
        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_create_validates_required_fields(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/admin/blog', [])
            ->assertSessionHasErrors(['title', 'body', 'status']);
    }

    public function test_featured_image_uploads_and_persists_path(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/admin/blog', [
            'title' => 'With image',
            'body' => 'Body.',
            'status' => 'draft',
            'featured_image' => UploadedFile::fake()->image('cover.jpg', 1200, 675),
        ])->assertRedirect();

        $post = Post::first();
        $this->assertNotNull($post->featured_image_path);
        Storage::disk('public')->assertExists($post->featured_image_path);
    }

    // ─── Sitemap ─────────────────────────────────────────────────────────

    public function test_sitemap_includes_published_blog_posts(): void
    {
        Post::factory()->published()->create(['slug' => 'first-post']);
        Post::factory()->create(['slug' => 'draft-post']);

        $response = $this->get('/sitemap.xml');
        $response->assertOk()
            ->assertSee('/blog/first-post', false)
            ->assertDontSee('/blog/draft-post', false);
    }
}
