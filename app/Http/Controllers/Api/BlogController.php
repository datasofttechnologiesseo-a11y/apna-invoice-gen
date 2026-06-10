<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public read-only blog API (no auth) — mirrors the web BlogController. Lists
 * published posts and serves a single post with its Markdown rendered to safe
 * HTML, ready for the mobile reader.
 */
class BlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::published()
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                    ->orWhere('excerpt', 'like', "%{$s}%")
                    ->orWhere('meta_keywords', 'like', "%{$s}%");
            }))
            ->orderByDesc('published_at')
            ->paginate(min((int) $request->integer('per_page', 12), 50));

        return response()->json([
            'data' => collect($posts->items())->map(fn (Post $p) => [
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'published_at' => optional($p->published_at)->toDateString(),
                'reading_minutes' => $p->reading_minutes ?: $p->computeReadingMinutes(),
                'featured_image_url' => $p->featured_image_path ? asset('storage/' . $p->featured_image_path) : null,
            ]),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();
        $post->increment('view_count');

        return response()->json([
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'published_at' => optional($post->published_at)->toDateString(),
            'reading_minutes' => $post->reading_minutes ?: $post->computeReadingMinutes(),
            'author' => $post->author?->name,
            'featured_image_url' => $post->featured_image_path ? asset('storage/' . $post->featured_image_path) : null,
            'body_html' => $post->renderedBody(),
        ]);
    }
}
