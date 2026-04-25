@props([
    'title',
    'eyebrow' => null,
    'lead' => null,
    'description' => null,
    'keywords' => null,
    'type' => 'website',
    'noindex' => false,
    'jsonLd' => [],
])
@php
    // Auto-generate a BreadcrumbList JSON-LD for every marketing page so Google
    // shows Home › {page} breadcrumbs in SERPs — free rich-result boost.
    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => url('/'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $title,
                'item' => url()->current(),
            ],
        ],
    ];
    $allJsonLd = array_merge([$breadcrumb], (array) $jsonLd);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
            <a href="{{ url('/#features') }}" class="hidden md:inline-block text-gray-600 hover:text-brand-700 font-medium">Features</a>
            <a href="{{ url('/#pricing') }}" class="hidden md:inline-block text-gray-600 hover:text-brand-700 font-medium">Pricing</a>
            <a href="{{ url('/#faq') }}" class="hidden md:inline-block text-gray-600 hover:text-brand-700 font-medium">FAQ</a>
            @auth
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Go to dashboard →</a>
            @else
                <a href="{{ route('login') }}" class="text-gray-700 hover:text-brand-700 font-medium px-3 py-2">Log in</a>
                <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Start free</a>
            @endauth
        </nav>
    </div>
</header>

<section class="relative overflow-hidden bg-gradient-to-br from-brand-50 via-white to-accent-50 border-b border-gray-100">
    <div class="absolute -top-24 -right-24 w-[400px] h-[400px] bg-brand-300 rounded-full blur-3xl opacity-20"></div>
    <div class="absolute -bottom-24 -left-24 w-[300px] h-[300px] bg-accent-300 rounded-full blur-3xl opacity-20"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-20">
        @if ($eyebrow)
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-100 text-brand-700 text-xs font-bold uppercase tracking-widest ring-1 ring-brand-200">
                {{ $eyebrow }}
            </span>
        @endif
        <h1 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 leading-tight">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-5 text-lg text-gray-600 leading-relaxed max-w-2xl">{{ $lead }}</p>
        @endif
    </div>
</section>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
    <div class="max-w-3xl space-y-6 text-gray-700 leading-relaxed
                [&_h2]:font-display [&_h2]:font-extrabold [&_h2]:text-2xl [&_h2]:text-gray-900 [&_h2]:mt-10 [&_h2]:mb-3
                [&_h3]:font-display [&_h3]:font-bold [&_h3]:text-xl [&_h3]:text-gray-900 [&_h3]:mt-6 [&_h3]:mb-2
                [&_p]:text-gray-700
                [&_a]:text-brand-700 [&_a]:font-medium hover:[&_a]:text-brand-800 [&_a]:underline [&_a]:decoration-brand-200 hover:[&_a]:decoration-brand-500
                [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:space-y-2
                [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:space-y-2
                [&_strong]:text-gray-900 [&_strong]:font-semibold">
        {{ $slot }}
    </div>
</main>

<x-site-footer />

@include('partials.cookie-banner')
@stack('scripts')

</body>
</html>
