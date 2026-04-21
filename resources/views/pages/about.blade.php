@php
    $effectiveDate = now()->format('F j, Y');
@endphp
<x-layouts.marketing
    title="About Datasoft Technologies"
    eyebrow="Company"
    lead="We build practical software for Indian businesses. Apna Invoice is our flagship product — GST-compliant invoicing that just works."
    description="Meet Datasoft Technologies (DST) — the Indian software company behind Apna Invoice, a free GST-compliant invoice generator built for SMEs, startups, freelancers, and CAs."
    keywords="about Datasoft Technologies, Apna Invoice company, Indian SaaS company, GST invoicing software India, DST invoice generator">

    <h2>Who we are</h2>
    <p>
        Datasoft Technologies (DST) is an Indian software company focused on the tools Indian SMEs and startups actually
        need day-to-day. We ship small, dependable products — no bloat, no enterprise theatre, no hidden fees.
    </p>

    <h2>What we believe</h2>
    <ul>
        <li><strong>Respect the user's time.</strong> Invoices should take a minute, not an afternoon.</li>
        <li><strong>GST-first, not GST-later.</strong> Every feature is compliance-aware by default.</li>
        <li><strong>Data stays in India.</strong> Your business records, your jurisdiction.</li>
        <li><strong>Free where we can be.</strong> Apna Invoice is free during beta and will stay free for solo operators.</li>
    </ul>

    <h2>What we ship</h2>
    <p>
        <strong>Apna Invoice</strong> is our first public product — GST 2.0-ready invoicing for Indian businesses.
        Create professional invoices, track payments, manage customers, and stay compliant without learning an
        accounting suite.
    </p>

    <h2>Get in touch</h2>
    <p>
        Want to partner, ask a question, or just say hi? Reach us at
        <a href="mailto:hello@datasofttechnologies.com">hello@datasofttechnologies.com</a> or visit
        <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer">datasofttechnologies.com</a>.
    </p>

    <p class="mt-10 text-sm text-gray-500">Last updated: {{ $effectiveDate }}</p>
</x-layouts.marketing>
