@php
    $faqs = [
        ['q' => 'How to use Apna Invoice, step by step?', 'a' => "1. Sign up free at apnainvoice.com. Continue with Google, or enter your mobile, email and password and verify the one-time code we send. No credit card.\n\n2. Add your business once: name, GSTIN, address, state. The state decides CGST + SGST (same state) or IGST (different state) automatically.\n\n3. Add your first customer. Name and state are required; GSTIN is optional for B2C. You can also click '+ New' on any invoice to add a customer inline (no page refresh, no lost data).\n\n4. Click 'New invoice', pick the customer, then type description, HSN/SAC, qty, rate and GST%. Defaults to GST 18%, qty 1.\n\n5. Click 'Issue'. The invoice locks, gets a permanent number (auto-resets every 1 April), and the PDF is generated. Goods invoices auto-print 3 copies (Original, Duplicate-Transporter, Triplicate-Supplier) per CGST Rule 48.\n\n6. Share on WhatsApp (pre-filled message), Email (PDF attached), or copy a 30-day public link. UPI QR is included if you've added a UPI ID.\n\n7. Record Receipt when received: full or part payment, with TDS support. The receipt PDF is generated automatically.\n\n8. At month-end, download the GSTR-1 CSV, GSTR-3B summary, receivables aging report, or a full ZIP backup from the dashboard.\n\nTip: Install Apna Invoice as a phone app via Chrome's 'Install' menu. It works like a native app."],
        ['q' => 'How can I create a GST invoice online for free in India?', 'a' => "Sign up on Apna Invoice (continue with Google or verify your mobile, no card required), add your business GSTIN and state once, then create your first GST invoice in about 60 seconds. The tool auto-calculates CGST + SGST for intra-state and IGST for inter-state supplies, supports HSN/SAC codes, and exports a GST-compliant tax invoice as PDF. It's completely free for unlimited invoices during beta."],
        ['q' => 'Is Apna Invoice the best free GST invoice generator for freelancers and small businesses in India?', 'a' => "We're built specifically for Indian freelancers, MSMEs, SMEs, startups, small shops and CAs. Apna Invoice is fully online (no installation), zero-cost, with India-first defaults: Indian numbering (lakhs and crores), GST slabs pre-loaded, FY-reset invoice numbers, UPI QR on every invoice, and one-click WhatsApp share, with no per-invoice or per-user limit."],
        ['q' => 'Does the invoice generator include HSN/SAC code and auto GST calculation?', 'a' => "Yes. Every line item has an HSN/SAC field with a built-in search link to the official GST portal, and you pick the GST rate (0%, 0.10%, 0.25%, 3%, 5%, 12%, 18%, 28%) per item. We auto-split CGST/SGST or IGST based on customer state, round to the paisa, and print the rate on every line as Rule 46 requires."],
        ['q' => 'Can I use this as free billing software for a small shop or MSME?', 'a' => "Yes. Small shops, retail stores, MSMEs and SMEs across India use Apna Invoice as a simple GST billing tool to issue tax invoices, cash memos for over-the-counter sales, and credit notes, and to track payment status. It runs in any browser on any device, with no desktop software to install and no billing-machine hardware needed. Free during beta with unlimited invoices."],
        ['q' => 'Can I download a GST invoice format for free?', 'a' => "Every invoice you create on Apna Invoice exports to a CBIC-compliant GST tax invoice PDF, five professional template styles (Classic Navy, Executive Maroon, Minimal Slate, Mercantile Forest, Heritage Burgundy). The PDF includes GSTIN, HSN/SAC, CGST/SGST/IGST split, place of supply, amount in words, signature block and your bank/UPI details. No template downloads to fiddle with, just create and download."],
        ['q' => 'Are these invoices GST-compliant?', 'a' => "Yes. We include GSTIN, HSN/SAC codes, place of supply, CGST/SGST or IGST split, invoice number, and amount in words (Indian format, in lakhs and crores). The format aligns with CBIC's prescribed tax invoice requirements."],
        ['q' => 'Do I need to know the GST rate for each item?', 'a' => 'Yes, but we pre-load the standard slabs (0%, 0.10%, 0.25%, 3%, 5%, 12%, 18%, 28%). You pick one per line item; the system handles CGST/SGST or IGST math based on customer state.'],
        ['q' => "What's the difference between draft and final?", 'a' => 'Drafts are editable and have no invoice number yet. Once you issue it, the invoice gets a permanent number (e.g. INV-0001), becomes read-only, and is considered legally issued. You can still mark payments against issued invoices.'],
        ['q' => 'Can I issue credit notes against an issued invoice?', 'a' => "Yes. Credit notes follow the GST Section 34 format: reason code, reference to the original invoice, and CGST/SGST or IGST reversal. They flow through your books and are ready for GSTR-1 reporting."],
        ['q' => 'I already maintain books in spreadsheets or another tool. Is it hard to switch?', 'a' => "No setup migration required for most users. Add your company once, paste in customers as you bill them, and carry on. You keep your existing invoice numbering series (we don't force ours). You can also export your Apna Invoice data at any time as a ZIP of CSV files, so you're never locked in."],
        ['q' => 'Can I bill international clients?', 'a' => 'Not yet. Apna Invoice is built for Indian domestic GST invoicing in INR (₹) only. Export under LUT/Bond, SEZ supplies, and multi-currency billing are intentionally out of scope while we stay focused on MSMEs, SMEs, startups and freelancers billing within India.'],
        ['q' => 'What happens to my data?', 'a' => 'It lives in your account, on our servers in India. You can export your invoices and customer data any time. We never sell data to third parties.'],
        ['q' => 'Who builds this?', 'a' => 'Datasoft Technologies (DST) is an Indian software company focused on practical tools for modern businesses. This product is free during beta while we grow.'],
    ];

    $appUrl = rtrim(config('app.url'), '/');
    $siteName = config('seo.name');

    $jsonLd = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $appUrl . '#organization',
            'name' => config('seo.organization.name'),
            'legalName' => config('seo.organization.legal_name'),
            'url' => config('seo.organization.url'),
            // sameAs is how Google ties the social profiles to this business
            // in its Knowledge Panel, so the footer links are declared here too.
            'sameAs' => array_values(array_filter([
                config('seo.organization.url'),
                config('seo.social.facebook'),
                config('seo.social.instagram'),
                config('seo.social.linkedin'),
            ])),
            'logo' => $appUrl . config('seo.organization.logo'),
            'foundingLocation' => ['@type' => 'Country', 'name' => 'India'],
            'areaServed' => 'IN',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => config('seo.organization.locality', 'Delhi NCR'),
                'addressRegion' => config('seo.organization.region', 'Delhi'),
                'addressCountry' => 'IN',
            ],
            'telephone' => config('seo.contact.phone_e164'),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'telephone' => config('seo.contact.phone_e164'),
                'email' => 'support@datasofttechnologies.com',
                'areaServed' => 'IN',
                'availableLanguage' => ['English', 'Hindi'],
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $appUrl . '#website',
            'name' => $siteName,
            'url' => $appUrl,
            'inLanguage' => 'en-IN',
            'publisher' => ['@id' => $appUrl . '#organization'],
            // No SearchAction: the site has no internal search endpoint, and a
            // non-functional sitelinks-searchbox target violates Google's
            // guideline (the feature was deprecated in 2024 regardless).
        ],
        // FAQPage schema now lives on the dedicated /faq page, and HowTo on
        // /how-to-use - those pages own that structured data so it isn't
        // duplicated across two URLs. The home page keeps a visible FAQ.
        // WebApplication schema, declares the page hosts a free GST calculator
        // tool. Targets rich-result eligibility for "free GST calculator",
        // "GST calculator India", "CGST SGST IGST calculator" queries.
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => 'Free GST Calculator',
            'alternateName' => [
                'GST Calculator India',
                'Online GST Calculator',
                'CGST SGST IGST Calculator',
                'Reverse GST Calculator',
                'Free GST Tax Calculator',
            ],
            'url' => $appUrl . '/#gst-calculator',
            'applicationCategory' => 'FinanceApplication',
            'operatingSystem' => 'Any (web-based)',
            'browserRequirements' => 'Requires JavaScript',
            'inLanguage' => 'en-IN',
            'isAccessibleForFree' => true,
            'description' => 'Free online GST calculator for India, compute CGST, SGST and IGST on any amount instantly. Supports intra-state and inter-state supplies, plus tax-inclusive and tax-exclusive modes (reverse GST). Works for all standard slabs: 0%, 5%, 12%, 18%, 28%. No login, no signup, runs in your browser.',
            'featureList' => [
                'Calculate CGST + SGST for intra-state supplies',
                'Calculate IGST for inter-state supplies',
                'Reverse-calculate GST from a tax-inclusive price',
                'Quick presets: ₹1K / ₹10K / ₹50K / ₹1L / ₹5L',
                'WhatsApp share of the calculation result',
                'No login or signup required',
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'INR',
            ],
            'creator' => [
                '@type' => 'Organization',
                'name' => config('seo.organization.name'),
                'url' => config('seo.organization.url'),
            ],
        ],
        // SoftwareApplication schema, declares the invoicing/billing tool
        // itself. Targets rich-result eligibility for "online invoice generator",
        // "free bill generator", "GST bill generator", "invoice generator India".
        // Single canonical node (@id) - Google drops an entity described by two
        // conflicting SoftwareApplication nodes, so this is the only one.
        [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            '@id' => $appUrl . '#software',
            'name' => 'Apna Invoice, Free GST Invoice & Bill Generator',
            'alternateName' => [
                'Online Invoice Generator',
                'Free Bill Generator',
                'GST Invoice Generator India',
                'GST Bill Generator',
                'Invoice Generator India',
                'Online Bill Generator',
                'Free Invoice Maker',
            ],
            'url' => $appUrl,
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'BillingAndInvoicing',
            'operatingSystem' => 'Any (web-based · PWA installable)',
            'browserRequirements' => 'Requires JavaScript',
            'inLanguage' => 'en-IN',
            'isAccessibleForFree' => true,
            'description' => 'Free online invoice generator and bill generator for Indian businesses. Issue GST-compliant tax invoices, bills, credit notes and receipts with auto CGST/SGST/IGST split, HSN/SAC, UPI QR and WhatsApp share. CGST Rule 46/49 compliant, GSTR-1 export, multi-copy printing per Rule 48 for goods. Built for SMEs, MSMEs, startups, freelancers, shops and CAs.',
            'featureList' => [
                'Free online GST invoice generator (no signup for the calculator)',
                'Bill generator for goods and services with auto CGST/SGST/IGST',
                'Quotation → Invoice in one click with separate FY-aware numbering',
                'Credit notes per Section 34 of the CGST Act',
                'Cash memos for over-the-counter sales',
                'GSTR-1 + GSTR-3B export · Receivables aging report',
                'WhatsApp + email + 30-day signed public-link sharing',
                'Multi-copy invoice PDFs (Original / Duplicate / Triplicate) per CGST Rule 48',
                'PWA, installable on any phone or desktop',
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'INR',
                'description' => 'Free during beta · Unlimited invoices, customers and quotations',
            ],
            'creator' => [
                '@type' => 'Organization',
                'name' => config('seo.organization.name'),
                'url' => config('seo.organization.url'),
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo
        title="Free GST Invoice Generator India"
        description="Free GST invoice generator for India. Automatic CGST, SGST and IGST, HSN codes, UPI QR and WhatsApp share in 60 seconds. Unlimited invoices, no card needed."
        keywords="free GST calculator, free GST calculator India, online GST calculator, CGST SGST IGST calculator, GST inclusive calculator, GST exclusive calculator, reverse GST calculator, GST calculator 5 12 18 28, free GST billing software India, online GST invoice generator, GST bill maker, GSTR-1 export, HSN SAC search, GST invoice format India, free invoicing app for SMEs, MSME billing software, freelancer invoice India, shop billing software, composition dealer Bill of Supply, audit defensible invoicing, GSTR-3B summary, UPI QR invoice, WhatsApp invoice share"
        type="website"
        :json-ld="$jsonLd" />
    {{-- PWA manifest + theme-color now come from <x-seo>. --}}
    <meta name="theme-color" content="#0f766e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Apna Invoice">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">

    {{-- Preload the brand logo, it's the LCP element on the landing header. --}}
    <link rel="preload" href="{{ asset('brand/apna-invoice-logo-sm.webp') }}" as="image" type="image/webp" fetchpriority="high">
    {{-- Fonts: warm the connection (crossorigin is REQUIRED - font files are
         fetched with CORS, so a plain preconnect warms the wrong connection),
         then load the stylesheet non-render-blocking. display=swap already
         paints text immediately in a fallback face and swaps the web font in
         on load, so making the request async costs nothing and frees up FCP. --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    <link rel="preload" as="style" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|plus-jakarta-sans:600,700,800&display=swap">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|plus-jakarta-sans:600,700,800&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|plus-jakarta-sans:600,700,800&display=swap"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900 overflow-x-hidden">

<!-- Announcement bar -->
<div class="bg-gradient-to-r from-brand-900 via-brand-800 to-brand-900 text-white text-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-center gap-2.5 flex-wrap">
        <span class="inline-flex flex-col w-4 h-3 rounded-[1px] overflow-hidden ring-1 ring-white/30 shrink-0" aria-label="Made in India">
            <span class="block w-full h-1/3 bg-[#ff9933]"></span>
            <span class="block w-full h-1/3 bg-white"></span>
            <span class="block w-full h-1/3 bg-[#138808]"></span>
        </span>
        <span><strong>Made for India's SMEs &amp; MSMEs.</strong> Unlimited bills, quotations and customers. <a href="{{ route('register') }}" class="underline font-semibold hover:text-accent-300">Get started free →</a></span>
    </div>
</div>

<!-- Header -->
<header class="sticky top-0 z-50 bg-white/85 backdrop-blur-md border-b border-gray-200 transition-shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-3 py-3 min-w-0 flex-shrink" aria-label="Apna Invoice home">
            <span class="inline-block">
                <x-brand-logo class="h-10 w-auto block" />
            </span>
        </a>
        <nav class="flex items-center gap-2 md:gap-6 text-sm flex-shrink-0">
            <a href="{{ route('pages.features') }}" class="hidden lg:inline-block text-base text-gray-700 hover:text-brand-700 font-semibold tracking-tight">Features</a>
            <a href="{{ route('pages.how-to-use') }}" class="hidden lg:inline-block text-base text-gray-700 hover:text-brand-700 font-semibold tracking-tight">How to use</a>
            <a href="{{ route('pages.pricing') }}" class="hidden lg:inline-block text-base text-gray-700 hover:text-brand-700 font-semibold tracking-tight">Pricing</a>
            <a href="{{ route('blog.index') }}" class="hidden lg:inline-block text-base text-gray-700 hover:text-brand-700 font-semibold tracking-tight">Blogs</a>
            <a href="{{ route('pages.faq') }}" class="hidden lg:inline-block text-base text-gray-700 hover:text-brand-700 font-semibold tracking-tight">FAQ</a>
            @auth
                <a href="{{ route('dashboard') }}" class="px-3 py-2 md:px-5 md:py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition whitespace-nowrap">Go to dashboard →</a>
            @else
                <a href="{{ route('login') }}" class="text-base text-gray-800 hover:text-brand-700 font-semibold tracking-tight px-2 py-2 md:px-3 whitespace-nowrap">Log in</a>
                <a href="{{ route('register') }}" class="px-3 py-2 md:px-5 md:py-2.5 rounded-lg bg-accent-700 hover:bg-accent-800 text-white font-semibold shadow-sm transition whitespace-nowrap">Signup for Free</a>
            @endauth
        </nav>
    </div>
</header>

<!-- Hero -->
<section class="relative overflow-hidden bg-white">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-12 md:pt-10 md:pb-20 grid lg:grid-cols-12 gap-10 lg:gap-12 items-start lg:items-stretch">

        {{-- ─── Left: marketing copy + features + CTA ─── --}}
        <div class="lg:col-span-7 animate-fade-up bg-white ring-1 ring-gray-200/80 shadow-2xl rounded-2xl overflow-hidden flex flex-col">
            {{-- macOS-style browser window chrome. Pairs with the phone frame on
                 the right so the hero reads as "works on desktop and mobile" and
                 gives the landing page a premium, product-forward feel. --}}
            <div class="relative flex items-center px-5 py-3.5 bg-gray-100/90 border-b border-gray-200">
                {{-- Traffic lights absolutely positioned so the URL pill can sit
                     dead-centre in the FULL bar width (mx-auto otherwise centres
                     it only in the space left of the dots, pushing it right). --}}
                <div class="absolute left-5 top-1/2 -translate-y-1/2 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-[#ff5f57]"></span>
                    <span class="w-3 h-3 rounded-full bg-[#febc2e]"></span>
                    <span class="w-3 h-3 rounded-full bg-[#28c840]"></span>
                </div>
                <div class="mx-auto flex items-center gap-1.5 px-4 py-1 rounded-md bg-white ring-1 ring-gray-200 text-xs text-gray-500 font-medium max-w-[55%] truncate">
                    <svg class="w-3 h-3 text-money-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    apnainvoice.com
                </div>
            </div>
            <div class="p-6 sm:p-8 lg:p-10 flex-1 flex flex-col">

            {{-- Trust pills, three of them deliberately echo the Indian tricolour
                 in reading order: saffron → white (with mini flag) → green. A
                 subtle but unmissable India cue without being kitsch. --}}
            <div class="flex items-center gap-2.5 flex-wrap mb-6">
                {{-- 1. Saffron band → Namaste pill --}}
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-accent-100 ring-1 ring-accent-400 text-accent-900 text-sm font-bold tracking-wide shadow-sm" aria-label="Namaste, welcome">
                    <span class="text-base leading-none" aria-hidden="true">🙏</span>
                    <span>नमस्ते · Welcome</span>
                </span>

                {{-- 2. White band → Made in India pill (clean tricolour, no chakra) --}}
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white ring-1 ring-gray-400 text-gray-900 text-sm font-bold tracking-wide uppercase shadow-sm">
                    <span class="inline-flex flex-col w-[18px] h-[14px] rounded-[2px] overflow-hidden ring-1 ring-gray-300" aria-hidden="true">
                        <span class="block w-full h-1/3 bg-[#ff9933]"></span>
                        <span class="block w-full h-1/3 bg-white"></span>
                        <span class="block w-full h-1/3 bg-[#138808]"></span>
                    </span>
                    Made in India
                </span>

                {{-- 3. Green band → Free during beta pill --}}
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-money-100 ring-1 ring-money-400 text-money-900 text-sm font-bold tracking-wide uppercase shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-money-600 animate-pulse"></span>
                    Free during beta
                </span>
            </div>

            {{-- HEADLINE, matches the marketing brief exactly --}}
            <h1 class="font-display font-extrabold tracking-tight text-gray-900 leading-[1.05]">
                <span class="block text-2xl sm:text-3xl md:text-4xl whitespace-nowrap">Free GST <span class="text-brand-700">Invoicing</span> Software</span>
                <span class="block mt-2 text-xl sm:text-2xl md:text-3xl font-extrabold text-brand-700 leading-tight">
                    Built for <span class="text-accent-700">Indian</span> Businesses
                </span>
            </h1>

            {{-- SR-only H2, keeps SEO weight on the long-form ranking phrases.
                 Combines the high-volume search variants without keyword-stuffing
                 the visible UI: GST invoice generator, online invoice generator,
                 free bill generator, GST bill generator, GST calculator. --}}
            <h2 class="sr-only">Best free online invoice generator, GST invoice generator and bill generator for India. Make GST-compliant tax invoices, bills, credit notes and receipts with auto CGST, SGST and IGST. Includes a free GST calculator (no login).</h2>

            {{-- Hindi tagline, small, restrained, signals India-first without dominating --}}
            <p class="mt-4 text-base sm:text-lg font-bold text-brand-900 leading-snug" lang="hi">
                आपका अपना <span class="text-accent-700">GST बिलिंग साथी</span>। हर invoice
                <span class="px-1.5 rounded text-money-800 bg-money-100">सिर्फ़&nbsp;60 सेकंड</span> में।
            </p>


            {{-- Four feature rows, clean icon + bold title + caption pattern from the brief --}}
            <ul class="mt-6 space-y-3 max-w-2xl" role="list">
                <li class="flex items-start gap-4">
                    <div class="shrink-0 w-12 h-12 rounded-full bg-accent-100 ring-1 ring-accent-200 text-accent-600 flex items-center justify-center" aria-hidden="true">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0 pt-1">
                        <div class="text-lg font-bold text-gray-900 leading-tight">Create Professional GST Invoices</div>
                        <div class="mt-0.5 text-sm text-gray-600">Auto CGST · SGST · IGST · HSN/SAC · place of supply</div>
                    </div>
                </li>
                <li class="flex items-start gap-4">
                    <div class="shrink-0 w-12 h-12 rounded-full bg-accent-100 ring-1 ring-accent-200 text-accent-700 flex items-center justify-center" aria-hidden="true">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="min-w-0 pt-1">
                        <div class="text-lg font-bold text-gray-900 leading-tight">100% Free, No Hidden Charges</div>
                        <div class="mt-0.5 text-sm text-gray-600">Unlimited invoices &amp; customers · no card required · free during beta</div>
                    </div>
                </li>
                <li class="flex items-start gap-4">
                    <div class="shrink-0 w-12 h-12 rounded-full bg-money-100 ring-1 ring-money-200 text-[#25D366] flex items-center justify-center" aria-hidden="true">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    </div>
                    <div class="min-w-0 pt-1">
                        <div class="text-lg font-bold text-gray-900 leading-tight">Share Instantly on WhatsApp</div>
                        <div class="mt-0.5 text-sm text-gray-600">One-click invoice sharing · UPI QR · email · 30-day public link</div>
                    </div>
                </li>
                <li class="flex items-start gap-4">
                    <div class="shrink-0 w-12 h-12 rounded-full bg-brand-100 ring-1 ring-brand-200 text-brand-700 flex items-center justify-center" aria-hidden="true">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                    </div>
                    <div class="min-w-0 pt-1">
                        <div class="text-lg font-bold text-gray-900 leading-tight">Secure, Fast &amp; Easy to Use</div>
                        <div class="mt-0.5 text-sm text-gray-600">India data residency · DPDP Act compliant · works on any device</div>
                    </div>
                </li>
            </ul>
            <p class="mt-4 text-sm text-gray-500">…plus GSTR-1/3B reports, credit notes, quotations, payment tracking and more. <a href="#features" class="text-brand-700 font-semibold hover:underline">See all features</a></p>

            {{-- Honest stat strip. Balances the hero height against the taller
                 calculator card on desktop (kills the dead whitespace) and
                 reinforces the value. Every figure here is factual: free beta,
                 60-second flow, 36 states/UTs preloaded, unlimited invoices. --}}
            <dl class="mt-auto pt-6 border-t border-gray-200 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-5">
                @foreach ([
                    ['n' => '₹0', 'l' => 'Free during beta'],
                    ['n' => '60 sec', 'l' => 'To your first invoice'],
                    ['n' => '36', 'l' => 'States & UTs built in'],
                    ['n' => 'Unlimited', 'l' => 'Invoices & customers'],
                ] as $stat)
                    <div>
                        <dt class="font-display font-extrabold text-xl md:text-2xl text-brand-800 leading-none">{{ $stat['n'] }}</dt>
                        <dd class="mt-1.5 text-xs sm:text-sm text-gray-500 leading-snug">{{ $stat['l'] }}</dd>
                    </div>
                @endforeach
            </dl>

            <p class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                <svg class="w-4 h-4 text-money-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1l7 3v6c0 4.5-3 8.3-7 9-4-.7-7-4.5-7-9V4l7-3zm-.7 12.3L6 10l1.4-1.4 1.9 1.9 4.4-4.4L15 7.5l-5.7 5.8z" clip-rule="evenodd"/></svg>
                Made in India by <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener" class="font-semibold text-gray-700 hover:text-brand-700">Datasoft Technologies</a>. Real humans on WhatsApp &amp; call.
            </p>

            </div>{{-- /window content --}}
        </div>{{-- /mac window --}}

        <!-- Free GST Calculator (no login needed), anchor target for deep links from blog/articles -->
        <div id="free-gst-calculator" class="relative lg:col-span-5 animate-fade-up flex justify-center items-end"
             x-data="{
                amount: 10000,
                rate: 18,
                scope: 'intra',
                mode: 'exclusive',
                get base() {
                    const a = parseFloat(this.amount) || 0;
                    return this.mode === 'inclusive' && this.rate > 0
                        ? a / (1 + this.rate / 100)
                        : a;
                },
                get tax() { return this.base * (this.rate / 100); },
                get cgst() { return this.scope === 'intra' ? this.tax / 2 : 0; },
                get sgst() { return this.scope === 'intra' ? this.tax / 2 : 0; },
                get igst() { return this.scope === 'inter' ? this.tax : 0; },
                get total() { return this.base + this.tax; },
                get halfRate() { return (this.rate / 2).toFixed(this.rate % 2 ? 1 : 0); },
                get taxPct() {
                    const t = this.total || 0;
                    return t > 0 ? Math.min(100, Math.max(0, (this.tax / t) * 100)) : 0;
                },
                fmt(n) { return new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0); },
                presets: [
                    { label: '₹1K',  v: 1000 },
                    { label: '₹10K', v: 10000 },
                    { label: '₹50K', v: 50000 },
                    { label: '₹1L',  v: 100000 },
                    { label: '₹5L',  v: 500000 },
                ],
                get whatsappLink() {
                    const lines = [
                        `*GST Calculation*`,
                        `Amount: ₹${this.fmt(this.base)} (${this.mode === 'inclusive' ? 'extracted from inclusive ₹' + this.fmt(parseFloat(this.amount) || 0) : 'excl. GST'})`,
                        `GST @ ${this.rate}% (${this.scope === 'intra' ? 'intra-state' : 'inter-state'}): ₹${this.fmt(this.tax)}`,
                    ];
                    if (this.scope === 'intra' && this.rate > 0) {
                        lines.push(`  • CGST @ ${this.halfRate}%: ₹${this.fmt(this.cgst)}`);
                        lines.push(`  • SGST @ ${this.halfRate}%: ₹${this.fmt(this.sgst)}`);
                    }
                    lines.push(`*Grand Total: ₹${this.fmt(this.total)}*`);
                    lines.push('');
                    // Deep link to the full calculator, prefilled with this exact
                    // calculation (+ campaign tags) so the recipient sees the same
                    // numbers - not a blank default - and the visit is attributable.
                    const p = new URLSearchParams({
                        amount: this.amount, rate: this.rate, mode: this.mode, supply: this.scope,
                        utm_source: 'whatsapp', utm_medium: 'share', utm_campaign: 'gst_calculator',
                    });
                    lines.push(`Calculated free at {{ url('/gst-calculator') }}?${p.toString()}`);
                    return 'https://wa.me/?text=' + encodeURIComponent(lines.join('\n'));
                }
             }"
             style="animation-delay: 0.15s; animation-fill-mode: both;">
            {{-- Phone frame: makes the live calculator read as a real mobile app
                 running on a device. Reuses the original two wrapper divs (bezel
                 + screen) so the Alpine root and tag balance stay intact. --}}
            <div class="relative w-full max-w-[390px] mx-auto rounded-[3rem] bg-gradient-to-b from-gray-800 to-gray-950 p-3 shadow-2xl ring-1 ring-white/10 transition-transform duration-500 hover:-translate-y-1">
                {{-- dynamic island --}}
                <div class="absolute top-4 left-1/2 -translate-x-1/2 z-30 w-24 h-6 bg-black rounded-full ring-1 ring-white/10" aria-hidden="true"></div>
                <div class="relative bg-white rounded-[2.25rem] overflow-hidden">
                    {{-- Dark header --}}
                    <div class="relative p-6 md:p-7 pt-9 md:pt-10 bg-gradient-to-br from-brand-950 via-brand-900 to-brand-800 text-white overflow-hidden">
                        <div class="absolute inset-0 bg-grid-soft opacity-[0.08]"></div>
                        <div class="absolute -top-16 -right-16 w-40 h-40 bg-accent-400 rounded-full blur-3xl opacity-30"></div>
                        <div class="relative flex justify-between items-start">
                            <div class="flex items-center gap-3">
                                <div>
                                    <div class="text-[10px] uppercase tracking-[0.2em] text-accent-300 font-bold">No login required</div>
                                    <h2 id="gst-calculator" class="font-display font-black text-lg sm:text-xl mt-0.5 leading-tight whitespace-nowrap">Free GST Calculator</h2>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-money-400/20 text-money-200 ring-1 ring-money-300/40">
                                <span class="w-1.5 h-1.5 rounded-full bg-money-300 animate-[pulse_2.5s_ease-in-out_infinite]"></span>
                                Live
                            </span>
                        </div>
                        <p class="relative mt-2 text-xs text-brand-200">CGST · SGST · IGST · inclusive or reverse GST.</p>
                    </div>

                    {{-- Inputs + breakdown --}}
                    <div class="p-5 md:p-6 space-y-3 text-sm">

                        {{-- Amount + quick presets (one-tap common Indian invoice amounts).
                             Heavier border + pencil cue + clear button so it reads as a
                             primary editable surface, not a read-only field. --}}
                        <div>
                            <label for="gst-amount" class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Amount
                                </span>
                                <span class="text-[10px] font-medium text-gray-500 normal-case tracking-normal">Type your amount ↓</span>
                            </label>
                            <div class="relative group">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-brand-700 font-bold pointer-events-none text-lg leading-none">₹</span>
                                {{-- Heavier border + amber focus ring makes it obvious this is the
                                     primary input. Group-hover shows the field comes alive on hover. --}}
                                <input id="gst-amount" type="number" min="0" step="any" inputmode="decimal" x-model.number="amount"
                                       placeholder="0"
                                       class="w-full pl-12 pr-12 py-3 text-xl font-mono font-extrabold tabular-nums bg-white border-2 border-brand-200 hover:border-brand-300 rounded-lg shadow-sm focus:border-brand-600 focus:ring-4 focus:ring-brand-200/60 focus:outline-none transition cursor-text">
                                {{-- Clear button, appears only when there's a value. Quick reset
                                     so users can swap amounts without selecting + deleting. --}}
                                <button type="button" @click="amount = 0; $nextTick(() => document.getElementById('gst-amount')?.focus())"
                                        x-show="parseFloat(amount) > 0"
                                        x-cloak
                                        class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-7 h-7 rounded-full text-gray-500 hover:text-white hover:bg-gray-600 transition"
                                        aria-label="Clear amount" title="Clear">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mr-1">Quick:</span>
                                <template x-for="p in presets" :key="p.v">
                                    <button type="button" @click="amount = p.v"
                                            :class="parseFloat(amount) === p.v ? 'bg-brand-700 text-white border-brand-600 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:border-brand-400 hover:text-brand-700 hover:shadow-sm'"
                                            class="inline-flex items-center justify-center min-h-[44px] px-2.5 text-xs font-bold border rounded-md transition tabular-nums"
                                            x-text="p.label"></button>
                                </template>
                            </div>
                        </div>

                        {{-- GST Rate as a button row (was a native <select>). Same pill-toggle
                             pattern as Supply / Amount-is below, consistent, obviously tappable,
                             and the selected rate is visible without opening a dropdown. Active
                             pill gets the brand fill so the current rate reads at a glance. --}}
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">GST Rate</label>
                            <div class="grid grid-cols-5 gap-1 p-1 bg-gray-100 rounded-lg">
                                @foreach ([0, 5, 12, 18, 28] as $r)
                                    <button type="button" @click="rate = {{ $r }}"
                                            :class="rate === {{ $r }} ? 'bg-brand-700 text-white shadow' : 'text-gray-700 hover:text-gray-900 hover:bg-white/70'"
                                            class="inline-flex items-center justify-center min-h-[44px] text-sm font-extrabold rounded transition tabular-nums">
                                        {{ $r }}%
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Toggles --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">Supply</label>
                                <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100 rounded-lg">
                                    <button type="button" @click="scope = 'intra'"
                                            :class="scope === 'intra' ? 'bg-brand-700 text-white shadow' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60'"
                                            class="inline-flex items-center justify-center min-h-[44px] text-xs font-bold rounded transition">Intra-state</button>
                                    <button type="button" @click="scope = 'inter'"
                                            :class="scope === 'inter' ? 'bg-brand-700 text-white shadow' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60'"
                                            class="inline-flex items-center justify-center min-h-[44px] text-xs font-bold rounded transition">Inter-state</button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">Amount is</label>
                                <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100 rounded-lg">
                                    <button type="button" @click="mode = 'exclusive'"
                                            :class="mode === 'exclusive' ? 'bg-brand-700 text-white shadow' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60'"
                                            class="inline-flex items-center justify-center min-h-[44px] text-xs font-bold rounded transition">Excl. GST</button>
                                    <button type="button" @click="mode = 'inclusive'"
                                            :class="mode === 'inclusive' ? 'bg-brand-700 text-white shadow' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60'"
                                            class="inline-flex items-center justify-center min-h-[44px] text-xs font-bold rounded transition">Incl. GST</button>
                                </div>
                            </div>
                        </div>

                        {{-- Inclusive-mode helper hint --}}
                        <template x-if="mode === 'inclusive' && rate > 0">
                            <div class="text-[11px] text-brand-700 bg-brand-50 ring-1 ring-brand-100 rounded-md px-3 py-2 leading-snug">
                                <span class="font-semibold">Reverse-calculation mode:</span> we extract the GST hidden inside your inclusive price.
                            </div>
                        </template>

                        {{-- Breakdown.

                             Consistent colour semantics across the panel:
                               • GREEN (money) = your retained value (taxable amount)
                               • AMBER (accent) = tax going to government (CGST / SGST / IGST)

                             CGST and SGST share the same amber family (just different
                             shades) because they're equal twin taxes, same rate, same
                             amount, only different recipient (Centre vs State). Using
                             two unrelated colours implied they were different kinds. --}}
                        <div class="pt-3 border-t border-gray-100 space-y-1.5">
                            <div class="flex justify-between text-gray-700 text-xs">
                                <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-money-500"></span>Taxable amount <span class="text-gray-500">(your value)</span></span>
                                <span class="font-mono tabular-nums">₹<span x-text="fmt(base)"></span></span>
                            </div>
                            <template x-if="scope === 'intra'">
                                <div class="space-y-1.5">
                                    <div class="flex justify-between text-gray-600 text-xs">
                                        <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-accent-500"></span>CGST @ <span x-text="halfRate"></span>% <span class="text-gray-500">(to Centre)</span></span>
                                        <span class="font-mono tabular-nums">₹<span x-text="fmt(cgst)"></span></span>
                                    </div>
                                    <div class="flex justify-between text-gray-600 text-xs">
                                        <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-accent-400"></span>SGST @ <span x-text="halfRate"></span>% <span class="text-gray-500">(to State)</span></span>
                                        <span class="font-mono tabular-nums">₹<span x-text="fmt(sgst)"></span></span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="scope === 'inter'">
                                <div class="flex justify-between text-gray-600 text-xs">
                                    <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-accent-500"></span>IGST @ <span x-text="rate"></span>% <span class="text-gray-500">(to Centre, split later)</span></span>
                                    <span class="font-mono tabular-nums">₹<span x-text="fmt(igst)"></span></span>
                                </div>
                            </template>

                        </div>

                        {{-- Grand Total --}}
                        <div class="mt-4 p-4 rounded-xl bg-gradient-to-br from-brand-50 via-accent-50 to-accent-50 ring-1 ring-brand-100">
                            <div class="flex items-baseline justify-between gap-3">
                                <div>
                                    <div class="text-[10px] uppercase tracking-widest text-brand-700 font-extrabold">Grand Total</div>
                                    <div class="text-[10px] text-gray-500 mt-0.5">Including GST</div>
                                </div>
                                <div class="font-display font-black text-2xl md:text-3xl bg-gradient-to-br from-brand-700 to-accent-600 bg-clip-text text-transparent tabular-nums">₹<span x-text="fmt(total)"></span></div>
                            </div>
                        </div>
                    </div>

                    {{-- CTA: primary action + WhatsApp share of the calculation.
                         Auth-aware and short enough to stay on one line inside the
                         phone screen. New visitors go to sign up (the conversion
                         goal); returning users get a "Log in" link below. --}}
                    <div class="px-6 md:px-7 pb-6 md:pb-7 space-y-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-2">
                            <a href="{{ auth()->check() ? route('invoices.create') : route('register') }}"
                               class="group flex items-center justify-center gap-2 px-4 py-3 bg-brand-900 hover:bg-brand-800 text-white rounded-lg font-bold text-sm transition shadow-sm whitespace-nowrap">
                                Create a GST invoice
                                <svg class="w-4 h-4 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M5 12h13"/></svg>
                            </a>
                            <a :href="whatsappLink" target="_blank" rel="noopener"
                               class="inline-flex items-center justify-center gap-2 px-4 py-3 bg-[#0f7540] hover:bg-[#0c5f34] text-white rounded-lg font-bold text-sm transition shadow-sm whitespace-nowrap"
                               title="Share this calculation on WhatsApp">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                Share
                            </a>
                        </div>
                        <p class="text-center text-sm text-gray-500">
                            @auth
                                You're signed in. <a href="{{ route('dashboard') }}" class="text-brand-700 font-semibold hover:underline">Go to dashboard →</a>
                            @else
                                Free · no card required · <a href="{{ route('login') }}" class="text-brand-700 font-semibold hover:underline">Log in</a>
                            @endauth
                        </p>
                        {{-- Pass authority from the home page to the dedicated calculator
                             landing page (reverse-GST content, FAQ, embed widget). --}}
                        <p class="text-center text-sm">
                            <a href="{{ route('pages.gst-calculator') }}" class="text-brand-700 font-semibold hover:underline">Open the full GST calculator →</a>
                        </p>
                    </div>
                </div>
            </div>


        </div>
    </div>
</section>

{{-- ─── Marketing slider, 4 hero posters that rotate every 5 s with prev/next
     controls, dot indicators, keyboard arrow nav, touch swipe, and the
     standard pause-on-hover behaviour. Uses the existing brand palette
     (teal gradient backing, amber accent on active dot, tricolor blur
     orbs). HD source images live at /brand/slider/. --}}
<section class="relative py-12 sm:py-16 bg-gradient-to-br from-brand-50/40 via-white to-accent-50/40 border-y border-gray-100 overflow-hidden"
         x-data='{
            slides: [
                {
                    src: "/brand/slider/slide-1-gst-software.webp",
                    alt: "Apna Invoice, free GST software for Indian SMEs, MSMEs and freelancers",
                    eyebrow: "Free for everyone",
                    title: "Free GST Software for Indian SMEs &amp; MSMEs",
                    sub: "Auto CGST · SGST · IGST · HSN/SAC. Unlimited invoices, built to be CA-friendly.",
                },
                {
                    src: "/brand/slider/slide-2-tablet-modern.webp",
                    alt: "Modern entrepreneur using Apna Invoice on a tablet to send GST-compliant invoices in seconds",
                    eyebrow: "60-second invoice",
                    title: "GST invoicing made easy in seconds",
                    sub: "From phone, tablet or laptop. Send via WhatsApp, email or signed link. 100% free.",
                },
                {
                    src: "/brand/slider/slide-3-freelancer.webp",
                    alt: "Freelancer using Apna Invoice to create GST invoices",
                    eyebrow: "Made for freelancers",
                    title: "फ्रीलांसर के लिए आसान GST बिलिंग",
                    sub: "इस्तेमाल करना बहुत आसान। Built for freelancers across all 36 states &amp; UTs.",
                },
            ],
            current: 0,
            timer: null,
            init() {
                this.start();
            },
            start() {
                this.stop();
                this.timer = setInterval(() => this.next(), 5500);
            },
            stop() {
                if (this.timer) { clearInterval(this.timer); this.timer = null; }
            },
            next() { this.current = (this.current + 1) % this.slides.length; },
            prev() { this.current = (this.current - 1 + this.slides.length) % this.slides.length; },
            goTo(i) { this.current = i; this.start(); },
         }'
         @mouseenter="stop()"
         @mouseleave="start()"
         @keydown.window.arrow-left="prev(); start()"
         @keydown.window.arrow-right="next(); start()">

    {{-- Decorative tricolor blur orbs, keep section visually anchored to brand. --}}
    <div class="absolute -top-32 -left-24 w-[420px] h-[420px] bg-accent-200 rounded-full blur-3xl opacity-30 hidden md:block" aria-hidden="true"></div>
    <div class="absolute -bottom-32 -right-24 w-[420px] h-[420px] bg-money-200 rounded-full blur-3xl opacity-30 hidden md:block" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Section eyebrow --}}
        <div class="text-center mb-8 sm:mb-10">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-100 ring-1 ring-brand-200 text-brand-800 text-xs font-bold uppercase tracking-widest">
                <span class="w-1.5 h-1.5 rounded-full bg-accent-500 animate-pulse"></span>
                See Apna Invoice in action
            </span>
            <h2 class="mt-3 font-display text-2xl sm:text-3xl md:text-4xl font-extrabold text-gray-900 leading-tight">
                Loved by <span class="text-brand-700">freelancers</span>, <span class="text-accent-700">shopkeepers</span>, <span class="text-money-700">consultants</span> &amp; CAs across India
            </h2>
        </div>

        {{-- Slider stage, square posters look best in a tall responsive viewport.
             We constrain max-width and let the image be the hero, with a small
             caption pill overlay (eyebrow + title + sub-text). Swipe via Alpine
             touch events on mobile; arrow buttons on desktop. --}}
        <div class="relative max-w-3xl mx-auto"
             @touchstart="touchStart = $event.changedTouches[0].screenX" x-data="{ touchStart: 0 }"
             @touchend="
                let dx = $event.changedTouches[0].screenX - touchStart;
                if (Math.abs(dx) > 50) { dx > 0 ? prev() : next(); start(); }
             ">

            {{-- Stage with rounded gradient border. All 3 remaining slides
                 are landscape (~1.5–1.79). We pin the stage at 3:2 (1.5)
                 and use object-cover with object-center, slides 2 + 3
                 (16:9) crop a few px from the sides; their key content
                 (faces + dashboards) is centered so nothing important is lost.
                 Fixed aspect = zero layout shift on slide change. --}}
            <div class="relative rounded-2xl sm:rounded-3xl bg-gradient-to-br from-brand-300/40 via-accent-300/40 to-money-300/40 p-[2px] shadow-xl sm:shadow-2xl">
                <div class="relative rounded-[calc(1rem-2px)] sm:rounded-[calc(1.5rem-2px)] overflow-hidden aspect-[3/2] bg-gradient-to-br from-brand-50 via-white to-accent-50">

                    <template x-for="(slide, i) in slides" :key="i">
                        <div class="absolute inset-0 transition-opacity duration-700 ease-in-out"
                             :class="current === i ? 'opacity-100 z-10' : 'opacity-0 z-0'"
                             :aria-hidden="current === i ? 'false' : 'true'">
                            <img :src="slide.src"
                                 :alt="slide.alt"
                                 class="w-full h-full object-cover object-center"
                                 loading="lazy"
                                 width="1536" height="1024">
                        </div>
                    </template>

                    {{-- Prev / next buttons, smaller, less intrusive on mobile;
                         full-size on sm+. Larger tap target via padding-box. --}}
                    <button type="button" @click="prev(); start()"
                            class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 z-20 inline-flex items-center justify-center w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-white/90 hover:bg-white text-brand-900 shadow-md sm:shadow-lg ring-1 ring-brand-200 hover:ring-accent-400 transition"
                            aria-label="Previous slide">
                        <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="next(); start()"
                            class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 z-20 inline-flex items-center justify-center w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-white/90 hover:bg-white text-brand-900 shadow-md sm:shadow-lg ring-1 ring-brand-200 hover:ring-accent-400 transition"
                            aria-label="Next slide">
                        <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- Active slide caption, sits BELOW the image so it doesn't fight
                 with the poster's own text. Updates reactively as slides change. --}}
            <div class="mt-5 text-center min-h-[80px]" x-show="slides[current]" x-cloak>
                <span class="inline-block px-2.5 py-0.5 rounded-full bg-accent-100 text-accent-800 ring-1 ring-accent-200 text-[10px] font-bold uppercase tracking-widest" x-text="slides[current].eyebrow"></span>
                <p class="mt-2 font-display text-lg sm:text-xl font-bold text-gray-900 leading-snug max-w-2xl mx-auto" x-html="slides[current].title"></p>
                <p class="mt-1 text-sm text-gray-600 max-w-2xl mx-auto" x-html="slides[current].sub"></p>
            </div>

            {{-- Dot indicators --}}
            <div class="mt-5 flex items-center justify-center gap-2.5">
                <template x-for="(slide, i) in slides" :key="`dot-${i}`">
                    <button type="button" @click="goTo(i)"
                            :class="current === i
                                ? 'w-8 h-2.5 bg-gradient-to-r from-accent-500 to-brand-700 ring-2 ring-accent-200'
                                : 'w-2.5 h-2.5 bg-gray-300 hover:bg-gray-400'"
                            class="rounded-full transition-all"
                            :aria-label="'Go to slide ' + (i + 1)"
                            :aria-current="current === i ? 'true' : 'false'"></button>
                </template>
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

        {{-- Bharat city strip, 12 major business hubs as location pills.
             Higher-contrast than the previous text-dot line to signal national reach. --}}
        <div class="mt-10 pt-8 border-t border-gray-200">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent-50 ring-1 ring-accent-200">
                    <svg class="w-3.5 h-3.5 text-accent-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2a6 6 0 00-6 6c0 4.5 6 10 6 10s6-5.5 6-10a6 6 0 00-6-6zm0 8a2 2 0 110-4 2 2 0 010 4z"/></svg>
                    <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-accent-800">Serving businesses across Bharat</span>
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2 md:gap-2.5">
                    @foreach (['Delhi','Mumbai','Bengaluru','Chennai','Hyderabad','Pune','Kolkata','Ahmedabad','Jaipur','Lucknow','Surat','Kochi'] as $city)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white ring-1 ring-gray-200 shadow-sm text-sm font-semibold text-gray-800 hover:ring-accent-300 hover:text-accent-800 transition">
                            <svg class="w-3 h-3 text-accent-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2a6 6 0 00-6 6c0 4.5 6 10 6 10s6-5.5 6-10a6 6 0 00-6-6zm0 8a2 2 0 110-4 2 2 0 010 4z"/></svg>
                            {{ $city }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How it works, 3 steps from signup to first invoice -->
<section class="relative py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center">
            <span class="inline-block px-3 py-1 rounded-full bg-accent-50 text-accent-700 text-xs font-bold uppercase tracking-wider">3-step setup</span>
            <h2 class="mt-4 font-display text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                Your first invoice in <span class="text-brand-700">60 seconds</span>.
            </h2>
            <p class="mt-3 text-gray-600">Sign up and add your business once, every invoice after that is 60 seconds flat. No onboarding call, nothing to learn.</p>
        </div>

        <div class="mt-14 grid md:grid-cols-3 gap-6 md:gap-8 relative">
            {{-- Connecting line on desktop --}}
            <div class="hidden md:block absolute top-10 left-[16%] right-[16%] h-px bg-gradient-to-r from-brand-200 via-accent-200 to-money-200 -z-0"></div>

            @foreach ([
                ['n' => '01', 'title' => 'Sign up', 'desc' => 'Continue with Google, or use your mobile, email and a password. A quick OTP verifies your number. No credit card, no company docs.', 'gradient' => 'from-brand-500 to-brand-700', 'ring' => 'ring-brand-200', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                ['n' => '02', 'title' => 'Add your business (one-time)', 'desc' => 'Paste GSTIN, pick state, upload logo. Letterhead auto-generates on every invoice.', 'gradient' => 'from-accent-500 to-accent-500', 'ring' => 'ring-accent-200', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                ['n' => '03', 'title' => 'Issue invoice in 60 seconds', 'desc' => 'Pick a customer, type one line item, tap Issue. PDF is ready to WhatsApp.', 'gradient' => 'from-money-500 to-money-700', 'ring' => 'ring-money-200', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
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
            <a href="{{ route('register') }}" class="group inline-flex items-center justify-center px-7 py-3.5 bg-accent-700 hover:bg-accent-800 text-white font-semibold rounded-xl shadow-brand transition">
                Signup for Free, no card needed
                <svg class="w-5 h-5 ml-2 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
            </a>
        </div>
    </div>
</section>

<!-- How to use, tabbed learning section -->
<section id="how-to" class="relative py-20 bg-gray-50 border-y border-gray-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center">
            <span class="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-bold uppercase tracking-wider">Learn the tool</span>
            <h2 class="mt-4 font-display text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">How to use Apna Invoice</h2>
            <p class="mt-3 text-gray-600">Pick a task to see the exact steps. No jargon, no manual to read.</p>
        </div>

        @php
            $howto = [
                ['key' => 'create', 'label' => 'Create an invoice', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                    'title' => 'Create & issue a GST invoice',
                    'steps' => [
                        ['t' => 'Sign up in seconds', 'd' => 'Continue with Google, or enter your mobile and verify it with the one-time code we send.'],
                        ['t' => 'Add your business once', 'd' => 'GSTIN, state and logo. Your letterhead is generated on every invoice automatically.'],
                        ['t' => 'Add the line items', 'd' => 'Pick a customer, type description, HSN/SAC, quantity, rate and GST%. CGST, SGST and IGST are calculated for you.'],
                        ['t' => 'Issue & download', 'd' => 'Tap Issue. The invoice is locked, gets a permanent number, and the GST invoice PDF is ready.'],
                    ],
                    'tip' => 'GST splits automatically: same state prints CGST + SGST, a different state prints IGST, based on the customer\'s state.'],
                ['key' => 'share', 'label' => 'Share & get paid', 'icon' => 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
                    'title' => 'Share the invoice and collect payment',
                    'steps' => [
                        ['t' => 'Share on WhatsApp', 'd' => 'One tap opens WhatsApp with a pre-filled message and the invoice link.'],
                        ['t' => 'Email or public link', 'd' => 'Email the PDF, or copy a secure 30-day public link the customer can open without logging in.'],
                        ['t' => 'Scan-and-pay UPI QR', 'd' => 'Add your UPI ID in settings and a payment QR prints on every invoice PDF.'],
                        ['t' => 'Record the payment', 'd' => 'Mark full or part payment (with TDS). A payment receipt PDF is generated automatically.'],
                    ],
                    'tip' => 'Outstanding invoices can send automatic WhatsApp and email reminders so you chase payments less.'],
                ['key' => 'reports', 'label' => 'Reports for your CA', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                    'title' => 'Month-end reports your CA actually asks for',
                    'steps' => [
                        ['t' => 'GSTR-1 export', 'd' => 'Download a GSTR-1-friendly CSV of B2B and B2C invoices for the period.'],
                        ['t' => 'GSTR-3B summary', 'd' => 'A pre-filled summary with output tax, ITC and net payable, laid out like the form.'],
                        ['t' => 'Receivables aging', 'd' => 'See who owes you, bucketed by how overdue they are.'],
                        ['t' => 'Profit & loss', 'd' => 'Accrual, cash and GST views, plus an expenses CSV and one-click backup.'],
                    ],
                    'tip' => 'Every report exports as both PDF (to review) and CSV (for spreadsheets, your accountant, or the GST portal).'],
                ['key' => 'docs', 'label' => 'Quotations & credit notes', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                    'title' => 'Quotations and credit notes, the compliant way',
                    'steps' => [
                        ['t' => 'Send a quotation', 'd' => 'Create a price proposal (proforma) and share it just like an invoice.'],
                        ['t' => 'Convert in one click', 'd' => 'When the customer accepts, turn the quotation into a tax invoice instantly.'],
                        ['t' => 'Issue a credit note', 'd' => 'Raise a Section 34 credit note against any issued invoice, with reason codes.'],
                        ['t' => 'Stay audit-ready', 'd' => 'Quotations use a separate FY-aware series and stay out of GST returns until converted.'],
                    ],
                    'tip' => 'We compute the Section 34(2) credit-note deadline per invoice and block late issuance, so you never file a worthless credit note.'],
            ];
        @endphp

        <div x-data="{ tab: 'create' }" class="mt-12">
            <div class="flex flex-wrap justify-center gap-2 sm:gap-3" role="tablist" aria-label="How to use Apna Invoice">
                @foreach ($howto as $t)
                    <button type="button" @click="tab = '{{ $t['key'] }}'" role="tab" :aria-selected="tab === '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}' ? 'bg-brand-700 text-white shadow-sm ring-brand-700' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-100'"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold ring-1 transition">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['icon'] }}"/></svg>
                        {{ $t['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="mt-8 bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-6 sm:p-10">
                @foreach ($howto as $p)
                    <div x-show="tab === '{{ $p['key'] }}'" x-cloak role="tabpanel" class="grid md:grid-cols-2 gap-8 lg:gap-12 items-start">
                        <div>
                            <h3 class="font-display text-xl sm:text-2xl font-extrabold text-gray-900">{{ $p['title'] }}</h3>
                            <ol class="mt-6 space-y-5">
                                @foreach ($p['steps'] as $i => $step)
                                    <li class="flex gap-4">
                                        <span class="shrink-0 w-8 h-8 rounded-full bg-brand-50 text-brand-700 font-bold flex items-center justify-center text-sm">{{ $i + 1 }}</span>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-gray-900">{{ $step['t'] }}</div>
                                            <div class="mt-0.5 text-sm text-gray-600 leading-relaxed">{{ $step['d'] }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                        <div class="rounded-xl bg-brand-50/60 ring-1 ring-brand-100 p-6">
                            <div class="flex items-center gap-2 text-brand-700 font-bold text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Good to know
                            </div>
                            <p class="mt-2 text-sm text-gray-700 leading-relaxed">{{ $p['tip'] }}</p>
                            <a href="{{ route('register') }}" class="mt-5 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition">
                                Try it free
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M5 12h13"/></svg>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mt-6 text-center text-sm text-gray-500">
                Prefer a slide-deck? <a href="{{ asset('downloads/apna-invoice-getting-started.pptx') }}" download class="text-brand-700 font-semibold hover:underline">Download the getting-started guide</a> or read the <a href="{{ route('help') }}" class="text-brand-700 font-semibold hover:underline">full how-to</a>.
            </p>
        </div>
    </div>
</section>

{{-- ─── Why Apna Invoice, competitive differentiator.
     Most free Indian billing tools are pretty PDF generators. We're
     positioned as the rule-aware alternative, actual CGST sections cited,
     audit-defensible by design. This section is the strongest reason a
     CA-using SME picks us over "easy and free" alternatives. --}}
<section class="relative py-24 bg-gradient-to-br from-white via-brand-50/30 to-accent-50/40 border-y border-gray-100 overflow-hidden">
    {{-- Subtle decorative orbs in tricolour --}}
    <div class="absolute -top-40 -left-32 w-[400px] h-[400px] bg-accent-200 rounded-full blur-3xl opacity-25" aria-hidden="true"></div>
    <div class="absolute -bottom-40 -right-32 w-[400px] h-[400px] bg-money-200 rounded-full blur-3xl opacity-25" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-100 ring-1 ring-brand-200 text-brand-800 text-xs font-bold uppercase tracking-widest">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1L3 4v6c0 4.5 3 8.3 7 9 4-.7 7-4.5 7-9V4l-7-3zm-.7 12.3L6 10l1.4-1.4 1.9 1.9 4.4-4.4L15 7.5l-5.7 5.8z" clip-rule="evenodd"/></svg>
                Built like the law · not just like an app
            </span>
            <h2 class="mt-4 font-display text-3xl sm:text-4xl md:text-5xl font-extrabold text-gray-900 leading-[1.05] tracking-tight">
                Your CA will <span class="text-brand-700">never bounce an invoice back</span>.
            </h2>
            <p class="mt-4 text-base sm:text-lg text-gray-700 leading-relaxed max-w-2xl">
                Other free billing apps make pretty PDFs. <strong class="text-gray-900">Apna Invoice is engineered around 12+ specific CGST Rules</strong>, so the document you issue today still passes muster when your CA opens GSTR-1 next month. No silent edge cases. No "we don't support that case." Audit-defensible by design.
            </p>
        </div>

        {{-- 6-card grid of regulation-backed differentiators. Each card cites
             the actual section / rule so the credibility is verifiable, not
             marketing fluff. Accent / brand / money tones rotate so the row
             keeps the tricolour identity. --}}
        <div class="mt-14 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
            @foreach ([
                [
                    'tone'  => 'brand',
                    'icon'  => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                    'title' => 'Tax Invoice · Bill of Supply · Invoice',
                    'rule'  => 'CGST Rule 46 + 49',
                    'body'  => 'Title is decided by your data, not a dropdown. No GSTIN → "Invoice". Composition dealer → "Bill of Supply" + Rule 49 footer note. Regular registered → "Tax Invoice". Always legally correct.',
                ],
                [
                    'tone'  => 'accent',
                    'icon'  => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                    'title' => 'Auto CGST · SGST · IGST split',
                    'rule'  => 'IGST Act §5 · CGST §9',
                    'body'  => 'Place of supply is computed from your state vs. the customer\'s state. Inter-state? IGST. Intra-state? CGST + SGST split to the paisa, with proper round-off. No manual math.',
                ],
                [
                    'tone'  => 'money',
                    'icon'  => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
                    'title' => 'Books-locked period close',
                    'rule'  => 'Audit best practice',
                    'body'  => 'Set "books locked until 31 Mar" once your CA signs off. After that, the system blocks editing or backdating any invoice, payment, expense, credit note, or cash memo into the closed FY. Audit trail logs every lock.',
                ],
                [
                    'tone'  => 'brand',
                    'icon'  => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                    'title' => 'Section 34(2) credit-note window',
                    'rule'  => 'CGST §34(2)',
                    'body'  => 'Credit notes can only reduce GST liability if issued by 30 Nov of the FY following the supply. We compute the deadline per invoice and block late issuance, politely, so you never file a worthless CN.',
                ],
                [
                    'tone'  => 'accent',
                    'icon'  => 'M13 10V3L4 14h7v7l9-11h-7z',
                    'title' => 'Reverse-charge ready',
                    'rule'  => 'CGST §9(3)/(4) · Rule 46(p)',
                    'body'  => 'Tick "RCM applicable", tax is zeroed out, the recipient declaration "Tax payable on reverse charge basis" prints automatically per Rule 46(p), GSTR-1 marks it correctly. Done.',
                ],
                [
                    'tone'  => 'money',
                    'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                    'title' => 'GST 2.0 ready (Sep 2025)',
                    'rule'  => '56th GST Council',
                    'body'  => 'New 40% sin/luxury slab. New 1.5% / 7.5% under-construction housing rates. Merchant export 0.10%. All loaded, pick the right slab from the dropdown, the system does the rest.',
                ],
            ] as $card)
                @php
                    $tone = $card['tone'];
                    $palette = [
                        'brand'   => ['ring' => 'ring-brand-200', 'icon_bg' => 'from-brand-600 to-brand-800', 'badge_bg' => 'bg-brand-50', 'badge_text' => 'text-brand-700', 'hover' => 'hover:ring-brand-400'],
                        'accent' => ['ring' => 'ring-accent-200', 'icon_bg' => 'from-accent-500 to-accent-600', 'badge_bg' => 'bg-accent-50', 'badge_text' => 'text-accent-800', 'hover' => 'hover:ring-accent-400'],
                        'money'   => ['ring' => 'ring-money-200', 'icon_bg' => 'from-money-500 to-money-700', 'badge_bg' => 'bg-money-50', 'badge_text' => 'text-money-800', 'hover' => 'hover:ring-money-400'],
                    ][$tone];
                @endphp
                <article class="group relative bg-white rounded-2xl ring-1 {{ $palette['ring'] }} {{ $palette['hover'] }} hover:ring-2 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1 p-6 md:p-7 flex flex-col">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br {{ $palette['icon_bg'] }} text-white flex items-center justify-center shadow-sm">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/></svg>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md {{ $palette['badge_bg'] }} {{ $palette['badge_text'] }} text-[10px] font-bold uppercase tracking-wider whitespace-nowrap font-mono">{{ $card['rule'] }}</span>
                    </div>
                    <h3 class="font-display text-lg md:text-xl font-bold text-gray-900 leading-tight">{{ $card['title'] }}</h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $card['body'] }}</p>
                </article>
            @endforeach
        </div>

        {{-- Closing reassurance, generic framing, no named competitors.
             Keeps the message ("CA-friendly · GSTR-1-ready · free") without
             the legal exposure of trademark parity claims. --}}
        <div class="mt-14 max-w-4xl mx-auto p-6 md:p-8 rounded-2xl bg-gradient-to-br from-brand-900 via-brand-800 to-brand-900 text-white text-center shadow-xl">
            <p class="font-display text-lg md:text-xl font-bold leading-snug">
                Your CA loves the <span class="text-accent-300">established billing tools</span>?
            </p>
            <p class="mt-2 text-sm md:text-base text-brand-100 leading-relaxed">
                They'll love this too, same audit defensibility, same CGST-Rule-46 compliance, for <span class="text-accent-300 font-bold">₹0 during beta</span>. Export GSTR-1 CSV, hand over the books, file in minutes.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-accent-500 hover:bg-accent-600 text-accent-950 font-bold shadow-md transition">
                    Try the free GST invoicing app
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M5 12h13"/></svg>
                </a>
                <a href="#features" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-100 hover:text-white">
                    See every feature ↓
                </a>
            </div>
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
            <p class="mt-4 text-lg text-gray-600">No bloated ERP. No spreadsheet gymnastics. Just the core tools to bill customers and get paid, the way India does business.</p>
        </div>

        <div class="mt-14 grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ([
                ['gr' => 'from-brand-500 to-brand-800', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title' => 'GST auto-detection', 'body' => "CGST+SGST for intra-state, IGST for inter-state. Picks up from customer's state automatically, no manual math."],
                ['gr' => 'from-accent-500 to-accent-600', 'icon' => 'M9 17v-2a4 4 0 014-4h2m-4-4V3m0 0L8 6m3-3l3 3M4 19h16a1 1 0 001-1v-7a1 1 0 00-1-1h-3.586a1 1 0 00-.707.293l-1.414 1.414a1 1 0 01-.707.293H9.414a1 1 0 01-.707-.293L7.293 10.293A1 1 0 006.586 10H3a1 1 0 00-1 1v7a1 1 0 001 1z', 'title' => 'One-click PDF', 'body' => 'Letterhead, logo, signature, HSN/SAC, amount in words (Indian format). Download or print, always pixel-perfect.'],
                ['gr' => 'from-money-500 to-money-700', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'title' => 'Draft → Issued flow', 'body' => 'Edit drafts as much as you want. Issue to lock the number and make it legally final. Atomic numbering, no duplicates.'],
                ['gr' => 'from-brand-600 to-accent-500', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Partial payments', 'body' => 'Record advance payments, track balance at a glance. Status moves from Final → Partially paid → Paid as you go.'],
                ['gr' => 'from-accent-500 to-accent-700', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'title' => 'Customer book', 'body' => 'Save customer details once, GSTIN, address, state. Reuse across invoices. Auto-fills the GST tax mode based on state.'],
                ['gr' => 'from-brand-700 to-money-600', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'title' => 'Payment reminders', 'body' => 'Auto-email nudges at 0 / 3 / 7 / 15 / 30 days past due, or send a one-tap WhatsApp follow-up. Receipts are generated the moment payment is recorded.'],
                ['gr' => 'from-danger-500 to-accent-600', 'icon' => 'M19 14l-7 7m0 0l-7-7m7 7V3', 'title' => 'Credit notes (GST Section 34)', 'body' => 'Issue credit notes against an issued invoice with reason codes. Compliant with CBIC Section 34 format for returns, adjustments, and disputes.'],
                ['gr' => 'from-accent-600 to-brand-700', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'title' => 'Multi-GSTIN / multi-branch', 'body' => 'Run multiple companies or state branches from one login. Each entity gets its own GSTIN, logo, numbering series, one click to switch.'],
                ['gr' => 'from-money-600 to-brand-700', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'title' => 'One-click backups', 'body' => 'Download a full ZIP of your invoices, customers, and PDFs anytime, or schedule it to email itself monthly. Your data, your move.'],
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

<!-- Excel vs Apna Invoice, addresses the #1 "competitor" for Indian SMEs -->
<section class="py-24 bg-gradient-to-b from-white to-gray-50 relative overflow-hidden">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-danger-50 text-danger-700 text-xs font-bold uppercase tracking-wider ring-1 ring-danger-100">
                <span class="w-1.5 h-1.5 rounded-full bg-danger-500"></span> The Excel Tax
            </span>
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">
                Still billing in <span class="line-through decoration-danger-400 decoration-4">Excel</span>? <br class="hidden md:block">
                There's a faster way.
            </h2>
            <p class="mt-5 text-lg text-gray-600">Most Indian SMEs still copy-paste an old invoice, fiddle with GST math, and email the file. Here's what you're losing every month.</p>
        </div>

        <div class="mt-14 grid md:grid-cols-2 gap-6 md:gap-8 items-stretch">
            {{-- Excel column --}}
            <div class="relative bg-white rounded-2xl p-7 md:p-9 ring-1 ring-danger-100 shadow-sm">
                <div class="absolute -top-3 left-7 inline-flex items-center gap-1.5 px-3 py-1 bg-danger-600 text-white text-xs font-bold uppercase tracking-wider rounded-full shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    Excel / WhatsApp PDFs
                </div>
                <h3 class="mt-2 font-display text-xl font-extrabold text-gray-900">The way most of India still bills</h3>
                <ul class="mt-6 space-y-4">
                    @foreach ([
                        'Manual CGST/SGST/IGST math, one wrong state and the whole invoice is non-compliant',
                        'No HSN/SAC codes handy, Google each line, paste, hope it\'s right',
                        'Amount in words written by hand, "lakh" vs "lac" arguments with the CA every month',
                        'Invoice numbers reset wrong at FY-end, GST officer flags it in audit',
                        'Version chaos: invoice_final_v3_FINAL.xlsx, nobody knows which one was sent',
                        'Can\'t WhatsApp the file directly, customers get a broken .xlsx preview',
                    ] as $pain)
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 w-5 h-5 rounded-full bg-danger-50 text-danger-500 flex items-center justify-center flex-shrink-0 ring-1 ring-danger-100">
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
                <div class="absolute -top-3 left-7 inline-flex items-center gap-1.5 px-3 py-1 bg-money-700 text-white text-xs font-bold uppercase tracking-wider rounded-full shadow-sm z-10">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    Apna Invoice
                </div>
                <h3 class="relative mt-2 font-display text-xl font-extrabold">Built for the way India bills</h3>
                <ul class="relative mt-6 space-y-4">
                    @foreach ([
                        'Same-state? Auto CGST+SGST. Inter-state? Auto IGST. Zero math on your end.',
                        'HSN / SAC codes pre-loaded, start typing, pick from the list. Every line is compliant.',
                        'Amount in words in proper Indian format, "Rupees One Lakh Twenty-Five Thousand Only".',
                        'Auto FY-reset numbering: ACME/2025-26/0001 → ACME/2026-27/0001 on 1st April.',
                        'One source of truth, every draft, edit, and revision is tracked with version history.',
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
                        Switch to Apna Invoice, it's free
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
                ['n' => '02', 't' => 'Add your customer', 'b' => 'Save their details once, we auto-fill them in every future invoice.'],
                ['n' => '03', 't' => 'Issue and share', 'b' => 'Fill line items, click Issue, download PDF. Done.'],
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
            <a href="{{ route('register') }}" class="inline-flex items-center px-8 py-4 bg-accent-500 hover:bg-accent-600 text-accent-950 font-bold rounded-xl shadow-glow transition text-lg">
                Start billing free →
            </a>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
            <span class="inline-block px-3 py-1 rounded-full bg-accent-50 text-accent-700 text-xs font-bold uppercase tracking-wider">What users say</span>
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">Loved by India's SMEs & Startups.</h2>
            <p class="mt-4 text-lg text-gray-600">From Mumbai consultancies to Bengaluru agencies, business owners use DST's invoice tool to close the month in hours, not days.</p>
            <p class="mt-3 text-xs text-gray-500">Representative scenarios from early private-beta users. Full case studies coming post-launch.</p>
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
                    'i' => 'AM', 'c' => 'from-accent-500 to-accent-600',
                ],
                [
                    'n' => 'Rohit Patel', 'r' => 'Director', 'company' => 'Patel Trading Co.',
                    'city' => 'Surat', 'industry' => 'Textile · Exports',
                    'q' => 'Finally, an invoicing tool that speaks Indian, lakhs, crores, HSN, SAC, state-based GST. Not a rebranded US product with bolted-on GST.',
                    'metric' => '₹50L+ billed/mo', 'metric_icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                    'i' => 'RP', 'c' => 'from-money-500 to-money-700',
                ],
            ] as $t)
                <div class="group relative bg-white rounded-2xl p-6 md:p-8 ring-1 ring-gray-100 shadow-sm hover:shadow-card hover:ring-brand-200 transition-all duration-300 flex flex-col">
                    {{-- Rating stars + metric pill --}}
                    <div class="flex items-center justify-between">
                        <div class="flex gap-0.5 text-accent-500" aria-label="5 out of 5 stars">
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
                            <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-500">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $t['city'] }}
                                </span>
                                <span class="text-gray-400" aria-hidden="true">·</span>
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
            <h2 class="mt-4 font-display text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight">Free for Indian MSMEs, SMEs <span class="text-brand-700">&amp; startups</span>.</h2>
            <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">Sign up today and get unlimited invoices, customers, and PDF exports, no credit card, no feature locks, no hidden add-ons. When we launch paid tiers for larger businesses, beta users keep the free plan.</p>
        </div>

        <div class="mt-12 relative">
            <div class="absolute -inset-4 bg-gradient-to-br from-brand-300 to-accent-300 rounded-3xl blur-3xl opacity-30"></div>
            <div class="relative bg-white rounded-2xl shadow-brand ring-1 ring-gray-100 overflow-hidden md:grid md:grid-cols-2">
                <div class="p-8 md:p-10 bg-gradient-to-br from-brand-900 to-brand-800 text-white">
                    <div class="text-xs font-bold tracking-widest uppercase text-accent-300">Early-bird plan</div>
                    <h3 class="mt-2 font-display text-3xl font-extrabold">DST Invoice · Free</h3>
                    <div class="mt-6 flex items-baseline gap-2">
                        <span class="text-6xl font-black font-display">₹0</span>
                        <span class="text-brand-200 text-sm">/ Free</span>
                    </div>
                    <p class="mt-4 text-brand-100 text-sm">For Indian solo professionals, SMEs &amp; startups billing up to 500 invoices/month.</p>
                    <a href="{{ route('register') }}" class="mt-8 block text-center px-6 py-3 bg-accent-500 hover:bg-accent-600 text-accent-950 font-bold rounded-xl transition">Claim free account →</a>
                </div>
                <div class="p-8 md:p-10">
                    <div class="text-xs font-bold tracking-widest uppercase text-brand-700">What's included</div>
                    <ul class="mt-4 space-y-3">
                        @foreach ([
                            'Unlimited GST invoices (CGST/SGST/IGST)',
                            'Unlimited customers, products & HSN/SAC codes',
                            'Logo, signature, letterhead',
                            'Ink-saver PDF export & print-ready view',
                            'Email, WhatsApp & public-link sharing',
                            'Payment reminders & receipt numbering',
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

<!-- Built for India, trust & compliance strip -->
<section class="py-20 bg-white relative overflow-hidden">
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-accent-200 to-transparent"></div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-money-200 to-transparent"></div>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white text-gray-800 text-xs font-bold uppercase tracking-wider ring-1 ring-gray-200 shadow-sm">
                {{-- Horizontal tricolour bar: saffron / white (Ashoka-navy ring) / green --}}
                <span class="inline-flex w-5 h-3 rounded-[2px] overflow-hidden ring-1 ring-gray-200" aria-hidden="true">
                    <span class="flex-1 bg-[#ff9933]"></span>
                    <span class="flex-1 bg-white border-y border-brand-700/30"></span>
                    <span class="flex-1 bg-[#138808]"></span>
                </span>
                Made in India · Built for Bharat
            </span>
            <h2 class="mt-4 font-display text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                Your data stays in India. <span class="bg-gradient-to-r from-accent-500 via-brand-600 to-money-600 bg-clip-text text-transparent">Always.</span>
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
                    'tile' => 'bg-brand-50 text-brand-700 ring-brand-100',
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
                    'card' => 'hover:ring-accent-200',
                    'tile' => 'bg-accent-50 text-accent-700 ring-accent-100',
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

{{-- ─── From the blog, drives discovery of /blog content + builds topical
     authority for SEO. Section is hidden when no posts are published yet,
     so the landing page degrades gracefully on a fresh install. --}}
@php
    $featuredPosts = \App\Models\Post::published()
        ->orderByDesc('published_at')
        ->limit(3)
        ->get();
@endphp
@if ($featuredPosts->isNotEmpty())
<section id="blog" class="py-20 sm:py-24 bg-white border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-10">
            <div>
                <span class="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-bold uppercase tracking-wider">Blogs</span>
                <h2 class="mt-3 font-display text-3xl sm:text-4xl font-extrabold text-gray-900 leading-tight">GST tips & invoicing how-tos</h2>
                <p class="mt-2 text-gray-600 max-w-2xl">Practical guides for Indian freelancers, MSMEs and CAs, written by the team behind Apna Invoice.</p>
            </div>
            <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-1 text-sm font-bold text-brand-700 hover:text-brand-800 self-start sm:self-end whitespace-nowrap">
                Browse all blogs
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            @foreach ($featuredPosts as $fp)
                <article class="group flex flex-col bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden hover:ring-brand-300 hover:shadow-md transition">
                    @if ($fp->featured_image_path)
                        <a href="{{ route('blog.show', $fp->slug) }}" class="block aspect-[16/9] bg-gray-100 overflow-hidden">
                            <img src="{{ asset('storage/' . $fp->featured_image_path) }}"
                                 alt="{{ $fp->featured_image_alt ?: $fp->title }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                 loading="lazy" width="800" height="450">
                        </a>
                    @else
                        <a href="{{ route('blog.show', $fp->slug) }}" class="block aspect-[16/9] bg-gradient-to-br from-brand-100 via-brand-50 to-accent-50 flex items-center justify-center p-6">
                            <span class="font-display font-extrabold text-2xl text-brand-700/30 text-center">{{ \Illuminate\Support\Str::limit($fp->title, 28) }}</span>
                        </a>
                    @endif
                    <div class="flex-1 p-5 flex flex-col gap-3">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 flex items-center gap-2">
                            <time datetime="{{ $fp->published_at?->toIso8601String() }}">{{ $fp->published_at?->format('d M Y') }}</time>
                            @if ($fp->reading_minutes)
                                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                <span>{{ $fp->reading_minutes }} min read</span>
                            @endif
                        </div>
                        <h3 class="font-display text-lg font-bold text-gray-900 leading-snug group-hover:text-brand-700 transition">
                            <a href="{{ route('blog.show', $fp->slug) }}">{{ $fp->title }}</a>
                        </h3>
                        @if ($fp->excerpt || $fp->effectiveMetaDescription())
                            <p class="text-sm text-gray-600 leading-relaxed">{{ \Illuminate\Support\Str::limit($fp->excerpt ?: $fp->effectiveMetaDescription(), 130) }}</p>
                        @endif
                        <a href="{{ route('blog.show', $fp->slug) }}" class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:text-brand-800">
                            Read article
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

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
                        <svg class="w-5 h-5 text-gray-500 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-5 text-gray-600 leading-relaxed whitespace-pre-line">{{ $faq['a'] }}</div>
                </details>
            @endforeach
        </div>

        {{-- Downloadable user guide, public, no signup needed.
             Lets prospects preview the full flow before creating an account. --}}
        <div class="mt-10 p-5 sm:p-6 rounded-2xl bg-white ring-1 ring-brand-100 shadow-card flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="shrink-0 w-12 h-12 rounded-xl bg-brand-50 ring-1 ring-brand-100 text-brand-700 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-widest text-accent-700">Free guide · No signup</div>
                <h3 class="mt-0.5 font-display text-lg font-extrabold text-gray-900">17-slide getting-started deck</h3>
                <p class="mt-1 text-sm text-gray-600">A complete walkthrough, from sign-up to your first paid GST invoice, in 17 slides. Share with your team or your CA.</p>
            </div>
            <a href="{{ asset('downloads/apna-invoice-getting-started.pptx') }}" download
               class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-sm transition whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Download .pptx
            </a>
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

<!-- Backed by Datasoft Technologies -->
<section class="py-16 sm:py-20 bg-gray-50 border-t border-gray-100">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-3xl ring-1 ring-gray-200 shadow-card p-6 sm:p-10 grid md:grid-cols-2 gap-8 lg:gap-12 items-center">
            <div>
                <div class="text-xs font-bold uppercase tracking-widest text-brand-700">Built &amp; supported by</div>
                <a href="{{ config('seo.organization.url') }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-3 group">
                    <img src="{{ asset('brand/dst-logo.webp') }}" alt="Datasoft Technologies" class="h-12 w-auto" width="932" height="320" loading="lazy">
                    <span class="font-display text-2xl font-extrabold text-gray-900 group-hover:text-brand-700 transition">Datasoft Technologies</span>
                </a>
                <p class="mt-4 text-gray-600 leading-relaxed">
                    Apna Invoice is built and <strong class="text-gray-900">actively maintained</strong> by Datasoft Technologies, an Indian software company. It's a supported product with a team behind it, free during beta, not a side-project left to gather dust.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ config('seo.contact.whatsapp_url') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#0f7540] hover:bg-[#0c5f34] text-white text-sm font-semibold transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        WhatsApp support
                    </a>
                    <a href="tel:{{ config('seo.contact.phone_e164') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-white ring-1 ring-gray-300 hover:bg-gray-50 text-gray-800 text-sm font-semibold transition">
                        <svg class="w-4 h-4 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        {{ config('seo.contact.phone_display') }}
                    </a>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                @foreach ([
                    ['icon' => 'M5 13l4 4L19 7', 'title' => 'Real-time support', 'sub' => 'WhatsApp & phone, real humans'],
                    ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title' => 'Data stays in India', 'sub' => 'DPDP Act compliant'],
                    ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'title' => 'Actively shipped', 'sub' => 'New features during beta'],
                    ['icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1', 'title' => 'Free during beta', 'sub' => 'No card, no per-invoice limit'],
                ] as $point)
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 p-4">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-brand-50 text-brand-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $point['icon'] }}"/></svg>
                        </span>
                        <div class="mt-2 font-semibold text-gray-900 text-sm">{{ $point['title'] }}</div>
                        <div class="text-xs text-gray-500">{{ $point['sub'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<x-site-footer variant="full" />

<x-whatsapp-float />
@include('partials.cookie-banner')

</body>
</html>
