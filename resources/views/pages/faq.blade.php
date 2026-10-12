@php
    $appName = config('seo.name', 'Apna Invoice');
    $faqs = config('faqs.general');

    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $faqs),
    ];
@endphp

<x-layouts.marketing
    title="GST Invoicing FAQ for India"
    eyebrow="Help"
    lead="Creating GST invoices free with Apna Invoice: how it works, HSN/SAC and GST rates, credit notes, GSTR-1 exports, data safety and more."
    description="FAQs about Apna Invoice, the free GST invoice generator for India: creating invoices, HSN/SAC, CGST/SGST/IGST, credit notes, GSTR-1 and data safety."
    keywords="Apna Invoice FAQ, GST invoice questions, how to create GST invoice, free invoice generator FAQ, GST billing help India"
    type="website"
    :json-ld="[$faqSchema]">

    <p>
        Short answers to the questions people ask most about billing with {{ $appName }}. For anything else,
        <a href="{{ route('pages.contact') }}">contact support</a>.
    </p>

    <div class="mt-8 divide-y divide-gray-200 border-y border-gray-200">
        @foreach ($faqs as $f)
            <div class="py-6">
                <h2 class="font-display text-lg md:text-xl font-bold leading-snug text-gray-900">{{ $f['q'] }}</h2>
                <p class="mt-2 text-gray-700">{!! nl2br(e($f['a'])) !!}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-10 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-6 sm:p-7">
        <h2 class="font-display text-xl font-bold text-gray-900">Ready to make your first GST invoice?</h2>
        <p class="mt-2 text-gray-600">
            It is free, with no card and unlimited invoices during beta.
            <a href="{{ route('register') }}">Create your free account</a>, try the
            <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>, or read the
            <a href="{{ route('pages.how-to-use') }}">step-by-step guide</a>.
        </p>
    </div>
</x-layouts.marketing>
