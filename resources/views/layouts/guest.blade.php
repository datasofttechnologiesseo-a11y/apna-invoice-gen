@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'noindex' => false,
])
<!DOCTYPE html>
<html lang="en-IN">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-seo
            :title="$title ?? 'Log in'"
            :description="$description ?? 'Sign in to Apna Invoice, the free GST invoice generator for Indian SMEs, startups, freelancers, and CAs.'"
            :keywords="$keywords ?? 'Apna Invoice login, free GST invoice generator India, invoice software signup, online invoice tool India'"
            :noindex="$noindex" />

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.google-analytics')
    </head>
    <body class="font-sans text-gray-900 antialiased bg-white">
        <a href="#main-content" class="skip-link">Skip to main content</a>
        <div class="min-h-screen lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">

            {{-- Brand panel (lg+). Solid deep teal, no glows: the logo, one
                 line on what the product is, and a faithful slice of what it
                 makes (an issued invoice with its GST split), so the first
                 thing a new user sees is the product, not adjectives. Not a
                 heading: the page's h1 is the form title. --}}
            @php
                // Sample invoice number in the current financial year.
                $fyStart = now()->month >= 4 ? now()->year : now()->year - 1;
                $sampleNo = 'INV/' . $fyStart . '-' . substr((string) ($fyStart + 1), -2) . '/0042';
            @endphp
            <aside class="hidden lg:flex lg:flex-col lg:justify-between lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto gap-10 p-10 xl:p-14 bg-brand-900 text-white">
                <a href="{{ url('/') }}" aria-label="Apna Invoice home" class="inline-flex self-start bg-white rounded-lg px-3 py-2">
                    <x-brand-logo class="h-9 w-auto block" />
                </a>

                <div class="max-w-md">
                    <p class="font-display font-bold text-3xl xl:text-[2.1rem] leading-tight tracking-tight">
                        GST billing and accounts for Indian businesses
                    </p>
                    <p class="mt-3 text-brand-100 leading-relaxed">
                        Create GST invoices, share them on WhatsApp and track every payment. Free during beta.
                    </p>

                    <div class="mt-8 rounded-xl bg-white text-gray-700 shadow-xl ring-1 ring-black/5 text-[13px] overflow-hidden" aria-hidden="true">
                        <div class="px-5 pt-4 pb-3 flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">Kulkarni Infotech Pvt Ltd</p>
                                <p class="text-gray-500 tabular-nums">{{ $sampleNo }} · Place of supply: Maharashtra (27)</p>
                            </div>
                            <span class="shrink-0 rounded-md bg-money-50 px-2 py-0.5 text-xs font-semibold text-money-700 ring-1 ring-money-200">Paid</span>
                        </div>
                        <dl class="px-5 py-3 border-t border-gray-100 space-y-1 tabular-nums">
                            <div class="flex justify-between"><dt>Taxable value</dt><dd class="text-gray-900">₹1,00,000</dd></div>
                            <div class="flex justify-between"><dt>CGST 9%</dt><dd class="text-gray-900">₹9,000</dd></div>
                            <div class="flex justify-between"><dt>SGST 9%</dt><dd class="text-gray-900">₹9,000</dd></div>
                        </dl>
                        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex justify-between items-baseline tabular-nums">
                            <span class="text-gray-500">Received by UPI</span>
                            <span class="font-display text-base font-bold text-gray-900">₹1,18,000</span>
                        </div>
                    </div>

                    <ul class="mt-8 space-y-2.5 text-[15px] text-brand-50">
                        @foreach ([
                            'CGST, SGST and IGST with HSN/SAC',
                            'GSTR-1 and GSTR-3B from your own books',
                            'UPI QR on every invoice PDF',
                            'Your data is never sold or shared',
                        ] as $point)
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 w-5 h-5 shrink-0 text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="text-sm text-brand-200">
                    Made in India by <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="font-semibold text-white underline decoration-white/30 underline-offset-2 hover:decoration-white transition">Datasoft Technologies</a>
                </p>
            </aside>

            {{-- Form panel --}}
            <main id="main-content" tabindex="-1" class="flex flex-col min-h-screen focus:outline-none">
                <div class="px-5 sm:px-10 pt-5 flex items-center justify-between gap-4">
                    <a href="{{ url('/') }}" class="lg:hidden inline-flex" aria-label="Apna Invoice home">
                        <x-brand-logo class="h-8 w-auto block" />
                    </a>
                    <a href="{{ url('/') }}" class="ml-auto inline-flex items-center gap-1.5 min-h-[44px] text-sm font-medium text-gray-600 hover:text-brand-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Back to home
                    </a>
                </div>
                <div class="w-full max-w-[26rem] mx-auto my-auto px-5 py-10 sm:px-0">
                    {{ $slot }}
                </div>
                <p class="lg:hidden px-5 pb-6 text-center text-xs text-gray-500">
                    Made in India by <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="font-semibold text-gray-600 hover:text-brand-700 transition-colors">Datasoft Technologies</a>
                </p>
            </main>
        </div>
        <x-whatsapp-float />
        @include('partials.cookie-banner')
        @stack('scripts')
    </body>
</html>
