@php
    $appName = config('seo.name', 'Apna Invoice');

    // Everything here is in the product today, and nothing is held back
    // for a paid tier during beta.
    $included = [
        'Unlimited GST invoices with CGST, SGST and IGST',
        'Unlimited customers, items and HSN/SAC codes',
        'Your logo, signature and letterhead on every PDF',
        'Five PDF styles, in colour or ink-saving',
        'Share by WhatsApp, email or a 30-day link',
        'Payment reminders and numbered receipts',
        'Quotations, credit notes and cash memos',
        'Purchases, expenses, stock and bank matching',
        'GSTR-1 JSON, GSTR-2B match and GSTR-3B',
        'Team access for your accountant or CA',
        'Backups to download, or by email every week',
        'Support by WhatsApp, phone and email',
    ];

    // The free plan, declared on the software itself. This was a Product,
    // which Google reads as a merchant listing and flags for the image,
    // shipping and return-policy fields a web app cannot have. URLs are on
    // APP_URL, like the canonical, never the request host.
    $offerSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        // Same app as the home page's node.
        '@id' => rtrim(config('app.url'), '/') . '#software',
        'name' => $appName,
        'description' => 'Free GST invoice generator and billing software for Indian MSMEs, SMEs, startups, freelancers and CAs.',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Any (web-based)',
        'url' => rtrim(config('app.url'), '/') . '/',
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'INR',
            'availability' => 'https://schema.org/InStock',
            'url' => rtrim(config('app.url'), '/') . '/pricing',
        ],
        'publisher' => [
            '@type' => 'Organization',
            '@id' => rtrim(config('app.url'), '/') . '#organization',
            'name' => config('seo.organization.name'),
            'url' => config('seo.organization.url'),
        ],
    ];
@endphp

<x-layouts.marketing
    title="Pricing: free GST invoice software for India"
    eyebrow="Beta pricing"
    lead="Free for Indian MSMEs, SMEs, startups and freelancers, with unlimited invoices, customers and PDF exports. No credit card, no feature locks, no hidden add-ons."
    description="Apna Invoice pricing: free during beta, with unlimited GST invoices, customers and PDF exports. No card, no feature locks. Beta users keep the free plan."
    keywords="free GST invoice software, free billing software India pricing, free invoice generator no card, GST invoicing free plan"
    type="website"
    :json-ld="[$offerSchema]">

    <p>
        {{ $appName }} is <strong>free during beta</strong>. When paid tiers launch for larger businesses,
        <strong>beta users keep the free plan</strong>.
    </p>

    <div class="mt-8 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 sm:flex sm:items-end sm:justify-between sm:gap-8">
            <div>
                <p class="text-sm font-semibold text-brand-700">Beta plan</p>
                <h2 data-toc="skip" class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $appName }} Free</h2>
                <p class="mt-5 flex items-baseline gap-2">
                    <span class="font-display text-5xl font-extrabold tracking-tight text-gray-900">₹0</span>
                    <span class="text-gray-500">per month, during beta</span>
                </p>
                <p class="mt-2 text-gray-600">For Indian freelancers, shops, MSMEs and startups.</p>
            </div>
            <div class="mt-6 sm:mt-0 sm:w-60 shrink-0">
                @auth
                    <a href="{{ route('dashboard') }}" class="flex items-center justify-center min-h-[48px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Go to dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="flex items-center justify-center min-h-[48px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Create free account</a>
                @endauth
                <p class="mt-2.5 text-center text-sm text-gray-500">No card needed</p>
            </div>
        </div>
        <div class="p-6 sm:p-8 bg-gray-50 border-t border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Included in the free plan</h3>
            <ul class="mt-4 grid sm:grid-cols-2 gap-x-8 gap-y-3 text-[15px] text-gray-700">
                @foreach ($included as $i)
                    <li class="flex items-start gap-2.5 leading-snug">
                        <svg class="w-5 h-5 shrink-0 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ $i }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="mt-6 rounded-lg border-l-[3px] border-accent-500 bg-accent-50/70 px-5 py-4 text-gray-700">
        <p class="font-semibold text-gray-900">Refer a friend and get lifetime free access.</p>
        <p class="mt-1">
            Share your referral code from the app. When a friend signs up with it and issues their first invoice, your
            account is free for life.
        </p>
    </div>

    <h2>Is it really free?</h2>
    <p>
        Yes. No card is required to sign up, there is no per-invoice charge, and no feature is locked behind a paywall
        during beta. Paid tiers for larger businesses will come later, but everyone who joins during beta keeps the free
        plan for what it covers today.
    </p>

    <h2>Who is the free plan for?</h2>
    <p>
        Indian freelancers, small shops, MSMEs, SMEs, startups and CAs. Export and SEZ invoices are supported, billed
        in ₹. Billing in a foreign currency is not available yet.
    </p>

    <h2>What if I pay for something later and change my mind?</h2>
    <p>
        Nothing is chargeable during beta, so there is nothing to refund today. When paid tiers arrive, our
        <a href="{{ route('pages.refund') }}">refund policy</a> sets out how cancellations and refunds work.
    </p>

    <h2>Get started</h2>
    <p>
        <a href="{{ route('register') }}">Create your free account</a> in about a minute. Not sure yet? See the full
        <a href="{{ route('pages.features') }}">feature list</a> or read
        <a href="{{ route('pages.how-to-use') }}">how it works</a>.
    </p>
</x-layouts.marketing>
