@php
    // One list for the home page and /faq (config/faqs.php), so the two
    // cannot drift apart again.
    $faqs = config('faqs.general');

    $appUrl = rtrim(config('app.url'), '/');
    $siteName = config('seo.name');

    // The one Organization node every page points back to by this @id.
    $orgRef = [
        '@type' => 'Organization',
        '@id' => $appUrl . '#organization',
        'name' => config('seo.organization.name'),
        'url' => config('seo.organization.url'),
    ];

    $jsonLd = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $appUrl . '#organization',
            'name' => config('seo.organization.name'),
            'legalName' => config('seo.organization.legal_name'),
            'url' => config('seo.organization.url'),
            // sameAs is how Google ties the social profiles to this business
            // in its Knowledge Panel, so the footer links are declared here
            // too. Profiles only: the company's own site is already `url`.
            'sameAs' => array_values(array_filter([
                config('seo.social.facebook'),
                config('seo.social.instagram'),
                config('seo.social.linkedin'),
            ])),
            'alternateName' => 'ApnaInvoice',
            // The Apna Invoice logo (square, with the wordmark) is what Google
            // shows for the brand; Datasoft is the legal entity behind it.
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $appUrl . config('seo.organization.logo'),
                'width' => 512,
                'height' => 512,
            ],
            'parentOrganization' => [
                '@type' => 'Organization',
                'name' => config('seo.organization.parent.name'),
                'url' => config('seo.organization.parent.url'),
                'logo' => $appUrl . config('seo.organization.parent.logo'),
            ],
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
            // name + alternateName feed the site name Google prints above
            // the result.
            'name' => $siteName,
            'alternateName' => ['ApnaInvoice', 'apnainvoice.com'],
            'url' => $appUrl . '/',
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
            'description' => 'Free online GST calculator for India, compute CGST, SGST and IGST on any amount. Supports intra-state and inter-state supplies, plus tax-inclusive and tax-exclusive modes (reverse GST). Works with the 0%, 5%, 18% and 40% slabs. No login, no signup, runs in your browser.',
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
            'creator' => $orgRef,
            'publisher' => $orgRef,
        ],
        // SoftwareApplication schema, declares the invoicing/billing tool
        // itself. Targets rich-result eligibility for "online invoice generator",
        // "free bill generator", "GST bill generator", "invoice generator India".
        // Single canonical node (@id) - Google drops an entity described by two
        // conflicting SoftwareApplication nodes. /features reuses this @id
        // for the same app rather than declaring a second one.
        [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            '@id' => $appUrl . '#software',
            // The product's real name. The search phrases belong in
            // alternateName and description, not stuffed into the name.
            'name' => $siteName,
            'alternateName' => [
                'Free GST Invoice & Bill Generator',
                'Online Invoice Generator',
                'Free Bill Generator',
                'GST Invoice Generator India',
                'GST Bill Generator',
                'Invoice Generator India',
                'Online Bill Generator',
                'Free Invoice Maker',
            ],
            'url' => $appUrl . '/',
            // The app icon (square), not the wide wordmark.
            'image' => $appUrl . '/brand/icon-512.png',
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
                'Counter bills on 80 mm thermal paper',
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
            'creator' => $orgRef,
            'publisher' => $orgRef,
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
        keywords="free GST calculator, free GST calculator India, online GST calculator, CGST SGST IGST calculator, GST inclusive calculator, GST exclusive calculator, reverse GST calculator, GST calculator 5 18 40, free GST billing software India, online GST invoice generator, GST bill maker, GSTR-1 export, HSN SAC search, GST invoice format India, free invoicing app for SMEs, MSME billing software, freelancer invoice India, shop billing software, composition dealer Bill of Supply, audit defensible invoicing, GSTR-3B summary, UPI QR invoice, WhatsApp invoice share"
        type="website"
        :json-ld="$jsonLd" />
    {{-- PWA manifest + theme-color come from <x-seo>; a second theme-color
         tag here was a duplicate. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Apna Invoice">

    {{-- Preload the brand logo, it's the LCP element on the landing header. --}}
    <link rel="preload" href="{{ asset('brand/apna-invoice-logo-sm.webp') }}" as="image" type="image/webp" fetchpriority="high">
    {{-- Fonts: warm the connection (crossorigin is REQUIRED - font files are
         fetched with CORS, so a plain preconnect warms the wrong connection),
         then load the stylesheet non-render-blocking. display=swap already
         paints text immediately in a fallback face and swaps the web font in
         on load, so making the request async costs nothing and frees up FCP. --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    <link rel="preload" as="style" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|plus-jakarta-sans:600,700,800&display=swap">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|plus-jakarta-sans:600,700,800&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|plus-jakarta-sans:600,700,800&display=swap"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900 overflow-x-hidden">
@php
    // One button, link and heading style for the whole page.
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-800';
    $btnSecondary = 'inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3 font-semibold text-gray-800 ring-1 ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400';
    $textLink = 'inline-flex items-center gap-1.5 font-semibold text-brand-700 hover:text-brand-800';
    $h2 = 'font-display text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900';
    $arrow = '<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M5 12h13"/></svg>';
    $check = '<svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';

    // Sample sale shown in the hero invoice and again as the matched bank
    // line further down. Amounts are computed so every row adds up.
    $sampleRate = 18;
    $sampleItems = [
        ['desc' => 'Laptop, Core i5, 16 GB RAM', 'hsn' => '8471', 'qty' => 2, 'unit' => 'NOS', 'rate' => 42500],
        ['desc' => 'Wireless keyboard and mouse set', 'hsn' => '8471', 'qty' => 6, 'unit' => 'NOS', 'rate' => 2500],
    ];
    $sampleTaxable = 0;
    foreach ($sampleItems as $k => $item) {
        $sampleItems[$k]['taxable'] = $item['qty'] * $item['rate'];
        $sampleItems[$k]['gst'] = $sampleItems[$k]['taxable'] * $sampleRate / 100;
        $sampleItems[$k]['total'] = $sampleItems[$k]['taxable'] + $sampleItems[$k]['gst'];
        $sampleTaxable += $sampleItems[$k]['taxable'];
    }
    $sampleGst = $sampleTaxable * $sampleRate / 100;
    $sampleTotal = $sampleTaxable + $sampleGst;

    // QR-style pattern for the UPI block: three finder squares plus fixed
    // pseudo-random modules. Decorative, it encodes nothing.
    $qrSize = 25;
    $qrPath = '';
    $seed = 7;
    for ($y = 0; $y < $qrSize; $y++) {
        for ($x = 0; $x < $qrSize; $x++) {
            $seed = ($seed * 1103515245 + 12345) % 2147483648;
            $inFinder = ($x < 8 && $y < 8) || ($x >= $qrSize - 8 && $y < 8) || ($x < 8 && $y >= $qrSize - 8);
            if (! $inFinder && (($seed >> 16) & 1)) {
                $qrPath .= "M{$x} {$y}h1v1h-1z";
            }
        }
    }
    $qrFinders = [[0, 0], [$qrSize - 7, 0], [0, $qrSize - 7]];

    // Product areas, each pointing at its section on /features.
    $areas = [
        [
            'title' => 'Billing',
            'lead' => 'Tax invoices, quotations, credit notes and receipts.',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'points' => [
                'Tax invoice, bill of supply or invoice, set by your GST registration',
                'Quotations that become invoices in one click',
                'Credit notes under Section 34, with the time limit tracked',
                'Part payments, TDS and numbered receipts',
                'Share on WhatsApp, by email or as a 30-day link',
            ],
            'links' => [['sales', 'More on billing']],
        ],
        [
            'title' => 'Purchases and stock',
            'lead' => 'Supplier bills, expenses and what is left on the shelf.',
            'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            'points' => [
                'Purchase invoices, with the supplier state read from the GSTIN',
                'Input tax credit, with blocked credit under Section 17(5) kept out',
                'Expenses by category, and cash memos for purchases without a bill',
                'Stock that moves with each invoice and purchase',
                'Low-stock alerts and stock adjustments',
            ],
            'links' => [['purchases', 'More on purchases'], ['inventory', 'More on stock']],
        ],
        [
            'title' => 'Banking and GST returns',
            'lead' => 'Match the bank statement and prepare returns from your books.',
            'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
            'points' => [
                'Statements in Excel or CSV from HDFC, SBI, ICICI, Axis and others',
                'Each bank line matched to a receipt, payment or expense',
                'GSTR-1 with a JSON file for the GST portal',
                'GSTR-2B matched against your purchase bills',
                'GSTR-3B tables 3.1, 4 and 6.1 worked out',
            ],
            'links' => [['banking', 'More on banking'], ['gst', 'More on GST returns']],
            'bankRow' => true,
        ],
        [
            'title' => 'Reports and team',
            'lead' => 'Month-end reports, several businesses and your CA in one login.',
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
            'points' => [
                'Profit and loss in accrual, cash and GST views',
                'Receivables and payables aging, sales and purchase registers',
                'Excel, CSV and PDF export for every report',
                'Several businesses, each with its own GSTIN and numbering',
                'Team roles for your accountant or CA, with an activity log',
            ],
            'links' => [['reports', 'More on reports'], ['business', 'More on team access']],
        ],
    ];

    // The rules the software applies, with the reference a CA will look for.
    $rules = [
        ['Rule 46', 'Every field a tax invoice must carry, including HSN/SAC, place of supply and the reverse charge statement.'],
        ['Rule 46(b)', 'Invoice numbers in a financial-year series of up to 16 characters, restarting on 1 April.'],
        ['Rule 48', 'Original, duplicate and triplicate copies for goods.'],
        ['Rule 49', 'A bill of supply with the required declaration for composition dealers.'],
        ['Section 34', 'Credit notes against the original invoice, blocked once the 30 November time limit has passed.'],
        ['Rule 88A', 'Input tax credit set off in the required order in GSTR-3B.'],
        ['GST 2.0', 'The 5%, 18% and 40% slabs from 22 Sep 2025, with the legacy 12% and 28%.'],
        ['Books lock', 'Lock a closed financial year so nothing in it can be changed or backdated.'],
    ];

    // Sample GSTR-1 summary, in the layout of the app's own GSTR-1 screen.
    $gstr1Rows = [
        ['4A', 'B2B invoices', 38, 1842500],
        ['5', 'B2C large (inter-state, over ₹1 lakh)', 2, 264000],
        ['7', 'B2C others', 9, 312450],
        ['9B', 'Credit notes (registered)', 3, 46200],
        ['12', 'HSN summary (B2B)', 14, 1796300],
        ['12', 'HSN summary (B2C)', 6, 576450],
    ];

    $steps = [
        ['Create your account', 'Continue with Google, or sign up with your mobile, email and a password. A one-time code verifies your number.'],
        ['Add your business', 'Enter your GSTIN, address and state once, and upload your logo. They print on every invoice.'],
        ['Issue and share', 'Pick a customer, add items with HSN/SAC and GST rate, and issue. Send the PDF on WhatsApp, by email or as a link.'],
    ];

    $included = [
        'Unlimited invoices, quotations and credit notes',
        'Unlimited customers, items and vendors',
        'Purchases, expenses, stock and bank reconciliation',
        'GSTR-1 JSON, GSTR-2B match and GSTR-3B',
        'Team access for your accountant or CA',
        'Full data export and a weekly email backup',
    ];

    // Latest articles, listed beside the FAQ. Hidden on a fresh install.
    $featuredPosts = \App\Models\Post::published()
        ->orderByDesc('published_at')
        ->limit(3)
        ->get();
@endphp

<a href="#main" class="skip-link">Skip to content</a>

{{-- The shared public header (components/site-header): the same links as
     every other public page, and a menu below lg so phones can reach them.
     Kept sticky here, as the home page header always was. --}}
<div class="sticky top-0 z-50">
    <x-site-header />
</div>

<main id="main">

{{-- 1. Hero: what it is, two actions, and the document it produces. --}}
<section class="bg-white">
    <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 pb-16 pt-12 sm:px-6 md:pb-20 md:pt-16 lg:grid-cols-12 lg:gap-10 lg:px-8">
        <div class="lg:col-span-5">
            <p lang="hi" class="text-sm font-semibold text-brand-700">आपका अपना GST बिलिंग साथी</p>
            <h1 class="mt-3 font-display text-4xl font-extrabold leading-[1.1] tracking-tight text-gray-900 md:text-5xl">Free GST invoicing software for Indian businesses</h1>
            <p class="mt-5 max-w-xl text-lg leading-relaxed text-gray-600">GST invoices, purchases, stock, bank reconciliation and GST returns, in one place.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                @auth
                    <a href="{{ route('dashboard') }}" class="{{ $btnPrimary }}">Go to dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="{{ $btnPrimary }}">Create free account</a>
                @endauth
                <a href="{{ route('pages.features') }}" class="{{ $btnSecondary }}">See features</a>
            </div>
            <p class="mt-4 text-sm text-gray-500">No card needed. Works on phone, tablet and computer.</p>
        </div>

        {{-- A tax invoice laid out like the PDF the app issues (invoices/pdf):
             supplier and title block, billed-to, line items with HSN and GST,
             amount in words, totals, UPI QR and the signatory block. --}}
        <figure class="lg:col-span-7">
            <div class="overflow-hidden rounded-xl bg-white text-[11px] leading-relaxed text-gray-700 shadow-sm ring-1 ring-gray-200 sm:text-xs">
                <div class="border-t-4 border-brand-700 p-4 sm:p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <div class="font-display text-sm font-bold text-gray-900 sm:text-base">Sharma Traders</div>
                            <div class="mt-1 text-gray-600">14 Laxmi Road, Shukrawar Peth<br>Pune, Maharashtra 411002</div>
                            <div class="mt-1"><span class="text-[10px] uppercase tracking-wide text-gray-500">GSTIN</span> <span class="font-mono font-semibold text-gray-900">27AAKFS4821M1Z5</span></div>
                        </div>
                        <div class="sm:text-right">
                            <div class="font-display text-sm font-extrabold uppercase tracking-wider text-gray-900 sm:text-base">Tax Invoice</div>
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Original for Recipient</div>
                            <div class="mt-2 inline-block rounded bg-gray-100 px-2 py-0.5 font-mono font-semibold text-gray-900">#SHT/2026-27/0042</div>
                            <div class="mt-2 text-gray-600">
                                <strong class="font-semibold text-gray-800">Date:</strong> 06 Oct 2026 · <strong class="font-semibold text-gray-800">Due:</strong> 21 Oct 2026<br>
                                <strong class="font-semibold text-gray-800">Place of supply:</strong> Maharashtra (27)<br>
                                <strong class="font-semibold text-gray-800">Reverse charge:</strong> No
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-gray-200 pt-3">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Billed to</div>
                        <div class="mt-0.5 font-semibold text-gray-900">Kulkarni Infotech Pvt Ltd</div>
                        <div class="text-gray-600">Baner Road, Pune, Maharashtra · State 27 411045</div>
                        <div><span class="text-[10px] uppercase tracking-wide text-gray-500">GSTIN</span> <span class="font-mono font-semibold text-gray-900">27AABCK7312R1Z9</span></div>
                    </div>

                    <table class="mt-4 w-full">
                        <thead>
                            <tr class="border-y border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wide text-gray-500">
                                <th scope="col" class="hidden py-1.5 pl-2 text-left font-semibold sm:table-cell">#</th>
                                <th scope="col" class="py-1.5 pl-2 text-left font-semibold">Description</th>
                                <th scope="col" class="px-1 py-1.5 text-left font-semibold">HSN/SAC</th>
                                <th scope="col" class="hidden px-1 py-1.5 text-right font-semibold sm:table-cell">Qty</th>
                                <th scope="col" class="hidden px-1 py-1.5 text-right font-semibold sm:table-cell">Rate</th>
                                <th scope="col" class="px-1 py-1.5 text-right font-semibold">Taxable ₹</th>
                                <th scope="col" class="px-1 py-1.5 text-right font-semibold">GST ₹</th>
                                <th scope="col" class="py-1.5 pl-1 pr-2 text-right font-semibold">Amount ₹</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($sampleItems as $i => $item)
                                <tr class="align-top">
                                    <td class="hidden py-2 pl-2 text-gray-500 sm:table-cell">{{ $i + 1 }}</td>
                                    <td class="py-2 pl-2 font-semibold text-gray-900">
                                        {{ $item['desc'] }}
                                        <div class="font-normal text-gray-500 sm:hidden">{{ $item['qty'] }} {{ $item['unit'] }} × {{ inr($item['rate']) }}</div>
                                    </td>
                                    <td class="px-1 py-2 font-mono">{{ $item['hsn'] }}</td>
                                    <td class="hidden whitespace-nowrap px-1 py-2 text-right tabular-nums sm:table-cell">{{ $item['qty'] }} {{ $item['unit'] }}</td>
                                    <td class="hidden px-1 py-2 text-right tabular-nums sm:table-cell">{{ inr($item['rate']) }}</td>
                                    <td class="px-1 py-2 text-right tabular-nums">{{ inr($item['taxable']) }}</td>
                                    <td class="px-1 py-2 text-right tabular-nums">
                                        <div class="text-[10px] text-gray-500">{{ $sampleRate }}%</div>
                                        {{ inr($item['gst']) }}
                                    </td>
                                    <td class="py-2 pl-1 pr-2 text-right font-semibold tabular-nums text-gray-900">{{ inr($item['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4 grid gap-4 border-t border-gray-200 pt-4 sm:grid-cols-2">
                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Amount in words (INR)</div>
                            <div class="mt-0.5 font-semibold text-gray-900">{{ \App\Support\NumberToWords::indianRupees($sampleTotal) }}</div>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="shrink-0 rounded-md bg-white p-1 ring-1 ring-gray-200">
                                    <svg class="h-14 w-14 text-gray-900" viewBox="0 0 {{ $qrSize }} {{ $qrSize }}" shape-rendering="crispEdges" aria-hidden="true">
                                        <path fill="currentColor" d="{{ $qrPath }}"/>
                                        @foreach ($qrFinders as [$fx, $fy])
                                            <rect x="{{ $fx }}" y="{{ $fy }}" width="7" height="7" fill="currentColor"/>
                                            <rect x="{{ $fx + 1 }}" y="{{ $fy + 1 }}" width="5" height="5" fill="#fff"/>
                                            <rect x="{{ $fx + 2 }}" y="{{ $fy + 2 }}" width="3" height="3" fill="currentColor"/>
                                        @endforeach
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-brand-800">Scan &amp; pay ₹{{ inr($sampleTotal) }}</div>
                                    <div class="text-gray-500">Any UPI app</div>
                                </div>
                            </div>
                        </div>
                        <dl class="space-y-1 self-start rounded-lg bg-gray-50 p-3 tabular-nums ring-1 ring-gray-200">
                            <div class="flex justify-between gap-4"><dt class="text-gray-600">Taxable value</dt><dd>₹{{ inr($sampleTaxable) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-600">CGST</dt><dd>₹{{ inr($sampleGst / 2) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-600">SGST</dt><dd>₹{{ inr($sampleGst / 2) }}</dd></div>
                            <div class="mt-1 flex justify-between gap-4 border-t border-gray-200 pt-1.5 text-sm font-bold text-gray-900"><dt>Grand Total</dt><dd>₹{{ inr($sampleTotal) }}</dd></div>
                        </dl>
                    </div>

                    <div class="mt-4 flex items-end justify-between gap-4 border-t border-gray-200 pt-3">
                        <div class="text-[10px] text-gray-500"><strong class="text-gray-700">E. &amp; O.E.</strong><br>Subject to Pune jurisdiction</div>
                        <div class="w-36 text-center sm:w-44">
                            <div class="text-[10px] font-semibold text-gray-900">For Sharma Traders</div>
                            <div class="h-8" aria-hidden="true"></div>
                            <div class="border-t border-gray-300 pt-1 text-[10px] text-gray-500">Authorised signatory</div>
                        </div>
                    </div>
                </div>
            </div>
            <figcaption class="mt-3 text-sm text-gray-500">A tax invoice from Apna Invoice, with sample data. A sale within Maharashtra, so GST splits into CGST and SGST.</figcaption>
        </figure>
    </div>
</section>

{{-- 2. Facts, each one true today. --}}
<section aria-label="At a glance" class="border-y border-gray-200 bg-gray-50">
    <dl class="mx-auto grid max-w-6xl gap-x-8 gap-y-6 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
        @foreach ([
            ['GST 2.0 rates', '5%, 18% and 40%, plus the legacy 12% and 28% for older supplies.'],
            ['GSTR-1 JSON', 'A file to upload on the GST portal, and Excel for the offline tool.'],
            ['UPI QR on every invoice', 'Add your UPI ID once. Customers scan and pay the balance.'],
            ['Free during beta', 'Unlimited invoices and customers, with every feature included.'],
        ] as [$fact, $detail])
            <div class="border-l-2 border-brand-700 pl-4">
                <dt class="font-semibold text-gray-900">{{ $fact }}</dt>
                <dd class="mt-1 text-sm leading-relaxed text-gray-600">{{ $detail }}</dd>
            </div>
        @endforeach
    </dl>
</section>

{{-- 3. Product areas, each linking to its part of /features. --}}
<section id="features" class="bg-white">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 md:py-20 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="{{ $h2 }}">From the first bill to the GST return</h2>
            <p class="mt-3 text-gray-600">Invoices and purchase bills feed your stock, bank matching, reports and GST returns, so nothing is entered twice.</p>
        </div>

        <div class="mt-10 grid gap-6 md:grid-cols-2">
            @foreach ($areas as $area)
                <article class="flex flex-col rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <svg class="h-6 w-6 text-brand-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $area['icon'] }}"/></svg>
                    <h3 class="mt-4 font-display text-lg font-bold text-gray-900">{{ $area['title'] }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $area['lead'] }}</p>
                    <ul class="mt-4 space-y-2 text-sm text-gray-700">
                        @foreach ($area['points'] as $point)
                            <li class="flex gap-2.5">{!! $check !!}<span>{{ $point }}</span></li>
                        @endforeach
                    </ul>

                    @if (! empty($area['bankRow']))
                        {{-- The receipt for the hero invoice, as a matched line on the
                             Bank Reconciliation screen. --}}
                        <div class="mt-5 overflow-hidden rounded-lg text-xs ring-1 ring-gray-200" role="group" aria-label="Sample matched bank line">
                            <div class="grid grid-cols-[auto_1fr_auto] gap-x-3 bg-gray-50 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                <span>Date</span><span>Bank narration</span><span class="text-right">Money in</span>
                            </div>
                            <div class="grid grid-cols-[auto_1fr_auto] items-start gap-x-3 px-3 pt-2">
                                <span class="whitespace-nowrap text-gray-700">11 Oct 2026</span>
                                <span class="min-w-0 break-words text-gray-900">NEFT-KULKARNI INFOTECH PVT LTD</span>
                                <span class="text-right font-mono tabular-nums text-money-700">{{ rupees($sampleTotal) }}</span>
                            </div>
                            <div class="flex items-center gap-2 px-3 pb-2.5 pt-1.5">
                                <span class="inline-flex items-center rounded-full bg-money-100 px-2 py-0.5 font-bold text-money-800">Matched</span>
                                <span class="text-gray-700">Receipt RCPT-0042</span>
                            </div>
                        </div>
                    @endif

                    <div class="mt-auto flex flex-wrap gap-x-6 gap-y-2 pt-6 text-sm">
                        @foreach ($area['links'] as [$anchor, $label])
                            <a href="{{ route('pages.features') }}#{{ $anchor }}" class="{{ $textLink }}">{{ $label }}{!! $arrow !!}</a>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- 4. GST compliance: the rules, and the return they produce. --}}
<section class="border-y border-gray-200 bg-gray-50">
    <div class="mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 md:py-20 lg:grid-cols-12 lg:gap-10 lg:px-8">
        <div class="lg:col-span-6">
            <h2 class="{{ $h2 }}">GST invoices your CA can file without corrections</h2>
            <p class="mt-3 max-w-xl text-gray-600">These rules are built into invoices, credit notes and returns.</p>
            <dl class="mt-8 border-b border-gray-200">
                @foreach ($rules as [$ref, $text])
                    <div class="grid grid-cols-[6.5rem_1fr] gap-4 border-t border-gray-200 py-3">
                        <dt class="font-mono text-xs font-semibold leading-6 text-brand-800">{{ $ref }}</dt>
                        <dd class="text-sm leading-6 text-gray-700">{{ $text }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <figure class="lg:col-span-6 lg:pt-1">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-5">
                    <div>
                        <div class="font-display text-sm font-bold text-gray-900">GSTR-1 Summary</div>
                        <div class="text-xs text-gray-500">October 2026 · Sharma Traders</div>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-semibold" aria-hidden="true">
                        <span class="rounded-md bg-white px-2.5 py-1.5 text-gray-800 ring-1 ring-gray-300">JSON (portal upload)</span>
                        <span class="rounded-md bg-money-600 px-2.5 py-1.5 text-white">Excel (offline tool)</span>
                    </div>
                </div>
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 text-[10px] uppercase tracking-wide text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-semibold sm:px-5">Table</th>
                            <th scope="col" class="px-2 py-2 text-left font-semibold">Section</th>
                            <th scope="col" class="px-2 py-2 text-right font-semibold">Records</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold sm:px-5">Taxable value (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($gstr1Rows as [$table, $label, $records, $taxable])
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2.5 font-semibold text-gray-900 sm:px-5">{{ $table }}</td>
                                <td class="px-2 py-2.5 text-gray-700">{{ $label }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums text-gray-700">{{ $records }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums text-gray-900 sm:px-5">{{ rupees($taxable) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t border-gray-200 bg-gray-50 px-4 py-2.5 text-[11px] text-gray-500 sm:px-5">Issued invoices and credit notes for the period. Totals tie to your GSTR-3B.</div>
            </div>
            <figcaption class="mt-3 text-sm text-gray-500">The GSTR-1 screen, with sample data.</figcaption>
        </figure>
    </div>
</section>

{{-- 5. Free GST calculator. Works without an account; the WebApplication
     schema above points at #gst-calculator. --}}
<section id="free-gst-calculator" class="bg-white">
    <div class="mx-auto grid max-w-6xl items-start gap-10 px-4 py-16 sm:px-6 md:py-20 lg:grid-cols-12 lg:gap-12 lg:px-8">
        <div class="lg:col-span-5">
            <h2 id="gst-calculator" class="{{ $h2 }}">Free GST calculator</h2>
            <p class="mt-3 text-gray-600">Add GST to an amount, or take it out of a GST-inclusive price. No login needed.</p>
            <dl class="mt-6 space-y-4 text-sm">
                <div>
                    <dt class="font-semibold text-gray-900">Same state</dt>
                    <dd class="mt-0.5 text-gray-600">The tax splits equally into CGST and SGST, so 18% is 9% and 9%.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-900">Another state</dt>
                    <dd class="mt-0.5 text-gray-600">The whole tax is IGST.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-900">GST-inclusive price</dt>
                    <dd class="mt-0.5 text-gray-600">₹1,180 at 18% is ₹1,000 plus ₹180 GST.</dd>
                </div>
            </dl>
            <a href="{{ route('pages.gst-calculator') }}" class="mt-6 {{ $textLink }} text-sm">Open the full GST calculator{!! $arrow !!}</a>
        </div>

        <div class="lg:col-span-7"
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
                fmt(n) { const v = Math.round((parseFloat(n) || 0) * 100) / 100; return v.toLocaleString('en-IN', Number.isInteger(v) ? { maximumFractionDigits: 0 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
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
                    // numbers, not a blank default, and the visit is attributable.
                    const p = new URLSearchParams({
                        amount: this.amount, rate: this.rate, mode: this.mode, supply: this.scope,
                        utm_source: 'whatsapp', utm_medium: 'share', utm_campaign: 'gst_calculator',
                    });
                    lines.push(`Calculated free at {{ url('/gst-calculator') }}?${p.toString()}`);
                    return 'https://wa.me/?text=' + encodeURIComponent(lines.join('\n'));
                }
             }">
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="space-y-5 p-5 sm:p-6">
                    <div>
                        <label for="gst-amount" class="block text-sm font-semibold text-gray-900">Amount</label>
                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 font-semibold text-gray-500" aria-hidden="true">₹</span>
                            <input id="gst-amount" type="number" min="0" step="any" inputmode="decimal" x-model.number="amount" value="10000" placeholder="0"
                                   class="block w-full rounded-lg border-gray-300 py-3 pl-9 pr-12 text-lg font-semibold tabular-nums text-gray-900 shadow-sm focus:border-brand-600 focus:ring-brand-600">
                            <button type="button" @click="amount = 0; $nextTick(() => document.getElementById('gst-amount')?.focus())"
                                    x-show="parseFloat(amount) > 0" x-cloak
                                    class="absolute right-1 top-1/2 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"
                                    aria-label="Clear amount">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2" role="group" aria-label="Quick amounts">
                            <template x-for="p in presets" :key="p.v">
                                <button type="button" @click="amount = p.v"
                                        :aria-pressed="parseFloat(amount) === p.v"
                                        :class="parseFloat(amount) === p.v ? 'bg-brand-50 text-brand-800 ring-brand-300' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'"
                                        class="inline-flex min-h-[44px] min-w-[3.25rem] items-center justify-center rounded-lg px-3 text-sm font-semibold tabular-nums ring-1 transition"
                                        x-text="p.label"></button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <div id="gst-rate-label" class="text-sm font-semibold text-gray-900">GST rate</div>
                        <div class="mt-1.5 grid grid-cols-4 gap-1 rounded-lg bg-gray-100 p-1" role="group" aria-labelledby="gst-rate-label">
                            @foreach ([0, 5, 18, 40] as $r)
                                <button type="button" @click="rate = {{ $r }}"
                                        :aria-pressed="rate === {{ $r }}"
                                        :class="rate === {{ $r }} ? 'bg-white text-brand-800 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900'"
                                        class="min-h-[44px] rounded-md text-sm font-semibold tabular-nums transition">{{ $r }}%</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <div id="gst-supply-label" class="text-sm font-semibold text-gray-900">Supply</div>
                            <div class="mt-1.5 grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-1" role="group" aria-labelledby="gst-supply-label">
                                <button type="button" @click="scope = 'intra'" :aria-pressed="scope === 'intra'"
                                        :class="scope === 'intra' ? 'bg-white text-brand-800 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900'"
                                        class="min-h-[44px] rounded-md text-sm font-semibold transition">Intra-state</button>
                                <button type="button" @click="scope = 'inter'" :aria-pressed="scope === 'inter'"
                                        :class="scope === 'inter' ? 'bg-white text-brand-800 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900'"
                                        class="min-h-[44px] rounded-md text-sm font-semibold transition">Inter-state</button>
                            </div>
                        </div>
                        <div>
                            <div id="gst-mode-label" class="text-sm font-semibold text-gray-900">Amount is</div>
                            <div class="mt-1.5 grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-1" role="group" aria-labelledby="gst-mode-label">
                                <button type="button" @click="mode = 'exclusive'" :aria-pressed="mode === 'exclusive'"
                                        :class="mode === 'exclusive' ? 'bg-white text-brand-800 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900'"
                                        class="min-h-[44px] rounded-md text-sm font-semibold transition">Excl. GST</button>
                                <button type="button" @click="mode = 'inclusive'" :aria-pressed="mode === 'inclusive'"
                                        :class="mode === 'inclusive' ? 'bg-white text-brand-800 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900'"
                                        class="min-h-[44px] rounded-md text-sm font-semibold transition">Incl. GST</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-b-xl border-t border-gray-200 bg-gray-50 p-5 sm:p-6">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Taxable value</dt>
                            <dd class="tabular-nums text-gray-900">₹<span x-text="fmt(base)">10,000</span></dd>
                        </div>
                        <div class="flex justify-between gap-4" x-show="scope === 'intra'">
                            <dt class="text-gray-600">CGST @ <span x-text="halfRate">9</span>%</dt>
                            <dd class="tabular-nums text-gray-900">₹<span x-text="fmt(cgst)">900</span></dd>
                        </div>
                        <div class="flex justify-between gap-4" x-show="scope === 'intra'">
                            <dt class="text-gray-600">SGST @ <span x-text="halfRate">9</span>%</dt>
                            <dd class="tabular-nums text-gray-900">₹<span x-text="fmt(sgst)">900</span></dd>
                        </div>
                        <div class="flex justify-between gap-4" x-show="scope === 'inter'" x-cloak>
                            <dt class="text-gray-600">IGST @ <span x-text="rate">18</span>%</dt>
                            <dd class="tabular-nums text-gray-900">₹<span x-text="fmt(igst)">1,800</span></dd>
                        </div>
                        <div class="mt-3 flex items-baseline justify-between gap-4 border-t border-gray-200 pt-3">
                            <dt class="font-semibold text-gray-900">Total incl. GST</dt>
                            <dd class="font-display text-2xl font-extrabold tabular-nums text-gray-900" aria-live="polite">₹<span x-text="fmt(total)">11,800</span></dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-gray-600" x-show="mode === 'inclusive' && rate > 0" x-cloak>GST is taken out of the amount you entered.</p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <a href="{{ auth()->check() ? route('invoices.create') : route('register') }}" class="{{ $btnPrimary }}">Create a GST invoice</a>
                        <a :href="whatsappLink" target="_blank" rel="noopener" class="{{ $btnSecondary }}">
                            <svg class="h-5 w-5 text-[#0f7540]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            Share on WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 6. How it works, once. --}}
<section class="border-y border-gray-200 bg-gray-50">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 md:py-20 lg:px-8">
        <h2 class="{{ $h2 }}">Start billing in three steps</h2>
        <ol class="mt-10 grid gap-8 md:grid-cols-3">
            @foreach ($steps as $i => [$title, $text])
                <li class="border-t-2 border-brand-700 pt-5">
                    <div class="text-sm font-semibold text-brand-700">Step {{ $i + 1 }}</div>
                    <h3 class="mt-1 font-display text-lg font-bold text-gray-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
        <div class="mt-10 flex flex-wrap gap-x-8 gap-y-3 text-sm">
            <a href="{{ route('pages.how-to-use') }}" class="{{ $textLink }}">Read the step-by-step guide{!! $arrow !!}</a>
            <a href="{{ asset('downloads/apna-invoice-getting-started.pptx') }}" download class="{{ $textLink }}">Download the getting-started slides (.pptx){!! $arrow !!}</a>
        </div>
    </div>
</section>

{{-- 7. Pricing summary; /pricing has the detail. --}}
<section id="pricing" class="bg-white">
    <div class="mx-auto grid max-w-6xl items-start gap-10 px-4 py-16 sm:px-6 md:py-20 lg:grid-cols-12 lg:gap-12 lg:px-8">
        <div class="lg:col-span-6">
            <h2 class="{{ $h2 }}">Free during beta. Free for life with one referral.</h2>
            <p class="mt-4 text-gray-600">Unlimited invoices, customers and PDF exports, and no card needed to sign up. When paid plans arrive for larger businesses, everyone who joined during beta keeps the free plan.</p>
            <div class="mt-6 border-l-2 border-accent-500 pl-4">
                <p class="font-semibold text-gray-900">Refer a friend</p>
                <p class="mt-1 text-sm leading-relaxed text-gray-600">Share your referral code from the app. When a friend signs up with it and issues their first invoice, your account is free for life.</p>
            </div>
            <a href="{{ route('pages.pricing') }}" class="mt-6 {{ $textLink }} text-sm">See pricing details{!! $arrow !!}</a>
        </div>

        <div class="lg:col-span-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                <div class="text-sm font-semibold text-gray-900">Apna Invoice Free</div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-display text-5xl font-extrabold tracking-tight text-gray-900">₹0</span>
                    <span class="text-sm text-gray-500">per month during beta</span>
                </div>
                <ul class="mt-6 space-y-2.5 text-sm text-gray-700">
                    @foreach ($included as $point)
                        <li class="flex gap-2.5">{!! $check !!}<span>{{ $point }}</span></li>
                    @endforeach
                </ul>
                @auth
                    <a href="{{ route('dashboard') }}" class="mt-8 w-full {{ $btnPrimary }}">Go to dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="mt-8 w-full {{ $btnPrimary }}">Create free account</a>
                @endauth
            </div>
        </div>
    </div>
</section>

{{-- 8. FAQ, from config/faqs.php (the same list /faq marks up as FAQPage),
     with the latest articles beside it. --}}
<section id="faq" class="border-t border-gray-200 bg-gray-50">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:py-20 lg:grid-cols-12 lg:gap-12 lg:px-8">
        <div class="lg:col-span-4">
            <h2 class="{{ $h2 }}">Frequently asked questions</h2>
            <p class="mt-3 text-gray-600">What business owners and CAs ask before they start.</p>
            <a href="{{ route('pages.faq') }}" class="mt-4 {{ $textLink }} text-sm">All questions{!! $arrow !!}</a>

            @if ($featuredPosts->isNotEmpty())
                <div class="mt-10 border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-semibold text-gray-900">From the blog</h3>
                    <ul class="mt-4 space-y-4">
                        @foreach ($featuredPosts as $fp)
                            <li>
                                <a href="{{ route('blog.show', $fp->slug) }}" class="text-sm font-medium leading-snug text-gray-800 hover:text-brand-700">{{ $fp->title }}</a>
                                <div class="mt-0.5 text-xs text-gray-500">
                                    <time datetime="{{ $fp->published_at?->toIso8601String() }}">{{ $fp->published_at?->format('d M Y') }}</time>
                                    @if ($fp->reading_minutes) · {{ $fp->reading_minutes }} min read @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('blog.index') }}" class="mt-5 {{ $textLink }} text-sm">All articles{!! $arrow !!}</a>
                </div>
            @endif
        </div>

        <div class="lg:col-span-8">
            <div class="divide-y divide-gray-200 border-y border-gray-200">
                @foreach ($faqs as $faq)
                    <details class="group">
                        <summary class="flex min-h-[44px] cursor-pointer list-none items-start justify-between gap-4 py-4 font-semibold text-gray-900 hover:text-brand-800 [&::-webkit-details-marker]:hidden">
                            <span>{{ $faq['q'] }}</span>
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-gray-500 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="max-w-3xl whitespace-pre-line pb-5 pr-8 leading-relaxed text-gray-600">{{ $faq['a'] }}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- 9. Closing call to action: the page's one dark section. --}}
<section class="bg-brand-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-16 text-center sm:px-6 md:py-20 lg:px-8">
        <h2 class="font-display text-2xl font-extrabold tracking-tight md:text-3xl">Bill your next customer with Apna Invoice</h2>
        <p class="mt-3 text-brand-100">Set up your business once and issue your first GST invoice in a few minutes.</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-5 py-3 font-semibold text-brand-900 shadow-sm transition hover:bg-brand-50">Go to dashboard</a>
            @else
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-5 py-3 font-semibold text-brand-900 shadow-sm transition hover:bg-brand-50">Create free account</a>
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg px-5 py-3 font-semibold text-white ring-1 ring-white/40 transition hover:bg-white/10">Log in</a>
            @endauth
        </div>
    </div>
</section>

</main>

<x-site-footer variant="full" />

<x-whatsapp-float />
@include('partials.cookie-banner')

</body>
</html>
