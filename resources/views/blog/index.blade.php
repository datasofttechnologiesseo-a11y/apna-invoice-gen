@php
    $blogJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        'name' => 'Apna Invoice Blog',
        'description' => 'GST tips, invoicing how-tos, and Indian small-business finance guides, written by the Apna Invoice team.',
        'url' => route('blog.index'),
        'inLanguage' => 'en-IN',
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('app.name', 'Apna Invoice'),
            'url' => url('/'),
        ],
        'blogPost' => $posts->map(fn ($p) => [
            '@type' => 'BlogPosting',
            'headline' => $p->title,
            'url' => route('blog.show', $p->slug),
            'datePublished' => $p->published_at?->toIso8601String(),
            'author' => ['@type' => 'Person', 'name' => $p->author?->name ?? config('app.name')],
        ])->all(),
    ];
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo
        title="GST & Invoicing Blog, Tips for Indian Small Businesses · Apna Invoice"
        description="Practical guides on GST invoicing, HSN/SAC codes, GSTR-1 / GSTR-3B filing, and small-business cash flow, written for Indian MSMEs, freelancers and CAs."
        keywords="GST blog India, invoicing tips, HSN SAC guide, GSTR-1 filing, MSME finance, free GST invoice software"
        type="website"
        :json-ld="$blogJsonLd" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900|plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900">

<header class="relative bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-3 py-4" aria-label="Apna Invoice home">
            <span class="inline-block bg-white rounded">
                <x-brand-logo class="h-12 md:h-14 w-auto block" />
            </span>
        </a>
        <nav class="flex items-center gap-2 md:gap-6 text-sm">
            <a href="{{ url('/#features') }}" class="hidden md:inline-block text-base text-gray-700 hover:text-brand-700 font-semibold">Features</a>
            <a href="{{ url('/#pricing') }}" class="hidden md:inline-block text-base text-gray-700 hover:text-brand-700 font-semibold">Pricing</a>
            <a href="{{ route('blog.index') }}" class="hidden md:inline-block text-base text-brand-700 font-bold">Blogs</a>
            @auth
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Go to dashboard →</a>
            @else
                <a href="{{ route('login') }}" class="text-base text-gray-800 hover:text-brand-700 font-semibold px-3 py-2">Log in</a>
                <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Start free</a>
            @endauth
        </nav>
    </div>
</header>

<section class="relative overflow-hidden bg-gradient-to-br from-brand-50 via-white to-accent-50 border-b border-gray-100">
    <div class="absolute -top-24 -right-24 w-[400px] h-[400px] bg-brand-300 rounded-full blur-3xl opacity-20"></div>
    <div class="absolute -bottom-24 -left-24 w-[300px] h-[300px] bg-accent-300 rounded-full blur-3xl opacity-20"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-100 text-brand-700 text-xs font-bold uppercase tracking-widest ring-1 ring-brand-200">Apna Invoice Blogs</span>
        <h1 class="mt-4 font-display text-3xl sm:text-4xl md:text-5xl font-extrabold text-gray-900 leading-tight">GST tips, invoicing how-tos, and small-business guides</h1>
        <p class="mt-4 text-base sm:text-lg text-gray-600 leading-relaxed max-w-2xl">Written for Indian freelancers, MSMEs and CAs. Get the most out of GST, save time on billing, and grow your business, without the jargon.</p>

        <form method="GET" class="mt-6 max-w-md">
            <label for="blog-search" class="sr-only">Search articles</label>
            <div class="relative">
                <input id="blog-search" type="search" name="search" value="{{ request('search') }}"
                       placeholder="Search articles…"
                       class="block w-full pl-10 pr-4 py-3 border-gray-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
            </div>
        </form>
    </div>
</section>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
    @if ($posts->isEmpty())
        <div class="text-center py-16">
            <div class="text-5xl mb-4">📝</div>
            <h2 class="font-display text-2xl font-bold text-gray-900">No posts yet</h2>
            <p class="mt-2 text-gray-600">@if (request('search'))No articles match "{{ request('search') }}". <a href="{{ route('blog.index') }}" class="text-brand-700 underline">Clear search</a>.@else We're cooking up our first guides, check back soon.@endif</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            @foreach ($posts as $post)
                <article class="group flex flex-col bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden hover:ring-brand-300 hover:shadow-md transition">
                    @if ($post->featured_image_path)
                        <a href="{{ route('blog.show', $post->slug) }}" class="block aspect-[16/9] bg-gray-100 overflow-hidden">
                            <img src="{{ asset('storage/' . $post->featured_image_path) }}"
                                 alt="{{ $post->featured_image_alt ?: $post->title }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                 loading="lazy" width="800" height="450">
                        </a>
                    @else
                        <a href="{{ route('blog.show', $post->slug) }}" class="block aspect-[16/9] bg-gradient-to-br from-brand-100 via-brand-50 to-saffron-50 flex items-center justify-center">
                            <span class="font-display font-extrabold text-3xl text-brand-700/30">{{ Str::limit($post->title, 22) }}</span>
                        </a>
                    @endif
                    <div class="flex-1 p-5 flex flex-col gap-3">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 flex items-center gap-2">
                            <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('d M Y') }}</time>
                            @if ($post->reading_minutes)
                                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                <span>{{ $post->reading_minutes }} min read</span>
                            @endif
                        </div>
                        <h2 class="font-display text-lg font-bold text-gray-900 leading-snug group-hover:text-brand-700 transition">
                            <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                        </h2>
                        @if ($post->excerpt || $post->effectiveMetaDescription())
                            <p class="text-sm text-gray-600 leading-relaxed">{{ Str::limit($post->excerpt ?: $post->effectiveMetaDescription(), 140) }}</p>
                        @endif
                        <a href="{{ route('blog.show', $post->slug) }}" class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:text-brand-800">
                            Read article
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
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
