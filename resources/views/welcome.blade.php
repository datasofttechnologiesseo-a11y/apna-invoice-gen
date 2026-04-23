@php
    $faqs = [
        ['q' => 'Are these invoices GST-compliant?', 'a' => "Yes. We include GSTIN, HSN/SAC codes, place of supply, CGST/SGST or IGST split, invoice number, and amount in words (Indian format — lakhs and crores). The format aligns with CBIC's prescribed tax invoice requirements."],
        ['q' => 'Do I need to know the GST rate for each item?', 'a' => 'Yes, but we pre-load the standard slabs (0%, 0.10%, 0.25%, 3%, 5%, 12%, 18%, 28%). You pick one per line item; the system handles CGST/SGST or IGST math based on customer state.'],
        ['q' => "What's the difference between draft and final?", 'a' => 'Drafts are editable and have no invoice number yet. Once you finalize, the invoice gets a permanent number (e.g. INV-0001), becomes read-only, and is considered legally issued. You can still mark payments against finalized invoices.'],
        ['q' => 'Can I bill international clients?', 'a' => 'Yes. We support INR, USD, EUR, GBP, AED, SGD, AUD and CAD. Exchange rate is captured per invoice. Export invoices still use the tax invoice format.'],
        ['q' => 'What happens to my data?', 'a' => 'It lives in your account, on our servers in India. You can export your invoices and customer data any time. We never sell data to third parties.'],
        ['q' => 'Who builds this?', 'a' => 'Datasoft Technologies (DST) — an Indian software company focused on practical tools for modern businesses. This product is free during beta while we grow.'],
    ];

    $appUrl = rtrim(config('app.url'), '/');
    $siteName = config('seo.name');

    $jsonLd = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('seo.organization.name'),
            'legalName' => config('seo.organization.legal_name'),
            'url' => config('seo.organization.url'),
            'logo' => $appUrl . config('seo.organization.logo'),
            'foundingLocation' => ['@type' => 'Country', 'name' => 'India'],
            'areaServed' => 'IN',
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $appUrl,
            'inLanguage' => 'en-IN',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $appUrl . '/?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'url' => $appUrl,
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'InvoicingSoftware',
            'operatingSystem' => 'Web',
            'description' => config('seo.description'),
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'INR',
                'availability' => 'https://schema.org/InStock',
            ],
            'inLanguage' => 'en-IN',
            'audience' => ['@type' => 'BusinessAudience', 'geographicArea' => 'India'],
            'featureList' => 'GST-compliant invoices, HSN/SAC codes, CGST/SGST/IGST auto-split, Indian number format (lakhs & crores), PDF export, payment reminders, WhatsApp sharing, customer and product management',
            'provider' => [
                '@type' => 'Organization',
                'name' => config('seo.organization.legal_name'),
                'url' => config('seo.organization.url'),
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], $faqs),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo
        title="Free GST Invoice Generator Online"
        description="Create GST-compliant invoices in 60 seconds. Free for Indian SMEs, startups, freelancers & CAs. HSN/SAC codes, CGST/SGST auto-split, amount in words (lakhs & crores), professional PDF export. GST 2.0 ready."
        type="website"
        :json-ld="$jsonLd" />
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload" as="image" href="{{ asset('brand/apna-invoice-logo-sm.jpg') }}" fetchpriority="high">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900|plus-jakarta-sans:700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/landing.js'])
</head>
<body class="font-sans antialiased bg-white text-gray-900 overflow-x-hidden">

<!-- Announcement bar -->
<div class="bg-gradient-to-r from-brand-900 via-brand-800 to-brand-900 text-white text-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-center gap-2 flex-wrap">
        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-accent-500 text-accent-900 text-[10px] font-bold uppercase tracking-wider">New</span>
        <span>Free forever during beta — unlimited GST invoices, unlimited customers. <a href="{{ route('register') }}" class="underline font-semibold hover:text-accent-300">Claim now →</a></span>
    </div>
</div>

<!-- Header -->
<header class="relative bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-3 py-4 min-w-0 flex-shrink" aria-label="Apna Invoice home">
            <span class="inline-block bg-white rounded">
                <x-brand-logo class="h-9 md:h-14 w-auto block" />
            </span>
        </a>
        <nav class="flex items-center gap-2 md:gap-6 text-sm flex-shrink-0">
            <a href="#features" class="hidden md:inline-block text-gray-600 hover:text-brand-700 font-medium">Features</a>
            <a href="#pricing" class="hidden md:inline-block text-gray-600 hover:text-brand-700 font-medium">Pricing</a>
            <a href="#faq" class="hidden md:inline-block text-gray-600 hover:text-brand-700 font-medium">FAQ</a>
            @auth
                <a href="{{ route('dashboard') }}" class="px-3 py-2 md:px-5 md:py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition whitespace-nowrap">Go to dashboard →</a>
            @else
                <a href="{{ route('login') }}" class="text-gray-700 hover:text-brand-700 font-medium px-2 py-2 md:px-3 whitespace-nowrap">Log in</a>
                <a href="{{ route('register') }}" class="px-3 py-2 md:px-5 md:py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition whitespace-nowrap">Start free</a>
            @endauth
        </nav>
    </div>
</header>

<!-- Hero -->
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-hero-mesh"></div>
    <div class="absolute inset-0 bg-grid-soft bg-grid-soft opacity-50"></div>
    <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-gradient-to-br from-brand-400 to-accent-500 rounded-full blur-3xl opacity-20 hidden md:block md:animate-float"></div>
    <div class="absolute -bottom-40 -left-20 w-[400px] h-[400px] bg-gradient-to-br from-saffron-400 to-brand-500 rounded-full blur-3xl opacity-15 hidden md:block md:animate-float" style="animation-delay: -3s;"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-20 md:pt-24 md:pb-28 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div class="animate-fade-up">
            <div class="flex items-center gap-2 flex-wrap mb-5">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white ring-1 ring-brand-200 text-brand-700 text-xs font-bold tracking-wide uppercase shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2.39 4.84L18 8l-4 3.9.94 5.48L10 14.77 5.06 17.38 6 11.9 2 8l5.61-1.16z"/></svg>
                    GST-Ready
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white ring-1 ring-saffron-200 text-saffron-700 text-xs font-bold tracking-wide uppercase shadow-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-saffron-500 animate-shimmer"></span>
                    Made in India
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-money-50 ring-1 ring-money-200 text-money-800 text-xs font-bold tracking-wide uppercase shadow-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-money-500"></span>
                    Free during beta
                </span>
            </div>
            <h1 class="font-display text-4xl sm:text-5xl md:text-[3.5rem] font-extrabold tracking-tight text-gray-900 leading-[1.05]">
                Your first GST invoice in
                <span class="relative inline-block">
                    <span class="relative bg-gradient-to-r from-brand-700 via-brand-600 to-accent-500 bg-clip-text text-transparent">60 seconds.</span>
                    <svg class="absolute -bottom-2 left-0 w-full" height="10" viewBox="0 0 200 10" preserveAspectRatio="none" aria-hidden="true">
                        <path d="M2 7 Q50 1, 100 6 T198 4" stroke="url(#heroUnderline)" stroke-width="3" fill="none" stroke-linecap="round"/>
                        <defs>
                            <linearGradient id="heroUnderline" x1="0" x2="1" y1="0" y2="0">
                                <stop offset="0%" stop-color="#1e3a8a"/>
                                <stop offset="100%" stop-color="#f97316"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </span>
                <span class="block mt-3 text-gray-900">Built for India's businesses.</span>
            </h1>
            <p class="mt-6 text-lg md:text-xl text-gray-600 max-w-xl leading-relaxed">
                Professional tax invoices with auto <strong class="text-gray-900">CGST/SGST/IGST</strong>,
                HSN/SAC codes, FY-reset numbering, one-click PDF and WhatsApp share —
                priced for the way Indian SMEs &amp; Startups actually work.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="group relative inline-flex items-center justify-center px-7 py-3.5 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-xl shadow-brand transition overflow-hidden">
                    <span class="relative z-10 flex items-center">
                        Start free — 60 seconds
                        <svg class="w-5 h-5 ml-2 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
                    </span>
                    <span class="absolute inset-0 bg-gradient-to-r from-brand-600 via-accent-500 to-brand-600 bg-[length:200%_100%] opacity-0 group-hover:opacity-100 transition-opacity animate-[shimmer_3s_linear_infinite]"></span>
                </a>
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-7 py-3.5 bg-white border-2 border-gray-200 hover:border-brand-500 text-gray-800 font-semibold rounded-xl transition">
                    I have an account
                </a>
            </div>
            <div class="mt-8 flex items-center gap-6 text-sm text-gray-500 flex-wrap">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-money-600" fill="currentColor" viewBox="0 0 20 20"><path d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"/></svg>
                    No card required
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-money-600" fill="currentColor" viewBox="0 0 20 20"><path d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"/></svg>
                    Unlimited invoices
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-money-600" fill="currentColor" viewBox="0 0 20 20"><path d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"/></svg>
                    Your data stays in India
                </div>
            </div>
        </div>

        <!-- Invoice mockup -->
        <div class="relative animate-fade-up" style="animation-delay: 0.15s; animation-fill-mode: both;">
            {{-- Ambient glow behind the card --}}
            <div class="absolute -inset-8 bg-gradient-to-br from-brand-400/40 via-accent-400/30 to-saffron-300/30 blur-3xl rounded-[3rem]"></div>

            {{-- Gradient border wrapper --}}
            <div class="relative rounded-3xl bg-gradient-to-br from-brand-300/60 via-accent-300/50 to-saffron-300/60 p-[1px] shadow-2xl transition-transform duration-500 hover:-translate-y-1">
                <div class="relative bg-white rounded-[calc(1.5rem-1px)] overflow-hidden">
                    {{-- Dark header with subtle pattern --}}
                    <div class="relative p-6 md:p-7 bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 text-white overflow-hidden">
                        <div class="absolute inset-0 bg-grid-soft opacity-[0.08]"></div>
                        <div class="absolute -top-16 -right-16 w-40 h-40 bg-accent-400 rounded-full blur-3xl opacity-30"></div>
                        <div class="relative flex justify-between items-start">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-accent-400 to-saffron-400 text-brand-900 font-black text-lg flex items-center justify-center shadow-lg ring-1 ring-white/20">A</div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-[0.2em] text-accent-300 font-bold">Tax Invoice</div>
                                    <div class="font-display font-black text-xl md:text-2xl mt-0.5">INV/2026-27/0042</div>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-money-400/20 text-money-200 ring-1 ring-money-300/40">
                                <span class="w-1.5 h-1.5 rounded-full bg-money-300 animate-[pulse_2.5s_ease-in-out_infinite]"></span>
                                Paid
                            </span>
                        </div>
                        <div class="relative mt-5 flex items-end justify-between">
                            <div class="text-sm">
                                <div class="font-semibold text-white">Acme Consulting LLP</div>
                                <div class="text-brand-200 text-xs mt-0.5">Mumbai · Maharashtra</div>
                            </div>
                            <div class="text-right text-xs">
                                <div class="text-brand-300/80">GSTIN</div>
                                <div class="font-mono text-brand-100 tracking-tight">27AABCU9603R1ZM</div>
                            </div>
                        </div>
                    </div>

                    {{-- Line items + totals --}}
                    <div class="p-6 md:p-7 space-y-3 text-sm">
                        <div class="flex items-center justify-between text-gray-700">
                            <span class="flex-1 min-w-0 pr-3">Consulting · 40 hrs × ₹2,500</span>
                            <span class="font-mono font-semibold text-gray-900 tabular-nums">₹1,00,000.00</span>
                        </div>
                        <div class="flex items-center justify-between text-gray-700">
                            <span class="flex-1 min-w-0 pr-3">Implementation add-on</span>
                            <span class="font-mono font-semibold text-gray-900 tabular-nums">₹25,000.00</span>
                        </div>
                        <div class="h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent my-3"></div>
                        <div class="flex justify-between text-gray-500 text-xs"><span>Subtotal</span><span class="font-mono tabular-nums">₹1,25,000.00</span></div>
                        <div class="flex justify-between text-gray-500 text-xs"><span>CGST @ 9%</span><span class="font-mono tabular-nums">₹11,250.00</span></div>
                        <div class="flex justify-between text-gray-500 text-xs"><span>SGST @ 9%</span><span class="font-mono tabular-nums">₹11,250.00</span></div>

                        {{-- Grand Total — the hero element --}}
                        <div class="mt-4 p-4 rounded-xl bg-gradient-to-br from-brand-50 via-accent-50 to-saffron-50 ring-1 ring-brand-100">
                            <div class="flex items-baseline justify-between">
                                <div>
                                    <div class="text-[10px] uppercase tracking-widest text-brand-700 font-extrabold">Grand Total</div>
                                    <div class="text-[10px] text-gray-500 mt-0.5">Amount receivable</div>
                                </div>
                                <div class="font-display font-black text-3xl md:text-4xl bg-gradient-to-br from-brand-700 to-accent-600 bg-clip-text text-transparent tabular-nums">₹1,47,500</div>
                            </div>
                        </div>
                    </div>

                    {{-- Action row --}}
                    <div class="px-6 md:px-7 pb-6 md:pb-7 grid grid-cols-3 gap-2">
                        <button class="px-3 py-2.5 text-sm bg-gray-900 hover:bg-black text-white rounded-lg font-semibold inline-flex items-center justify-center gap-1.5 transition shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            PDF
                        </button>
                        <button class="px-3 py-2.5 text-sm bg-[#25D366] hover:bg-[#1ebe5b] text-white rounded-lg font-semibold inline-flex items-center justify-center gap-1.5 transition shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                            WhatsApp
                        </button>
                        <button class="px-3 py-2.5 text-sm bg-accent-500 hover:bg-accent-600 text-white rounded-lg font-semibold inline-flex items-center justify-center gap-1.5 transition shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Email
                        </button>
                    </div>
                </div>
            </div>

            {{-- Floating status pills — corners, no overlap with invoice content --}}
            <div class="hidden md:flex absolute -top-5 -left-5 items-center gap-2.5 px-3.5 py-2.5 bg-white rounded-xl shadow-xl ring-1 ring-gray-100 animate-float z-10" style="animation-delay:-1s;">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-money-400 to-money-600 text-white flex items-center justify-center font-bold shadow-sm">₹</div>
                <div class="text-xs">
                    <div class="font-bold text-gray-900 font-display tabular-nums">₹<span data-countup="1250000" data-format="inr">0</span></div>
                    <div class="text-gray-500 text-[11px]">collected this month</div>
                </div>
            </div>

            <div class="hidden md:flex absolute -top-5 -right-5 items-center gap-2.5 px-3.5 py-2.5 bg-white rounded-xl shadow-xl ring-1 ring-gray-100 animate-float z-10" style="animation-delay:-4s;">
                <div class="w-9 h-9 rounded-lg bg-[#25D366]/10 text-[#25D366] flex items-center justify-center shadow-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                </div>
                <div class="text-xs">
                    <div class="font-bold text-gray-900">Reminder sent</div>
                    <div class="text-gray-500 text-[11px]">via WhatsApp · just now</div>
                </div>
            </div>

            <div class="hidden md:flex absolute -bottom-5 -left-5 items-center gap-2.5 px-3.5 py-2.5 bg-white rounded-xl shadow-xl ring-1 ring-gray-100 animate-float z-10" style="animation-delay:-6s;">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-saffron-400 to-accent-500 text-white flex items-center justify-center shadow-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2.39 4.84L18 8l-4 3.9.94 5.48L10 14.77 5.06 17.38 6 11.9 2 8l5.61-1.16z"/></svg>
                </div>
                <div class="text-xs">
                    <div class="font-bold text-gray-900">GST 2.0 compliant</div>
                    <div class="text-gray-500 text-[11px]">HSN, SAC, e-invoice ready</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust bar -->
<section class="relative bg-gray-50 py-10 border-y border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-10">
            @foreach ([
                ['n' => '60 s', 'l' => 'Your first invoice'],
                ['n' => '36',   'l' => 'States & UTs supported'],
                ['n' => '₹0',   'l' => 'While in beta'],
                ['n' => '100%', 'l' => 'GST-compliant format'],
            ] as $t)
                <div class="text-center md:text-left">
                    <div class="text-3xl md:text-4xl font-display font-extrabold bg-gradient-to-br from-brand-700 to-accent-600 bg-clip-text text-transparent">{{ $t['n'] }}</div>
                    <div class="text-gray-600 text-sm mt-1">{{ $t['l'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- How it works — 3 steps from signup to first invoice -->
<section class="relative py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center">
            <span class="inline-block px-3 py-1 rounded-full bg-accent-50 text-accent-700 text-xs font-bold uppercase tracking-wider">3-step setup</span>
            <h2 class="mt-4 font-display text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                Your first invoice in <span class="text-brand-700">60 seconds</span>.
            </h2>
            <p class="mt-3 text-gray-600">Sign up and add your business once — every invoice after that is 60 seconds flat. No onboarding call, nothing to learn.</p>
        </div>

        <div class="mt-14 grid md:grid-cols-3 gap-6 md:gap-8 relative">
            {{-- Connecting line on desktop --}}
            <div class="hidden md:block absolute top-10 left-[16%] right-[16%] h-px bg-gradient-to-r from-brand-200 via-accent-200 to-money-200 -z-0"></div>

            @foreach ([
                ['n' => '01', 'title' => 'Sign up', 'desc' => 'Just email and password. No credit card, no company docs.', 'gradient' => 'from-brand-500 to-brand-700', 'ring' => 'ring-brand-200', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                ['n' => '02', 'title' => 'Add your business (one-time)', 'desc' => 'Paste GSTIN, pick state, upload logo. Letterhead auto-generates on every invoice.', 'gradient' => 'from-accent-500 to-saffron-500', 'ring' => 'ring-accent-200', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                ['n' => '03', 'title' => 'Issue invoice — 60 seconds', 'desc' => 'Pick a customer, type one line item, hit Finalize. PDF is ready to WhatsApp.', 'gradient' => 'from-money-500 to-money-700', 'ring' => 'ring-money-200', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ] as $step)
                <div class="relative bg-white rounded-2xl p-6 md:p-8 ring-1 {{ $step['ring'] }} shadow-sm hover:shadow-card transition-all duration-300 hover:-translate-y-1 z-10">
                    <div class="flex items-start gap-4">
                        <div class="relative w-20 h-20 shrink-0 rounded-2xl bg-gradient-to-br {{ $step['gradient'] }} flex items-center justify-center text-white shadow-md">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $step['icon'] }}"/>
                            </svg>
                            <span class="absolute -top-2 -right-2 w-8 h-8 rounded-full bg-white ring-2 {{ $step['ring'] }} flex items-center justify-center text-gray-900 font-display font-extrabold text-sm">{{ $step['n'] }}</span>
                        </div>
                    </div>
                    <h3 class="mt-5 font-display text-lg font-bold text-gray-900">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $step['desc'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-12 text-center">
            <a href="{{ route('register') }}" class="group inline-flex items-center justify-center px-7 py-3.5 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-xl shadow-brand transition">
                Start free — no card needed
                <svg class="w-5 h-5 ml-2 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
            </a>
        </div>
    </div>
</section>

<!-- Features -->
<section id="features" class="py-24 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <span class="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-bold uppercase tracking-wider">What's inside</span>
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">
                Everything you need. <span class="text-brand-700">Nothing you don't.</span>
            </h2>
            <p class="mt-4 text-lg text-gray-600">No bloated ERP. No spreadsheet gymnastics. Just the core tools to bill customers and get paid — the way India does business.</p>
        </div>

        <div class="mt-14 grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ([
                ['gr' => 'from-brand-500 to-brand-800', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title' => 'GST auto-detection', 'body' => "CGST+SGST for intra-state, IGST for inter-state. Picks up from customer's state automatically — no manual math."],
                ['gr' => 'from-accent-500 to-saffron-600', 'icon' => 'M9 17v-2a4 4 0 014-4h2m-4-4V3m0 0L8 6m3-3l3 3M4 19h16a1 1 0 001-1v-7a1 1 0 00-1-1h-3.586a1 1 0 00-.707.293l-1.414 1.414a1 1 0 01-.707.293H9.414a1 1 0 01-.707-.293L7.293 10.293A1 1 0 006.586 10H3a1 1 0 00-1 1v7a1 1 0 001 1z', 'title' => 'One-click PDF', 'body' => 'Letterhead, logo, signature, HSN/SAC, amount in words (Indian format). Download or print — always pixel-perfect.'],
                ['gr' => 'from-money-500 to-money-700', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'title' => 'Draft → Final workflow', 'body' => 'Edit drafts as much as you want. Finalize to lock the number and make it legally issued. Atomic numbering, no duplicates.'],
                ['gr' => 'from-brand-600 to-accent-500', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Partial payments', 'body' => 'Record advance payments, track balance at a glance. Status moves from Final → Partially paid → Paid as you go.'],
                ['gr' => 'from-saffron-500 to-accent-700', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'title' => 'Customer book', 'body' => 'Save customer details once — GSTIN, address, state. Reuse across invoices. Auto-fills the GST tax mode based on state.'],
                ['gr' => 'from-brand-700 to-money-600', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'title' => 'Payment reminders', 'body' => 'Auto-email nudges at 0 / 3 / 7 / 15 / 30 days past due, or send a one-tap WhatsApp follow-up. Receipts are generated the moment payment is recorded.'],
            ] as $f)
                <div class="group relative bg-white rounded-2xl p-6 ring-1 ring-gray-100 hover:ring-brand-200 shadow-sm hover:shadow-card transition">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $f['gr'] }} flex items-center justify-center text-white shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                    </div>
                    <h3 class="mt-5 text-lg font-bold text-gray-900">{{ $f['title'] }}</h3>
                    <p class="mt-2 text-gray-600 leading-relaxed">{{ $f['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Excel vs Apna Invoice — addresses the #1 "competitor" for Indian SMEs -->
<section class="py-24 bg-gradient-to-b from-white to-gray-50 relative overflow-hidden">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-50 text-red-700 text-xs font-bold uppercase tracking-wider ring-1 ring-red-100">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> The Excel Tax
            </span>
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">
                Still billing in <span class="line-through decoration-red-400 decoration-4">Excel</span>? <br class="hidden md:block">
                There's a faster way.
            </h2>
            <p class="mt-5 text-lg text-gray-600">Most Indian SMEs still copy-paste an old invoice, fiddle with GST math, and email the file. Here's what you're losing every month.</p>
        </div>

        <div class="mt-14 grid md:grid-cols-2 gap-6 md:gap-8 items-stretch">
            {{-- Excel column --}}
            <div class="relative bg-white rounded-2xl p-7 md:p-9 ring-1 ring-red-100 shadow-sm">
                <div class="absolute -top-3 left-7 inline-flex items-center gap-1.5 px-3 py-1 bg-red-500 text-white text-xs font-bold uppercase tracking-wider rounded-full shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    Excel / WhatsApp PDFs
                </div>
                <h3 class="mt-2 font-display text-xl font-extrabold text-gray-900">The way most of India still bills</h3>
                <ul class="mt-6 space-y-4">
                    @foreach ([
                        'Manual CGST/SGST/IGST math — one wrong state and the whole invoice is non-compliant',
                        'No HSN/SAC codes handy — Google each line, paste, hope it\'s right',
                        'Amount in words written by hand — "lakh" vs "lac" arguments with the CA every month',
                        'Invoice numbers reset wrong at FY-end — GST officer flags it in audit',
                        'Version chaos: invoice_final_v3_FINAL.xlsx — nobody knows which one was sent',
                        'Can\'t WhatsApp the file directly — customers get a broken .xlsx preview',
                    ] as $pain)
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 w-5 h-5 rounded-full bg-red-50 text-red-500 flex items-center justify-center flex-shrink-0 ring-1 ring-red-100">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </div>
                            <span class="text-sm text-gray-700 leading-relaxed">{{ $pain }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Apna Invoice column --}}
            <div class="relative bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 rounded-2xl p-7 md:p-9 ring-1 ring-brand-600 shadow-brand text-white">
                <div class="absolute inset-0 rounded-2xl overflow-hidden pointer-events-none">
                    <div class="absolute -top-24 -right-24 w-64 h-64 bg-accent-400 rounded-full blur-3xl opacity-20"></div>
                </div>
                <div class="absolute -top-3 left-7 inline-flex items-center gap-1.5 px-3 py-1 bg-money-500 text-white text-xs font-bold uppercase tracking-wider rounded-full shadow-sm z-10">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    Apna Invoice
                </div>
                <h3 class="relative mt-2 font-display text-xl font-extrabold">Built for the way India bills</h3>
                <ul class="relative mt-6 space-y-4">
                    @foreach ([
                        'Same-state? Auto CGST+SGST. Inter-state? Auto IGST. Zero math on your end.',
                        'HSN / SAC codes pre-loaded — start typing, pick from the list. Every line is compliant.',
                        'Amount in words in proper Indian format — "Rupees One Lakh Twenty-Five Thousand Only".',
                        'Auto FY-reset numbering: ACME/2025-26/0001 → ACME/2026-27/0001 on 1st April.',
                        'One source of truth — every draft, edit, and revision is tracked with version history.',
                        'One-click WhatsApp share with a preview-ready PDF. Customer opens it on their phone.',
                    ] as $win)
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 w-5 h-5 rounded-full bg-money-400/30 text-money-200 flex items-center justify-center flex-shrink-0 ring-1 ring-money-300/30">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-sm text-brand-50 leading-relaxed">{{ $win }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="relative mt-7 pt-6 border-t border-white/10">
                    <a href="{{ route('register') }}" class="group inline-flex items-center gap-2 text-accent-300 hover:text-accent-200 font-bold text-sm transition">
                        Switch to Apna Invoice — it's free
                        <svg class="w-4 h-4 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5-5 5M5 12h13"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="py-24 bg-gradient-to-br from-brand-950 via-brand-900 to-brand-800 text-white relative overflow-hidden">
    <div class="absolute inset-0 bg-grid-soft bg-grid-soft opacity-[0.08]"></div>
    <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-accent-500 rounded-full blur-3xl opacity-20"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-accent-300 text-xs font-bold uppercase tracking-wider">3 Easy Steps</span>
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold tracking-tight">Bill a customer in 60 seconds.</h2>
        </div>
        <div class="mt-14 grid md:grid-cols-3 gap-6 md:gap-8">
            @foreach ([
                ['n' => '01', 't' => 'Set up your business', 'b' => 'Company name, GSTIN, logo, default terms. Uploading takes a minute.'],
                ['n' => '02', 't' => 'Add your customer', 'b' => 'Save their details once — we auto-fill them in every future invoice.'],
                ['n' => '03', 't' => 'Issue and share', 'b' => 'Fill line items, click Finalize, download PDF. Done.'],
            ] as $i => $s)
                <div class="relative">
                    <div class="absolute -top-4 -left-4 text-8xl font-black font-display text-white/5 select-none">{{ $s['n'] }}</div>
                    <div class="relative bg-white/5 backdrop-blur-sm rounded-2xl p-6 md:p-8 ring-1 ring-white/10 h-full">
                        <div class="text-accent-400 font-black text-sm tracking-widest">STEP {{ $s['n'] }}</div>
                        <h3 class="mt-3 text-2xl font-display font-bold">{{ $s['t'] }}</h3>
                        <p class="mt-3 text-brand-100 leading-relaxed">{{ $s['b'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-14 text-center">
            <a href="{{ route('register') }}" class="inline-flex items-center px-8 py-4 bg-accent-500 hover:bg-accent-600 text-accent-900 font-bold rounded-xl shadow-glow transition text-lg">
                Start billing free →
            </a>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
            <span class="inline-block px-3 py-1 rounded-full bg-saffron-50 text-saffron-700 text-xs font-bold uppercase tracking-wider">What users say</span>
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">Loved by India's SMEs & Startups.</h2>
            <p class="mt-4 text-lg text-gray-600">From Mumbai consultancies to Bengaluru agencies, business owners use DST's invoice tool to close the month in hours, not days.</p>
        </div>
        <div class="mt-14 grid md:grid-cols-3 gap-6">
            @foreach ([
                [
                    'n' => 'Priya Sharma', 'r' => 'Founder', 'company' => 'Kavya Design Studio',
                    'city' => 'Mumbai', 'industry' => 'Design · Branding',
                    'q' => 'We went from Excel chaos to clean GST invoices in a week. The state auto-detection saves me 10 minutes on every inter-state invoice.',
                    'metric' => '8 hrs/month saved', 'metric_icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                    'i' => 'PS', 'c' => 'from-brand-500 to-brand-700',
                ],
                [
                    'n' => 'Arjun Menon', 'r' => 'Practicing CA', 'company' => 'Menon & Associates',
                    'city' => 'Kochi', 'industry' => 'Tax · Advisory',
                    'q' => 'Indian-format amount-in-words (lakhs, crores), correct CGST/SGST/IGST logic, FY-reset numbering. This is built by people who actually know Indian compliance.',
                    'metric' => '100% audit-ready', 'metric_icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                    'i' => 'AM', 'c' => 'from-accent-500 to-saffron-600',
                ],
                [
                    'n' => 'Rohit Patel', 'r' => 'Director', 'company' => 'Patel Trading Co.',
                    'city' => 'Surat', 'industry' => 'Textile · Exports',
                    'q' => 'Finally, an invoicing tool that speaks Indian — lakhs, crores, HSN, SAC, state-based GST. Not a rebranded US product with bolted-on GST.',
                    'metric' => '₹50L+ billed/mo', 'metric_icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                    'i' => 'RP', 'c' => 'from-money-500 to-money-700',
                ],
            ] as $t)
                <div class="group relative bg-white rounded-2xl p-6 md:p-8 ring-1 ring-gray-100 shadow-sm hover:shadow-card hover:ring-brand-200 transition-all duration-300 flex flex-col">
                    {{-- Rating stars + metric pill --}}
                    <div class="flex items-center justify-between">
                        <div class="flex gap-0.5 text-saffron-500" aria-label="5 out of 5 stars">
                            @for ($s = 0; $s < 5; $s++)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.967a1 1 0 00.95.69h4.17c.969 0 1.371 1.24.588 1.81l-3.373 2.455a1 1 0 00-.363 1.118l1.287 3.967c.3.922-.755 1.688-1.54 1.118L10.488 15.6a1 1 0 00-1.176 0l-3.37 2.451c-.784.57-1.838-.196-1.539-1.118l1.287-3.967a1 1 0 00-.363-1.118L1.954 9.394c-.783-.57-.38-1.81.588-1.81h4.17a1 1 0 00.95-.69l1.287-3.967z"/></svg>
                            @endfor
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-money-50 text-money-800 text-[11px] font-bold ring-1 ring-money-100">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $t['metric_icon'] }}"/></svg>
                            {{ $t['metric'] }}
                        </span>
                    </div>

                    <p class="mt-5 text-gray-800 text-[15px] md:text-base leading-relaxed flex-1">&ldquo;{{ $t['q'] }}&rdquo;</p>

                    <div class="mt-6 pt-5 border-t border-gray-100 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br {{ $t['c'] }} text-white font-bold flex items-center justify-center text-sm shadow-sm flex-shrink-0">{{ $t['i'] }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-gray-900 truncate">{{ $t['n'] }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $t['r'] }} · {{ $t['company'] }}</div>
                            <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-400">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $t['city'] }}
                                </span>
                                <span class="text-gray-300">·</span>
                                <span>{{ $t['industry'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Pricing -->
<section id="pricing" class="py-24 bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <span class="inline-block px-3 py-1 rounded-full bg-accent-100 text-accent-800 text-xs font-bold uppercase tracking-wider">Beta pricing</span>
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">Free forever. Seriously.</h2>
            <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">Sign up today and get unlimited invoices, customers, and PDF exports — no credit card, no feature locks. When we launch paid tiers, early users keep the free plan.</p>
        </div>

        <div class="mt-12 relative">
            <div class="absolute -inset-4 bg-gradient-to-br from-brand-300 to-accent-300 rounded-3xl blur-3xl opacity-30"></div>
            <div class="relative bg-white rounded-2xl shadow-brand ring-1 ring-gray-100 overflow-hidden md:grid md:grid-cols-2">
                <div class="p-8 md:p-10 bg-gradient-to-br from-brand-900 to-brand-700 text-white">
                    <div class="text-xs font-bold tracking-widest uppercase text-accent-300">Early-bird plan</div>
                    <h3 class="mt-2 font-display text-3xl font-extrabold">DST Invoice · Free</h3>
                    <div class="mt-6 flex items-baseline gap-2">
                        <span class="text-6xl font-black font-display">₹0</span>
                        <span class="text-brand-200 text-sm">/forever</span>
                    </div>
                    <p class="mt-4 text-brand-100 text-sm">For Indian solo professionals, SMEs &amp; startups billing up to 500 invoices/month.</p>
                    <a href="{{ route('register') }}" class="mt-8 block text-center px-6 py-3 bg-accent-500 hover:bg-accent-600 text-accent-900 font-bold rounded-xl transition">Claim free account →</a>
                </div>
                <div class="p-8 md:p-10">
                    <div class="text-xs font-bold tracking-widest uppercase text-brand-700">What's included</div>
                    <ul class="mt-4 space-y-3">
                        @foreach ([
                            'Unlimited GST invoices (CGST/SGST/IGST)',
                            'Unlimited customers, products & HSN/SAC codes',
                            'Logo, signature, letterhead',
                            'Ink-saver PDF export & print-ready view',
                            'Email, WhatsApp &amp; public-link sharing',
                            'Payment reminders &amp; receipt numbering',
                            'All 36 Indian states & UTs pre-loaded',
                            'Priority email support',
                        ] as $i)
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-money-100 text-money-700 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-gray-700">{{ $i }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Built for India — trust & compliance strip -->
<section class="py-20 bg-white relative overflow-hidden">
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-saffron-200 to-transparent"></div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-money-200 to-transparent"></div>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-saffron-50 text-saffron-700 text-xs font-bold uppercase tracking-wider ring-1 ring-saffron-100">
                <span class="relative flex w-2 h-2">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-saffron-400 opacity-60"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-saffron-500"></span>
                </span>
                Made in India · Built for Bharat
            </span>
            <h2 class="mt-4 font-display text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                Your data stays in India. <span class="bg-gradient-to-r from-saffron-500 via-brand-600 to-money-600 bg-clip-text text-transparent">Always.</span>
            </h2>
            <p class="mt-4 text-gray-600">Hosted on Indian servers, built under the DPDP Act, designed for GST 2.0. Zero offshore data transfers.</p>
        </div>

        <div class="mt-12 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
            @foreach ([
                [
                    'label' => 'GST 2.0 Ready', 'sub' => 'HSN/SAC + FY numbering',
                    'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                    'card' => 'hover:ring-money-200',
                    'tile' => 'bg-money-50 text-money-600 ring-money-100',
                ],
                [
                    'label' => 'DPDP Compliant', 'sub' => 'Indian data residency',
                    'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
                    'card' => 'hover:ring-brand-200',
                    'tile' => 'bg-brand-50 text-brand-600 ring-brand-100',
                ],
                [
                    'label' => '36 States & UTs', 'sub' => 'Every jurisdiction pre-loaded',
                    'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
                    'card' => 'hover:ring-accent-200',
                    'tile' => 'bg-accent-50 text-accent-600 ring-accent-100',
                ],
                [
                    'label' => '₹ in Lakhs & Crores', 'sub' => 'Indian number system',
                    'icon' => 'M11 11V9a2 2 0 00-2-2h-2M9 13h6m-3-7v1m0 8v1m-6-1h12a2 2 0 002-2V8a2 2 0 00-2-2H6a2 2 0 00-2 2v4a2 2 0 002 2z',
                    'card' => 'hover:ring-saffron-200',
                    'tile' => 'bg-saffron-50 text-saffron-600 ring-saffron-100',
                ],
            ] as $badge)
                <div class="group relative bg-white rounded-2xl p-5 md:p-6 ring-1 ring-gray-100 {{ $badge['card'] }} hover:shadow-card transition-all duration-300 hover:-translate-y-0.5 text-center">
                    <div class="mx-auto w-12 h-12 rounded-xl {{ $badge['tile'] }} flex items-center justify-center ring-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $badge['icon'] }}"/>
                        </svg>
                    </div>
                    <div class="mt-3 font-display font-extrabold text-gray-900 text-sm md:text-base">{{ $badge['label'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 leading-snug">{{ $badge['sub'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-24 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <span class="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-bold uppercase tracking-wider">FAQ</span>
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">Questions? We have answers.</h2>
        </div>
        <div class="mt-12 space-y-3">
            @foreach ($faqs as $faq)
                <details class="group rounded-xl bg-gray-50 hover:bg-white ring-1 ring-gray-200 hover:ring-brand-200 transition">
                    <summary class="flex items-center justify-between p-5 cursor-pointer font-semibold text-gray-900">
                        <span>{{ $faq['q'] }}</span>
                        <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-5 text-gray-600 leading-relaxed">{{ $faq['a'] }}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>

<!-- Final CTA -->
<section class="py-24 bg-gradient-to-br from-brand-900 via-brand-800 to-accent-900 text-white relative overflow-hidden">
    <div class="absolute inset-0 bg-grid-soft bg-grid-soft opacity-[0.08]"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight">Your next invoice is 60 seconds away.</h2>
        <p class="mt-4 text-lg text-brand-100 max-w-2xl mx-auto">Join the Indian businesses using DST Invoice to ship invoices, not chase them. Free during beta.</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('register') }}" class="inline-flex items-center px-8 py-4 bg-white text-brand-900 font-bold rounded-xl hover:bg-accent-100 transition text-lg shadow-glow">
                Create free account →
            </a>
            <a href="{{ route('login') }}" class="inline-flex items-center px-8 py-4 bg-white/10 backdrop-blur-sm ring-1 ring-white/20 text-white font-semibold rounded-xl hover:bg-white/20 transition text-lg">
                Log in
            </a>
        </div>
    </div>
</section>

<x-site-footer variant="full" />

</body>
</html>
