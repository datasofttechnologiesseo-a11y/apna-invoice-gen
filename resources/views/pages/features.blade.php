@php
    $appName = config('seo.name', 'Apna Invoice');

    // Who uses it, in their own terms. Only what the software does today.
    $businesses = [
        ['Shops and showrooms', 'Bill walk-in customers at the counter, print on 80 mm paper, take UPI or cash, and see which items are running low.'],
        ['Wholesalers and distributors', 'IGST is charged automatically when goods go to another state. Add the transporter and e-way bill number, and track who still owes you.'],
        ['Service businesses and freelancers', 'Bill with SAC codes, record TDS your client deducts, send the invoice on WhatsApp and get paid by UPI.'],
        ['Traders and manufacturers', 'Enter supplier bills, claim input tax credit, keep stock in and out, and see profit and loss for the month.'],
        ['CAs and accountants', 'Get your client\'s GSTR-1 JSON, 2B match and GSTR-3B figures, registers in Excel, and your own login with the Accountant role.'],
    ];

    // Feature areas, each shown with a real screen from a sample business.
    $sections = [
        [
            'id' => 'billing', 'title' => 'GST invoices', 'screen' => 'invoice-form.webp', 'url' => 'apnainvoice.com/invoices/create',
            'alt' => 'Invoice form with four items, HSN codes, GST rates and the IGST total',
            'intro' => 'Pick the customer, add items, and the tax works itself out from the place of supply.',
            'features' => [
                ['CGST and SGST, or IGST', 'Same state or another state, decided from the customer\'s state. UTGST where it applies.'],
                ['HSN and SAC on every line', 'A "Find code" link opens the official GST portal search when you are not sure of a code.'],
                ['Save and issue', 'The invoice gets the next number in your series for the financial year. Drafts stay editable.'],
                ['Your own numbering', 'Continue the series you already use, within the 16-character limit of Rule 46.'],
                ['Five PDF styles', 'With your logo and signature, in colour or ink-saving, with original, duplicate and triplicate copies.'],
                ['Discount before tax', 'One discount on the whole bill, in rupees or percent, applied before GST.'],
                ['Export, SEZ and reverse charge', 'Zero-rated supplies under LUT or with IGST, and reverse-charge invoices with the declaration.'],
                ['Quotations and credit notes', 'Turn an accepted quotation into an invoice in one click. Credit notes follow Section 34.'],
            ],
        ],
        [
            'id' => 'payments', 'title' => 'Share and get paid', 'screen' => 'm-invoice-view.webp', 'mobile' => true,
            'alt' => 'An issued invoice on a phone, with share and payment buttons',
            'intro' => 'Send the bill the way your customer prefers, and keep track of what has come in.',
            'features' => [
                ['WhatsApp, email or a link', 'WhatsApp opens with the message ready. Email goes with the PDF. A link stays valid for 30 days.'],
                ['UPI QR on the invoice', 'Add your UPI ID once. Customers scan and pay the balance from any UPI app.'],
                ['Part payments and TDS', 'Record what came in, by cash, UPI, NEFT or cheque, and TDS the customer deducted. Receipts are numbered.'],
                ['Reminders', 'Email reminders go after the due date. A WhatsApp reminder is one tap from the invoice.'],
                ['Counter bills', 'Walk-in customers and 80 mm thermal printing for shops.'],
            ],
        ],
        [
            'id' => 'purchases', 'title' => 'Purchases and expenses', 'screen' => 'purchases.webp', 'url' => 'apnainvoice.com/purchases',
            'alt' => 'List of purchase invoices with amounts due to each supplier',
            'intro' => 'Enter supplier bills once. Input tax credit and profit and loss follow from them.',
            'features' => [
                ['Purchase invoices', 'Items and GST as on the supplier\'s bill. The supplier\'s state is read from the GSTIN.'],
                ['Input tax credit', 'Eligible credit goes to GSTR-3B. Blocked credit under Section 17(5) stays out.'],
                ['180-day rule', 'Credit on a bill unpaid for 180 days is reversed, and claimed again once you pay (Rule 37).'],
                ['Payments made', 'What you have paid each supplier and what is still due, with payables aging.'],
                ['Expenses and cash memos', 'Rent, electricity, transport and other costs, and cash purchases where the seller gave no bill.'],
            ],
        ],
        [
            'id' => 'stock', 'title' => 'Items and stock', 'screen' => 'stock.webp', 'url' => 'apnainvoice.com/reports/stock-summary',
            'alt' => 'Stock summary with opening, in, out and closing quantity for each item',
            'intro' => 'Turn on stock for the items you keep, and leave it off for services.',
            'features' => [
                ['Stock in and out', 'Issued invoices take stock out, purchases bring it in, cancellations and returns put it back.'],
                ['Low stock', 'Set a reorder level. Items at or below it show on the dashboard.'],
                ['Adjustments', 'Record damage, breakage or a physical count, with the history of every movement.'],
                ['Stock summary', 'Opening, in, out and closing for any period, in Excel or PDF.'],
            ],
        ],
        [
            'id' => 'banking', 'title' => 'Bank reconciliation', 'screen' => 'bank.webp', 'url' => 'apnainvoice.com/bank',
            'alt' => 'Bank statement lines matched to receipts and payments, with one line left to review',
            'intro' => 'Upload the statement you download from net banking. Most lines match on their own.',
            'features' => [
                ['Any bank', 'Excel or CSV as downloaded from HDFC, SBI, ICICI, Axis and other banks.'],
                ['Matched for you', 'Each line is matched to a receipt, supplier payment or expense of the same amount. Cash entries are never matched to the bank.'],
                ['Fill the gaps', 'Add a missing receipt or expense from the bank line, or mark bank charges as done.'],
            ],
        ],
        [
            'id' => 'gst', 'title' => 'GST returns', 'screen' => 'gstr1.webp', 'url' => 'apnainvoice.com/finance/gstr1',
            'alt' => 'GSTR-1 summary for October with B2B, B2C and HSN tables',
            'intro' => 'Prepared from your own invoices and bills. You or your CA file them on the GST portal.',
            'features' => [
                ['GSTR-1', 'Every table, with a JSON file to upload on the portal and an Excel file for the offline tool.'],
                ['GSTR-2B match', 'Upload the 2B file. Each supplier bill shows as matched, mismatched, missing or not yet in 2B.'],
                ['GSTR-3B', 'Tables 3.1, 4 and 6.1, with credit set off in the Rule 88A order and unused credit carried to the next month.'],
                ['HSN summary', 'Table 12, split for B2B and B2C.'],
                ['Monthly or quarterly', 'For monthly filers and the QRMP scheme, with due dates on the dashboard.'],
            ],
        ],
        [
            'id' => 'reports', 'title' => 'Reports for your CA', 'screen' => 'reports.webp', 'url' => 'apnainvoice.com/reports',
            'alt' => 'Reports page listing registers, aging and summary reports',
            'intro' => 'The month-end reports an accountant asks for, in the format they want.',
            'features' => [
                ['Profit and loss', 'Accrual, cash and GST views, net of credit notes and blocked credit.'],
                ['Aging', 'Who owes you and whom you owe, by 0 to 30, 31 to 60, 61 to 90 and over 90 days.'],
                ['Registers and ledgers', 'Sales, purchases, receipts, credit notes and a running ledger for each customer.'],
                ['Excel, CSV, PDF, print', 'Every report and list, for any month, quarter or year.'],
            ],
        ],
        [
            'id' => 'business', 'title' => 'Your business and team', 'screen' => 'dashboard.webp', 'url' => 'apnainvoice.com/dashboard',
            'alt' => 'Dashboard with sales, receivables, payables, overdue invoices and GST due dates',
            'intro' => 'One login for every GSTIN you run, and the right access for each person who helps.',
            'features' => [
                ['Several businesses', 'Each GSTIN with its own logo, numbering and books.'],
                ['Team roles', 'Admin, Accountant or CA, and Billing staff. Staff can bill but cannot cancel invoices or see accounts.'],
                ['Activity log and books lock', 'Every change is recorded. Lock a closed year so nothing in it can be changed.'],
                ['Import and backup', 'Bring customers, items and vendors in from Excel. Download all your data any time, or get it by email every week.'],
                ['On your phone', 'Works in the phone\'s browser and installs on the home screen like an app.'],
            ],
        ],
    ];

    $compliance = [
        'Every detail Rule 46 needs on a tax invoice',
        'Original, duplicate and triplicate copies (Rule 48)',
        'Credit notes within the Section 34 time limit',
        'GST 2.0 rates of 5%, 18% and 40%, and the older slabs',
        'UTGST in Union Territories, IGST across states',
        'Invoice numbers within 16 characters',
        'Input tax credit set off in the Rule 88A order',
        'Amounts in lakh and crore, with the amount in words',
    ];

    $notYet = ['E-invoice (IRN) generation', 'E-way bill generation', 'Filing returns from the app', 'Tally export', 'Hindi screens', 'Recurring invoices'];

    $origin = rtrim(config('app.url'), '/');
    $appSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        '@id' => $origin . '#software',
        'name' => $appName,
        'url' => $origin . '/',
        'image' => $origin . '/brand/icon-512.webp',
        'screenshot' => $origin . '/brand/screens/invoice-view.webp',
        'applicationCategory' => 'BusinessApplication',
        'applicationSubCategory' => 'BillingAndInvoicing',
        'operatingSystem' => 'Any (web-based)',
        'browserRequirements' => 'Requires JavaScript',
        'inLanguage' => 'en-IN',
        'isAccessibleForFree' => true,
        'featureList' => collect($sections)->flatMap(fn ($s) => array_column($s['features'], 0))->values()->all(),
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'],
        'publisher' => [
            '@type' => 'Organization',
            '@id' => $origin . '#organization',
            'name' => config('seo.organization.name'),
            'url' => config('seo.organization.url'),
        ],
    ];
@endphp

<x-layouts.marketing
    title="Features: GST billing, stock and returns"
    eyebrow="Features"
    lead="GST invoices, purchases, stock, bank matching and GST returns for Indian businesses, in one account. Free during beta."
    description="GST invoices, quotations, purchases, stock, bank matching, GSTR-1 JSON, GSTR-2B match and GSTR-3B. Free billing software for Indian businesses."
    keywords="GST billing software features, GST invoice software India, GSTR-1 JSON, GSTR-2B reconciliation, GSTR-3B, bank reconciliation, inventory, free billing software"
    :wide="true"
    :json-ld="[$appSchema]">

    {{-- Real screens first: what the product looks like, before what it says. --}}
    <div class="relative pb-10 sm:pb-14">
        <x-screen src="invoice-view.webp" :eager="true" url="apnainvoice.com/invoices/SE-26-27-0001"
                  alt="An issued GST tax invoice with CGST and SGST, receipt and share options"
                  class="lg:w-[86%]" />
        <x-screen src="m-invoice-form.webp" :mobile="true" :eager="true"
                  alt="New invoice on a phone with the customer chosen and Save and issue at the bottom"
                  class="hidden sm:block absolute right-0 bottom-0 !w-[190px] lg:!w-[230px] !max-w-none" />
    </div>
    <p class="text-sm text-gray-500">Screens from a sample business, Sharma Electricals in Pune.</p>

    {{-- Who it is for --}}
    <section aria-labelledby="who-title" class="mt-12 md:mt-16">
        <h2 id="who-title" class="font-display text-2xl md:text-[1.75rem] font-bold leading-tight text-gray-900">Made for your kind of business</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($businesses as [$who, $what])
                <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 leading-snug">{{ $who }}</h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $what }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Jump to an area. Pinned while the sections scroll past. --}}
    <nav aria-label="Feature areas" class="sticky top-0 z-20 mt-12 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 py-3 bg-white/95 backdrop-blur-sm border-b border-gray-200">
        <ul class="flex gap-1.5 overflow-x-auto pb-0.5 [scrollbar-width:none]">
            @foreach ($sections as $s)
                <li class="shrink-0">
                    <a href="#{{ $s['id'] }}" class="inline-flex items-center min-h-[40px] px-3.5 rounded-lg text-sm font-semibold text-gray-700 hover:text-brand-800 hover:bg-brand-50 transition-colors">{{ $s['title'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Each area: the real screen beside the details, alternating sides. --}}
    @foreach ($sections as $i => $s)
        <section id="{{ $s['id'] }}" class="scroll-mt-20 pt-14 md:pt-20" aria-labelledby="{{ $s['id'] }}-title">
            <div class="grid items-center gap-8 lg:gap-12 {{ ! empty($s['mobile']) ? 'lg:grid-cols-[minmax(0,1fr)_280px]' : 'lg:grid-cols-2' }}">
                <div class="{{ $i % 2 === 1 && empty($s['mobile']) ? 'lg:order-2' : '' }}">
                    <h2 id="{{ $s['id'] }}-title" class="font-display text-2xl md:text-[1.75rem] font-bold leading-tight text-gray-900">{{ $s['title'] }}</h2>
                    <p class="mt-2 text-gray-600 text-[1.0625rem] leading-relaxed">{{ $s['intro'] }}</p>
                    <dl class="mt-6 space-y-4">
                        @foreach ($s['features'] as [$name, $body])
                            <div class="border-l-2 border-brand-200 pl-4">
                                <dt class="font-semibold text-gray-900">{{ $name }}</dt>
                                <dd class="mt-0.5 text-sm text-gray-600 leading-relaxed">{{ $body }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
                <div class="{{ $i % 2 === 1 && empty($s['mobile']) ? 'lg:order-1' : '' }}">
                    <x-screen :src="$s['screen']" :alt="$s['alt']" :url="$s['url'] ?? null" :mobile="! empty($s['mobile'])" />
                </div>
            </div>
        </section>
    @endforeach

    {{-- GST compliance: the page's one dark section. --}}
    <section aria-labelledby="compliance-title" class="mt-16 md:mt-20 rounded-xl bg-brand-900 text-white p-6 sm:p-8 md:p-10">
        <h2 id="compliance-title" class="font-display text-2xl md:text-[1.75rem] font-bold leading-tight text-white">Follows the GST rules</h2>
        <ul class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-3.5">
            @foreach ($compliance as $point)
                <li class="flex items-start gap-3 text-brand-50 leading-relaxed">
                    <svg class="mt-0.5 w-5 h-5 shrink-0 text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ $point }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Honest limits: better found here than after signing up. --}}
    <section aria-labelledby="not-yet-title" class="mt-8 rounded-xl bg-white ring-1 ring-gray-200 p-6 sm:p-8">
        <h2 id="not-yet-title" class="font-display text-xl font-bold text-gray-900">Not in {{ $appName }} yet</h2>
        <p class="mt-2 text-gray-600 leading-relaxed">
            {{ implode(' · ', $notYet) }}.
            Returns are prepared here and filed on the GST portal, and e-way bills are generated on the free government portal, with the number typed on the invoice.
        </p>
    </section>

    {{-- Where to read more. --}}
    <nav aria-label="Read more" class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            [route('pages.pricing'), 'Pricing', 'Free during beta, and what that covers.'],
            [route('pages.faq'), 'FAQ', 'GSTIN, IGST, e-way bills, Tally, phone use.'],
            [route('pages.gst-invoice-format'), 'GST invoice format', 'What Rule 46 asks a tax invoice to show.'],
            [route('pages.gst-calculator'), 'GST calculator', 'Add or remove GST, free and without sign-up.'],
        ] as [$href, $label, $hint])
            <a href="{{ $href }}" class="block rounded-xl bg-white ring-1 ring-gray-200 hover:ring-brand-300 p-4 transition">
                <span class="font-semibold text-brand-800">{{ $label }}</span>
                <span class="mt-1 block text-sm text-gray-600">{{ $hint }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Start --}}
    <section aria-labelledby="start-title" class="mt-8 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-6 sm:p-8 md:flex md:items-center md:justify-between md:gap-10">
        <div class="max-w-2xl">
            <h2 id="start-title" class="font-display text-2xl font-bold leading-tight text-gray-900">Start billing free</h2>
            <p class="mt-2 text-gray-600 leading-relaxed">No card needed and no limit on invoices. Refer a friend who issues their first invoice, and your account stays free for life.</p>
        </div>
        <div class="mt-6 md:mt-0 flex flex-wrap gap-3 md:shrink-0">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Go to dashboard</a>
            @else
                <a href="{{ route('register') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Create free account</a>
            @endauth
            <a href="{{ route('pages.how-to-use') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-white ring-1 ring-gray-300 hover:ring-gray-400 text-gray-800 font-semibold transition">How to use</a>
        </div>
    </section>
</x-layouts.marketing>
