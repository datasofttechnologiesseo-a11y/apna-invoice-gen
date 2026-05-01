@php
    $effectiveDate = now()->format('F j, Y');

    $aboutSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'name' => 'About Apna Invoice',
        'inLanguage' => 'en-IN',
        'about' => [
            '@type' => 'Organization',
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
    title="About Apna Invoice — Built in India for MSMEs, SMEs & Startups"
    eyebrow="Company"
    lead="Made in India by Datasoft Technologies, an Indian SaaS startup based in Delhi NCR. Apna Invoice is built for Indian MSMEs, SMEs, startups, freelancers and CAs — practical software for people who'd rather be billing customers than wrestling spreadsheets."
    description="About Apna Invoice — a free GST invoice generator made in India by Datasoft Technologies. Built for Indian MSMEs, SMEs, startups, freelancers and CAs operating below ₹5 crore turnover."
    keywords="about Apna Invoice, made in India invoice software, Indian SaaS company, GST invoicing India, MSME SaaS India, Datasoft Technologies, Delhi NCR SaaS"
    :json-ld="[$aboutSchema]">

    <h2>Who we are</h2>
    <p>
        <strong>Datasoft Technologies (DST)</strong> is an Indian SaaS company based in Delhi NCR, focused on the tools Indian
        MSMEs, SMEs and startups actually need day-to-day. We ship small, dependable products — no bloat, no enterprise theatre,
        no hidden fees. Every feature is designed so a solo proprietor can master it in under an hour, and a CA can audit it
        without learning a new vocabulary.
    </p>

    <h2>What we believe</h2>
    <ul>
        <li><strong>Respect the user's time.</strong> Invoices should take a minute, not an afternoon.</li>
        <li><strong>GST-first, not GST-later.</strong> Every feature is compliance-aware by default — CGST/SGST/IGST split, place of supply, HSN/SAC, Section&nbsp;34 credit notes, audit trail under Section&nbsp;128.</li>
        <li><strong>Data stays in India.</strong> Your business records, your jurisdiction. DPDP Act 2023 compliant.</li>
        <li><strong>Free where we can be.</strong> Apna Invoice is free during beta and will stay free for solo operators.</li>
    </ul>

    <h2>What we ship</h2>
    <p>
        <strong>Apna Invoice</strong> is our first public product — a GST-compliant invoicing tool for Indian businesses
        operating below ₹5 crore aggregate turnover. Create professional tax invoices, track payments, manage customers,
        and stay compliant — without learning an accounting suite.
    </p>

    <h2>Get in touch</h2>
    <p>
        Want to partner, ask a question, or just say hi? Call or WhatsApp us on
        <a href="tel:{{ config('seo.contact.phone_e164') }}" class="font-mono">{{ config('seo.contact.phone_display') }}</a>,
        email <a href="mailto:hello@datasofttechnologies.com">hello@datasofttechnologies.com</a>,
        or visit <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer">datasofttechnologies.com</a>.
    </p>

    <p class="mt-10 text-sm text-gray-500">Last updated: {{ $effectiveDate }}</p>
</x-layouts.marketing>
