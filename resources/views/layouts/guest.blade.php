@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'noindex' => false,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-seo
            :title="$title ?? 'Log in'"
            :description="$description ?? 'Sign in to Apna Invoice, the free GST invoice generator for Indian SMEs, startups, freelancers, and CAs.'"
            :keywords="$keywords ?? 'Apna Invoice login, free GST invoice generator India, invoice software signup, online invoice tool India'"
            :noindex="$noindex" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.google-analytics')
    </head>
    <body class="font-sans text-gray-900 antialiased bg-white">
        <div class="min-h-screen lg:grid lg:grid-cols-2">

            {{-- Brand panel (lg+): premium first impression. Deep navy with soft
                 blurred brand orbs for depth; logo, value proposition, trust cues. --}}
            <aside class="hidden lg:flex lg:flex-col lg:justify-between relative overflow-hidden p-12 xl:p-16 text-white bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950">
                <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-brand-500/25 blur-3xl pointer-events-none"></div>
                <div class="absolute top-1/3 -right-24 w-96 h-96 rounded-full bg-saffron-500/20 blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 left-1/4 w-96 h-96 rounded-full bg-money-500/15 blur-3xl pointer-events-none"></div>

                <a href="{{ url('/') }}" aria-label="Apna Invoice home" class="relative inline-flex items-center self-start bg-white rounded-xl px-3 py-2 shadow-lg">
                    <x-brand-logo class="h-9 w-auto block" />
                </a>

                <div class="relative max-w-md">
                    <h2 class="font-display font-extrabold text-3xl xl:text-4xl leading-tight tracking-tight">
                        GST invoicing, done in <span class="text-saffron-400">60 seconds</span>.
                    </h2>
                    <p class="mt-4 text-brand-100 text-base leading-relaxed">
                        Create GST-compliant invoices, share them on WhatsApp, and get paid faster. Built for Indian businesses, free during beta.
                    </p>
                    <ul class="mt-8 space-y-3.5">
                        @foreach ([
                            'Auto CGST, SGST &amp; IGST with HSN/SAC',
                            'GSTR-1 &amp; GSTR-3B ready, audit-friendly',
                            'UPI QR on every invoice PDF',
                            'Your data stays in India',
                        ] as $point)
                            <li class="flex items-center gap-3 text-sm text-brand-50">
                                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-saffron-400/20 text-saffron-300">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                                {!! $point !!}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="relative flex items-center gap-2 text-xs text-brand-200">
                    <span class="inline-flex flex-col w-4 h-3 rounded-[1px] overflow-hidden ring-1 ring-white/30">
                        <span class="block w-full h-1/3 bg-[#ff9933]"></span>
                        <span class="block w-full h-1/3 bg-white"></span>
                        <span class="block w-full h-1/3 bg-[#138808]"></span>
                    </span>
                    Made in India · by Datasoft Technologies
                </div>
            </aside>

            {{-- Form panel --}}
            <main class="flex flex-col min-h-screen overflow-y-auto">
                <div class="w-full max-w-md mx-auto my-auto px-6 py-10 sm:px-10 animate-fade-up">
                    {{-- Logo for mobile (brand panel is hidden below lg) --}}
                    <div class="lg:hidden mb-8 flex justify-center">
                        <a href="{{ url('/') }}" aria-label="Apna Invoice home" class="inline-block">
                            <x-brand-logo class="h-12 w-auto block" />
                        </a>
                    </div>

                    {{ $slot }}

                    <p class="mt-8 text-center text-xs text-gray-400">
                        Powered by <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="font-semibold text-gray-500 hover:text-brand-700 transition">Datasoft Technologies</a>
                    </p>
                </div>
            </main>
        </div>
        @include('partials.cookie-banner')
        @stack('scripts')
    </body>
</html>
