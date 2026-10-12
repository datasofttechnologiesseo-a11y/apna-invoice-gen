@php
    $appName = config('seo.name', 'Apna Invoice');
    // Schema URLs on APP_URL, like the canonical, never the request host.
    $origin = rtrim(config('app.url'), '/');
    $url = $origin . '/cash-memo-format';

    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'Cash Memo Format: What It Is, What It Must Contain, and a Free Way to Make One',
        'inLanguage' => 'en-IN',
        'mainEntityOfPage' => $url,
        'image' => $origin . '/brand/og/cash-memo-format.png',
        // Fixed, real dates (first published / last real content change),
        // never now(). Bump dateModified only when the guide itself changes.
        'datePublished' => '2026-07-07',
        'dateModified' => '2026-10-11',
        // Author and publisher are the Organization node from the home page.
        'author' => ['@type' => 'Organization', '@id' => $origin . '#organization', 'name' => config('seo.organization.name'), 'url' => config('seo.organization.url')],
        'publisher' => [
            '@type' => 'Organization',
            '@id' => $origin . '#organization',
            'name' => config('seo.organization.name'),
            'logo' => ['@type' => 'ImageObject', 'url' => $origin . config('seo.organization.logo'), 'width' => 512, 'height' => 512],
        ],
        'description' => 'Cash memo format explained for Indian businesses: mandatory fields, cash memo vs invoice vs receipt, GST treatment, and a free online cash memo maker.',
    ];

    $faqs = [
        ['q' => 'What is a cash memo?',
         'a' => 'A cash memo is the document a seller issues for a cash (spot-payment) sale. It records what was sold, the quantity, rate and total, and acts as proof of purchase for the buyer and a record of sale for the seller. It is common for over-the-counter retail and small purchases.'],
        ['q' => 'What is the difference between a cash memo and an invoice?',
         'a' => 'An invoice is a demand for payment: it is issued first and payment may follow later (credit sale). A cash memo is issued when payment happens on the spot, so nothing is outstanding. Legally, a GST-registered seller\'s tax invoice has prescribed fields under CGST Rule 46; a cash memo for small B2C sales is simpler.'],
        ['q' => 'What should a cash memo format contain?',
         'a' => 'Seller name and address (plus GSTIN if registered), a serial number and date, item-wise description with quantity, rate and amount, any discount, the GST rate and split if tax is charged, the grand total (ideally in words), and the payment mode. A signature or stamp adds authenticity.'],
        ['q' => 'Is a cash memo valid for GST?',
         'a' => 'For B2C sales below ₹200 a registered seller need not issue a full tax invoice for each sale (a consolidated invoice at day-end is allowed). Above that, or when the buyer asks, a tax invoice with GSTIN and HSN/SAC is the safe course. If the seller charges GST on a cash memo, the CGST/SGST or IGST split must be shown.'],
        ['q' => 'Can I make a cash memo online for free?',
         'a' => 'Yes. For a counter sale, bill the walk-in customer in ' . $appName . ': itemised lines with HSN/SAC, automatic GST split, discount, payment mode and amount in words, printed on A4 or an 80 mm thermal printer. For a cash purchase where the seller gives no bill, record a cash memo; it goes straight into your expenses with the GST input tracked.'],
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
    title="Cash Memo Format & Free Maker"
    eyebrow="Guide"
    lead="What a cash memo must contain, how it differs from an invoice and a receipt, when GST applies, and a free way to make printable cash memos online."
    description="Cash memo format for India: the fields every cash memo needs, cash memo vs invoice vs receipt, GST on cash sales, plus a free online cash memo maker."
    keywords="cash memo format, cash memo bill format, cash memo format in word, cash memo vs invoice, cash memo GST, cash memo maker online, cash memo format for shop, kirana cash memo, cash bill format India"
    type="article"
    :json-ld="[$articleSchema, $faqSchema]">

    <p>
        Every counter sale needs a paper trail. The <strong>cash memo</strong> is the simplest one there is: proof
        that goods changed hands and cash was received, in one small document. This guide covers the standard cash
        memo format, how it differs from an invoice and a receipt, what GST expects of it, and a free way to make
        printable cash memos without designing anything in Word.
    </p>

    <h2>The standard cash memo format</h2>
    <p>A complete cash memo carries:</p>
    <div class="mt-5 overflow-x-auto rounded-xl ring-1 ring-gray-200">
        <table class="w-full text-left text-[15px]">
            <thead class="bg-gray-50 text-sm text-gray-600">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold w-2/5">Field</th>
                    <th scope="col" class="px-4 py-3 font-semibold">What to show</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 align-top">
                @foreach ([
                    ['Seller details', 'Business name, address, phone, and GSTIN if registered.'],
                    ['Memo number and date', 'A serial number unique within your business, and the sale date.'],
                    ['Item lines', 'Description, HSN/SAC (if GST applies), quantity, unit, rate and amount.'],
                    ['Discount', 'Shown before tax, if any.'],
                    ['GST', 'The rate and the CGST + SGST split (same state) or IGST (inter-state), when tax is charged.'],
                    ['Grand total', 'In figures and, ideally, in words to prevent tampering.'],
                    ['Payment mode', 'Cash, UPI or card, plus a reference number for digital payments.'],
                    ['Signature or stamp', 'Of the seller or the authorised person.'],
                ] as [$field, $rule])
                    <tr>
                        <th scope="row" class="px-4 py-3 font-semibold text-gray-900">{{ $field }}</th>
                        <td class="px-4 py-3 text-gray-700">{{ $rule }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>Cash memo vs invoice vs receipt</h2>
    <p>
        The three are often confused, but the distinction is simple. An <strong>invoice</strong> asks for payment: it
        is issued for credit sales and payment follows later. A <strong>cash memo</strong> records a spot sale: payment
        happened at the counter and nothing is outstanding. A <strong>receipt</strong> acknowledges a payment against an
        earlier invoice. If you sell on credit, read our
        <a href="{{ route('pages.gst-invoice-format') }}">GST invoice format guide</a>, since a tax invoice has stricter,
        legally prescribed fields.
    </p>
    <div class="mt-5 overflow-x-auto rounded-xl ring-1 ring-gray-200">
        <table class="w-full min-w-[32rem] text-left text-[15px]">
            <thead class="bg-gray-50 text-sm text-gray-600">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">Document</span></th>
                    <th scope="col" class="px-4 py-3 font-semibold">Issued when</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Money outstanding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 align-top text-gray-700">
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900">Invoice</th>
                    <td class="px-4 py-3">A credit sale is made</td>
                    <td class="px-4 py-3">Yes, until the customer pays</td>
                </tr>
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900">Cash memo</th>
                    <td class="px-4 py-3">A sale is paid for at the counter</td>
                    <td class="px-4 py-3">None</td>
                </tr>
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900">Receipt</th>
                    <td class="px-4 py-3">Payment arrives against an earlier invoice</td>
                    <td class="px-4 py-3">Reduced by the amount received</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>GST on cash sales</h2>
    <p>
        A GST-registered seller charging tax on a cash sale must show the rate and the correct split: CGST + SGST when
        buyer and seller are in the same state, IGST across states. For small B2C sales under ₹200, GST law permits a
        consolidated invoice at the end of the day instead of one per sale. Unregistered sellers issue plain cash memos
        with no tax fields at all. To check the maths on any amount, use the
        <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>.
    </p>

    <h2>Make cash memos online, free</h2>
    <p>
        {{ $appName }} handles both sides of a cash deal. For a <strong>counter sale</strong>, bill the walk-in
        customer with itemised lines, HSN/SAC, automatic CGST/SGST or IGST split, discount, payment mode and amount in
        words, then print it on A4 or an 80 mm thermal printer, or share it on WhatsApp. For a <strong>cash
        purchase</strong> where the seller gives no bill, record a cash memo in your own books: it goes straight into
        your expenses, with the GST input tracked for GSTR-3B. <a href="{{ route('register') }}">Create a free account</a>
        and make your first bill in about a minute.
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
