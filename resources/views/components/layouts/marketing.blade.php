@props([
    'title',
    'eyebrow' => null,
    'lead' => null,
    'description' => null,
    'keywords' => null,
    'type' => 'website',
    'noindex' => false,
    'jsonLd' => [],
    // Wide pages (Features) lay out their own sections and cards instead of
    // reading as one column of article text.
    'wide' => false,
    // Policy pages: the side column lists the other policies instead of
    // the sign-up card.
    'legal' => false,
])
@php
    $appUrl = rtrim(config('app.url'), '/');
    // Same origin as <x-seo>'s canonical (APP_URL, never the request Host), so
    // the schema graph and the canonical tag always name the same URL - a
    // request on www. or a preview host must not mint a second identity.
    $canonical = $appUrl . (request()->path() === '/' ? '/' : '/' . request()->path());

    // Auto-generate a BreadcrumbList JSON-LD for every marketing page so Google
    // shows Home › {page} breadcrumbs in SERPs, free rich-result boost. Given an
    // @id so the WebPage node below can reference it (one linked entity graph).
    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        '@id' => $canonical . '#breadcrumb',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => $appUrl . '/',
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                // The crumb is the page's short name ("Pricing", not the
                // whole "Pricing: Free GST Invoice Software for India"): it
                // is printed as the breadcrumb trail in search results.
                'name' => str_contains($title, ': ') ? strstr($title, ': ', true) : $title,
                'item' => $canonical,
            ],
        ],
    ];

    // Auto-generate a WebPage node for every marketing page. Without this, pages
    // that don't pass their own jsonLd (legal/utility pages) emit only a
    // breadcrumb and no page-level entity. Tying each page to the site's WebSite
    // entity via isPartOf, and to its breadcrumb, gives Google a clean graph and
    // makes each URL an addressable entity - better than an orphan document.
    $webPage = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $canonical . '#webpage',
        'url' => $canonical,
        'name' => $title,
        'description' => $description ?: $lead ?: config('seo.description'),
        'inLanguage' => 'en-IN',
        // WebSite and Organization by the same @ids the home page declares,
        // so every inner page ties back to the Apna Invoice site and the
        // business behind it.
        'isPartOf' => [
            '@type' => 'WebSite',
            '@id' => $appUrl . '#website',
            'name' => config('seo.name'),
            'url' => $appUrl . '/',
        ],
        'breadcrumb' => ['@id' => $canonical . '#breadcrumb'],
        'publisher' => [
            '@type' => 'Organization',
            '@id' => $appUrl . '#organization',
            'name' => config('seo.organization.name'),
            'url' => config('seo.organization.url'),
        ],
    ];

    $allJsonLd = array_merge([$breadcrumb, $webPage], (array) $jsonLd);

    // "On this page", read from the page's own h2s. Each h2 gets an id here,
    // server-side, so the jump links work without JavaScript and are
    // crawlable. An h2 marked data-toc="skip" (a plan name inside a card) is
    // left alone.
    $body = (string) $slot;
    $toc = [];
    if (! $wide) {
        $usedIds = [];
        $body = preg_replace_callback('#<h2(\s[^>]*)?>(.*?)</h2>#s', function ($m) use (&$toc, &$usedIds) {
            $attrs = $m[1] ?? '';
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($text === '' || str_contains($attrs, 'data-toc="skip"')) {
                return $m[0];
            }
            if (preg_match('/\sid="([^"]+)"/', $attrs, $idMatch)) {
                $id = $idMatch[1];
                $out = $m[0];
            } else {
                $base = \Illuminate\Support\Str::slug($text) ?: 'section';
                $id = $base;
                for ($n = 2; isset($usedIds[$id]); $n++) {
                    $id = $base . '-' . $n;
                }
                $out = '<h2 id="' . e($id) . '"' . $attrs . '>' . $m[2] . '</h2>';
            }
            $usedIds[$id] = true;
            $toc[] = ['id' => $id, 'text' => $text];

            return $out;
        }, $body) ?? (string) $slot;
    }

    $policies = [
        ['route' => 'pages.privacy', 'label' => 'Privacy policy'],
        ['route' => 'pages.terms', 'label' => 'Terms of service'],
        ['route' => 'pages.refund', 'label' => 'Refund policy'],
        ['route' => 'pages.cookies', 'label' => 'Cookie settings'],
        ['route' => 'pages.security', 'label' => 'Security'],
    ];

    // Reading typography for the page body. Direct-child selectors only, so
    // a widget dropped into the page (calculator, plan card, form) keeps its
    // own type and spacing instead of inheriting article styles. Text links
    // are any <a> without a class: buttons and cards always carry classes,
    // so they keep their own look.
    $prose = 'text-gray-700 text-[1.0625rem] leading-[1.75] marker:text-brand-600
        [&>*:first-child]:!mt-0
        [&>p]:mt-4 [&>ul]:mt-4 [&>ol]:mt-4
        [&>h2]:mt-14 [&>h2]:font-display [&>h2]:text-2xl md:[&>h2]:text-[1.75rem] [&>h2]:font-bold [&>h2]:leading-tight [&>h2]:text-gray-900
        [&>h3]:mt-8 [&>h3]:font-display [&>h3]:text-lg [&>h3]:font-bold [&>h3]:leading-snug [&>h3]:text-gray-900
        [&>h2+p]:mt-3 [&>h2+ul]:mt-3 [&>h2+ol]:mt-3 [&>h3+p]:mt-1.5 [&>h3+ul]:mt-2
        [&>ul]:list-disc [&>ul]:pl-5 [&>ul>li]:mt-2 [&>ul>li]:pl-1
        [&>ol]:list-decimal [&>ol]:pl-5 [&>ol>li]:mt-2 [&>ol>li]:pl-1 [&>ol>li]:marker:font-semibold
        [&_strong]:font-semibold [&_strong]:text-gray-900
        [&_a:not([class])]:font-medium [&_a:not([class])]:text-brand-700 [&_a:not([class])]:underline [&_a:not([class])]:decoration-brand-300 [&_a:not([class])]:underline-offset-2 hover:[&_a:not([class])]:text-brand-800 hover:[&_a:not([class])]:decoration-brand-700';
@endphp
<!DOCTYPE html>
{{-- en-IN like the home page and the blog, matching og:locale and hreflang.
     The app locale is plain "en" (it drives translations), which left every
     marketing page declaring a different language from its own metadata. --}}
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo
        :title="$title"
        :description="$description ?: $lead"
        :keywords="$keywords"
        :type="$type"
        :noindex="$noindex"
        :json-ld="$allJsonLd" />
    {{-- crossorigin is required for the font-file connection; non-blocking
         stylesheet load pairs with display=swap (see welcome.blade.php). --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    <link rel="preload" as="style" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|plus-jakarta-sans:600,700,800&display=swap">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|plus-jakarta-sans:600,700,800&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|plus-jakarta-sans:600,700,800&display=swap"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900">
<a href="#main-content" class="skip-link">Skip to main content</a>

<x-site-header />

{{-- Page title. Light and left-aligned, on the same left edge as the header
     logo and the body text below. The short saffron rule before the eyebrow
     is the only ornament. --}}
<section class="bg-gray-50 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-10 md:pt-14 md:pb-14">
        <div class="max-w-3xl">
            @if ($eyebrow)
                <p class="flex items-center gap-2.5 text-sm font-semibold text-brand-700">
                    <span class="w-6 h-0.5 rounded-full bg-accent-500" aria-hidden="true"></span>{{ $eyebrow }}
                </p>
            @endif
            <h1 class="{{ $eyebrow ? 'mt-3' : '' }} font-display text-4xl md:text-5xl font-extrabold text-gray-900 leading-[1.12] tracking-tight">{{ $title }}</h1>
            @if ($lead)
                <p class="mt-4 text-lg md:text-xl text-gray-600 leading-relaxed max-w-2xl">{{ $lead }}</p>
            @endif
        </div>
    </div>
</section>

<main id="main-content" tabindex="-1" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14 focus:outline-none">
    @if ($wide)
        {!! $body !!}
    @else
        <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_16rem] lg:gap-14 xl:gap-20">
            <div class="min-w-0 max-w-3xl {{ $prose }}">
                {!! $body !!}
            </div>

            <aside class="hidden lg:block" aria-label="{{ $legal ? 'Policies and contents' : 'Contents and sign-up' }}">
                <div class="sticky top-6 max-h-[calc(100vh-3rem)] overflow-y-auto space-y-8 pb-2">
                    @if (count($toc) >= 3)
                        <nav aria-label="On this page">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">On this page</p>
                            <ol class="mt-3 border-l border-gray-200 text-sm">
                                @foreach ($toc as $item)
                                    <li>
                                        <a href="#{{ $item['id'] }}" class="block -ml-px border-l-2 border-transparent pl-3 py-1.5 leading-snug text-gray-600 hover:text-gray-900 hover:border-gray-400 transition-colors">{{ $item['text'] }}</a>
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                    @endif

                    @if ($legal)
                        <nav aria-label="Policies">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Policies</p>
                            <ul class="mt-3 text-sm space-y-0.5">
                                @foreach ($policies as $policy)
                                    @php $current = request()->routeIs($policy['route']); @endphp
                                    <li>
                                        <a href="{{ route($policy['route']) }}" @if ($current) aria-current="page" @endif
                                           class="block rounded-md px-2.5 py-1.5 -mx-2.5 {{ $current ? 'bg-brand-50 text-brand-800 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">{{ $policy['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @else
                        <div class="rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5">
                            <p class="font-display font-bold text-gray-900 leading-snug">GST billing, free during beta</p>
                            <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">Invoices, purchases, stock and GST returns for your business. No card needed.</p>
                            @auth
                                <a href="{{ route('dashboard') }}" class="mt-4 flex items-center justify-center min-h-[44px] rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors">Go to dashboard</a>
                            @else
                                <a href="{{ route('register') }}" class="mt-4 flex items-center justify-center min-h-[44px] rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors">Create free account</a>
                            @endauth
                            <div class="mt-4 pt-4 border-t border-gray-100 text-sm text-gray-600">
                                Questions? Call or WhatsApp
                                <a href="tel:{{ config('seo.contact.phone_e164') }}" class="block mt-0.5 font-semibold text-gray-900 hover:text-brand-700 tabular-nums">{{ config('seo.contact.phone_display') }}</a>
                            </div>
                        </div>
                    @endif
                </div>
            </aside>
        </div>

        @if ($legal)
            {{-- The side column is hidden on phones; the policy links still
                 need a way between them there. --}}
            <nav aria-label="Policies" class="lg:hidden mt-12 pt-6 border-t border-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Policies</p>
                <ul class="mt-2 flex flex-wrap gap-x-5 text-sm">
                    @foreach ($policies as $policy)
                        <li><a href="{{ route($policy['route']) }}" @if (request()->routeIs($policy['route'])) aria-current="page" @endif class="inline-flex items-center min-h-[40px] {{ request()->routeIs($policy['route']) ? 'text-gray-900 font-semibold' : 'text-brand-700 hover:text-brand-800' }}">{{ $policy['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    @endif
</main>

<x-site-footer />

@include('partials.cookie-banner')
@stack('scripts')

</body>
</html>
