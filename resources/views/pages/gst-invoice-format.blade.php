@php
    $appName = config('seo.name', 'Apna Invoice');
    $url = url('/free-gst-invoice-format');
    $today = now()->toAtomString();

    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'GST Invoice Format in India: Mandatory Fields, Rules and a Free Template',
        'inLanguage' => 'en-IN',
        'mainEntityOfPage' => $url,
        'datePublished' => $today,
        'dateModified' => $today,
        'author' => ['@type' => 'Organization', 'name' => config('seo.organization.name'), 'url' => config('seo.organization.url')],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('seo.organization.name'),
            'logo' => ['@type' => 'ImageObject', 'url' => url('/brand/dst-logo.png')],
        ],
        'description' => 'What a GST-compliant invoice must contain under CGST Rule 46: the mandatory fields, the CGST, SGST and IGST split, HSN and SAC codes, and a free template you can use today.',
    ];

    $howToSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'How to make a GST invoice',
        'inLanguage' => 'en-IN',
        'step' => [
            ['@type' => 'HowToStep', 'position' => 1, 'name' => 'Add your details and the customer details', 'text' => 'Put your business name, address and GSTIN, and the customer name, address and GSTIN if they have one.'],
            ['@type' => 'HowToStep', 'position' => 2, 'name' => 'Number and date the invoice', 'text' => 'Use a unique serial number for the financial year and the date of supply.'],
            ['@type' => 'HowToStep', 'position' => 3, 'name' => 'List the items with HSN or SAC', 'text' => 'For each line add the description, HSN or SAC code, quantity, rate and taxable value.'],
            ['@type' => 'HowToStep', 'position' => 4, 'name' => 'Apply the right GST split', 'text' => 'Same state means CGST and SGST. Different state means IGST. Show the rate and amount for each.'],
            ['@type' => 'HowToStep', 'position' => 5, 'name' => 'Show the total and sign', 'text' => 'Add the grand total in figures and words, then sign or add a digital signature.'],
        ],
    ];

    $faqs = [
        ['q' => 'What fields are mandatory on a GST invoice?',
         'a' => 'Under CGST Rule 46 a tax invoice must show the supplier name, address and GSTIN, a unique invoice number and date, the customer details, HSN or SAC codes, the taxable value, the GST rate and amount split into CGST and SGST or IGST, the place of supply for inter-state sales, and the signature of the supplier.'],
        ['q' => 'Is HSN code mandatory on every invoice?',
         'a' => 'HSN for goods and SAC for services is required when either party is GST registered. The number of digits depends on turnover. For sales between two unregistered parties it is not strictly required, though it is good practice.'],
        ['q' => 'When do I charge CGST and SGST versus IGST?',
         'a' => 'When the supplier and the place of supply are in the same state, you charge CGST and SGST. When they are in different states, you charge IGST. The total tax is the same, only the split changes.'],
        ['q' => 'Can I make a GST invoice for free?',
         'a' => 'Yes. You can create a fully GST-compliant invoice free with ' . $appName . '. The CGST, SGST and IGST split, HSN and SAC fields and a clean PDF are handled for you.'],
    ];

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
    title="GST Invoice Format: Mandatory Fields, Rules & Free Template"
    eyebrow="Guide"
    lead="Everything a GST invoice must contain under CGST Rule 46, explained in plain English, with a sample layout and a free way to make one."
    description="GST invoice format for India. The mandatory fields under CGST Rule 46, the CGST, SGST and IGST split, HSN and SAC codes, a sample layout and a free template."
    keywords="GST invoice format, tax invoice format India, GST compliant invoice, HSN code invoice, SAC code invoice, CGST SGST IGST invoice, GST invoice format download, GST bill format"
    type="article"
    :json-ld="[$articleSchema, $howToSchema, $faqSchema]">

    <p>
        A GST invoice is not just a bill. It is a legal document, and the rules for what it must contain come from
        <strong>CGST Rule 46</strong>. Get the fields right and your customer can claim input tax credit and your books
        stay clean. Get them wrong and you invite queries at filing time. This guide lists exactly what belongs on a
        GST invoice, shows a sample layout, and gives you a free way to make one without memorising any of it.
    </p>

    <h2>The mandatory fields on a GST invoice</h2>
    <p>Under CGST Rule 46, a tax invoice issued by a registered person must show all of the following.</p>
    <ul>
        <li><strong>Supplier details</strong>, your business name, address and GSTIN.</li>
        <li><strong>Invoice number</strong>, a unique serial for the financial year, up to 16 characters.</li>
        <li><strong>Invoice date</strong>, the date the invoice is issued.</li>
        <li><strong>Customer details</strong>, name and address, plus GSTIN if they are registered.</li>
        <li><strong>Place of supply</strong>, the state, which decides CGST and SGST versus IGST.</li>
        <li><strong>HSN or SAC code</strong>, for each item of goods or service.</li>
        <li><strong>Description, quantity and unit</strong> for each line.</li>
        <li><strong>Taxable value</strong> after any discount.</li>
        <li><strong>GST rate and amount</strong>, split into CGST and SGST, or IGST.</li>
        <li><strong>Whether tax is on reverse charge</strong>, if it applies.</li>
        <li><strong>Total amount</strong> in figures, often also in words.</li>
        <li><strong>Signature</strong> of the supplier or an authorised person, physical or digital.</li>
    </ul>

    <h2>A sample GST invoice layout</h2>
    <p>Here is how those fields come together on a simple invoice for a same-state sale at 18% GST.</p>

    <div class="not-prose my-6 rounded-xl ring-1 ring-gray-200 overflow-hidden text-sm">
        <div class="bg-gray-50 px-5 py-4 flex items-center justify-between border-b border-gray-200">
            <div>
                <div class="font-bold text-gray-900">Your Business Name</div>
                <div class="text-gray-500 text-xs">GSTIN: 27ABCDE1234F1Z5 · Maharashtra</div>
            </div>
            <div class="text-right">
                <div class="font-bold text-gray-900">TAX INVOICE</div>
                <div class="text-gray-500 text-xs">No: INV/2026-27/0001 · Date: {{ now()->format('d M Y') }}</div>
            </div>
        </div>
        <div class="px-5 py-3 text-xs text-gray-600 border-b border-gray-100">
            <span class="font-semibold text-gray-800">Bill to:</span> Customer Pvt Ltd · GSTIN 27FGHIJ5678K1Z3 · Place of supply: Maharashtra
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50 text-gray-500 text-[11px] uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-3 py-2">Item</th>
                        <th class="text-left px-3 py-2">HSN/SAC</th>
                        <th class="text-right px-3 py-2">Qty</th>
                        <th class="text-right px-3 py-2">Rate</th>
                        <th class="text-right px-3 py-2">Taxable</th>
                        <th class="text-right px-3 py-2">GST 18%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-mono">
                    <tr>
                        <td class="px-3 py-2 font-sans">Consulting services</td>
                        <td class="px-3 py-2">998311</td>
                        <td class="px-3 py-2 text-right">10</td>
                        <td class="px-3 py-2 text-right">1,500.00</td>
                        <td class="px-3 py-2 text-right">15,000.00</td>
                        <td class="px-3 py-2 text-right">2,700.00</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-sans">Website design</td>
                        <td class="px-3 py-2">998314</td>
                        <td class="px-3 py-2 text-right">1</td>
                        <td class="px-3 py-2 text-right">25,000.00</td>
                        <td class="px-3 py-2 text-right">25,000.00</td>
                        <td class="px-3 py-2 text-right">4,500.00</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex flex-col items-end gap-1 text-xs font-mono">
            <div class="flex gap-8"><span class="text-gray-500 font-sans">Taxable value</span><span>₹ 40,000.00</span></div>
            <div class="flex gap-8"><span class="text-gray-500 font-sans">CGST 9%</span><span>₹ 3,600.00</span></div>
            <div class="flex gap-8"><span class="text-gray-500 font-sans">SGST 9%</span><span>₹ 3,600.00</span></div>
            <div class="flex gap-8 text-base font-bold text-gray-900 border-t border-gray-300 pt-1 mt-1"><span class="font-sans">Total</span><span>₹ 47,200.00</span></div>
        </div>
    </div>
    <p class="text-sm text-gray-500">
        Note how the 18% splits into CGST 9% and SGST 9% for a same-state sale. For a customer in another state, the same
        ₹7,200 would show as a single IGST 18% line instead.
    </p>

    <h2>How to make a GST invoice in five steps</h2>
    <ol>
        <li>Add your business details and the customer's details, including GSTINs where they exist.</li>
        <li>Give the invoice a unique number for the financial year, and the date of supply.</li>
        <li>List each item with its HSN or SAC code, quantity, rate and taxable value.</li>
        <li>Apply the correct split. Same state is CGST and SGST. Different state is IGST.</li>
        <li>Show the grand total and add your signature.</li>
    </ol>

    <h2>Make a GST invoice free, without the manual work</h2>
    <p>
        You should not have to remember Rule 46 to send a bill. With <strong>{{ $appName }}</strong> you pick the customer,
        add the items, and the GSTIN, place of supply, CGST, SGST and IGST split and HSN or SAC fields are handled for you.
        You get a clean, compliant PDF to share on WhatsApp in seconds.
        <a href="{{ route('register') }}">Create your first GST invoice free</a>, or try the
        <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a> first.
    </p>

    <h2>Frequently asked questions</h2>
    @foreach ($faqs as $f)
        <h3>{{ $f['q'] }}</h3>
        <p>{{ $f['a'] }}</p>
    @endforeach

</x-layouts.marketing>
