<x-layouts.marketing
    title="Press kit and brand assets"
    eyebrow="Media"
    lead="Logo, boilerplate, quick facts and press contact for journalists, partners and reviewers covering Apna Invoice."
    description="Apna Invoice press kit: logos, company boilerplate and media contact for journalists covering Indian SaaS, GST tools, MSMEs, and startups."
    keywords="Apna Invoice press kit, Apna Invoice media, Indian SaaS press, GST software press coverage, MSME SaaS India journalist, Datasoft Technologies brand assets">

    <h2>Boilerplate</h2>
    <blockquote class="mt-4 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 sm:px-6 sm:py-5 text-gray-700">
        <p>
            <strong>Apna Invoice</strong> is a free GST-compliant invoicing product built in India for MSMEs, SMEs, startups,
            freelancers and CAs operating below ₹5 crore turnover. Made by <strong>Datasoft Technologies</strong>, the product
            offers unlimited invoices and customers free during beta, with auto CGST/SGST/IGST split,
            HSN/SAC codes, FY-reset numbering, UPI QR on every invoice, WhatsApp share, and Section&nbsp;34 credit notes.
            Built India-first: Indian number formatting (lakhs/crores), state-driven place of supply, and CBIC Rule 46-aligned tax invoices.
        </p>
    </blockquote>

    <h2>Quick facts</h2>
    <dl class="mt-4 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm divide-y divide-gray-100 text-[15px]">
        @foreach ([
            ['Product', 'Apna Invoice'],
            ['Company', 'Datasoft Technologies'],
            ['Jurisdiction', 'India'],
            ['Category', 'GST invoicing / SMB SaaS'],
            ['Pricing', 'Free during beta, paid plans post-launch'],
        ] as [$term, $value])
            <div class="px-5 py-3.5 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-gray-500">{{ $term }}</dt>
                <dd class="mt-0.5 sm:mt-0 sm:col-span-2 font-medium text-gray-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <h2>Logo &amp; assets</h2>
    <div class="mt-4 grid sm:grid-cols-2 gap-4">
        <figure class="rounded-xl ring-1 ring-gray-200 bg-white p-6 text-center">
            <div class="inline-block bg-white p-4 rounded-lg">
                <x-brand-logo class="h-12 w-auto mx-auto" />
            </div>
            <figcaption class="mt-3 text-sm text-gray-500">Primary wordmark (light backgrounds)</figcaption>
        </figure>
        <figure class="rounded-xl ring-1 ring-gray-200 bg-brand-950 p-6 text-center">
            <div class="inline-block bg-white p-4 rounded-lg">
                <x-brand-logo class="h-12 w-auto mx-auto" />
            </div>
            <figcaption class="mt-3 text-sm text-gray-300">On dark backgrounds (use white card)</figcaption>
        </figure>
    </div>

    <h2>Press contact</h2>
    <p>
        For interviews, review units, or comment on Indian SaaS/GST news, email
        <a href="mailto:press@datasofttechnologies.com">press@datasofttechnologies.com</a>
        or call / WhatsApp <a href="tel:{{ config('seo.contact.phone_e164') }}">{{ config('seo.contact.phone_display') }}</a>.
        We typically reply within 24 hours.
    </p>
</x-layouts.marketing>
