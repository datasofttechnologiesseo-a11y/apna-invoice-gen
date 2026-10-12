@php
    // Canonical and schema URLs are built on APP_URL, never the request Host
    // (same rule as <x-seo>), so www./preview hosts cannot mint duplicates.
    $origin = rtrim(config('app.url'), '/');
    $blogUrl = $origin . route('blog.index', [], false);
    $pageUrl = $posts->currentPage() > 1 ? $blogUrl . '?page=' . $posts->currentPage() : $blogUrl;

    $blogJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        'name' => 'Apna Invoice Blog',
        'description' => 'GST tips, invoicing how-tos, and Indian small-business finance guides, written by the Apna Invoice team.',
        'url' => $blogUrl,
        'inLanguage' => 'en-IN',
        // The home page's Organization and WebSite nodes, by @id: the blog is
        // part of the Apna Invoice site, published by the business behind it.
        'publisher' => [
            '@type' => 'Organization',
            '@id' => $origin . '#organization',
            'name' => config('seo.organization.name'),
            'url' => config('seo.organization.url'),
        ],
        'isPartOf' => [
            '@type' => 'WebSite',
            '@id' => $origin . '#website',
            'name' => config('seo.name', 'Apna Invoice'),
            'url' => $origin . '/',
        ],
        'blogPost' => $posts->map(fn ($p) => [
            '@type' => 'BlogPosting',
            'headline' => $p->title,
            'url' => $origin . route('blog.show', $p->slug, false),
            'datePublished' => $p->published_at?->toIso8601String(),
            'author' => $p->author?->name
                ? ['@type' => 'Person', 'name' => $p->author->name]
                : ['@type' => 'Organization', '@id' => $origin . '#organization', 'name' => config('seo.organization.name')],
        ])->all(),
    ];
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php $filtered = filled(request('search')); @endphp
    <x-seo
        {{-- The search box can mint an unbounded number of URLs. Page 1 of a
             search already canonicalised to /blog, but page 2 self-canonicalised
             - the same filter de-duplicated on one page and advertised as its
             own on the next. Every filtered page now points at /blog and is
             noindex, follow: kept out of the index, still walked through to the
             posts. Unfiltered pagination is unaffected and stands on its own. --}}
        :url="$filtered ? $blogUrl : $pageUrl"
        :noindex="$filtered"
        :follow="true"
        {{-- Page 2+ stand on their own canonicals, so each needs its own
             title; otherwise every page of the archive repeats page 1's. --}}
        :title="'GST & Invoicing Blog for Small Business' . ($posts->currentPage() > 1 ? ' (Page ' . $posts->currentPage() . ')' : '')"
        description="Practical guides on GST invoicing, HSN/SAC codes, GSTR-1 / GSTR-3B filing, and small-business cash flow, written for Indian MSMEs, freelancers and CAs."
        keywords="GST blog India, invoicing tips, HSN SAC guide, GSTR-1 filing, MSME finance, free GST invoice software"
        type="website"
        :json-ld="[$blogJsonLd]" />
    <link rel="alternate" type="application/rss+xml" title="{{ config('seo.name', config('app.name')) }} Blog" href="{{ route('blog.feed') }}">
    {{-- Non-blocking font load with crossorigin preconnect (see blog/show). --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload" as="style" href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900">
<a href="#main-content" class="skip-link">Skip to main content</a>

<x-site-header />

<section class="bg-gray-50 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-10 md:pt-14 md:pb-14">
        <div class="max-w-3xl">
            <p class="flex items-center gap-2.5 text-sm font-semibold text-brand-700">
                <span class="w-6 h-0.5 rounded-full bg-accent-500" aria-hidden="true"></span>Apna Invoice Blogs
            </p>
            <h1 class="mt-3 font-display text-4xl md:text-5xl font-extrabold text-gray-900 leading-[1.12] tracking-tight">GST tips, invoicing how-tos, and small-business guides</h1>
            <p class="mt-4 text-lg md:text-xl text-gray-600 leading-relaxed max-w-2xl">Written for Indian freelancers, MSMEs and CAs. Practical help with GST, billing and running a small business.</p>

            <form method="GET" class="mt-7 max-w-md" role="search">
                <label for="blog-search" class="sr-only">Search articles</label>
                <div class="relative">
                    <input id="blog-search" type="search" name="search" value="{{ request('search') }}"
                           placeholder="Search articles…"
                           class="block w-full min-h-[48px] pl-11 pr-4 border-gray-300 rounded-lg bg-white shadow-sm focus:border-brand-600 focus:ring-brand-600 text-base" aria-label="Search articles…">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                </div>
            </form>
        </div>
    </div>
</section>

<main id="main-content" tabindex="-1" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14 focus:outline-none">
    @if ($posts->isEmpty())
        <div class="max-w-xl py-12">
            <h2 class="font-display text-2xl font-bold text-gray-900">No posts yet</h2>
            <p class="mt-2 text-gray-600">@if (request('search'))No articles match "{{ request('search') }}". <a href="{{ route('blog.index') }}" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-700">Clear search</a>.@else New guides are published here as they are written.@endif</p>
        </div>
    @else
        @if (request('search'))
            <p class="mb-6 text-gray-600">
                Articles matching <span class="font-semibold text-gray-900">"{{ request('search') }}"</span> ·
                <a href="{{ route('blog.index') }}" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-700">Clear search</a>
            </p>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
            @foreach ($posts as $post)
                <article class="group relative flex flex-col bg-white rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden hover:ring-gray-300 hover:shadow-md transition">
                    @if ($post->featured_image_path)
                        <div class="aspect-[16/9] bg-gray-100 overflow-hidden border-b border-gray-200">
                            <img src="{{ asset('storage/' . $post->featured_image_path) }}"
                                 alt="{{ $post->featured_image_alt ?: $post->title }}"
                                 class="w-full h-full object-cover"
                                 loading="lazy" width="800" height="450">
                        </div>
                    @else
                        {{-- No cover: a ruled-ledger panel rather than a stretched
                             gradient, so the grid still lines up. --}}
                        <div class="aspect-[16/9] border-b border-gray-200 bg-gray-50 bg-[repeating-linear-gradient(to_bottom,transparent_0,transparent_27px,rgb(229_231_235)_27px,rgb(229_231_235)_28px)] relative" aria-hidden="true">
                            <span class="absolute inset-y-0 left-10 w-px bg-accent-300"></span>
                            <svg class="absolute left-[3.75rem] top-1/2 -translate-y-1/2 w-8 h-8 text-brand-700/70" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                    @endif
                    <div class="flex-1 p-5 flex flex-col">
                        <div class="text-sm text-gray-500 flex items-center gap-2">
                            <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('d M Y') }}</time>
                            @if ($post->reading_minutes)
                                <span aria-hidden="true">·</span>
                                <span>{{ $post->reading_minutes }} min read</span>
                            @endif
                        </div>
                        <h2 class="mt-2 font-display text-lg font-bold text-gray-900 leading-snug group-hover:text-brand-700 transition-colors">
                            {{-- The whole card is the link target: the title's
                                 link is stretched over it. --}}
                            <a href="{{ route('blog.show', $post->slug) }}" class="after:absolute after:inset-0">{{ $post->title }}</a>
                        </h2>
                        @if ($post->excerpt || $post->effectiveMetaDescription())
                            <p class="mt-2 text-[15px] text-gray-600 leading-relaxed">{{ Str::limit($post->excerpt ?: $post->effectiveMetaDescription(), 140) }}</p>
                        @endif
                        <span class="mt-auto pt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700" aria-hidden="true">
                            Read article
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-10">{{ $posts->links() }}</div>
    @endif
</main>

<x-site-footer />

@include('partials.cookie-banner')
@stack('scripts')

</body>
</html>
