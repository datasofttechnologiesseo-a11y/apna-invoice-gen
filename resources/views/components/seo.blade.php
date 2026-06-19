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
])

@php
    $siteName = config('seo.name', config('app.name'));
    $suffix = config('seo.title_suffix', '');
    $fullTitle = $title
        ? $title . ' · ' . $siteName
        : ($siteName . $suffix);

    $descr = $description ?: config('seo.description');
    $kw = $keywords ?: config('seo.keywords');
    $canonical = $url ?: url()->current();

    $appUrl = rtrim(config('app.url'), '/');
    $imgPath = $image ?: config('seo.og_image');
    $ogImage = $imgPath && str_starts_with($imgPath, 'http')
        ? $imgPath
        : $appUrl . '/' . ltrim($imgPath, '/');
    $ogImageType = str_ends_with(strtolower($ogImage), '.png') ? 'image/png' : 'image/jpeg';

    $locale = config('seo.locale', 'en_IN');
    $twitter = config('seo.twitter_handle');
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

{{-- Favicon / app icons --}}
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/brand/apna-invoice-logo.png">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title ?: $siteName }}">
<meta property="og:description" content="{{ $descr }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $locale }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:secure_url" content="{{ $ogImage }}">
<meta property="og:image:type" content="{{ $ogImageType }}">
<meta property="og:image:width" content="{{ config('seo.og_image_width', 1200) }}">
<meta property="og:image:height" content="{{ config('seo.og_image_height', 630) }}">
<meta property="og:image:alt" content="{{ $title ?: $siteName }}">

@if ($type === 'article')
    @if ($publishedTime)<meta property="article:published_time" content="{{ $publishedTime }}">@endif
    @if ($modifiedTime)<meta property="article:modified_time" content="{{ $modifiedTime }}">@endif
    @if ($section)<meta property="article:section" content="{{ $section }}">@endif
    <meta property="article:publisher" content="{{ config('seo.organization.url') }}">
@endif

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title ?: $siteName }}">
<meta name="twitter:description" content="{{ $descr }}">
<meta name="twitter:image" content="{{ $ogImage }}">
<meta name="twitter:image:alt" content="{{ $title ?: $siteName }}">
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
