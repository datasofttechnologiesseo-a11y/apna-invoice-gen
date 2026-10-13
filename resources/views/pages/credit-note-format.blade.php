@php
    $appName = config('seo.name', 'Apna Invoice');
    // Schema URLs on APP_URL, like the canonical, never the request host.
    $origin = rtrim(config('app.url'), '/');
    $url = $origin . '/gst-credit-note-format';

    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'GST Credit Note Format: Section 34 Rules, Mandatory Fields and Deadline',
        'inLanguage' => 'en-IN',
        'mainEntityOfPage' => $url,
        'image' => $origin . '/brand/og/credit-note-format.png',
        // Fixed, real dates (first published / last real content change),
        // never now(). Bump dateModified only when the guide itself changes.
        'datePublished' => '2026-07-07',
        'dateModified' => '2026-07-18',
        // Author and publisher are the Organization node from the home page.
        'author' => ['@type' => 'Organization', '@id' => $origin . '#organization', 'name' => config('seo.organization.name'), 'url' => config('seo.organization.url')],
        'publisher' => [
            '@type' => 'Organization',
            '@id' => $origin . '#organization',
            'name' => config('seo.organization.name'),
            'logo' => ['@type' => 'ImageObject', 'url' => $origin . config('seo.organization.logo'), 'width' => 512, 'height' => 512],
        ],
        'description' => 'GST credit note format under Section 34 of the CGST Act: when to issue one, the mandatory fields, the 30 November deadline, and a free way to issue compliant credit notes.',
    ];

    $faqs = [
        ['q' => 'When should I issue a credit note under GST?',
         'a' => 'Under Section 34 of the CGST Act, a supplier issues a credit note when the taxable value or tax charged on an invoice exceeds what was actually due: goods returned by the buyer, goods found deficient, a post-sale discount agreed in advance, or an invoicing error that overcharged the customer.'],
        ['q' => 'What must a GST credit note contain?',
         'a' => 'The supplier\'s name, address and GSTIN; a unique serial number and date; the recipient\'s details (GSTIN if registered); the number and date of the original tax invoice; the taxable value being credited with the rate and amount of CGST/SGST or IGST reversed; and the signature of the supplier or authorised person.'],
        ['q' => 'What is the deadline for issuing a GST credit note?',
         'a' => 'A credit note must be declared in a GST return no later than 30 November following the end of the financial year in which the original supply was made (or the date of the annual return, if earlier). After that window closes, a credit note cannot reduce your GST liability; only a commercial adjustment outside GST is possible.'],
        ['q' => 'Does a credit note reduce my GST liability?',
         'a' => 'Yes, that is its purpose. The tax reversed on the credit note reduces your outward liability in GSTR-1/GSTR-3B for the period, provided it is declared within the Section 34(2) window and the buyer reverses any input tax credit they claimed.'],
        ['q' => 'Can I issue a partial credit note?',
         'a' => 'Yes. A credit note can cover part of an invoice, for example one returned item out of five, or a percentage discount. The tax reversal is calculated proportionately on the credited amount at the same rate as the original supply.'],
        ['q' => 'What is the difference between a credit note and a debit note?',
         'a' => 'A credit note reduces the value or tax of an original invoice: you charged too much, so your GST liability goes down. A debit note does the opposite: you charged too little (higher rate, extra quantity, price revised up), so it raises the value and increases liability. The supplier issues both. A credit note lowers what the buyer owes and requires them to reverse any excess input tax credit; a debit note increases what the buyer owes and lets them claim additional credit. Both are reported in GSTR-1.'],
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
    title="GST credit note format and rules"
    eyebrow="Guide"
    lead="When to issue a credit note, what it must contain under Section 34 of the CGST Act, the 30 November deadline that catches businesses out, and a free way to issue compliant credit notes."
    description="GST credit note format under Section 34: mandatory fields, the 30 November deadline, credit note vs debit note, and how the tax reversal works. Issue them free."
    keywords="credit note format GST, GST credit note, credit note under GST, section 34 CGST credit note, credit note format India, credit note time limit GST, credit note against invoice, sales return GST credit note"
    type="article"
    :json-ld="[$articleSchema, $faqSchema]">

    <p>
        Sales returns, billing mistakes and after-sale discounts all end the same way: you charged more GST than was
        finally due. The law gives you one instrument to correct it, the <strong>credit note</strong> under
        <strong>Section 34 of the CGST Act</strong>. Issue it in the right format and on time, and your liability drops
        in the next return. Issue it wrongly or late, and the excess tax stays paid.
    </p>

    <h2>When a credit note is the right document</h2>
    <ul>
        <li><strong>Goods returned:</strong> the buyer sends back part or all of a supply.</li>
        <li><strong>Deficient goods or services:</strong> quality disputes settled with a price reduction.</li>
        <li><strong>Overbilling:</strong> wrong rate, wrong quantity, or tax charged higher than applicable.</li>
        <li><strong>Post-sale discount:</strong> where the discount was agreed before or at the time of supply.</li>
    </ul>
    <p>
        The reverse case, where you undercharged, calls for a <em>debit note</em> (a supplementary invoice), not a credit note.
    </p>

    <h2>Credit note vs debit note under GST</h2>
    <p>
        Both are issued by the <strong>supplier</strong> and both adjust an already-issued tax invoice, but they move
        the liability in opposite directions.
    </p>
    <div class="mt-5 overflow-x-auto rounded-xl ring-1 ring-gray-200">
        <table class="w-full min-w-[36rem] text-left text-[15px]">
            <thead class="bg-gray-50 text-sm text-gray-600">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold w-1/4"><span class="sr-only">Point</span></th>
                    <th scope="col" class="px-4 py-3 font-semibold text-gray-900">Credit note</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-gray-900">Debit note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 align-top text-gray-700">
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900">Who issues it and why</th>
                    <td class="px-4 py-3">The supplier, when the invoice value or tax was <em>too high</em> (returns, deficiency, overbilling, agreed discount)</td>
                    <td class="px-4 py-3">The supplier, when it was <em>too low</em> (under-charged rate or quantity, upward price revision)</td>
                </tr>
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900">Effect on GST liability</th>
                    <td class="px-4 py-3"><strong>Reduces</strong> the supplier's output tax</td>
                    <td class="px-4 py-3"><strong>Increases</strong> it</td>
                </tr>
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900">Effect on the buyer</th>
                    <td class="px-4 py-3">The buyer must <strong>reverse</strong> excess input tax credit</td>
                    <td class="px-4 py-3">The buyer can <strong>claim additional</strong> credit</td>
                </tr>
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900">Where it appears</th>
                    <td class="px-4 py-3">Declared in <strong>GSTR-1</strong> (and flows into GSTR-3B); bound by the Section 34(2) 30&nbsp;November deadline</td>
                    <td class="px-4 py-3">Declared in <strong>GSTR-1</strong> (and flows into GSTR-3B); no such time limit for reporting</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>Mandatory fields in a GST credit note</h2>
    <ul>
        <li>Supplier's name, address and <strong>GSTIN</strong></li>
        <li>A <strong>unique serial number</strong> (its own series, not the invoice series) and the date of issue</li>
        <li>Recipient's name, address and GSTIN (or delivery address for unregistered buyers)</li>
        <li><strong>Reference to the original tax invoice</strong>, with its number and date</li>
        <li>The <strong>taxable value credited</strong>, and the rate and amount of <strong>CGST + SGST or IGST reversed</strong></li>
        <li>Signature or digital signature of the supplier or authorised signatory</li>
    </ul>

    <h2>The deadline most businesses miss</h2>
    <div class="mt-4 flex gap-3 rounded-lg border-l-[3px] border-accent-500 bg-accent-50/70 px-5 py-4 text-gray-700">
        <svg class="mt-1 w-5 h-5 shrink-0 text-accent-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <p>
            Section 34(2) sets a hard clock: the credit note must be declared in a return <strong>no later than 30 November
            following the end of the financial year of the original supply</strong> (or the annual return date, if earlier).
            A March 2026 invoice, for instance, can be credited with GST effect only until 30 November 2026. After that, a
            credit note can still settle accounts commercially, but it cannot reduce your GST liability.
            {{ $appName }} tracks this window per invoice and warns you before it closes.
        </p>
    </div>

    <h2>How the tax reversal works</h2>
    <p>
        The credit note reverses tax at the same rate and in the same heads as the original invoice (CGST + SGST for a
        same-state supply, IGST for inter-state), in proportion to the amount credited. It reduces your outward liability
        in GSTR-1 and GSTR-3B for the period you declare it, and the buyer (if registered) must reverse the matching
        input tax credit. To sanity-check any split, use the
        <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>.
    </p>

    <h2>Issue compliant credit notes, free</h2>
    <p>
        In {{ $appName }}, a credit note is two clicks from the invoice it corrects: pick a reason, enter the amount
        (full or partial), and the CGST/SGST/IGST reversal, serial numbering, invoice reference and Section 34 deadline
        check are handled for you. The buyer gets a PDF and the reversal flows into your GSTR-1.
        <a href="{{ route('register') }}">Create a free account</a>, or start from the
        <a href="{{ route('pages.gst-invoice-format') }}">GST invoice format guide</a> if you're setting up your
        billing from scratch.
    </p>

    <h2>Frequently asked questions</h2>
    <div class="mt-4 divide-y divide-gray-200 border-y border-gray-200">
        @foreach ($faqs as $f)
            <div class="py-5">
                <h3 class="font-display text-lg font-bold leading-snug text-gray-900">{{ $f['q'] }}</h3>
                <p class="mt-1.5 text-gray-700">{{ $f['a'] }}</p>
            </div>
        @endforeach
    </div>
</x-layouts.marketing>
