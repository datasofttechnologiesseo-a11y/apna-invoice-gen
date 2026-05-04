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
        :json-ld="[$articleJsonLd, $breadcrumbJsonLd]" />
    <link rel="canonical" href="{{ route('blog.show', $post->slug) }}">
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
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Dashboard →</a>
            @else
                <a href="{{ route('login') }}" class="text-base text-gray-800 hover:text-brand-700 font-semibold px-3 py-2">Log in</a>
                <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Start free</a>
            @endauth
        </nav>
    </div>
</header>

{{-- Article hero — title, byline, meta, optional cover --}}
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
    {{-- Markdown body — rendered server-side via league/commonmark with safe-HTML
         escaping. Typography is driven by `[&_h2]:` etc. utilities so we don't
         need a Tailwind Typography plugin. --}}
    <div class="prose-content
                text-gray-800 leading-relaxed text-[16px] sm:text-[17px]
                [&_h2]:font-display [&_h2]:font-extrabold [&_h2]:text-2xl [&_h2]:sm:text-3xl [&_h2]:text-gray-900 [&_h2]:mt-12 [&_h2]:mb-4 [&_h2]:leading-tight
                [&_h3]:font-display [&_h3]:font-bold [&_h3]:text-xl [&_h3]:sm:text-2xl [&_h3]:text-gray-900 [&_h3]:mt-8 [&_h3]:mb-3
                [&_h4]:font-bold [&_h4]:text-lg [&_h4]:text-gray-900 [&_h4]:mt-6 [&_h4]:mb-2
                [&_p]:my-5
                [&_a]:text-brand-700 [&_a]:font-medium [&_a]:underline [&_a]:decoration-brand-200 hover:[&_a]:decoration-brand-500
                [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-5 [&_ul]:space-y-2
                [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-5 [&_ol]:space-y-2
                [&_li]:leading-relaxed
                [&_strong]:text-gray-900 [&_strong]:font-bold
                [&_em]:italic
                [&_blockquote]:border-l-4 [&_blockquote]:border-brand-300 [&_blockquote]:pl-5 [&_blockquote]:my-6 [&_blockquote]:italic [&_blockquote]:text-gray-700
                [&_code]:bg-gray-100 [&_code]:text-brand-800 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-[14px] [&_code]:font-mono
                [&_pre]:bg-gray-900 [&_pre]:text-gray-100 [&_pre]:p-4 [&_pre]:rounded-lg [&_pre]:overflow-x-auto [&_pre]:my-6 [&_pre]:text-sm
                [&_pre_code]:bg-transparent [&_pre_code]:text-gray-100 [&_pre_code]:p-0
                [&_table]:w-full [&_table]:my-6 [&_table]:border-collapse
                [&_th]:bg-gray-50 [&_th]:font-semibold [&_th]:text-left [&_th]:p-3 [&_th]:border [&_th]:border-gray-200
                [&_td]:p-3 [&_td]:border [&_td]:border-gray-200
                [&_img]:rounded-lg [&_img]:my-6 [&_img]:w-full [&_img]:max-w-full [&_img]:h-auto
                [&_hr]:my-10 [&_hr]:border-gray-200">
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

    {{-- CTA block — drive blog traffic to product signup --}}
    <div class="mt-14 p-6 sm:p-8 rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 text-white">
        <h3 class="font-display font-extrabold text-2xl sm:text-3xl">Ready to issue your first GST invoice — free?</h3>
        <p class="mt-2 text-brand-100 max-w-xl">Apna Invoice is a free GST invoicing tool for Indian SMEs, MSMEs and freelancers. Auto CGST/SGST, HSN/SAC search, UPI QR, WhatsApp share — all in 60 seconds.</p>
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

</body>
</html>
