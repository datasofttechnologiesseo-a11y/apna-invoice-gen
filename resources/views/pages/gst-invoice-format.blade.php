@php
    $appName = config('seo.name', 'Apna Invoice');
    // Schema URLs on APP_URL, like the canonical, never the request host.
    $origin = rtrim(config('app.url'), '/');
    $url = $origin . '/free-gst-invoice-format';
    // Fixed, real dates - NOT now(). A now()-based value made the Article claim
    // it was published AND modified "today" on every single request, which
    // Google distrusts. Bump dateModified only when this guide's content changes.
    $datePublished = '2026-05-04';
    $dateModified = '2026-07-18';

    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'GST Invoice Format in India: Mandatory Fields, Rules and a Free Template',
        'inLanguage' => 'en-IN',
        'mainEntityOfPage' => $url,
        'image' => $origin . '/brand/og/gst-invoice-format.png',
        'datePublished' => $datePublished,
        'dateModified' => $dateModified,
        // Author and publisher are the Organization node from the home page.
        'author' => ['@type' => 'Organization', '@id' => $origin . '#organization', 'name' => config('seo.organization.name'), 'url' => config('seo.organization.url')],
        'publisher' => [
            '@type' => 'Organization',
            '@id' => $origin . '#organization',
            'name' => config('seo.organization.name'),
            'logo' => ['@type' => 'ImageObject', 'url' => $origin . config('seo.organization.logo'), 'width' => 512, 'height' => 512],
        ],
        'description' => 'What a GST-compliant invoice must contain under CGST Rule 46: the mandatory fields, the CGST, SGST and IGST split, HSN and SAC codes, and a free template you can use today.',
    ];

    // One list for the visible "five steps" and the HowTo, so the markup
    // always describes what the page shows. They had drifted into two
    // different sets of wording.
    $steps = [
        'Add your business details and the customer\'s details, including GSTINs where they exist.',
        'Give the invoice a unique number for the financial year, and the date of supply.',
        'List each item with its HSN or SAC code, quantity, rate and taxable value.',
        'Apply the correct split. Same state is CGST and SGST. Different state is IGST.',
        'Show the grand total and add your signature.',
    ];

    $howToSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'How to make a GST invoice in five steps',
        'inLanguage' => 'en-IN',
        'step' => array_map(fn ($text, $i) => [
            '@type' => 'HowToStep',
            'position' => $i + 1,
            'text' => $text,
        ], $steps, array_keys($steps)),
    ];

    $faqs = [
        ['q' => 'What fields are mandatory on a GST invoice?',
         'a' => 'Under CGST Rule 46 a tax invoice must show the supplier name, address and GSTIN, a unique invoice number and date, the customer details, HSN or SAC codes, the taxable value, the GST rate and amount split into CGST and SGST or IGST, the place of supply for inter-state sales, and the signature of the supplier.'],
        ['q' => 'Is HSN code mandatory on every invoice?',
         'a' => 'HSN for goods and SAC for services is required when either party is GST registered. The number of digits depends on turnover. For sales between two unregistered parties it is not strictly required, though it is good practice.'],
        ['q' => 'When do I charge CGST and SGST versus IGST?',
         'a' => 'When the supplier and the place of supply are in the same state, you charge CGST and SGST. When they are in different states, you charge IGST. The total tax is the same, only the split changes.'],
        ['q' => 'What is the difference between a proforma invoice and a GST tax invoice?',
         'a' => 'A proforma invoice is a pre-sale quote sent before a deal is confirmed. It carries no GST liability and gives the buyer no input tax credit. The tax invoice under CGST Rule 46 is the legal document raised once the supply happens, and it is what fixes your GST liability and the buyer\'s ITC.'],
        ['q' => 'Do I need to generate an e-invoice (IRN) for my invoices?',
         'a' => 'E-invoicing, where each invoice gets an IRN and QR code from the government portal, applies only to businesses above the notified aggregate-turnover threshold (currently Rs 5 crore; always confirm the latest notification). Below that limit a normal Rule 46 tax invoice, like the format described here, is sufficient.'],
        ['q' => 'Can I make a GST invoice for free?',
         'a' => 'Yes. You can create a fully GST-compliant invoice free with ' . $appName . '. The CGST, SGST and IGST split, HSN and SAC fields and a clean PDF are handled for you.'],
    ];

    // The sample invoice is dated today, so its number carries the current
    // financial year (April to March) and never looks stale.
    $sampleDate = now();
    $fyStart = $sampleDate->month >= 4 ? $sampleDate->year : $sampleDate->year - 1;
    $sampleNumber = 'INV/' . $fyStart . '-' . substr((string) ($fyStart + 1), -2) . '/0042';

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
    title="GST Invoice Format: Rules & Template"
    eyebrow="Guide"
    lead="Everything a GST invoice must contain under CGST Rule 46, explained in plain English, with a sample layout and a free way to make one."
    description="GST invoice format for India. The mandatory fields under CGST Rule 46, the CGST, SGST and IGST split, HSN and SAC codes, a sample layout and a free template."
    keywords="GST invoice format, tax invoice format India, GST compliant invoice, HSN code invoice, SAC code invoice, CGST SGST IGST invoice, GST invoice format download, GST bill format"
    type="article"
    :json-ld="[$articleSchema, $howToSchema, $faqSchema]">

    <p>
        A GST invoice is not just a bill. It is a legal document, and the rules for what it must contain come from
        <strong>CGST Rule 46</strong>. Get the fields right and your customer can claim input tax credit and your books
        stay clean. Get them wrong and you invite queries at filing time. This guide lists what belongs on a GST
        invoice, shows a sample layout, and gives you a free way to make one without memorising any of it.
    </p>

    <h2>The mandatory fields on a GST invoice</h2>
    <p>Under CGST Rule 46, a tax invoice issued by a registered person must show all of the following.</p>
    <div class="mt-5 overflow-x-auto rounded-xl ring-1 ring-gray-200">
        <table class="w-full text-left text-[15px]">
            <thead class="bg-gray-50 text-sm text-gray-600">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold w-2/5">Field</th>
                    <th scope="col" class="px-4 py-3 font-semibold">What it must show</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 align-top">
                @foreach ([
                    ['Supplier details', 'Your business name, address and GSTIN.'],
                    ['Invoice number', 'A unique serial for the financial year, up to 16 characters.'],
                    ['Invoice date', 'The date the invoice is issued.'],
                    ['Customer details', 'Name and address, plus GSTIN if they are registered.'],
                    ['Place of supply', 'The state, which decides CGST and SGST versus IGST.'],
                    ['HSN or SAC code', 'For each item of goods or service.'],
                    ['Description, quantity and unit', 'For each line.'],
                    ['Taxable value', 'After any discount.'],
                    ['GST rate and amount', 'Split into CGST and SGST, or IGST.'],
                    ['Whether tax is on reverse charge', 'If it applies.'],
                    ['Total amount', 'In figures, often also in words.'],
                    ['Signature', 'Of the supplier or an authorised person, physical or digital.'],
                ] as [$field, $rule])
                    <tr>
                        <th scope="row" class="px-4 py-3 font-semibold text-gray-900">{{ $field }}</th>
                        <td class="px-4 py-3 text-gray-700">{{ $rule }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>A sample GST invoice layout</h2>
    <p>The same fields on an invoice for a same-state sale at 18% GST, laid out the way {{ $appName }} prints it.</p>

    <figure class="mt-6">
        <div class="rounded-xl bg-white ring-1 ring-gray-300 shadow-sm overflow-hidden text-[13px] leading-relaxed text-gray-700">
            <div class="px-5 sm:px-6 py-4 flex flex-wrap items-start justify-between gap-4 border-b border-gray-200">
                <div>
                    <p class="font-display text-base font-bold text-gray-900">Sharma Traders</p>
                    <p>Shop 14, Laxmi Road, Pune 411030</p>
                    <p class="tabular-nums">GSTIN 27AAKFS4512M1Z3</p>
                </div>
                <div class="sm:text-right">
                    <p class="font-bold tracking-wide text-gray-900">TAX INVOICE</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Original for recipient</p>
                    <p class="mt-1 tabular-nums">No. {{ $sampleNumber }}</p>
                    <p class="tabular-nums">Date: {{ $sampleDate->format('d M Y') }}</p>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 border-b border-gray-200">
                <div class="px-5 sm:px-6 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Billed to</p>
                    <p class="mt-1 font-semibold text-gray-900">Kulkarni Infotech Pvt Ltd</p>
                    <p>Baner, Pune 411045</p>
                    <p class="tabular-nums">GSTIN 27AABCK7781Q1ZP</p>
                </div>
                <div class="px-5 sm:px-6 py-3 border-t sm:border-t-0 sm:border-l border-gray-200">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Place of supply</p>
                    <p class="mt-1 font-semibold text-gray-900">Maharashtra (27)</p>
                    <p>Reverse charge: No</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[36rem]">
                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="text-left font-semibold px-5 sm:px-6 py-2.5">Description of goods / services</th>
                            <th scope="col" class="text-left font-semibold px-3 py-2.5">HSN/SAC</th>
                            <th scope="col" class="text-right font-semibold px-3 py-2.5">Qty</th>
                            <th scope="col" class="text-right font-semibold px-3 py-2.5">Rate</th>
                            <th scope="col" class="text-right font-semibold px-3 py-2.5">GST</th>
                            <th scope="col" class="text-right font-semibold px-5 sm:px-6 py-2.5">Taxable value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 tabular-nums">
                        <tr>
                            <td class="px-5 sm:px-6 py-2.5 text-gray-900">Dell Latitude 3540 laptop</td>
                            <td class="px-3 py-2.5">8471</td>
                            <td class="px-3 py-2.5 text-right">2 Nos</td>
                            <td class="px-3 py-2.5 text-right">45,000</td>
                            <td class="px-3 py-2.5 text-right">18%</td>
                            <td class="px-5 sm:px-6 py-2.5 text-right text-gray-900">90,000</td>
                        </tr>
                        <tr>
                            <td class="px-5 sm:px-6 py-2.5 text-gray-900">HP LaserJet printer</td>
                            <td class="px-3 py-2.5">8443</td>
                            <td class="px-3 py-2.5 text-right">1 Nos</td>
                            <td class="px-3 py-2.5 text-right">10,000</td>
                            <td class="px-3 py-2.5 text-right">18%</td>
                            <td class="px-5 sm:px-6 py-2.5 text-right text-gray-900">10,000</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="grid sm:grid-cols-2 border-t border-gray-200">
                <div class="px-5 sm:px-6 py-4 order-2 sm:order-1 border-t sm:border-t-0 border-gray-200">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Amount in words (INR)</p>
                    <p class="mt-1 font-medium text-gray-900">Rupees One Lakh Eighteen Thousand Only</p>
                </div>
                <dl class="px-5 sm:px-6 py-4 space-y-1.5 tabular-nums order-1 sm:order-2 sm:border-l border-gray-200">
                    <div class="flex justify-between gap-4"><dt>Taxable value</dt><dd class="text-gray-900">₹1,00,000</dd></div>
                    <div class="flex justify-between gap-4"><dt>CGST @ 9%</dt><dd class="text-gray-900">₹9,000</dd></div>
                    <div class="flex justify-between gap-4"><dt>SGST @ 9%</dt><dd class="text-gray-900">₹9,000</dd></div>
                    <div class="flex justify-between gap-4 border-t border-gray-200 pt-2 !mt-2.5 text-[15px] font-bold text-gray-900"><dt>Grand Total</dt><dd>₹1,18,000</dd></div>
                </dl>
            </div>
            <div class="px-5 sm:px-6 py-3 border-t border-gray-200 flex items-end justify-between gap-4 text-[12px] text-gray-500">
                <span>Bank and UPI details print here</span>
                <span class="text-right">For Sharma Traders<br><span class="font-semibold text-gray-700">Authorised signatory</span></span>
            </div>
        </div>
        <figcaption class="mt-3 text-sm text-gray-500">
            The 18% splits into CGST 9% and SGST 9% for a same-state sale. For a customer in another state, the same
            ₹18,000 would show as a single IGST 18% line instead.
        </figcaption>
    </figure>

    <h2>How to make a GST invoice in five steps</h2>
    <div class="mt-6">
        <ol>
            @foreach ($steps as $n => $step)
                <li class="relative flex gap-4 {{ $loop->last ? '' : 'pb-5' }}">
                    @unless ($loop->last)
                        <span class="absolute left-4 top-9 bottom-0 w-px -translate-x-1/2 bg-gray-200" aria-hidden="true"></span>
                    @endunless
                    <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white ring-1 ring-gray-300 text-sm font-semibold text-gray-900 tabular-nums" aria-hidden="true">{{ $n + 1 }}</span>
                    <p class="min-w-0 pt-1 text-gray-700">{{ $step }}</p>
                </li>
            @endforeach
        </ol>
    </div>

    <h2>Make a GST invoice free, without the manual work</h2>
    <p>
        You should not have to remember Rule 46 to send a bill. With <strong>{{ $appName }}</strong> you pick the customer,
        add the items, and the GSTIN, place of supply, CGST, SGST and IGST split and HSN or SAC fields are handled for you.
        You get a compliant PDF, ready to share on WhatsApp.
        <a href="{{ route('register') }}">Create your first GST invoice free</a>, or try the
        <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a> first.
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
