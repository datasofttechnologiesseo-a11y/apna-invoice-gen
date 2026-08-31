@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'url' => null,
    'image' => null,
    'type' => 'website',
    'noindex' => false,
    'jsonLd' => [],
    'publishedTime' => null,
    'modifiedTime' => null,
    'section' => null,
    // Optional richer social title/description (link unfurls) without changing
    // the <title> shown in the browser tab / SERP. Falls back to $title/$descr.
    'ogTitle' => null,
    'ogDescription' => null,
])

@php
    $siteName = config('seo.name', config('app.name'));
    $suffix = config('seo.title_suffix', '');
    $fullTitle = $title
        ? $title . ' · ' . $siteName
        : ($siteName . $suffix);

    $descr = $description ?: config('seo.description');
    $kw = $keywords ?: config('seo.keywords');

    $appUrl = rtrim(config('app.url'), '/');
    // Build the canonical from the CONFIGURED production origin, not the request
    // host. `url()->current()` mirrors whatever Host the request arrived on
    // (www, apex, an IP vhost, a hosting preview subdomain), so every alias
    // would self-canonicalize and split indexing signals. Anchoring to APP_URL
    // means one canonical host for canonical + hreflang + og:url everywhere.
    // A page that needs a query string in its canonical (e.g. blog pagination)
    // passes an explicit :url, which wins.
    $canonical = $url ?: ($appUrl . (request()->path() === '/' ? '/' : '/' . request()->path()));
    $imgPath = $image ?: config('seo.og_image');
    $ogImage = $imgPath && str_starts_with($imgPath, 'http')
        ? $imgPath
        : $appUrl . '/' . ltrim($imgPath, '/');
    $ogImageType = str_ends_with(strtolower($ogImage), '.png') ? 'image/png' : 'image/jpeg';

    $locale = config('seo.locale', 'en_IN');
    $twitter = config('seo.twitter_handle');

    // Social-card title/description default to the page title/description but
    // can be overridden for a richer unfurl (e.g. "Invoice INV-042 — ₹12,500").
    $socialTitle = $ogTitle ?: ($title ?: $siteName);
    $socialDescr = $ogDescription ?: $descr;
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $descr }}">
@if ($kw)
    <meta name="keywords" content="{{ $kw }}">
@endif

<link rel="canonical" href="{{ $canonical }}">
{{-- Single-locale (en-IN) self-referencing hreflang + x-default. --}}
<link rel="alternate" hreflang="en-in" href="{{ $canonical }}">
<link rel="alternate" hreflang="x-default" href="{{ $canonical }}">

@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
@endif

{{-- Search-engine ownership verification (set via env). --}}
@if ($gsv = config('seo.google_site_verification'))
    <meta name="google-site-verification" content="{{ $gsv }}">
@endif
@if ($bing = config('seo.bing_site_verification'))
    <meta name="msvalidate.01" content="{{ $bing }}">
@endif

<meta name="author" content="{{ config('seo.organization.name') }}">
<meta name="publisher" content="{{ config('seo.organization.name') }}">
<meta name="theme-color" content="#1e3a8a">
<meta http-equiv="Content-Language" content="en-IN">

{{-- Favicon / app icons + PWA manifest. Living here means every public page
     (landing pages, blog) gets the install prompt + icons, not just the home
     page and the logged-in app shell. --}}
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/brand/apna-invoice-logo.png">
<link rel="manifest" href="/manifest.json">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $socialTitle }}">
<meta property="og:description" content="{{ $socialDescr }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $locale }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:secure_url" content="{{ $ogImage }}">
<meta property="og:image:type" content="{{ $ogImageType }}">
<meta property="og:image:width" content="{{ config('seo.og_image_width', 1200) }}">
<meta property="og:image:height" content="{{ config('seo.og_image_height', 630) }}">
<meta property="og:image:alt" content="{{ $socialTitle }}">

@if ($type === 'article')
    @if ($publishedTime)<meta property="article:published_time" content="{{ $publishedTime }}">@endif
    @if ($modifiedTime)<meta property="article:modified_time" content="{{ $modifiedTime }}">@endif
    @if ($section)<meta property="article:section" content="{{ $section }}">@endif
    <meta property="article:publisher" content="{{ config('seo.organization.url') }}">
@endif

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $socialTitle }}">
<meta name="twitter:description" content="{{ $socialDescr }}">
<meta name="twitter:image" content="{{ $ogImage }}">
<meta name="twitter:image:alt" content="{{ $socialTitle }}">
@if ($twitter)
    <meta name="twitter:site" content="{{ $twitter }}">
    <meta name="twitter:creator" content="{{ $twitter }}">
@endif

{{-- Geo targeting (India) --}}
<meta name="geo.region" content="IN">
<meta name="geo.placename" content="India">

@foreach ((array) $jsonLd as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
