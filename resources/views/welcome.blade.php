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
        // The GST calculator is described on /gst-calculator itself; a second
        // node here, with another name and URL, conflicted with it.
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
            'image' => $appUrl . '/brand/icon-512.webp',
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
        description="Free GST billing software and invoice generator for India. CGST, SGST and IGST worked out, HSN/SAC, UPI QR and WhatsApp share, GSTR-1 JSON for the portal. Unlimited invoices, no card needed."
        keywords="free GST calculator, free GST calculator India, online GST calculator, CGST SGST IGST calculator, GST inclusive calculator, GST exclusive calculator, reverse GST calculator, GST calculator 5 18 40, free GST billing software India, online GST invoice generator, GST bill maker, GSTR-1 export, HSN SAC search, GST invoice format India, free invoicing app for SMEs, MSME billing software, freelancer invoice India, shop billing software, composition dealer Bill of Supply, audit defensible invoicing, GSTR-3B summary, UPI QR invoice, WhatsApp invoice share"
        type="website"
        :json-ld="$jsonLd" />
    {{-- PWA manifest + theme-color come from <x-seo>; a second theme-color
         tag here was a duplicate. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Apna Invoice">

    {{-- The logo is the one image in the first view (the sign-up form
         fills the hero; the screens sit below it). --}}
    <link rel="preload" href="{{ asset('brand/apna-invoice-logo-sm.webp') }}" as="image" type="image/webp" fetchpriority="high">
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased bg-white text-gray-900 overflow-x-hidden">
@php
    // Button, link and heading styles for the calculator and FAQ below. The
    // landing sections come from partials/landing, shared with /start.
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-800';
    $btnSecondary = 'inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3 font-semibold text-gray-800 ring-1 ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400';
    $textLink = 'inline-flex items-center gap-1.5 font-semibold text-brand-700 hover:text-brand-800';
    $h2 = 'font-display text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900';
    $arrow = '<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M5 12h13"/></svg>';

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

{{-- Hero with the sign-up form, who it is for, what it does, what is free,
     and the three steps: the same sections as the ads page (/start), from
     partials/landing. Ad and campaign tags on the URL are kept server-side
     (CaptureAttribution), so the form needs nothing extra for them. --}}
@include('partials.landing.hero', ['formPrefix' => 'home-'])
@include('partials.landing.audience')
@include('partials.landing.usp')
@include('partials.landing.included')
@include('partials.landing.steps')

{{-- Free GST calculator, kept for organic search. Works without an account; the WebApplication
     schema above points at #gst-calculator. --}}
<section id="free-gst-calculator" class="scroll-mt-20 border-y border-gray-200 bg-gray-50">
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
                        @auth
                            <a href="{{ route('invoices.create') }}" class="{{ $btnPrimary }}">Create a GST invoice</a>
                        @else
                            <a href="#signup" data-lp-signup class="{{ $btnPrimary }}">Create a GST invoice</a>
                        @endauth
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

{{-- FAQ, from config/faqs.php (the same list /faq marks up as FAQPage),
     with the latest articles beside it. --}}
<section id="faq" class="scroll-mt-20 bg-white">
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

@include('partials.landing.final-cta')

</main>

<x-site-footer variant="full" />

<x-whatsapp-float />
@include('partials.cookie-banner')
@include('partials.install-app')
{{-- After the two bars above: it reads their state to stay clear of them. --}}
@include('partials.landing.sticky-cta')

{{-- Scripts pushed by components, e.g. the captcha in the sign-up form. --}}
@stack('scripts')

</body>
</html>
