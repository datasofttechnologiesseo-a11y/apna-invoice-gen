@php
    $appName = config('seo.name', 'Apna Invoice');
    $url = url('/free-billing-software');

    $appSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $appName . ' — Free Billing Software',
        'alternateName' => ['Free Billing Software India', 'GST Billing Software', 'Online Billing Software', 'Shop Billing Software'],
        'url' => $url,
        'applicationCategory' => 'BusinessApplication',
        'applicationSubCategory' => 'BillingAndInvoicing',
        'operatingSystem' => 'Any (web-based)',
        'inLanguage' => 'en-IN',
        'isAccessibleForFree' => true,
        'description' => 'Free online billing software for Indian shops, MSMEs and service businesses. GST bills with auto CGST/SGST/IGST, cash memos, thermal receipt printing, UPI QR and WhatsApp share. No installation, no licence fee.',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'],
        'publisher' => ['@type' => 'Organization', 'name' => config('seo.organization.name'), 'url' => config('seo.organization.url')],
    ];

    $faqs = [
        ['q' => 'Is this billing software really free?',
         'a' => 'Yes. ' . $appName . ' is free to use — unlimited bills, customers and products, with no card required. It runs in your browser, so there is nothing to install and no licence to buy.'],
        ['q' => 'Can I use it for a small shop without a computer?',
         'a' => 'Yes. It works on any phone in the browser and can be installed like an app from Chrome. Retail counters can print 80mm thermal receipts directly, and every bill can be shared on WhatsApp.'],
        ['q' => 'Does it make GST bills?',
         'a' => 'Yes. Bills are GST-compliant per CGST Rule 46: GSTIN, HSN/SAC codes, automatic CGST + SGST for same-state sales and IGST for inter-state, amount in words, and multi-copy printing for goods. If you are not GST-registered, it produces simple bills without tax fields.'],
        ['q' => 'How is billing software different from an invoice generator?',
         'a' => 'An invoice generator makes one document at a time. Billing software runs the workflow around it — saved customers and products, payment and dues tracking, cash memos for counter sales, credit notes for returns, and GSTR-1/GSTR-3B reports at filing time. ' . $appName . ' does both.'],
        ['q' => 'Can my accountant use the data at filing time?',
         'a' => 'Yes. Export GSTR-1 as CSV, view a GSTR-3B summary, download a receivables aging report, and take a full ZIP backup of your books — everything a CA needs, in the formats they expect.'],
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
    title="Free Billing Software for India — GST Bills, Cash Memos & Receipts"
    eyebrow="Free tool"
    lead="Run your billing on any phone or computer — GST bills with auto CGST/SGST/IGST, cash memos for counter sales, thermal receipts, UPI QR and WhatsApp share. Free, nothing to install."
    description="Free billing software for Indian shops, MSMEs and service businesses. Make GST bills with auto CGST/SGST/IGST, print thermal receipts, issue cash memos and credit notes, track dues, and export GSTR-1. Online, no installation, no licence fee."
    keywords="free billing software India, billing software for small shop, GST billing software free, online billing software, shop billing software, retail billing software India, billing app for small business, kirana billing software, billing software without installation"
    :json-ld="[$appSchema, $faqSchema]">

    <p>
        Most billing software in India is either a desktop program you must install and license per computer, or
        a monthly subscription that costs more than a small shop's electricity bill. <strong>{{ $appName }}</strong> is
        neither: it's free billing software that runs in the browser on any phone, tablet or computer — make GST bills,
        print receipts, track who owes you money, and hand your CA clean reports at filing time.
    </p>

    <h2>Everything a small business needs to bill</h2>
    <ul>
        <li><strong>GST bills that are actually compliant</strong> — GSTIN, HSN/SAC on every line, automatic CGST + SGST
            for same-state sales and IGST for inter-state, amount in words, and Original/Duplicate/Triplicate copies for
            goods per CGST Rule 48.</li>
        <li><strong>Counter sales</strong> — cash memos for over-the-counter purchases and 80mm thermal receipt printing
            for retail counters.</li>
        <li><strong>Get paid faster</strong> — a UPI QR code on every bill, one-tap WhatsApp share with a pre-written
            message, and payment reminders for overdue bills.</li>
        <li><strong>Know your dues</strong> — every bill tracks paid, partially paid and outstanding amounts; the
            receivables aging report shows who is sitting on your money.</li>
        <li><strong>Filing-ready books</strong> — GSTR-1 CSV export, GSTR-3B summary, expense tracking with input tax
            credit, and a full ZIP backup your accountant can open.</li>
    </ul>

    <h2>No installation, no licence, no per-computer fee</h2>
    <p>
        Because it runs in the browser, there is nothing to install and nothing to update. Open it on the shop
        phone, the office computer and your laptop at home — it's the same account and the same books everywhere.
        You can also install it like an app from Chrome's menu so it sits on your home screen.
    </p>

    <h2>Built for Indian businesses</h2>
    <p>
        Indian number format (lakhs and crores), all GST slabs pre-loaded, financial-year invoice numbering that
        resets on 1 April, state-wise place of supply for correct tax splits, and UPI-first payments. It's billing
        software that works the way Indian businesses actually bill — from kirana counters to consultants.
    </p>

    <h2>Frequently asked questions</h2>
    @foreach ($faqs as $f)
        <h3>{{ $f['q'] }}</h3>
        <p>{{ $f['a'] }}</p>
    @endforeach

    <h2>Start billing in 60 seconds</h2>
    <p>
        <a href="{{ route('register') }}">Create your free account</a> — no card, no trial timer. Add your business
        once, and your first GST bill is a minute away. Want to see the maths first? Try the free
        <a href="{{ route('pages.gst-calculator') }}">GST calculator</a>, or read what a compliant bill must contain
        in our <a href="{{ route('pages.gst-invoice-format') }}">GST invoice format guide</a>.
    </p>
</x-layouts.marketing>
