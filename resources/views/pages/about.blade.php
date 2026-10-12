@php
    $effectiveDate = 'October 11, 2026';

    $aboutSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'name' => 'About Apna Invoice',
        'inLanguage' => 'en-IN',
        'about' => [
            '@type' => 'Organization',
            // Same entity as the home page's Organization node.
            '@id' => rtrim(config('app.url'), '/') . '#organization',
            'name' => config('seo.organization.name'),
            'legalName' => config('seo.organization.legal_name'),
            'url' => config('seo.organization.url'),
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
    ];
@endphp
<x-layouts.marketing
    title="About Us: Made in India for MSMEs"
    eyebrow="Company"
    lead="Made in India by Datasoft Technologies, an Indian SaaS startup based in Delhi NCR. Apna Invoice is built for Indian MSMEs, SMEs, startups, freelancers and CAs who would rather bill customers than maintain spreadsheets."
    description="Apna Invoice is a free GST invoice generator made in India for MSMEs, SMEs, startups, freelancers and CAs. Simple GST billing, built by Datasoft Technologies."
    keywords="about Apna Invoice, made in India invoice software, Indian SaaS company, GST invoicing India, MSME SaaS India, Datasoft Technologies, Delhi NCR SaaS"
    :json-ld="[$aboutSchema]">

    <h2>Who we are</h2>
    <p>
        <strong>Datasoft Technologies (DST)</strong> is an Indian SaaS company based in Delhi NCR, focused on the tools Indian
        MSMEs, SMEs and startups need day to day. We build small, dependable products with no hidden fees. Each feature
        is designed so a proprietor can learn it in under an hour and a CA can audit it without learning new terms.
    </p>

    <dl class="mt-8 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm divide-y divide-gray-100 text-[15px]">
        @foreach ([
            ['Company', 'Datasoft Technologies'],
            ['Based in', config('seo.organization.locality', 'Delhi NCR') . ', India'],
            ['Product', 'Apna Invoice, GST billing for Indian businesses'],
            ['Pricing', 'Free during beta'],
            ['Support', 'Phone, WhatsApp and email, in English and Hindi'],
        ] as [$term, $value])
            <div class="px-5 py-3.5 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-gray-500">{{ $term }}</dt>
                <dd class="mt-0.5 sm:mt-0 sm:col-span-2 font-medium text-gray-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <h2>What we believe</h2>
    <div class="mt-5 grid gap-x-8 gap-y-6 sm:grid-cols-2">
        @foreach ([
            ["Respect the user's time.", 'Invoices should take a minute, not an afternoon.'],
            ['GST-first, not GST-later.', 'Every feature is compliance-aware by default: CGST/SGST/IGST split, place of supply, HSN/SAC, Section 34 credit notes, audit trail under Section 128.'],
            ['Your data is yours.', 'Export it any time; we never sell it. DPDP Act 2023 compliant.'],
            ['Free where we can be.', 'Apna Invoice is free during beta and will stay free for solo operators.'],
        ] as [$belief, $detail])
            <div class="border-t border-gray-200 pt-4">
                <h3 class="font-semibold text-gray-900">{{ $belief }}</h3>
                <p class="mt-1.5 text-[15px] text-gray-600 leading-relaxed">{{ $detail }}</p>
            </div>
        @endforeach
    </div>

    <h2>What we ship</h2>
    <p>
        <strong>Apna Invoice</strong> is our first public product: a GST-compliant invoicing tool for Indian businesses
        below ₹5 crore aggregate turnover. Create tax invoices, track payments, manage customers and stay compliant
        without learning an accounting suite.
    </p>

    <h2>Get in touch</h2>
    <p>
        For partnerships or questions, call or WhatsApp us on
        <a href="tel:{{ config('seo.contact.phone_e164') }}">{{ config('seo.contact.phone_display') }}</a>,
        email <a href="mailto:hello@datasofttechnologies.com">hello@datasofttechnologies.com</a>,
        or visit <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener">datasofttechnologies.com</a>.
    </p>
    <p>
        Writing about us? Our <a href="{{ route('pages.press') }}">press kit</a> has the logo, brand colours and a
        short description you can quote.
    </p>

    <p class="!mt-10 text-sm text-gray-500">Last updated: {{ $effectiveDate }}</p>
</x-layouts.marketing>
