<?php

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The command edits real published articles, so the safety behaviour matters
 * more than the restructuring itself: it must not write unless told to, it
 * must back up before it does, and the backup must actually restore.
 */
class RestructureCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Without this the suite writes backup files into real storage and
        // each test sees the previous one's leftovers.
        Storage::fake();
    }

    private const FLAT = "Some Section Heading\n\nA paragraph of real body copy that is long enough to read as prose rather than a heading.\n\n\u{2022} first bullet\n\u{2022} second bullet";

    private function flatPost(string $slug = 'flat'): Post
    {
        $post = new Post;
        $post->user_id = User::factory()->create()->id;
        $post->title = 'A Flattened Post';
        $post->slug = $slug;
        $post->status = 'published';
        $post->published_at = now();
        $post->excerpt = 'x';
        $post->body = self::FLAT;
        $post->save();

        return $post;
    }

    public function test_a_dry_run_reports_without_writing_anything(): void
    {
        $post = $this->flatPost();

        $this->artisan('blog:restructure')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(self::FLAT, $post->fresh()->body,
            'a dry run must never modify the post');
    }

    public function test_apply_rewrites_the_body_and_writes_a_backup(): void
    {
        $post = $this->flatPost();

        $this->artisan('blog:restructure --apply')->assertSuccessful();

        $body = $post->fresh()->body;
        $this->assertStringContainsString('## Some Section Heading', $body);
        $this->assertStringContainsString('- first bullet', $body);

        $this->assertTrue(Storage::exists("blog-backups/{$post->id}.json"),
            'the original must be backed up before it is overwritten');
    }

    public function test_revert_puts_the_original_body_back_exactly(): void
    {
        $post = $this->flatPost();

        $this->artisan('blog:restructure --apply')->assertSuccessful();
        $this->assertNotSame(self::FLAT, $post->fresh()->body);

        $this->artisan('blog:restructure --revert')->assertSuccessful();

        $this->assertSame(self::FLAT, $post->fresh()->body,
            'revert must restore the body byte for byte');
    }

    public function test_a_single_post_can_be_targeted_by_slug(): void
    {
        $target = $this->flatPost('target');
        $other = $this->flatPost('other');

        $this->artisan('blog:restructure --post=target --apply')->assertSuccessful();

        $this->assertStringContainsString('##', $target->fresh()->body);
        $this->assertSame(self::FLAT, $other->fresh()->body,
            'targeting one post must leave the others alone');
    }

    public function test_a_formatting_pass_does_not_reorder_the_blog(): void
    {
        // The blog lists by date. Bumping updated_at would shuffle every post
        // to the top as though it had just been rewritten by its author.
        $post = $this->flatPost();
        $before = $post->fresh()->updated_at;

        $this->artisan('blog:restructure --apply')->assertSuccessful();

        $this->assertEquals(
            $before->timestamp,
            $post->fresh()->updated_at->timestamp,
            'updated_at should not move for a formatting pass'
        );
    }

    public function test_running_it_again_reports_nothing_left_to_do(): void
    {
        $this->flatPost();

        $this->artisan('blog:restructure --apply')->assertSuccessful();

        $this->artisan('blog:restructure')
            ->expectsOutputToContain('already structured')
            ->assertSuccessful();
    }

    public function test_reverting_with_no_backups_is_harmless(): void
    {
        $post = $this->flatPost();

        $this->artisan('blog:restructure --revert')
            ->expectsOutputToContain('No backups found')
            ->assertSuccessful();

        $this->assertSame(self::FLAT, $post->fresh()->body);
    }
}
