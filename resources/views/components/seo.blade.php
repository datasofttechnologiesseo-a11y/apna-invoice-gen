@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'url' => null,
    'image' => null,
    'type' => 'website',
    'noindex' => false,
    'jsonLd' => [],
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

    $locale = config('seo.locale', 'en_IN');
    $twitter = config('seo.twitter_handle');
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $descr }}">
@if ($kw)
    <meta name="keywords" content="{{ $kw }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
@endif
<meta name="author" content="{{ config('seo.organization.name') }}">
<meta name="theme-color" content="#1e3a8a">
<meta http-equiv="Content-Language" content="en-IN">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title ?: $siteName }}">
<meta property="og:description" content="{{ $descr }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $locale }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="{{ config('seo.og_image_width', 1200) }}">
<meta property="og:image:height" content="{{ config('seo.og_image_height', 630) }}">
<meta property="og:image:alt" content="{{ $title ?: $siteName }}">

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title ?: $siteName }}">
<meta name="twitter:description" content="{{ $descr }}">
<meta name="twitter:image" content="{{ $ogImage }}">
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
