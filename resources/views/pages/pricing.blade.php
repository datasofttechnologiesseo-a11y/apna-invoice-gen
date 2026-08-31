@php
    $appName = config('seo.name', 'Apna Invoice');

    $included = [
        'Unlimited GST invoices (CGST/SGST/IGST)',
        'Unlimited customers, products & HSN/SAC codes',
        'Logo, signature & letterhead on every PDF',
        'Ink-saver PDF export & print-ready view',
        'Email, WhatsApp & 30-day public-link sharing',
        'Payment reminders & automatic receipt numbering',
        'Quotations, credit notes & cash memos',
        'GSTR-1 CSV, GSTR-3B summary & receivables aging',
        'All 36 Indian states & UTs pre-loaded',
        'One-click ZIP backup of all your data',
        'Priority email support',
    ];

    // Offer schema — declares the free plan for rich results.
    $offerSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $appName . ' — GST Invoicing',
        'description' => 'Free GST invoice generator and billing software for Indian MSMEs, SMEs, startups, freelancers and CAs.',
        'brand' => ['@type' => 'Brand', 'name' => $appName],
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'INR',
            'availability' => 'https://schema.org/InStock',
            'url' => url('/pricing'),
        ],
    ];
@endphp

<x-layouts.marketing
    title="Pricing: Free GST Invoice Software for India"
    eyebrow="Beta pricing"
    lead="Free for Indian MSMEs, SMEs, startups and freelancers — unlimited invoices, customers and PDF exports. No credit card, no feature locks, no hidden add-ons."
    description="Apna Invoice pricing: free during beta — unlimited GST invoices, customers & PDF exports, no card, no feature locks. Beta users keep the free plan."
    keywords="free GST invoice software, free billing software India pricing, free invoice generator no card, GST invoicing free plan"
    type="website"
    :json-ld="[$offerSchema]">

    <p>
        {{ $appName }} is <strong>free during beta</strong>. Sign up today and get unlimited invoices, customers and
        PDF exports — no credit card, no feature locks, no hidden add-ons. When we launch paid tiers for larger
        businesses, <strong>beta users keep the free plan</strong>.
    </p>

    <div class="not-prose my-8 rounded-2xl ring-1 ring-gray-200 shadow-sm overflow-hidden md:grid md:grid-cols-2">
        <div class="p-8 bg-gradient-to-br from-brand-900 to-brand-700 text-white">
            <div class="text-xs font-bold tracking-widest uppercase text-accent-300">Early-bird plan</div>
            <h2 class="mt-2 font-display text-2xl font-extrabold text-white">DST Invoice · Free</h2>
            <div class="mt-6 flex items-baseline gap-2">
                <span class="text-6xl font-black">₹0</span>
                <span class="text-brand-200 text-sm">/ forever, during beta</span>
            </div>
            <p class="mt-4 text-brand-100 text-sm">For Indian solo professionals, SMEs &amp; startups billing up to 500 invoices/month.</p>
            <a href="{{ route('register') }}" class="mt-8 block text-center px-6 py-3 bg-accent-500 hover:bg-accent-600 text-accent-900 font-bold rounded-xl transition">Claim free account →</a>
        </div>
        <div class="p-8 bg-white">
            <div class="text-xs font-bold tracking-widest uppercase text-brand-700">What's included</div>
            <ul class="mt-4 space-y-2.5">
                @foreach ($included as $i)
                    <li class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 mt-1 text-money-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-gray-700 text-sm">{{ $i }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <h2>Is it really free?</h2>
    <p>
        Yes. There's no card required to sign up, no per-invoice charge, and no feature locked behind a paywall during
        beta. We'll introduce paid tiers for larger businesses later, but everyone who joins during beta keeps the free
        plan for what it covers today.
    </p>

    <h2>Who is the free plan for?</h2>
    <p>
        Indian freelancers, small shops, MSMEs, SMEs, startups and CAs billing within India in ₹. If you need export
        billing under LUT/Bond, SEZ supplies or multi-currency, those are intentionally out of scope for now.
    </p>

    <h2>Get started</h2>
    <p>
        <a href="{{ route('register') }}">Create your free account</a> in about a minute. Not sure yet? See the full
        <a href="{{ route('pages.features') }}">feature list</a> or read
        <a href="{{ route('pages.how-to-use') }}">how it works</a>.
    </p>
</x-layouts.marketing>
