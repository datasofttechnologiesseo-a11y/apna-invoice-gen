<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Environment\Environment;

/**
 * Blog post for the public-facing /blog section.
 *
 * Markdown rendering is centralised here so the controllers and views never
 * see raw input — everything goes through league/commonmark with strict
 * HTML escaping, so even if an admin pastes <script>, it gets sanitised.
 */
class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'slug', 'excerpt', 'body',
        'featured_image_path', 'featured_image_alt',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image_path',
        'status', 'published_at', 'reading_minutes',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────────

    /** Visible to the public — published AND publish date in the past. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
                 ->whereNotNull('published_at')
                 ->where('published_at', '<=', now());
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function isScheduled(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Effective <title> — falls back to the heading title. */
    public function effectiveMetaTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    /** Effective <meta description> — falls back to the excerpt or auto-summary. */
    public function effectiveMetaDescription(): string
    {
        if ($this->meta_description) {
            return $this->meta_description;
        }
        if ($this->excerpt) {
            return Str::limit($this->excerpt, 160);
        }
        // Strip markdown to get a plain-text summary
        $plain = preg_replace('/[#*_`>\[\]\(\)]/', '', (string) $this->body);
        $plain = preg_replace('/\s+/', ' ', $plain);
        return Str::limit(trim($plain), 160);
    }

    /** Effective social-share image — falls back to the featured image. */
    public function effectiveOgImage(): ?string
    {
        return $this->og_image_path ?: $this->featured_image_path;
    }

    /**
     * Render the Markdown body to safe HTML. Escaped output by default —
     * raw <script> and on*= handlers are stripped, but standard formatting
     * (links, lists, code blocks, tables) renders correctly.
     */
    public function renderedBody(): string
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new AutolinkExtension());

        $converter = new MarkdownConverter($environment);
        $html = $converter->convert((string) $this->body)->getContent();

        // SEO: the post title is the page's one <h1>. A "# Heading" in the
        // body would emit a second <h1> — which also rendered as plain 16px
        // text because nothing styled body h1s. Demote body h1 → h2 so the
        // author's heading displays properly AND the page keeps a single H1.
        return str_replace(['<h1>', '<h1 ', '</h1>'], ['<h2>', '<h2 ', '</h2>'], $html);
    }

    /** Compute reading minutes from body. ~200 wpm is the SEO industry default. */
    public function computeReadingMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body));
        return max(1, (int) ceil($words / 200));
    }

    /** Generate a URL-safe slug from a title; ensures uniqueness. */
    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $i = 2;
        while (self::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
                ->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}
