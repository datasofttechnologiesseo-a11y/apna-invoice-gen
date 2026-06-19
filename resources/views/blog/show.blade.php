@php
    $ogImage = $post->effectiveOgImage()
        ? asset('storage/' . $post->effectiveOgImage())
        : asset('brand/apna-invoice-logo-sm.jpg');

    $articleJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $post->effectiveMetaDescription(),
        'image' => $ogImage,
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at?->toIso8601String(),
        'author' => [
            '@type' => 'Person',
            'name' => $post->author->name ?? config('app.name', 'Apna Invoice'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('app.name', 'Apna Invoice'),
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('brand/apna-invoice-logo-sm.jpg'),
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => route('blog.show', $post->slug),
        ],
        'inLanguage' => 'en-IN',
        'wordCount' => str_word_count(strip_tags($post->body)),
    ];

    $breadcrumbJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blogs', 'item' => route('blog.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title, 'item' => route('blog.show', $post->slug)],
        ],
    ];
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
        :url="route('blog.show', $post->slug)"
        :published-time="$post->published_at?->toIso8601String()"
        :modified-time="$post->updated_at?->toIso8601String()"
        section="Blog"
        :json-ld="[$articleJsonLd, $breadcrumbJsonLd]" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- Lora (serif) added for the article body, long-form reading typography.
         figtree stays for chrome/UI, plus-jakarta-sans stays for display headlines. --}}
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900|plus-jakarta-sans:400,500,600,700,800|lora:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Article body, long-read typography. Serif body font, generous
           line-height, larger size, more vertical breathing. Drop-cap on the
           first paragraph + anchor link on hover for h2/h3. */
        .article-body { font-family: 'Lora', Georgia, 'Times New Roman', serif; }
        .article-body p,
        .article-body li { font-size: 18px; line-height: 1.78; letter-spacing: 0.005em; }
        @media (max-width: 640px) {
            .article-body p,
            .article-body li { font-size: 17px; line-height: 1.72; }
        }
        .article-body p { margin: 1.4em 0; }

        /* Drop-cap on the very first paragraph, magazine touch.
           Skips when the first child is a heading/image (no drop-cap mid-section). */
        .article-body > p:first-of-type::first-letter {
            float: left;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 3.6em;
            line-height: 0.85;
            padding: 0.08em 0.12em 0 0;
            font-weight: 800;
            color: #075985;  /* brand-700 */
        }

        /* Anchor link for headings, small § that fades in on hover, lets
           readers deep-link sections. JS adds the link target. */
        .article-body .heading-anchor {
            margin-left: 0.4em;
            color: #94a3b8;
            text-decoration: none;
            opacity: 0;
            transition: opacity 150ms;
            font-weight: 400;
        }
        .article-body h2:hover .heading-anchor,
        .article-body h3:hover .heading-anchor { opacity: 1; }
        .article-body .heading-anchor:hover { color: #075985; }

        /* Reading progress bar, pinned to top, brand gradient. */
        #reading-progress {
            position: fixed; top: 0; left: 0;
            height: 3px; width: 0%;
            background: linear-gradient(to right, #f59e0b, #ea580c);
            z-index: 50;
            transition: width 50ms linear;
            pointer-events: none;
        }
    </style>
    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900">

{{-- Scroll-driven reading progress bar, pure JS, no library. Updates on
     scroll using requestAnimationFrame for smoothness. --}}
<div id="reading-progress" aria-hidden="true"></div>

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
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Dashboard →</a>
            @else
                <a href="{{ route('login') }}" class="text-base text-gray-800 hover:text-brand-700 font-semibold px-3 py-2">Log in</a>
                <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Start free</a>
            @endauth
        </nav>
    </div>
</header>

{{-- Article hero, title, byline, meta, optional cover --}}
<section class="bg-gradient-to-br from-brand-50 via-white to-accent-50 border-b border-gray-100">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
        <nav aria-label="Breadcrumb" class="text-xs uppercase tracking-wider font-bold text-gray-500 mb-3">
            <a href="{{ url('/') }}" class="hover:text-brand-700">Home</a>
            <span class="mx-1.5 text-gray-300">/</span>
            <a href="{{ route('blog.index') }}" class="hover:text-brand-700">Blogs</a>
        </nav>
        <h1 class="font-display text-3xl sm:text-4xl md:text-5xl font-extrabold text-gray-900 leading-tight">{{ $post->title }}</h1>
        @if ($post->excerpt)
            <p class="mt-4 text-base sm:text-lg text-gray-600 leading-relaxed">{{ $post->excerpt }}</p>
        @endif
        <div class="mt-5 flex items-center gap-3 text-sm text-gray-500">
            <span class="font-semibold text-gray-700">{{ $post->author->name ?? config('app.name') }}</span>
            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
            <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('d M Y') }}</time>
            @if ($post->reading_minutes)
                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                <span>{{ $post->reading_minutes }} min read</span>
            @endif
        </div>
    </div>
</section>

@if ($post->featured_image_path)
    <figure class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-2 md:-mt-4">
        <img src="{{ asset('storage/' . $post->featured_image_path) }}"
             alt="{{ $post->featured_image_alt ?: $post->title }}"
             class="w-full rounded-xl shadow-lg ring-1 ring-gray-200 aspect-[16/9] object-cover"
             width="1200" height="675">
    </figure>
@endif

<article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
    {{-- Markdown body, rendered server-side via league/commonmark with safe-HTML
         escaping. `article-body` (defined in the <style> block above) drives
         the long-read typography: Lora serif for paragraphs, drop-cap on the
         first paragraph, hover anchors on headings. Tailwind utilities still
         shape the secondary elements (headings, blockquote, code, tables). --}}
    <div class="article-body
                text-gray-800
                [&_h2]:font-display [&_h2]:font-extrabold [&_h2]:text-3xl [&_h2]:sm:text-4xl [&_h2]:text-gray-900 [&_h2]:mt-14 [&_h2]:mb-5 [&_h2]:leading-tight [&_h2]:scroll-mt-20
                [&_h3]:font-display [&_h3]:font-bold [&_h3]:text-2xl [&_h3]:sm:text-3xl [&_h3]:text-gray-900 [&_h3]:mt-10 [&_h3]:mb-4 [&_h3]:scroll-mt-20
                [&_h4]:font-display [&_h4]:font-bold [&_h4]:text-xl [&_h4]:text-gray-900 [&_h4]:mt-8 [&_h4]:mb-3
                [&_a]:text-brand-700 [&_a]:font-medium [&_a]:underline [&_a]:decoration-brand-200 [&_a]:decoration-2 [&_a]:underline-offset-2 hover:[&_a]:decoration-brand-500
                [&_ul]:list-disc [&_ul]:pl-7 [&_ul]:my-6 [&_ul]:space-y-3
                [&_ol]:list-decimal [&_ol]:pl-7 [&_ol]:my-6 [&_ol]:space-y-3
                [&_strong]:text-gray-900 [&_strong]:font-bold
                [&_em]:italic
                [&_blockquote]:border-l-4 [&_blockquote]:border-saffron-400 [&_blockquote]:pl-6 [&_blockquote]:my-8 [&_blockquote]:italic [&_blockquote]:text-gray-700 [&_blockquote]:bg-saffron-50/40 [&_blockquote]:py-2 [&_blockquote]:rounded-r
                [&_code]:bg-gray-100 [&_code]:text-brand-800 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-[0.9em] [&_code]:font-mono
                [&_pre]:bg-gray-900 [&_pre]:text-gray-100 [&_pre]:p-5 [&_pre]:rounded-lg [&_pre]:overflow-x-auto [&_pre]:my-8 [&_pre]:text-sm [&_pre]:leading-relaxed
                [&_pre_code]:bg-transparent [&_pre_code]:text-gray-100 [&_pre_code]:p-0 [&_pre_code]:text-inherit
                [&_table]:w-full [&_table]:my-8 [&_table]:border-collapse [&_table]:text-base
                [&_th]:bg-gray-50 [&_th]:font-semibold [&_th]:text-left [&_th]:p-3 [&_th]:border [&_th]:border-gray-200
                [&_td]:p-3 [&_td]:border [&_td]:border-gray-200
                [&_img]:rounded-lg [&_img]:my-8 [&_img]:w-full [&_img]:max-w-full [&_img]:h-auto [&_img]:shadow-sm [&_img]:ring-1 [&_img]:ring-gray-200
                [&_hr]:my-12 [&_hr]:border-t-2 [&_hr]:border-gray-100"
         id="article-body">
        {!! $post->renderedBody() !!}
    </div>

    {{-- Article footer: keywords + share line --}}
    @if ($post->meta_keywords)
        <div class="mt-12 pt-6 border-t border-gray-200 flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold text-gray-500 mr-1">Topics:</span>
            @foreach (explode(',', $post->meta_keywords) as $kw)
                @php $k = trim($kw); @endphp
                @if ($k)
                    <span class="inline-block px-2.5 py-1 bg-brand-50 text-brand-700 rounded-full text-xs font-medium ring-1 ring-brand-100">{{ $k }}</span>
                @endif
            @endforeach
        </div>
    @endif

    {{-- CTA block, drive blog traffic to product signup --}}
    <div class="mt-14 p-6 sm:p-8 rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 text-white">
        <h3 class="font-display font-extrabold text-2xl sm:text-3xl">Ready to issue your first GST invoice, free?</h3>
        <p class="mt-2 text-brand-100 max-w-xl">Apna Invoice is a free GST invoicing tool for Indian SMEs, MSMEs and freelancers. Auto CGST/SGST, HSN/SAC search, UPI QR, WhatsApp share, all in 60 seconds.</p>
        <div class="mt-5 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-5 py-3 bg-saffron-500 hover:bg-saffron-600 text-brand-900 rounded-lg font-bold shadow-sm transition">Go to dashboard →</a>
            @else
                <a href="{{ route('register') }}" class="inline-flex items-center px-5 py-3 bg-saffron-500 hover:bg-saffron-600 text-brand-900 rounded-lg font-bold shadow-sm transition">Start free →</a>
                <a href="{{ route('login') }}" class="inline-flex items-center px-5 py-3 bg-white/10 hover:bg-white/20 ring-1 ring-white/30 text-white rounded-lg font-semibold transition">Log in</a>
            @endauth
        </div>
    </div>
</article>

@if ($related->isNotEmpty())
    <section class="bg-gray-50 border-t border-gray-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h2 class="font-display text-2xl font-extrabold text-gray-900 mb-6">More blogs</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($related as $rp)
                    <a href="{{ route('blog.show', $rp->slug) }}" class="group block bg-white rounded-xl ring-1 ring-gray-200 p-5 hover:ring-brand-300 hover:shadow-md transition">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500">{{ $rp->published_at?->format('d M Y') }} @if ($rp->reading_minutes) · {{ $rp->reading_minutes }} min @endif</div>
                        <div class="mt-2 font-display font-bold text-base text-gray-900 group-hover:text-brand-700 transition leading-snug">{{ $rp->title }}</div>
                        @if ($rp->excerpt)
                            <div class="mt-2 text-xs text-gray-600 leading-relaxed">{{ Str::limit($rp->excerpt, 100) }}</div>
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
            a.textContent = '§';
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
