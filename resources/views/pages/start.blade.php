@php
    // Ads landing page. The same sections as the home page, from
    // partials/landing, with nothing that leads away from signing up: no
    // site navigation, no blog or feature links, no WhatsApp float. Kept out
    // of the index; the canonical points at the home page.
    //
    // ?for= picks the headline so it matches the ad that was clicked. Only
    // the keys below are honoured; anything else shows the default.
    $variants = [
        'shop' => [
            'h1' => 'Free billing software for shops, with GST',
            'sub' => 'Bill walk-in customers at the counter, print on 80 mm paper, and take UPI with the QR on the bill. Free during beta, no card needed.',
            'audience' => 'shop',
        ],
        'wholesale' => [
            'h1' => 'Free GST billing for wholesalers and distributors',
            'sub' => 'IGST is charged when goods go to another state. Add the transporter and e-way bill number, and see who still owes you. Free during beta, no card needed.',
            'audience' => 'wholesale',
        ],
        'services' => [
            'h1' => 'Free GST invoices for service businesses',
            'sub' => 'Bill with SAC codes, record the TDS your client deducts, and send the invoice on WhatsApp with a UPI QR to pay. Free during beta, no card needed.',
            'audience' => 'services',
        ],
        'freelancer' => [
            'h1' => 'Free GST invoice maker for freelancers',
            'sub' => 'Bill a client in another state with IGST, record the TDS they deduct, and get paid by UPI from the QR on the invoice. Free during beta, no card needed.',
            'audience' => 'services',
        ],
        'ca' => [
            'h1' => 'Free GST billing for your clients, with GSTR-1 ready for you',
            'sub' => 'Your clients issue invoices and enter purchase bills. You log in as their accountant and download the GSTR-1 JSON, the 2B match and GSTR-3B figures. Free during beta.',
            'audience' => 'ca',
        ],
        'gst-invoice' => [
            'h1' => 'Make GST invoices for free',
            'sub' => 'CGST and SGST, or IGST, worked out from the place of supply. HSN/SAC on every line, your logo, and a PDF to share on WhatsApp. Free during beta, no card needed.',
        ],
        'billing-software' => [
            'h1' => 'Free billing software with GST, purchases and stock',
            'sub' => 'Invoices, purchase bills, stock and bank matching in one account, on your phone or computer. Free during beta, no card needed.',
        ],
        'gstr1' => [
            'h1' => 'GSTR-1 JSON straight from your invoices',
            'sub' => 'Issue GST invoices through the month. When the return is due, download the GSTR-1 JSON and upload it on the GST portal, or send it to your CA. Free during beta, no card needed.',
        ],
    ];

    $for = request()->query('for');
    $variant = is_string($for) && isset($variants[$for]) ? $variants[$for] : null;

    $h1 = $variant['h1'] ?? 'Free GST billing software for Indian businesses';
    $sub = $variant['sub'] ?? 'GST worked out from the place of supply. Bills shared on WhatsApp with a UPI QR to pay. GSTR-1 ready for the portal. Free during beta, no card needed.';
    $activeAudience = $variant['audience'] ?? null;

    // Sign-up happens on this page (the form in the hero). The ad click and
    // campaign tags on the URL are kept server-side by CaptureAttribution
    // and saved on the new account, so the form carries nothing extra.
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo
        :title="$h1"
        description="Free GST billing software for Indian businesses. GST invoices, WhatsApp share with UPI QR, GSTR-1 JSON, purchases, stock and bank matching. Free during beta, no card needed."
        :url="rtrim(config('app.url'), '/') . '/'"
        :noindex="true" />

    <link rel="preload" href="{{ asset('brand/apna-invoice-logo-sm.webp') }}" as="image" type="image/webp" fetchpriority="high">
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Carries the Google Ads tag and the sign-up conversion. --}}
    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900 overflow-x-hidden">

<a href="#main" class="skip-link">Skip to content</a>

{{-- Logo and Log in only. The logo is not a link: this page has one job. --}}
<header class="bg-white border-b border-gray-200">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <x-brand-logo class="h-9 w-auto block md:h-10" />
        @auth
            <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-brand-700 px-4 text-[15px] font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Go to dashboard</a>
        @else
            <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-[15px] font-semibold text-gray-800 ring-1 ring-gray-300 transition hover:bg-gray-50 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Log in</a>
        @endauth
    </div>
</header>

<main id="main">
    @include('partials.landing.hero', ['h1' => $h1, 'sub' => $sub, 'formPrefix' => 'hero-'])
    @include('partials.landing.audience', ['activeAudience' => $activeAudience])
    @include('partials.landing.usp')
    @include('partials.landing.included')
    @include('partials.landing.steps')
    @include('partials.landing.final-cta')
</main>

<footer class="border-t border-gray-200 bg-gray-50">
    <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p>&copy; {{ now()->year }} {{ config('seo.organization.legal_name', 'Datasoft Technologies') }}. Made in India.</p>
        <nav aria-label="Legal" class="flex gap-5">
            <a href="{{ route('pages.privacy') }}" class="inline-flex min-h-[44px] items-center hover:text-brand-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Privacy</a>
            <a href="{{ route('pages.terms') }}" class="inline-flex min-h-[44px] items-center hover:text-brand-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Terms</a>
        </nav>
    </div>
</footer>

@include('partials.cookie-banner')
@include('partials.landing.sticky-cta')

{{-- Scripts pushed by components, e.g. the captcha in the sign-up form. --}}
@stack('scripts')

</body>
</html>
