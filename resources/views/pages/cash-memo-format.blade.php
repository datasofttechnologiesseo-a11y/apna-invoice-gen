@php
    $appName = config('seo.name', 'Apna Invoice');
    $url = url('/cash-memo-format');

    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'Cash Memo Format: What It Is, What It Must Contain, and a Free Way to Make One',
        'inLanguage' => 'en-IN',
        'mainEntityOfPage' => $url,
        'author' => ['@type' => 'Organization', 'name' => config('seo.organization.name'), 'url' => config('seo.organization.url')],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('seo.organization.name'),
            'logo' => ['@type' => 'ImageObject', 'url' => url('/brand/dst-logo.png')],
        ],
        'description' => 'Cash memo format explained for Indian businesses: mandatory fields, cash memo vs invoice vs receipt, GST treatment, and a free online cash memo maker.',
    ];

    $faqs = [
        ['q' => 'What is a cash memo?',
         'a' => 'A cash memo is the document a seller issues for a cash (spot-payment) sale. It records what was sold, the quantity, rate and total, and acts as proof of purchase for the buyer and a record of sale for the seller. It is common for over-the-counter retail and small purchases.'],
        ['q' => 'What is the difference between a cash memo and an invoice?',
         'a' => 'An invoice is a demand for payment - it is issued first and payment may follow later (credit sale). A cash memo is issued when payment happens on the spot, so nothing is outstanding. Legally, a GST-registered seller\'s tax invoice has prescribed fields under CGST Rule 46; a cash memo for small B2C sales is simpler.'],
        ['q' => 'What should a cash memo format contain?',
         'a' => 'Seller name and address (plus GSTIN if registered), a serial number and date, item-wise description with quantity, rate and amount, any discount, the GST rate and split if tax is charged, the grand total (ideally in words), and the payment mode. A signature or stamp adds authenticity.'],
        ['q' => 'Is a cash memo valid for GST?',
         'a' => 'For B2C sales below ₹200 a registered seller need not issue a full tax invoice for each sale (a consolidated invoice at day-end is allowed). Above that, or when the buyer asks, a tax invoice with GSTIN and HSN/SAC is the safe course. If the seller charges GST on a cash memo, the CGST/SGST or IGST split must be shown.'],
        ['q' => 'Can I make a cash memo online for free?',
         'a' => 'Yes. ' . $appName . ' includes a free cash memo maker: itemised lines with HSN/SAC, automatic GST split, discount, payment mode, amount in words, and a printable PDF - plus every memo flows into your expense ledger automatically.'],
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
    lead="What a cash memo must contain, how it differs from an invoice and a receipt, when GST applies - and a free way to make printable cash memos online."
    description="Cash memo format for India: the fields every cash memo needs, cash memo vs invoice vs receipt, GST on cash sales, plus a free online cash memo maker."
    keywords="cash memo format, cash memo bill format, cash memo format in word, cash memo vs invoice, cash memo GST, cash memo maker online, cash memo format for shop, kirana cash memo, cash bill format India"
    type="article"
    :json-ld="[$articleSchema, $faqSchema]">

    <p>
        Every counter sale needs a paper trail. The <strong>cash memo</strong> is the simplest one there is: proof
        that goods changed hands and cash was received, in one small document. This guide covers the standard cash
        memo format, how it differs from an invoice and a receipt, what GST expects of it - and a free way to make
        clean, printable cash memos without designing anything in Word.
    </p>

    <h2>The standard cash memo format</h2>
    <p>A complete cash memo carries:</p>
    <ul>
        <li><strong>Seller details</strong> - business name, address, phone, and GSTIN if registered.</li>
        <li><strong>Memo number and date</strong> - a serial number unique within your business, and the sale date.</li>
        <li><strong>Item lines</strong> - description, HSN/SAC (if GST applies), quantity, unit, rate and amount.</li>
        <li><strong>Discount</strong> - shown before tax, if any.</li>
        <li><strong>GST</strong> - the rate and the CGST + SGST split (same state) or IGST (inter-state), when tax is charged.</li>
        <li><strong>Grand total</strong> - in figures and, ideally, in words to prevent tampering.</li>
        <li><strong>Payment mode</strong> - cash, UPI, card - plus a reference number for digital payments.</li>
        <li><strong>Signature or stamp</strong> - of the seller or the authorised person.</li>
    </ul>

    <h2>Cash memo vs invoice vs receipt</h2>
    <p>
        The three get mixed up constantly, but the distinction is simple. An <strong>invoice</strong> asks for payment -
        it is issued for credit sales and payment follows later. A <strong>cash memo</strong> records a spot sale - payment
        happened at the counter, nothing is outstanding. A <strong>receipt</strong> acknowledges a payment against an
        earlier invoice. If you sell on credit, read our
        <a href="{{ route('pages.gst-invoice-format') }}">GST invoice format guide</a> - that document has stricter,
        legally prescribed fields.
    </p>

    <h2>GST on cash sales</h2>
    <p>
        A GST-registered seller charging tax on a cash sale must show the rate and the correct split - CGST + SGST when
        buyer and seller are in the same state, IGST across states. For small B2C sales under ₹200, GST law permits a
        consolidated invoice at the end of the day instead of one per sale. Unregistered sellers issue plain cash memos
        with no tax fields at all. To check the maths on any amount, use the
        <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>.
    </p>

    <h2>Make cash memos online, free</h2>
    <p>
        {{ $appName }} includes a <strong>free cash memo maker</strong> built for Indian counters: itemised lines with
        HSN/SAC, automatic CGST/SGST or IGST split, discounts, payment mode with reference number, amount in words, and
        a print-ready PDF. Each memo also lands in your expense ledger automatically, so your books stay filing-ready
        without double entry. <a href="{{ route('register') }}">Create a free account</a> and issue your first cash memo
        in about a minute.
    </p>

    <h2>Frequently asked questions</h2>
    @foreach ($faqs as $f)
        <h3>{{ $f['q'] }}</h3>
        <p>{{ $f['a'] }}</p>
    @endforeach
</x-layouts.marketing>
