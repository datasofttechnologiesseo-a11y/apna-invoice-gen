@php
    // Every URL in the schema is built on APP_URL - the same origin as the
    // canonical tag - never on whatever Host the request arrived with.
    $origin = rtrim(config('app.url'), '/');
    $postUrl = $origin . route('blog.show', $post->slug, false);

    // A post without a cover falls back to the site's 1200x630 share card.
    // It used to fall back to the 960x225 header logo, which WhatsApp and
    // LinkedIn either drop or crop to an unreadable strip, and which is too
    // small for the large-image Article treatment in Google.
    $ogImage = $post->effectiveOgImage()
        ? $origin . '/storage/' . ltrim($post->effectiveOgImage(), '/')
        : $origin . '/' . ltrim(config('seo.og_image', '/brand/og-card.png'), '/');

    $orgRef = [
        '@type' => 'Organization',
        '@id' => $origin . '#organization',
        'name' => config('seo.organization.name'),
        'url' => config('seo.organization.url'),
        'logo' => ['@type' => 'ImageObject', 'url' => $origin . config('seo.organization.logo')],
    ];

    $articleJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $post->effectiveMetaDescription(),
        'image' => $ogImage,
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at?->toIso8601String(),
        // A named writer is a Person; with no author on record the post is
        // credited to the company, not to a "Person" called Apna Invoice.
        'author' => $post->author?->name
            ? ['@type' => 'Person', 'name' => $post->author->name]
            : $orgRef,
        // The business behind Apna Invoice (the home page's Organization
        // node, by @id), and the post is part of the Apna Invoice site.
        'publisher' => $orgRef,
        'isPartOf' => [
            '@type' => 'WebSite',
            '@id' => $origin . '#website',
            'name' => config('seo.name', 'Apna Invoice'),
            'url' => $origin . '/',
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $postUrl,
        ],
        'inLanguage' => 'en-IN',
        'wordCount' => str_word_count(strip_tags($post->body)),
    ];

    $breadcrumbJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $origin . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blogs', 'item' => $origin . route('blog.index', [], false)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title, 'item' => $postUrl],
        ],
    ];

    // FAQPage markup, built from the question headings the author actually
    // wrote. Google renders it as an expandable block in the results, which is
    // a real click-through difference on "can I / what is / how do I" queries -
    // which is most of this subject.
    //
    // Two pairs is the floor: a single Q&A is not a FAQ, and marking one up as
    // though it were invites the "invalid FAQ" warnings in Search Console.
    $faqPairs = $post->faqPairs();
    $faqJsonLd = count($faqPairs) >= 2 ? [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($pair) => [
            '@type' => 'Question',
            'name' => $pair['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $pair['answer']],
        ], $faqPairs),
    ] : null;

@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo
        :title="$post->effectiveMetaTitle()"
        :description="$post->effectiveMetaDescription()"
        :keywords="$post->meta_keywords"
        type="article"
        :image="$ogImage"
        :url="$postUrl"
        :published-time="$post->published_at?->toIso8601String()"
        :modified-time="$post->updated_at?->toIso8601String()"
        section="Blog"
        :author="$post->author?->name"
        :json-ld="array_values(array_filter([$articleJsonLd, $breadcrumbJsonLd, $faqJsonLd]))" />
    <link rel="alternate" type="application/rss+xml" title="{{ config('seo.name', config('app.name')) }} Blog" href="{{ route('blog.feed') }}">
    {{-- crossorigin: font CSS + files are CORS-fetched, so the warmed
         connection must match. Non-blocking load (media=print → all onload)
         with a noscript fallback keeps fonts off the critical render path -
         blog articles are the primary organic entry pages. Inter sets the
         article body (as on every other page) and Jakarta the headings; the
         separate serif body face was dropped, one font download fewer. --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload" as="style" href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet"></noscript>
    @if ($post->featured_image_path)
        {{-- Preload the cover - it's the article LCP on posts that have one. --}}
        <link rel="preload" as="image" href="{{ asset('storage/' . $post->featured_image_path) }}" fetchpriority="high">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Article body, long-read typography: a comfortable size and line
           height for Inter, and room between paragraphs. Fixed px so the
           reading size does not shrink with the app's 90% root scale. */
        .article-body p,
        .article-body li { font-size: 17.5px; line-height: 1.75; }
        @media (max-width: 640px) {
            .article-body p,
            .article-body li { font-size: 16.5px; line-height: 1.7; }
        }
        .article-body p { margin: 1.15em 0; }
        .article-body > :first-child { margin-top: 0; }

        /* Anchor link for headings: a small # that appears on hover, so
           readers can deep-link a section. JS adds the link. */
        .article-body .heading-anchor {
            margin-left: 0.35em;
            color: #9ca3af;
            text-decoration: none;
            opacity: 0;
            transition: opacity 150ms;
            font-weight: 500;
        }
        .article-body h2:hover .heading-anchor,
        .article-body h3:hover .heading-anchor,
        .article-body .heading-anchor:focus { opacity: 1; }
        .article-body .heading-anchor:hover { color: #167c88; }

        /* Reading progress, pinned to the top edge in the brand teal. */
        #reading-progress {
            position: fixed; top: 0; left: 0;
            height: 3px; width: 0%;
            background: #167c88;
            z-index: 50;
            transition: width 50ms linear;
            pointer-events: none;
        }
    </style>
    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900">
<a href="#article-body" class="skip-link">Skip to article</a>

{{-- Scroll-driven reading progress bar, pure JS, no library. Updates on
     scroll using requestAnimationFrame for smoothness. --}}
<div id="reading-progress" aria-hidden="true"></div>

<x-site-header />

{{-- Article title, byline and meta --}}
<section class="bg-gray-50 border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-10 md:pt-12 md:pb-12">
        <nav aria-label="Breadcrumb" class="text-sm text-gray-500">
            <a href="{{ url('/') }}" class="hover:text-brand-700">Home</a>
            <span class="mx-1.5 text-gray-400" aria-hidden="true">/</span>
            <a href="{{ route('blog.index') }}" class="hover:text-brand-700">Blogs</a>
        </nav>
        <h1 class="mt-4 font-display text-3xl sm:text-4xl md:text-[2.75rem] font-extrabold text-gray-900 leading-[1.15] tracking-tight">{{ $post->title }}</h1>
        @if ($post->excerpt)
            <p class="mt-4 text-lg md:text-xl text-gray-600 leading-relaxed">{{ $post->excerpt }}</p>
        @endif
        <div class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
            <span class="font-semibold text-gray-800">{{ $post->author?->name ?? config('app.name') }}</span>
            <span aria-hidden="true">·</span>
            <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('d M Y') }}</time>
            @php
                // Fall back to computing it, the way the API already does. A
                // post saved outside the admin form has no stored value, and
                // showing no reading time at all is worse than a computed one.
                $minutes = $post->reading_minutes ?: $post->computeReadingMinutes();
                // "Updated" only when the edit is meaningfully later than
                // publication - a same-day typo fix is not a freshness signal
                // and dating it twice just looks noisy.
                $updated = $post->updated_at && $post->published_at
                    && $post->updated_at->gt($post->published_at->copy()->addDays(1));
            @endphp
            @if ($minutes)
                <span aria-hidden="true">·</span>
                <span>{{ $minutes }} min read</span>
            @endif
            @if ($updated)
                <span aria-hidden="true">·</span>
                <span>Updated <time datetime="{{ $post->updated_at->toIso8601String() }}">{{ $post->updated_at->format('d M Y') }}</time></span>
            @endif
        </div>
    </div>
</section>

@if ($post->featured_image_path)
    <figure class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 md:mt-10">
        {{-- Above-the-fold cover = the article LCP. fetchpriority high +
             eager so the preload scanner starts it immediately. --}}
        <img src="{{ asset('storage/' . $post->featured_image_path) }}"
             alt="{{ $post->featured_image_alt ?: $post->title }}"
             class="w-full rounded-xl ring-1 ring-gray-200 aspect-[16/9] object-cover"
             width="1200" height="675" fetchpriority="high" loading="eager" decoding="async">
    </figure>
@endif

<article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-12">
    {{-- Table of contents - crawlable jump links (ids stamped server-side in
         renderedBody), shown only when the post has enough structure. --}}
    @php $toc = $post->tableOfContents(); @endphp
    @if (count($toc) >= 3)
        <nav aria-label="Table of contents" class="mb-10 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-5 sm:p-6">
            <p class="text-sm font-semibold text-gray-900">In this article</p>
            <ol class="mt-3 space-y-1.5 text-[15px]">
                @foreach ($toc as $i => $item)
                    <li class="flex gap-3">
                        <span class="w-5 shrink-0 text-right text-gray-400 tabular-nums">{{ $i + 1 }}.</span>
                        <a href="#{{ $item['id'] }}" class="text-gray-700 hover:text-brand-700 hover:underline decoration-brand-300 underline-offset-2">{{ $item['text'] }}</a>
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    {{-- Markdown body, rendered server-side via league/commonmark with safe-HTML
         escaping. `article-body` (defined in the <style> block above) sets the
         reading size; Tailwind utilities shape headings, quotes, code and
         tables. Tables scroll sideways on a phone instead of overflowing. --}}
    <div class="article-body
                text-gray-800
                [&_h2]:font-display [&_h2]:font-bold [&_h2]:text-2xl [&_h2]:sm:text-[1.75rem] [&_h2]:text-gray-900 [&_h2]:mt-12 [&_h2]:mb-4 [&_h2]:leading-tight [&_h2]:scroll-mt-20
                [&_h3]:font-display [&_h3]:font-bold [&_h3]:text-xl [&_h3]:sm:text-[1.375rem] [&_h3]:text-gray-900 [&_h3]:mt-9 [&_h3]:mb-3 [&_h3]:leading-snug [&_h3]:scroll-mt-20
                [&_h4]:font-display [&_h4]:font-bold [&_h4]:text-lg [&_h4]:text-gray-900 [&_h4]:mt-7 [&_h4]:mb-2
                [&_a]:text-brand-700 [&_a]:font-medium [&_a]:underline [&_a]:decoration-brand-300 [&_a]:underline-offset-2 hover:[&_a]:decoration-brand-700
                [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-5 [&_ul]:space-y-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-5 [&_ol]:space-y-2
                marker:text-brand-600
                [&_strong]:text-gray-900 [&_strong]:font-semibold
                [&_em]:italic
                [&_blockquote]:border-l-[3px] [&_blockquote]:border-brand-600 [&_blockquote]:bg-gray-50 [&_blockquote]:pl-5 [&_blockquote]:pr-4 [&_blockquote]:py-1 [&_blockquote]:my-7 [&_blockquote]:text-gray-700 [&_blockquote]:rounded-r-lg
                [&_code]:bg-gray-100 [&_code]:text-gray-900 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-[0.9em] [&_code]:font-mono
                [&_pre]:bg-gray-900 [&_pre]:text-gray-100 [&_pre]:p-5 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_pre]:my-7 [&_pre]:text-sm [&_pre]:leading-relaxed
                [&_pre_code]:bg-transparent [&_pre_code]:text-gray-100 [&_pre_code]:p-0 [&_pre_code]:text-inherit
                [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto md:[&_table]:table [&_table]:w-full [&_table]:my-7 [&_table]:border-collapse [&_table]:text-[15px]
                [&_th]:bg-gray-50 [&_th]:font-semibold [&_th]:text-left [&_th]:text-gray-900 [&_th]:px-3 [&_th]:py-2.5 [&_th]:border [&_th]:border-gray-200
                [&_td]:px-3 [&_td]:py-2.5 [&_td]:border [&_td]:border-gray-200 [&_td]:align-top
                [&_img]:rounded-xl [&_img]:my-7 [&_img]:w-full [&_img]:max-w-full [&_img]:h-auto [&_img]:ring-1 [&_img]:ring-gray-200
                [&_hr]:my-10 [&_hr]:border-t [&_hr]:border-gray-200"
         id="article-body" tabindex="-1">
        {!! $post->renderedBody() !!}
    </div>

    {{-- Article footer: keywords + share line --}}
    @if ($post->meta_keywords)
        <div class="mt-12 pt-6 border-t border-gray-200 flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold text-gray-700 mr-1">Topics</span>
            @foreach (explode(',', $post->meta_keywords) as $kw)
                @php $k = trim($kw); @endphp
                @if ($k)
                    {{-- The blog index already searches meta_keywords, so a tag
                         becomes working topic navigation for free rather than a
                         chip that looks clickable and is not. --}}
                    <a href="{{ route('blog.index', ['search' => $k]) }}"
                       class="inline-flex items-center min-h-[36px] px-3 bg-white text-gray-700 rounded-lg ring-1 ring-gray-300 hover:ring-gray-400 hover:text-gray-900 transition">{{ $k }}</a>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Share row - WhatsApp first (how content actually spreads in India),
         then X / LinkedIn / copy link. Plain links, no tracking scripts. --}}
    @php
        $shareUrl = route('blog.show', $post->slug);
        $shareText = $post->title . ' - ' . $shareUrl;
    @endphp
    <div class="mt-6 flex flex-wrap items-center gap-2 text-sm">
        <span class="font-semibold text-gray-700 mr-1">Share</span>
        <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 min-h-[40px] px-3.5 rounded-lg bg-white text-gray-800 ring-1 ring-gray-300 font-semibold hover:ring-gray-400 transition">
            <svg class="w-4 h-4 text-[#0f7540]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.511-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.884 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            WhatsApp
        </a>
        <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode($shareUrl) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 min-h-[40px] px-3.5 rounded-lg bg-white text-gray-800 ring-1 ring-gray-300 font-semibold hover:ring-gray-400 transition">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            Post
        </a>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($shareUrl) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 min-h-[40px] px-3.5 rounded-lg bg-white text-gray-800 ring-1 ring-gray-300 font-semibold hover:ring-gray-400 transition">
            <svg class="w-3.5 h-3.5 text-[#0A66C2]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            LinkedIn
        </a>
        <button type="button"
                onclick="navigator.clipboard.writeText('{{ $shareUrl }}').then(() => { this.querySelector('span').textContent = 'Copied'; setTimeout(() => this.querySelector('span').textContent = 'Copy link', 1500); })"
                class="inline-flex items-center gap-2 min-h-[40px] px-3.5 rounded-lg bg-white text-gray-800 ring-1 ring-gray-300 font-semibold hover:ring-gray-400 transition">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            <span>Copy link</span>
        </button>
    </div>

    {{-- Previous / next - chronological path through the archive for readers
         and crawlers alike. --}}
    @if (($previous ?? null) || ($next ?? null))
        <nav aria-label="More articles" class="mt-10 grid sm:grid-cols-2 gap-3">
            @if ($previous ?? null)
                <a href="{{ route('blog.show', $previous->slug) }}" class="group rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-4 hover:ring-gray-300 transition">
                    <div class="text-sm text-gray-500">Previous</div>
                    <div class="mt-1 font-semibold text-gray-900 group-hover:text-brand-700 leading-snug">{{ $previous->title }}</div>
                </a>
            @else
                <span></span>
            @endif
            @if ($next ?? null)
                <a href="{{ route('blog.show', $next->slug) }}" class="group rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-4 hover:ring-gray-300 transition sm:text-right">
                    <div class="text-sm text-gray-500">Next</div>
                    <div class="mt-1 font-semibold text-gray-900 group-hover:text-brand-700 leading-snug">{{ $next->title }}</div>
                </a>
            @endif
        </nav>
    @endif

    {{-- Sign-up, the page's one dark section. --}}
    <aside aria-labelledby="blog-cta-title" class="mt-12 rounded-xl bg-brand-900 text-white p-6 sm:p-8">
        <h2 id="blog-cta-title" class="font-display font-bold text-2xl leading-tight">Make GST invoices free with Apna Invoice</h2>
        <p class="mt-2 text-brand-100 max-w-xl leading-relaxed">CGST, SGST or IGST worked out from the place of supply, HSN/SAC on every line, a UPI QR on the PDF and one-tap WhatsApp share. Free during beta, no card needed.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center min-h-[44px] px-5 bg-white hover:bg-brand-50 text-brand-900 rounded-lg font-semibold transition-colors">Go to dashboard</a>
            @else
                <a href="{{ route('register') }}" class="inline-flex items-center min-h-[44px] px-5 bg-white hover:bg-brand-50 text-brand-900 rounded-lg font-semibold transition-colors">Create free account</a>
                <a href="{{ route('login') }}" class="inline-flex items-center min-h-[44px] px-5 ring-1 ring-white/40 hover:bg-white/10 text-white rounded-lg font-semibold transition-colors">Log in</a>
            @endauth
        </div>
    </aside>
</article>

@if ($related->isNotEmpty())
    <section class="bg-gray-50 border-t border-gray-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-14">
            <h2 class="font-display text-2xl font-bold text-gray-900">More blogs</h2>
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($related as $rp)
                    <a href="{{ route('blog.show', $rp->slug) }}" class="group block bg-white rounded-xl ring-1 ring-gray-200 shadow-sm p-5 hover:ring-gray-300 hover:shadow-md transition">
                        <div class="text-sm text-gray-500">{{ $rp->published_at?->format('d M Y') }} @if ($rp->reading_minutes) · {{ $rp->reading_minutes }} min read @endif</div>
                        <div class="mt-2 font-display font-bold text-lg text-gray-900 group-hover:text-brand-700 transition-colors leading-snug">{{ $rp->title }}</div>
                        @if ($rp->excerpt)
                            <div class="mt-2 text-[15px] text-gray-600 leading-relaxed">{{ Str::limit($rp->excerpt, 100) }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

<x-site-footer />

@include('partials.cookie-banner')
@stack('scripts')

<script>
    // Article enhancements, runs once on load. No dependencies.
    (function() {
        const article = document.getElementById('article-body');
        const bar = document.getElementById('reading-progress');
        if (!article || !bar) return;

        // 1) Stamp ids on every h2/h3 and append a hover anchor link.
        //    Lets readers deep-link sections; also surfaces a TOC in the URL bar.
        const slug = (text) => (text || '')
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .substring(0, 80);
        const used = new Set();
        article.querySelectorAll('h2, h3').forEach((h) => {
            if (h.id) { used.add(h.id); return; }
            let base = slug(h.textContent) || 'section';
            let id = base, i = 2;
            while (used.has(id)) { id = base + '-' + i; i++; }
            used.add(id);
            h.id = id;
            const a = document.createElement('a');
            a.href = '#' + id;
            a.className = 'heading-anchor';
            a.setAttribute('aria-label', 'Link to this section');
            a.textContent = '#';
            h.appendChild(a);
        });

        // 2) Reading progress bar, measure how far the article body is scrolled
        //    relative to its own height (not the whole page). More accurate than
        //    a window-scroll % which hits 100% before the article ends.
        function updateProgress() {
            const rect = article.getBoundingClientRect();
            const viewH = window.innerHeight || document.documentElement.clientHeight;
            const total = rect.height + rect.top - viewH;
            const scrolled = Math.max(0, -rect.top);
            const pct = total > 0 ? Math.min(100, (scrolled / total) * 100) : 0;
            bar.style.width = pct.toFixed(1) + '%';
        }
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => { updateProgress(); ticking = false; });
        }, { passive: true });
        updateProgress();
    })();
</script>

</body>
</html>
