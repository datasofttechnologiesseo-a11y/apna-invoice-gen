@php
    $effectiveDate = now()->format('F j, Y');
@endphp
<x-layouts.marketing
    title="About Apna Invoice — Indian SaaS for GST Invoicing"
    eyebrow="Company"
    lead="Apna Invoice is built in India, for Indian MSMEs, SMEs, startups, freelancers and CAs. Practical software for people who'd rather be billing customers than wrestling spreadsheets."
    description="Meet Datasoft Technologies — the Indian SaaS company behind Apna Invoice. A free, GST 2.0-compliant invoice generator made in India for MSMEs, SMEs, startups, freelancers and CAs."
    keywords="about Apna Invoice, Indian SaaS company, made in India invoice software, GST invoicing India, MSME SaaS India, Datasoft Technologies">

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
