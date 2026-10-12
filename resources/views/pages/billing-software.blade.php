@php
    $appName = config('seo.name', 'Apna Invoice');
    // The same app as the home page's SoftwareApplication (same @id and
    // url, both on APP_URL), so Google sees one Apna Invoice, not a second
    // product.
    $appSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        '@id' => rtrim(config('app.url'), '/') . '#software',
        // The product's name; the search phrases stay in alternateName.
        'name' => $appName,
        'alternateName' => ['Free Billing Software India', 'GST Billing Software', 'Online Billing Software', 'Shop Billing Software'],
        'url' => rtrim(config('app.url'), '/') . '/',
        'applicationCategory' => 'BusinessApplication',
        'applicationSubCategory' => 'BillingAndInvoicing',
        'operatingSystem' => 'Any (web-based)',
        'inLanguage' => 'en-IN',
        'isAccessibleForFree' => true,
        'description' => 'Free online billing software for Indian shops, MSMEs and service businesses. GST bills with auto CGST/SGST/IGST, counter bills, thermal receipt printing, UPI QR and WhatsApp share. No installation, no licence fee.',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'],
        'publisher' => ['@type' => 'Organization', '@id' => rtrim(config('app.url'), '/') . '#organization', 'name' => config('seo.organization.name'), 'url' => config('seo.organization.url')],
    ];

    $faqs = [
        ['q' => 'Is this billing software really free?',
         'a' => 'Yes. ' . $appName . ' is free to use: unlimited bills, customers and products, with no card required. It runs in your browser, so there is nothing to install and no licence to buy.'],
        ['q' => 'Can I use it for a small shop without a computer?',
         'a' => 'Yes. It works on any phone in the browser and can be installed like an app from Chrome. Retail counters can print 80mm thermal receipts directly, and every bill can be shared on WhatsApp.'],
        ['q' => 'Does it make GST bills?',
         'a' => 'Yes. Bills are GST-compliant per CGST Rule 46: GSTIN, HSN/SAC codes, automatic CGST + SGST for same-state sales and IGST for inter-state, amount in words, and multi-copy printing for goods. If you are not GST-registered, it produces simple bills without tax fields.'],
        ['q' => 'How is billing software different from an invoice generator?',
         'a' => 'An invoice generator makes one document at a time. Billing software runs the workflow around it: saved customers and products, payment and dues tracking, counter bills for walk-in customers, credit notes for returns, and GSTR-1/GSTR-3B reports at filing time. ' . $appName . ' does both.'],
        ['q' => 'Can my accountant use the data at filing time?',
         'a' => 'Yes. Export GSTR-1 as JSON for the portal or Excel for the offline tool, view a GSTR-3B summary, download a receivables aging report, and take a full ZIP backup of your books, in the formats a CA expects.'],
    ];

    // The sample counter bill is dated today and numbered in the current
    // financial year (April to March).
    $billDate = now();
    $fyStart = $billDate->month >= 4 ? $billDate->year : $billDate->year - 1;
    $billNumber = 'INV/' . $fyStart . '-' . substr((string) ($fyStart + 1), -2) . '/0118';

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
    title="Free Billing Software India"
    eyebrow="Free tool"
    lead="Run your billing on any phone or computer: GST bills with auto CGST/SGST/IGST, counter bills on thermal receipts, UPI QR and WhatsApp share. Free, with nothing to install."
    description="Free billing software for Indian shops and MSMEs. Make GST bills with automatic CGST, SGST and IGST, print counter bills and track dues online."
    keywords="free billing software India, billing software for small shop, GST billing software free, online billing software, shop billing software, retail billing software India, billing app for small business, kirana billing software, billing software without installation"
    :json-ld="[$appSchema, $faqSchema]">

    <p>
        Most billing software in India is either a desktop program you must install and license per computer, or
        a monthly subscription that costs more than a small shop's electricity bill. <strong>{{ $appName }}</strong> is
        free billing software that runs in the browser on any phone, tablet or computer. Make GST bills, print receipts,
        track who owes you money and give your CA clean reports at filing time.
    </p>

    <h2>Everything a small business needs to bill</h2>
    <ul>
        <li><strong>Compliant GST bills.</strong> GSTIN, HSN/SAC on every line, automatic CGST + SGST for same-state
            sales and IGST for inter-state, amount in words, and Original/Duplicate/Triplicate copies for goods per
            CGST Rule 48.</li>
        <li><strong>Counter sales.</strong> Bill walk-in customers and print on 80 mm thermal paper at the counter.
            Cash purchases from sellers who give no bill are recorded as cash memos.</li>
        <li><strong>Get paid faster.</strong> A UPI QR code on every bill, one-tap WhatsApp share with a pre-written
            message, and payment reminders for overdue bills.</li>
        <li><strong>Know your dues.</strong> Every bill tracks paid, partially paid and outstanding amounts, and the
            receivables aging report shows who owes you and for how long.</li>
        <li><strong>Filing-ready books.</strong> GSTR-1 JSON and Excel export, GSTR-3B summary, expense tracking with input
            tax credit, and a full ZIP backup your accountant can open.</li>
    </ul>

    {{-- A counter bill as the 80 mm thermal print lays it out
         (invoices/print-thermal): text-only header, item name on one line,
         quantity x rate and GST on the next, then the tax split and total. --}}
    <figure class="mt-8 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-8 sm:px-8">
        <div class="mx-auto w-[17.5rem] max-w-full bg-white shadow-md ring-1 ring-gray-200 px-4 pt-5 pb-6 font-mono text-[12px] leading-[1.45] text-gray-900 tabular-nums">
            <p class="text-center text-[15px] font-bold tracking-wide">Sharma General Store</p>
            <p class="text-center text-[11px]">Shop 3, Karve Road, Pune (Maharashtra)</p>
            <p class="text-center text-[11px]">GSTIN: 27BXPPS6621K1ZQ</p>
            <hr class="my-2 border-0 border-t border-dashed border-gray-500">
            <p class="text-center text-[13px] font-bold">TAX INVOICE</p>
            <p class="flex justify-between text-[11px]"><span>No: <b>{{ $billNumber }}</b></span><span>{{ $billDate->format('d/m/Y') }}</span></p>
            <hr class="my-2 border-0 border-t border-dashed border-gray-500">
            @foreach ([
                ['Basmati rice 5 kg', '1 Bag', '600', '630'],
                ['Toor dal 1 kg', '2 Pkt', '160', '336'],
                ['Sunflower oil 1 L', '2 Btl', '180', '378'],
            ] as [$item, $qty, $rate, $amount])
                <p>{{ $item }}</p>
                <p class="flex justify-between gap-2 text-[10.5px]"><span>{{ $qty }} &times; ₹{{ $rate }} @ 5% GST</span><b class="text-[12px]">₹{{ $amount }}</b></p>
            @endforeach
            <hr class="my-2 border-0 border-t border-dashed border-gray-500">
            <p class="flex justify-between"><span>Subtotal</span><span>₹1,280</span></p>
            <p class="flex justify-between text-[11px]"><span>CGST</span><span>₹32</span></p>
            <p class="flex justify-between text-[11px]"><span>SGST</span><span>₹32</span></p>
            <hr class="my-2 border-0 border-t border-dashed border-gray-500">
            <p class="flex justify-between text-[15px] font-bold"><span>TOTAL</span><span>₹1,344</span></p>
            <p class="flex justify-between text-[11px]"><span>Paid</span><span>₹1,344</span></p>
        </div>
        <figcaption class="mt-5 text-center text-sm text-gray-500">A counter bill printed on 80 mm thermal paper.</figcaption>
    </figure>

    <h2>No installation, no licence, no per-computer fee</h2>
    <p>
        Because it runs in the browser, there is nothing to install and nothing to update. Open it on the shop
        phone, the office computer and your laptop at home, with the same account and the same books on each.
        You can also install it like an app from Chrome's menu so it sits on your home screen.
    </p>

    <h2>Built for Indian businesses</h2>
    <p>
        Indian number format (lakhs and crores), all GST slabs pre-loaded, financial-year invoice numbering that
        resets on 1 April, state-wise place of supply for correct tax splits, and UPI-first payments. It works the way
        Indian businesses bill, from kirana counters to consultants.
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

    <h2>Start billing today</h2>
    <p>
        <a href="{{ route('register') }}">Create your free account</a>. No card, no trial timer. Add your business
        once, and your first GST bill is a minute away. Want to see the maths first? Try the
        <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>, or read what a compliant bill must contain
        in our <a href="{{ route('pages.gst-invoice-format') }}">GST invoice format guide</a>.
    </p>
</x-layouts.marketing>
