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
        'description' => 'GST rules, invoices and returns explained for Indian shop owners, traders, freelancers and CAs, by the Apna Invoice team.',
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
        :title="'GST and invoicing blog for small business' . ($posts->currentPage() > 1 ? ' (Page ' . $posts->currentPage() . ')' : '')"
        description="Guides on GST invoices, HSN codes, GSTR-1, GSTR-3B, credit notes and getting paid on time, for Indian shop owners, traders, freelancers and CAs."
        keywords="GST blog India, GST invoice rules, HSN SAC guide, GSTR-1 filing, GSTR-3B, free GST invoice software"
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

@php
    $search = trim((string) request('search'));
    $activeTopic = $search !== '' ? mb_strtolower($search) : null;
    $chip = 'inline-flex items-center min-h-[36px] px-3 rounded-lg text-sm font-medium ring-1 transition';
    $chipOff = 'bg-white text-gray-700 ring-gray-300 hover:ring-gray-400 hover:text-gray-900';
    $chipOn = 'bg-brand-700 text-white ring-brand-700';
    // Tags are stored lower-case; print GST, HSN, GSTR-1 and the like in capitals.
    $topicLabel = fn (string $t) => \Illuminate\Support\Str::ucfirst(preg_replace_callback(
        '/\b(gstr-?\w+|gstin|gst|hsn|sac|itc|tds|tcs|upi|msme|b2b|b2c|rcm|lut|sez|pan|igst|cgst|sgst|utgst|ca)\b/u',
        fn ($m) => mb_strtoupper($m[1]), $t));
@endphp
<section class="bg-gray-50 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-10 md:pt-14 md:pb-12">
        <div class="max-w-3xl">
            <p class="flex items-center gap-2.5 text-sm font-semibold text-brand-700">
                <span class="w-6 h-0.5 rounded-full bg-accent-500" aria-hidden="true"></span>Apna Invoice blog
            </p>
            <h1 class="mt-3 font-display text-4xl md:text-5xl font-extrabold text-gray-900 leading-[1.12] tracking-tight">GST and billing guides</h1>
            <p class="mt-4 text-lg md:text-xl text-gray-600 leading-relaxed max-w-2xl">GST rules, invoices, returns and getting paid, explained for shop owners, traders, freelancers and their CAs.</p>

            <form method="GET" action="{{ route('blog.index') }}" class="mt-7 max-w-md" role="search">
                <label for="blog-search" class="sr-only">Search articles</label>
                <div class="relative">
                    <input id="blog-search" type="search" name="search" value="{{ $search }}"
                           placeholder="Search, for example HSN or credit note"
                           class="block w-full min-h-[48px] pl-11 pr-4 border-gray-300 rounded-lg bg-white shadow-sm focus:border-brand-600 focus:ring-brand-600 text-base">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                </div>
            </form>
        </div>

        {{-- Topic filters: the tags used on more than one post (see
             BlogController::index). Each one is a search on that tag. --}}
        @if (! empty($topics))
            <nav aria-label="Topics" class="mt-6 flex flex-wrap items-center gap-2">
                <a href="{{ route('blog.index') }}" class="{{ $chip }} {{ $search === '' ? $chipOn : $chipOff }}" @if ($search === '') aria-current="page" @endif>All articles</a>
                @foreach ($topics as $topic)
                    <a href="{{ route('blog.index', ['search' => $topic]) }}"
                       class="{{ $chip }} {{ $activeTopic === $topic ? $chipOn : $chipOff }}"
                       @if ($activeTopic === $topic) aria-current="page" @endif>{{ $topicLabel($topic) }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</section>

<main id="main-content" tabindex="-1" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14 focus:outline-none">
    @if ($posts->isEmpty())
        <div class="max-w-xl py-6">
            @if ($search !== '')
                <h2 class="font-display text-2xl font-bold text-gray-900">No articles match "{{ $search }}"</h2>
                <p class="mt-2 text-gray-600">Try a shorter word, such as GST, HSN or GSTR-1, or <a href="{{ route('blog.index') }}" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-700">see all articles</a>.</p>
            @else
                <h2 class="font-display text-2xl font-bold text-gray-900">No articles yet</h2>
                <p class="mt-2 text-gray-600">New guides are added here as they are written. Until then, the <a href="{{ route('pages.gst-invoice-format') }}" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-700">GST invoice format guide</a> and the <a href="{{ route('pages.how-to-use') }}" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-700">how to use guide</a> cover the basics.</p>
            @endif
        </div>
    @else
        @if ($search !== '')
            <p class="mb-6 text-gray-600">
                {{ $posts->total() }} {{ \Illuminate\Support\Str::plural('article', $posts->total()) }} matching <span class="font-semibold text-gray-900">"{{ $search }}"</span> ·
                <a href="{{ route('blog.index') }}" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-700">See all articles</a>
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

    {{-- Two plain next steps, below the list: the free calculator (no
         account) and sign-up. --}}
    <aside aria-label="Free tools" class="mt-14 grid gap-4 md:grid-cols-2">
        <a href="{{ route('pages.gst-calculator') }}" class="group flex flex-col rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5 sm:p-6 hover:ring-gray-300 hover:shadow transition">
            <span class="font-display text-lg font-bold text-gray-900 group-hover:text-brand-700 transition-colors">GST calculator</span>
            <span class="mt-1.5 text-[15px] text-gray-600 leading-relaxed">Add or remove GST on any amount and see the CGST, SGST or IGST split. Free, no account needed.</span>
            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700">Open the calculator
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </span>
        </a>
        <div class="flex flex-col rounded-xl bg-brand-900 text-white p-5 sm:p-6">
            <span class="font-display text-lg font-bold">Make your GST invoices on Apna Invoice</span>
            <span class="mt-1.5 text-[15px] text-brand-100 leading-relaxed">Invoices, payments, purchases and GSTR-1 in one place. Free during beta. No card.</span>
            <span class="mt-4 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center min-h-[44px] px-5 bg-white hover:bg-brand-50 text-brand-900 rounded-lg font-semibold transition-colors">Go to dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="inline-flex items-center min-h-[44px] px-5 bg-white hover:bg-brand-50 text-brand-900 rounded-lg font-semibold transition-colors">Create free account</a>
                    <a href="{{ route('pages.how-to-use') }}" class="inline-flex items-center min-h-[44px] px-5 ring-1 ring-white/40 hover:bg-white/10 text-white rounded-lg font-semibold transition-colors">How it works</a>
                @endauth
            </span>
        </div>
    </aside>
</main>

<x-site-footer />

@include('partials.cookie-banner')
@stack('scripts')

</body>
</html>
