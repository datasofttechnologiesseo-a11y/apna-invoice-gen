@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'url' => null,
    'image' => null,
    'type' => 'website',
    'noindex' => false,
    // Only meaningful with noindex. A search-results or filtered list wants
    // noindex + follow: keep it out of the index, but let the crawler walk
    // through it to the pages it links. Defaults false so the signed-in app
    // keeps the noindex, nofollow it has always had.
    'follow' => false,
    'jsonLd' => [],
    'publishedTime' => null,
    'modifiedTime' => null,
    'section' => null,
    'author' => null,
    // Optional richer social title/description (link unfurls) without changing
    // the <title> shown in the browser tab / SERP. Falls back to $title/$descr.
    'ogTitle' => null,
    'ogDescription' => null,
])

@php
    // A bound attribute (:title="'Import ' . $x") reaches a component already
    // HTML-escaped - Laravel sanitises bound component attributes - and every
    // tag below escapes again on output, so "Products & Services" printed as
    // "Products &amp; Services" in the tab, search results and link previews.
    // Decode to plain text here; the output escaping keeps it safe.
    $plain = function ($v) {
        if (! is_string($v)) {
            return $v;
        }
        for ($i = 0; $i < 3 && ($d = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== $v; $i++) {
            $v = $d;
        }

        return $v;
    };
    $title = $plain($title);
    $description = $plain($description);
    $keywords = $plain($keywords);
    $ogTitle = $plain($ogTitle);
    $ogDescription = $plain($ogDescription);

    $siteName = config('seo.name', config('app.name'));
    $suffix = config('seo.title_suffix', '');
    $fullTitle = $title
        ? $title . ' · ' . $siteName
        : ($siteName . $suffix);

    $descr = $description ?: config('seo.description');
    // The site-wide keyword fallback is for indexable pages only. A noindex
    // page (the app, a customer's shared invoice) has nothing to rank for.
    $kw = $keywords ?: ($noindex ? null : config('seo.keywords'));

    $appUrl = rtrim(config('app.url'), '/');
    // Build the canonical from the CONFIGURED production origin, not the request
    // host. `url()->current()` mirrors whatever Host the request arrived on
    // (www, apex, an IP vhost, a hosting preview subdomain), so every alias
    // would self-canonicalize and split indexing signals. Anchoring to APP_URL
    // means one canonical host for canonical + hreflang + og:url everywhere.
    // A page that needs a query string in its canonical (e.g. blog pagination)
    // passes an explicit :url, which wins.
    $canonical = $url ?: ($appUrl . (request()->path() === '/' ? '/' : '/' . request()->path()));
    // Per-page share card, resolved from the route name: pages.gst-calculator
    // uses brand/og/gst-calculator.png. Every page sharing one card meant a
    // link to the calculator unfurled exactly like a link to pricing, and the
    // thumbnail told the reader nothing about where they were going.
    //
    // Resolved by convention rather than a prop on every page, so adding a
    // card later is dropping a file in - and the file_exists check means a
    // missing card silently falls back to the default rather than emitting an
    // og:image that 404s, which is worse than a generic one.
    $autoCard = null;
    if (! $image && ($routeName = request()->route()?->getName())) {
        $slug = str_starts_with($routeName, 'pages.')
            ? substr($routeName, strlen('pages.'))
            : null;
        if ($slug && preg_match('/^[a-z0-9-]+$/', $slug)
            && is_file(public_path("brand/og/{$slug}.png"))) {
            $autoCard = "/brand/og/{$slug}.png";
        }
    }

    $imgPath = $image ?: ($autoCard ?: config('seo.og_image'));
    $ogImage = $imgPath && str_starts_with($imgPath, 'http')
        ? $imgPath
        : $appUrl . '/' . ltrim($imgPath, '/');

    // og:image:width/height/type describe the actual file. They used to be
    // hard-coded to 1200x630 and PNG-or-JPEG, so a blog cover of another size
    // or a WebP was announced with the wrong size and type, and scrapers that
    // trust those tags (LinkedIn, WhatsApp, Facebook) crop or drop the
    // preview. Measured from disk when the image is ours (a header read, not
    // a decode); left out when it cannot be measured, which is better than
    // wrong.
    $ogImageWidth = $ogImageHeight = null;
    $ogImageType = null;
    $ogImageHost = parse_url($ogImage, PHP_URL_HOST);
    $ogImagePath = rawurldecode((string) parse_url($ogImage, PHP_URL_PATH));
    if (in_array($ogImageHost, [parse_url($appUrl, PHP_URL_HOST), request()->getHost()], true)
        && $ogImagePath !== '' && ! str_contains($ogImagePath, '..')
        && is_file($ogImageFile = public_path(ltrim($ogImagePath, '/')))
        && ($ogImageSize = @getimagesize($ogImageFile))) {
        [$ogImageWidth, $ogImageHeight] = $ogImageSize;
        $ogImageType = $ogImageSize['mime'] ?? null;
    }
    $ogImageType ??= match (strtolower(pathinfo($ogImagePath, PATHINFO_EXTENSION))) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => null,
    };

    $locale = config('seo.locale', 'en_IN');
    $twitter = config('seo.twitter_handle');

    // Social-card title/description default to the page title/description but
    // can be overridden for a richer unfurl (e.g. "Invoice INV-042 - ₹12,500").
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
    <meta name="robots" content="noindex, {{ $follow ? 'follow' : 'nofollow' }}">
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
<meta name="theme-color" content="#167c88">
<meta http-equiv="Content-Language" content="en-IN">

{{-- Favicon / app icons + PWA manifest. Living here means every public page
     (landing pages, blog) gets the install prompt + icons, not just the home
     page and the logged-in app shell. --}}
{{-- Icons are the logo MARK only (document + rupee arrow) - the wordmark is
     unreadable at 16-48px. Google wants a square icon in a multiple of 48px,
     hence the 48/96 PNGs. Bump ?v= when the artwork changes so browsers and
     Google's favicon cache drop the old one. --}}
<link rel="icon" href="/favicon.ico?v=3" sizes="16x16 32x32 48x48 64x64">
<link rel="icon" type="image/png" sizes="48x48" href="/brand/favicon-48.png?v=3">
<link rel="icon" type="image/png" sizes="96x96" href="/brand/favicon-96.png?v=3">
<link rel="icon" type="image/png" sizes="144x144" href="/brand/favicon-144.png?v=3">
<link rel="icon" type="image/png" sizes="192x192" href="/brand/icon-192.png?v=3">
<link rel="apple-touch-icon" sizes="180x180" href="/brand/apple-touch-icon.png?v=3">
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
@if ($ogImageType)
<meta property="og:image:type" content="{{ $ogImageType }}">
@endif
@if ($ogImageWidth && $ogImageHeight)
<meta property="og:image:width" content="{{ $ogImageWidth }}">
<meta property="og:image:height" content="{{ $ogImageHeight }}">
@endif
<meta property="og:image:alt" content="{{ $socialTitle }}">

@if ($type === 'article')
    @if ($publishedTime)<meta property="article:published_time" content="{{ $publishedTime }}">@endif
    @if ($modifiedTime)<meta property="article:modified_time" content="{{ $modifiedTime }}">@endif
    @if ($section)<meta property="article:section" content="{{ $section }}">@endif
    @if ($author)<meta property="article:author" content="{{ $author }}">@endif
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

{{-- JSON_HEX_TAG is load-bearing, not tidiness. Without it a value
     containing "</script>" - a blog post title is enough - closes this block
     early: the structured data is silently lost and everything after the
     sequence is parsed as HTML, so the title becomes a script the browser
     runs. JSON_UNESCAPED_SLASHES stays (readable URLs) and does not help
     here, because it is the "<" that ends the tag, not the slash. --}}
@foreach ((array) $jsonLd as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
