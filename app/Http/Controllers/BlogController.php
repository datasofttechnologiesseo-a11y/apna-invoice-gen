<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public blog — drives SEO traffic from the landing page. Index lists the
 * latest published posts; show renders an individual post with full SEO
 * meta tags + JSON-LD Article schema for Google rich-results.
 *
 * Auth-free; the admin panel handles authoring under
 * Admin\BlogAdminController.
 */
class BlogController extends Controller
{
    /** Posts per page on the index — small so the list stays fast and SEO-friendly. */
    private const PER_PAGE = 12;

    public function index(Request $request): View
    {
        $posts = Post::published()
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                  ->orWhere('excerpt', 'like', "%{$s}%")
                  ->orWhere('meta_keywords', 'like', "%{$s}%");
            }))
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('blog.index', compact('posts'));
    }

    public function show(string $slug): View
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        // Soft view counter — increment without locking; cheap.
        $post->increment('view_count');

        $related = Post::published()
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('blog.show', compact('post', 'related'));
    }
}
