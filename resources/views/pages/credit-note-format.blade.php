@php
    $appName = config('seo.name', 'Apna Invoice');
    $url = url('/gst-credit-note-format');

    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'GST Credit Note Format: Section 34 Rules, Mandatory Fields and Deadline',
        'inLanguage' => 'en-IN',
        'mainEntityOfPage' => $url,
        'author' => ['@type' => 'Organization', 'name' => config('seo.organization.name'), 'url' => config('seo.organization.url')],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('seo.organization.name'),
            'logo' => ['@type' => 'ImageObject', 'url' => url('/brand/dst-logo.png')],
        ],
        'description' => 'GST credit note format under Section 34 of the CGST Act: when to issue one, the mandatory fields, the 30 November deadline, and a free way to issue compliant credit notes.',
    ];

    $faqs = [
        ['q' => 'When should I issue a credit note under GST?',
         'a' => 'Under Section 34 of the CGST Act, a supplier issues a credit note when the taxable value or tax charged on an invoice exceeds what was actually due — goods returned by the buyer, goods found deficient, a post-sale discount agreed in advance, or an invoicing error that overcharged the customer.'],
        ['q' => 'What must a GST credit note contain?',
         'a' => 'The supplier\'s name, address and GSTIN; a unique serial number and date; the recipient\'s details (GSTIN if registered); the number and date of the original tax invoice; the taxable value being credited with the rate and amount of CGST/SGST or IGST reversed; and the signature of the supplier or authorised person.'],
        ['q' => 'What is the deadline for issuing a GST credit note?',
         'a' => 'A credit note must be declared in a GST return no later than 30 November following the end of the financial year in which the original supply was made (or the date of the annual return, if earlier). After that window closes, a credit note cannot reduce your GST liability — only a commercial adjustment outside GST is possible.'],
        ['q' => 'Does a credit note reduce my GST liability?',
         'a' => 'Yes — that is its purpose. The tax reversed on the credit note reduces your outward liability in GSTR-1/GSTR-3B for the period, provided it is declared within the Section 34(2) window and the buyer reverses any input tax credit they claimed.'],
        ['q' => 'Can I issue a partial credit note?',
         'a' => 'Yes. A credit note can cover part of an invoice — for example one returned item out of five, or a percentage discount. The tax reversal is calculated proportionately on the credited amount at the same rate as the original supply.'],
    ];

    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type' => 'Question', 'name' => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $faqs),
    ];
@endphp

<x-layouts.marketing
    title="GST Credit Note Format: Section 34 Rules, Fields & Deadline"
    eyebrow="Guide"
    lead="When to issue a credit note, exactly what it must contain under Section 34 of the CGST Act, the 30 November deadline that catches businesses out — and a free way to issue compliant credit notes."
    description="GST credit note format explained: Section 34 rules, mandatory fields, the 30 November deadline, partial credit notes, and how the tax reversal works. Issue compliant credit notes free with Apna Invoice."
    keywords="credit note format GST, GST credit note, credit note under GST, section 34 CGST credit note, credit note format India, credit note time limit GST, credit note against invoice, sales return GST credit note"
    type="article"
    :json-ld="[$articleSchema, $faqSchema]">

    <p>
        Sales returns, billing mistakes and after-sale discounts all end the same way: you charged more GST than was
        finally due, and the law gives you exactly one instrument to correct it — the <strong>credit note</strong> under
        <strong>Section 34 of the CGST Act</strong>. Get its format and timing right and your liability drops in the next
        return. Get it wrong — or issue it late — and the excess tax stays yours. Here is everything the format requires.
    </p>

    <h2>When a credit note is the right document</h2>
    <ul>
        <li><strong>Goods returned</strong> — the buyer sends back part or all of a supply.</li>
        <li><strong>Deficient goods or services</strong> — quality disputes settled with a price reduction.</li>
        <li><strong>Overbilling</strong> — wrong rate, wrong quantity, or tax charged higher than applicable.</li>
        <li><strong>Post-sale discount</strong> — where the discount was agreed before or at the time of supply.</li>
    </ul>
    <p>
        The reverse case — you undercharged — calls for a <em>debit note</em> (a supplementary invoice), not a credit note.
    </p>

    <h2>Mandatory fields in a GST credit note</h2>
    <ul>
        <li>Supplier's name, address and <strong>GSTIN</strong></li>
        <li>A <strong>unique serial number</strong> (its own series, not the invoice series) and the date of issue</li>
        <li>Recipient's name, address and GSTIN (or delivery address for unregistered buyers)</li>
        <li><strong>Reference to the original tax invoice</strong> — its number and date</li>
        <li>The <strong>taxable value credited</strong>, and the rate and amount of <strong>CGST + SGST or IGST reversed</strong></li>
        <li>Signature or digital signature of the supplier or authorised signatory</li>
    </ul>

    <h2>The deadline most businesses miss</h2>
    <p>
        Section 34(2) sets a hard clock: the credit note must be declared in a return <strong>no later than 30 November
        following the end of the financial year of the original supply</strong> (or the annual return date, if earlier).
        A March 2026 invoice, for instance, can be credited with GST effect only until 30 November 2026. After that, a
        credit note can still settle accounts commercially — but it cannot reduce your GST liability.
        {{ $appName }} tracks this window per invoice and warns you before it closes.
    </p>

    <h2>How the tax reversal works</h2>
    <p>
        The credit note reverses tax at the same rate and in the same heads as the original invoice — CGST + SGST for a
        same-state supply, IGST for inter-state — proportionate to the amount credited. It reduces your outward liability
        in GSTR-1 and GSTR-3B for the period you declare it, and the buyer (if registered) must reverse the matching
        input tax credit. To sanity-check any split, use the free
        <a href="{{ route('pages.gst-calculator') }}">GST calculator</a>.
    </p>

    <h2>Issue compliant credit notes, free</h2>
    <p>
        In {{ $appName }}, a credit note is two clicks from the invoice it corrects: pick a reason, enter the amount
        (full or partial), and the CGST/SGST/IGST reversal, serial numbering, invoice reference and Section 34 deadline
        check are handled for you — with a clean PDF for the buyer and the reversal flowing into your GSTR-1.
        <a href="{{ route('register') }}">Create a free account</a>, or start from the
        <a href="{{ route('pages.gst-invoice-format') }}">GST invoice format guide</a> if you're setting up your
        billing from scratch.
    </p>

    <h2>Frequently asked questions</h2>
    @foreach ($faqs as $f)
        <h3>{{ $f['q'] }}</h3>
        <p>{{ $f['a'] }}</p>
    @endforeach
</x-layouts.marketing>
