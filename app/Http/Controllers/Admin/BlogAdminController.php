<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Admin authoring panel for blog posts. Sits under the super-admin
 * middleware so only the operator can write. Routes are mounted at
 * /admin/blog.
 */
class BlogAdminController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::with('author')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%");
            }))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.blog.index', compact('posts'));
    }

    public function create(): View
    {
        $post = new Post([
            'status' => 'draft',
            'body' => "## Introduction\n\nWrite your post here. Markdown is supported — links, lists, code blocks, tables, images.\n\n## Section\n\nDetail.\n",
        ]);
        return view('admin.blog.edit', compact('post'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePost($request);

        $featuredPath = $this->handleImage($request, 'featured_image');
        $ogPath = $this->handleImage($request, 'og_image');

        $post = Post::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => ! empty($data['slug']) ? $data['slug'] : Post::generateSlug($data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'featured_image_path' => $featuredPath,
            'featured_image_alt' => $data['featured_image_alt'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'og_image_path' => $ogPath,
            'status' => $data['status'],
            'published_at' => $this->resolvePublishDate($data),
        ]);
        $post->update(['reading_minutes' => $post->computeReadingMinutes()]);

        AuditLog::record('blog.post.created',
            "Post created: {$post->title} ({$post->status})",
            $post
        );

        return redirect()->route('admin.blog.edit', $post)
            ->with('status', 'Post saved.');
    }

    public function edit(Post $post): View
    {
        return view('admin.blog.edit', compact('post'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validatePost($request, $post->id);

        $featuredPath = $this->handleImage($request, 'featured_image', $post->featured_image_path);
        $ogPath = $this->handleImage($request, 'og_image', $post->og_image_path);

        $post->update([
            'title' => $data['title'],
            'slug' => ! empty($data['slug']) ? $data['slug'] : Post::generateSlug($data['title'], $post->id),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'featured_image_path' => $featuredPath,
            'featured_image_alt' => $data['featured_image_alt'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'og_image_path' => $ogPath,
            'status' => $data['status'],
            'published_at' => $this->resolvePublishDate($data),
        ]);
        $post->update(['reading_minutes' => $post->computeReadingMinutes()]);

        AuditLog::record('blog.post.updated',
            "Post updated: {$post->title} ({$post->status})",
            $post
        );

        return redirect()->route('admin.blog.edit', $post)
            ->with('status', 'Post saved.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $title = $post->title;
        // Clean up uploaded images alongside the row.
        foreach ([$post->featured_image_path, $post->og_image_path] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }
        $post->delete();

        AuditLog::record('blog.post.deleted', "Post deleted: {$title}");
        return redirect()->route('admin.blog.index')->with('status', 'Post deleted.');
    }

    /**
     * Quick publish/unpublish toggle from the index list — saves a click
     * vs entering the editor every time.
     */
    public function togglePublish(Post $post): RedirectResponse
    {
        if ($post->isDraft()) {
            $post->update([
                'status' => 'published',
                'published_at' => $post->published_at ?: now(),
            ]);
            $msg = 'Post published.';
        } else {
            $post->update(['status' => 'draft']);
            $msg = 'Post moved back to draft.';
        }
        AuditLog::record('blog.post.toggled', "Post {$post->status}: {$post->title}", $post);
        return back()->with('status', $msg);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function validatePost(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', 'regex:/^[a-z0-9-]+$/',
                'unique:posts,slug' . ($ignoreId ? ',' . $ignoreId : ''),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'featured_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'featured_image_alt' => ['nullable', 'string', 'max:200'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:200'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * Persist an uploaded image to public/storage/posts and return its
     * stored path. Falls back to the existing path if no new file uploaded.
     */
    private function handleImage(Request $request, string $field, ?string $existing = null): ?string
    {
        if (! $request->hasFile($field)) {
            return $existing;
        }
        // Replace the old image cleanly.
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }
        return $request->file($field)->store('posts', 'public');
    }

    /**
     * Resolve published_at: if status=published, use the supplied date or
     * "now". For drafts, null it out so the published scope ignores it.
     */
    private function resolvePublishDate(array $data): ?string
    {
        if (($data['status'] ?? 'draft') !== 'published') {
            return null;
        }
        return ! empty($data['published_at']) ? $data['published_at'] : now()->toDateTimeString();
    }
}
