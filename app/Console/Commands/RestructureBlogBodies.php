<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\Blog\BodyRestructurer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuild Markdown structure in posts that were pasted in as plain text.
 *
 * This edits real published content, so it reports by default and only writes
 * when told to. Every change is backed up first and can be put back with
 * --revert, because a heuristic that is right nine times out of ten is still
 * wrong on the tenth post.
 */
class RestructureBlogBodies extends Command
{
    protected $signature = 'blog:restructure
        {--apply : Write the changes. Without this the command only reports.}
        {--post= : Restructure a single post by id or slug.}
        {--revert : Put back the most recent backup instead of restructuring.}
        {--show : Print the rewritten body in full rather than a summary.}';

    protected $description = 'Rebuild headings and lists in blog posts that lost their formatting on paste';

    private const BACKUP_DIR = 'blog-backups';

    public function handle(BodyRestructurer $restructurer): int
    {
        if ($this->option('revert')) {
            return $this->revert();
        }

        $posts = $this->targetPosts();
        if ($posts->isEmpty()) {
            $this->warn('No posts matched.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $this->line($apply ? '<fg=yellow>APPLYING changes.</>' : '<fg=cyan>Dry run.</> Nothing is written; add --apply when the diff looks right.');
        $this->newLine();

        $touched = 0;

        foreach ($posts as $post) {
            $result = $restructurer->restructure((string) $post->body, $post->title);
            $changes = array_filter($result['changes']);

            if ($result['body'] === (string) $post->body || $changes === []) {
                $this->line(sprintf('  <fg=gray>#%-3d %-46s already structured</>', $post->id, $this->clip($post->title, 44)));
                continue;
            }

            $touched++;
            $summary = collect($changes)->map(fn ($n, $k) => "{$n} {$k}")->implode(', ');
            $this->line(sprintf('  <fg=green>#%-3d</> %-46s %s', $post->id, $this->clip($post->title, 44), $summary));

            if ($this->option('show')) {
                $this->newLine();
                $this->line('<fg=gray>'.str_repeat('-', 70).'</>');
                $this->line($result['body']);
                $this->line('<fg=gray>'.str_repeat('-', 70).'</>');
                $this->newLine();
            }

            if ($apply) {
                $this->backup($post);
                // withoutTimestamps: this is a formatting pass, not an edit by
                // the author, so it should not reorder the blog by updated_at.
                Post::withoutTimestamps(function () use ($post, $result) {
                    $post->body = $result['body'];
                    $post->save();
                });
            }
        }

        $this->newLine();

        if ($touched === 0) {
            $this->info('Every post already has structure. Nothing to do.');

            return self::SUCCESS;
        }

        if ($apply) {
            $this->info("Rewrote {$touched} post(s).");
            $this->line('  Backups: <fg=cyan>'.Storage::path(self::BACKUP_DIR).'</>');
            $this->line('  Put them back with: <fg=yellow>php artisan blog:restructure --revert</>');
        } else {
            $this->info("{$touched} post(s) would change.");
            $this->line('  Review one in full: <fg=yellow>php artisan blog:restructure --post=<id> --show</>');
            $this->line('  Then write it:      <fg=yellow>php artisan blog:restructure --apply</>');
        }

        return self::SUCCESS;
    }

    private function targetPosts()
    {
        $query = Post::query()->orderBy('id');

        if ($needle = $this->option('post')) {
            $query->where(fn ($q) => $q->where('id', $needle)->orWhere('slug', $needle));
        }

        return $query->get();
    }

    private function backup(Post $post): void
    {
        Storage::put(
            self::BACKUP_DIR."/{$post->id}.json",
            json_encode([
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'body' => $post->getOriginal('body'),
                'backed_up_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function revert(): int
    {
        $files = Storage::files(self::BACKUP_DIR);
        if ($files === []) {
            $this->warn('No backups found. Nothing to revert.');

            return self::SUCCESS;
        }

        $only = $this->option('post');
        $restored = 0;

        foreach ($files as $file) {
            $data = json_decode((string) Storage::get($file), true);
            if (! is_array($data) || ! isset($data['id'], $data['body'])) {
                continue;
            }
            if ($only && (string) $data['id'] !== (string) $only && ($data['slug'] ?? null) !== $only) {
                continue;
            }

            $post = Post::find($data['id']);
            if (! $post) {
                continue;
            }

            Post::withoutTimestamps(function () use ($post, $data) {
                $post->body = $data['body'];
                $post->save();
            });
            $restored++;
            $this->line("  restored #{$data['id']} {$data['title']}");
        }

        $this->newLine();
        $this->info("Reverted {$restored} post(s) to the body stored before the restructure.");

        return self::SUCCESS;
    }

    private function clip(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1).'…' : $text;
    }
}
