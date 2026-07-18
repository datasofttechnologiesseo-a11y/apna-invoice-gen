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
            ->with('author:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                  ->orWhere('excerpt', 'like', "%{$s}%")
                  ->orWhere('meta_keywords', 'like', "%{$s}%");
            }))
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // A page number past the end (e.g. /blog?page=999) otherwise returns 200
        // with an empty grid — an infinite soft-404 URL space that wastes crawl
        // budget. Serve a real 404 instead. Page 1 of an empty blog stays valid.
        abort_if($posts->currentPage() > $posts->lastPage(), 404);

        return view('blog.index', compact('posts'));
    }

    public function show(string $slug): View
    {
        $post = Post::published()->with('author:id,name')->where('slug', $slug)->firstOrFail();

        // Soft view counter — increment without locking; cheap. withoutTimestamps
        // is essential: a bare increment() would touch updated_at on every view,
        // and that timestamp feeds article:modified_time, BlogPosting
        // dateModified AND the sitemap <lastmod>. Bumping it per pageview makes
        // every post look "modified today" on every crawl — noise Google learns
        // to ignore, killing recrawl prioritisation for genuinely edited posts.
        Post::withoutTimestamps(fn () => $post->increment('view_count'));

        $related = Post::published()
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        // Previous / next published posts — keeps readers on the site and
        // gives crawlers a chronological path through the whole archive.
        $previous = Post::published()->where('published_at', '<', $post->published_at)
            ->orderByDesc('published_at')->first(['id', 'slug', 'title']);
        $next = Post::published()->where('published_at', '>', $post->published_at)
            ->orderBy('published_at')->first(['id', 'slug', 'title']);

        return view('blog.show', compact('post', 'related', 'previous', 'next'));
    }
}
