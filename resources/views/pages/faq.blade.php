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
    title="Apna Invoice FAQ: GST Invoicing Questions Answered"
    eyebrow="Help"
    lead="Everything about creating GST invoices free with Apna Invoice — how it works, HSN/SAC and GST rates, credit notes, GSTR-1 exports, data safety and more."
    description="FAQs about Apna Invoice, the free GST invoice generator for India: creating invoices, HSN/SAC, CGST/SGST/IGST, credit notes, GSTR-1 and data safety."
    keywords="Apna Invoice FAQ, GST invoice questions, how to create GST invoice, free invoice generator FAQ, GST billing help India"
    type="website"
    :json-ld="[$faqSchema]">

    <p>
        Short answers to the questions people ask most about billing with {{ $appName }}. Still stuck?
        <a href="{{ route('pages.contact') }}">Contact support</a> — a real person replies.
    </p>

    @foreach ($faqs as $f)
        <h2>{{ $f['q'] }}</h2>
        <p>{!! nl2br(e($f['a'])) !!}</p>
    @endforeach

    <h2>Ready to make your first GST invoice?</h2>
    <p>
        It takes about 60 seconds and it's free — no card, unlimited invoices during beta.
        <a href="{{ route('register') }}">Create your free account</a>, try the
        <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>, or read the
        <a href="{{ route('pages.how-to-use') }}">step-by-step guide</a>.
    </p>
</x-layouts.marketing>
