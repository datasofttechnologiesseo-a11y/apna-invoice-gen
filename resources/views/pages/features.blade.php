@php
    $appName = config('seo.name', 'Apna Invoice');

    // Every feature in the product, grouped the way the app's menu is. Only
    // what the software does today: no roadmap items.
    $sections = [
        [
            'id' => 'sales', 'title' => 'Sales and billing', 'tint' => 'brand',
            'intro' => 'GST invoices that are right the first time, shared the way your customers like.',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'features' => [
                ['GST tax invoices', 'CGST and SGST (or UTGST) for same-state sales, IGST across states, picked from the place of supply.'],
                ['Bill of supply', 'Composition dealers get a bill of supply with the declaration the law requires.'],
                ['Draft, then issue', 'Edit a draft freely. Issuing fixes the number in your financial-year series, with no gaps or duplicates.'],
                ['Five PDF styles', 'Classic, Executive, Minimal, Mercantile and Heritage. Colour or ink-saving, with your logo and signature.'],
                ['Original, duplicate, triplicate', 'Choose the copies to print: for the recipient, the transporter and your records.'],
                ['Share in one tap', 'WhatsApp with a ready message, email with the PDF attached, or a secure link valid for 30 days.'],
                ['UPI QR on every invoice', 'Customers scan and pay the exact balance from any UPI app.'],
                ['Quotations', 'Send a quote in its own number series and turn it into an invoice in one click.'],
                ['Credit notes', 'Returns and price corrections under Section 34, with the deadline tracked for you.'],
                ['Payments and receipts', 'Part payments, TDS deducted by the customer, numbered receipts, and the balance kept up to date.'],
                ['Payment reminders', 'Email reminders go out on a schedule after the due date, or follow up on WhatsApp in one tap.'],
                ['Export and SEZ invoices', 'Zero-rated supplies under LUT or with IGST, with port code and shipping bill details.'],
                ['Reverse charge', 'Invoices for supplies where the recipient pays the tax, with the required declaration.'],
                ['Ship-to and e-way bill', 'A separate delivery address, transporter details and the e-way bill number on the invoice.'],
                ['Counter bills', 'Bill walk-in customers and print on 80 mm thermal paper at the counter.'],
                ['Discounts before tax', 'An invoice-level discount, in rupees or percent, applied before GST as the law requires.'],
            ],
        ],
        [
            'id' => 'purchases', 'title' => 'Purchases and expenses', 'tint' => 'accent',
            'intro' => 'Record what you buy so input tax credit and profit are always accurate.',
            'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            'features' => [
                ['Purchase invoices', "Enter supplier bills with items and GST. The supplier's state is read from the GSTIN."],
                ['Input tax credit', 'Eligible GST flows into GSTR-3B; blocked credit under Section 17(5) stays out.'],
                ['Payments made', 'Record what you have paid and see what is still due to each supplier.'],
                ['Expenses', 'Rent, salaries, utilities and other costs by category, with GST input where it applies.'],
                ['Cash memos', 'A voucher in your own books for cash purchases where the seller gives no bill.'],
                ['Vendor book', 'Save suppliers once with GSTIN and state, and pick them on every purchase.'],
            ],
        ],
        [
            'id' => 'inventory', 'title' => 'Items and stock', 'tint' => 'money',
            'intro' => 'Save what you sell once and know what is left on the shelf.',
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            'features' => [
                ['Goods and services', 'Name, HSN or SAC, unit, rate and GST rate, filled in on the invoice when you pick the item.'],
                ['Stock tracking', 'Optional per item: issued invoices reduce stock, purchases add to it, cancellations put it back.'],
                ['Low-stock alerts', 'Set a reorder level and see low items on the dashboard and in the stock summary.'],
                ['Stock adjustments', 'Record damage, returns or a physical count, with a full movement history.'],
                ['Stock value and summary', 'Stock valued at purchase price, and opening, in, out and closing for any period in Excel or PDF.'],
            ],
        ],
        [
            'id' => 'banking', 'title' => 'Banking', 'tint' => 'brand',
            'intro' => 'Reconcile your bank account with your books in minutes.',
            'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
            'features' => [
                ['Upload any bank statement', 'Excel or CSV from net banking, read as downloaded from HDFC, SBI, ICICI, Axis and others.'],
                ['Automatic matching', 'Each bank line is matched to a receipt, supplier payment or expense of the same amount.'],
                ['Fill the gaps', 'Record a missing receipt or expense straight from the bank line, or mark bank charges as done.'],
            ],
        ],
        [
            'id' => 'gst', 'title' => 'GST returns', 'tint' => 'accent',
            'intro' => 'Returns prepared from your own books, ready for you or your CA to file.',
            'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'features' => [
                ['GSTR-1', 'All tables, with a JSON file to upload on the GST portal and an Excel file for the offline tool.'],
                ['GSTR-2B match', 'Upload GSTR-2B and see each supplier invoice as matched, mismatched, missing or not yet in 2B.'],
                ['GSTR-3B', 'Tables 3.1, 4 and 6.1 worked out, with input tax credit set off in the order Rule 88A requires.'],
                ['HSN summary', 'The HSN-wise summary for GSTR-1 Table 12, split for B2B and B2C.'],
                ['Monthly or quarterly', 'Works for monthly filers and the QRMP scheme, with due dates shown on the dashboard.'],
            ],
        ],
        [
            'id' => 'reports', 'title' => 'Reports', 'tint' => 'money',
            'intro' => 'The reports your CA asks for at month-end, one click away.',
            'icon' => 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z',
            'features' => [
                ['Profit and loss', 'Accrual, cash and GST views, net of credit notes and blocked GST.'],
                ['Receivables and payables aging', 'Who owes you and whom you owe, by 0-30, 31-60, 61-90 and 90+ days.'],
                ['Sales and purchase registers', 'Every invoice and bill with taxable value and tax, for any month, quarter or year.'],
                ['Customer ledger', 'A running balance of invoices, payments and credit notes for each customer.'],
                ['More registers', 'Receipts, supplier payments, credit notes, quotations, customers, vendors, items and stock.'],
                ['Excel, CSV, PDF and print', 'Every report and list exports in the format your accountant prefers.'],
            ],
        ],
        [
            'id' => 'business', 'title' => 'Business and team', 'tint' => 'brand',
            'intro' => 'Run every business you own, with the right people given the right access.',
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
            'features' => [
                ['Multiple businesses', 'Each GSTIN with its own logo, numbering and books, switched from one login.'],
                ['Team access with roles', 'Invite an admin, your accountant or CA, or billing staff, each with the right permissions.'],
                ['Activity log', 'Every change is recorded with who made it and when.'],
                ['Books lock', 'Lock a closed financial year so nothing filed can be changed or backdated.'],
                ['Import from Excel', 'Bring in customers, items and vendors from a spreadsheet, with a preview before saving.'],
                ['Backups', 'Download all your data at any time, or have a backup emailed to you every week.'],
                ['Any device', 'Works in any browser on phone, tablet or computer, and installs like an app.'],
            ],
        ],
        [
            'id' => 'tools', 'title' => 'Free tools', 'tint' => 'accent',
            'intro' => 'Useful without an account.',
            'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
            'features' => [
                ['GST calculator', 'Add or remove GST at any rate with the CGST, SGST and IGST split. Free to embed on your own site.'],
                ['Invoice format guides', 'What a GST invoice, cash memo and credit note must contain, with samples.'],
            ],
        ],
    ];

    $featureCount = collect($sections)->sum(fn ($s) => count($s['features']));

    $compliance = [
        'Every field CGST Rule 46 requires on a tax invoice',
        'Copies as Rule 48 requires: original, duplicate, triplicate',
        'Credit notes under Section 34 with the time limit tracked',
        'GST 2.0 rates (5%, 18%, 40%) and the legacy slabs',
        'UTGST for Union Territories and IGST across states',
        'Invoice numbers within the 16-character limit',
        'Rule 88A order for setting off input tax credit',
        'Amounts in lakh and crore, with amount in words',
    ];

    // The same app the home page describes (same @id), listing every feature on this page.
    $origin = rtrim(config('app.url'), '/');
    $orgRef = [
        '@type' => 'Organization',
        '@id' => $origin . '#organization',
        'name' => config('seo.organization.name'),
        'url' => config('seo.organization.url'),
    ];
    $appSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        '@id' => $origin . '#software',
        'name' => $appName,
        'url' => $origin . '/',
        'image' => $origin . '/brand/icon-512.png',
        'applicationCategory' => 'BusinessApplication',
        'applicationSubCategory' => 'BillingAndInvoicing',
        'operatingSystem' => 'Any (web-based)',
        'browserRequirements' => 'Requires JavaScript',
        'inLanguage' => 'en-IN',
        'isAccessibleForFree' => true,
        'featureList' => collect($sections)->flatMap(fn ($s) => array_column($s['features'], 0))->values()->all(),
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'],
        'publisher' => $orgRef,
    ];
@endphp

<x-layouts.marketing
    title="Features: GST billing, stock and returns"
    eyebrow="Features"
    lead="GST billing, purchases, stock, banking and GST returns in one place, built for Indian businesses. Every feature is free during beta."
    description="GST invoices, quotations, credit notes, purchases, stock, bank reconciliation, GSTR-1 JSON, GSTR-2B match and GSTR-3B. Free for Indian businesses."
    keywords="GST billing software features, GST invoice software India, GSTR-1 JSON, GSTR-2B reconciliation, GSTR-3B, bank reconciliation, inventory, free billing software"
    :wide="true"
    :json-ld="[$appSchema]">

    {{-- At a glance. Every figure is counted from the lists above or the
         report registry, so it cannot drift from what the page shows. --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        @foreach ([
            [$featureCount . ' features', 'across ' . count($sections) . ' areas'],
            ['GSTR-1, 2B, 3B', 'prepared from your books'],
            [count(config('reports.registry', [])) . ' reports', 'in Excel, CSV and PDF'],
            ['₹0', 'free during beta'],
        ] as [$big, $small])
            <div class="rounded-xl bg-white ring-1 ring-gray-200 shadow-sm px-4 py-4 sm:px-5">
                <p class="font-display text-xl sm:text-2xl font-extrabold text-gray-900 tabular-nums">{{ $big }}</p>
                <p class="mt-0.5 text-sm text-gray-600">{{ $small }}</p>
            </div>
        @endforeach
    </div>

    {{-- Jump to a section. Pinned while the sections scroll past. --}}
    <nav aria-label="Feature areas" class="sticky top-0 z-20 mt-8 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 py-3 bg-white/95 backdrop-blur-sm border-b border-gray-200">
        <ul class="flex gap-1.5 overflow-x-auto pb-0.5 [scrollbar-width:none]">
            @foreach ($sections as $s)
                <li class="shrink-0">
                    <a href="#{{ $s['id'] }}" class="inline-flex items-center min-h-[40px] px-3.5 rounded-lg text-sm font-semibold text-gray-700 hover:text-brand-800 hover:bg-brand-50 transition-colors">{{ $s['title'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    @foreach ($sections as $s)
        <section id="{{ $s['id'] }}" class="scroll-mt-20 pt-12 md:pt-14" aria-labelledby="{{ $s['id'] }}-title">
            <div class="flex items-start gap-3 max-w-3xl">
                <svg class="mt-1 w-6 h-6 shrink-0 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['icon'] }}"/></svg>
                <div>
                    <h2 id="{{ $s['id'] }}-title" class="font-display text-2xl md:text-[1.75rem] font-bold leading-tight text-gray-900">{{ $s['title'] }}</h2>
                    <p class="mt-1.5 text-gray-600 text-[1.0625rem] leading-relaxed">{{ $s['intro'] }}</p>
                </div>
            </div>
            <ul class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($s['features'] as [$name, $body])
                    <li class="rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5">
                        <h3 class="font-semibold text-gray-900 leading-snug">{{ $name }}</h3>
                        <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">{{ $body }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    {{-- GST compliance: the page's one dark section. --}}
    <section aria-labelledby="compliance-title" class="mt-14 md:mt-16 rounded-xl bg-brand-900 text-white p-6 sm:p-8 md:p-10">
        <h2 id="compliance-title" class="font-display text-2xl md:text-[1.75rem] font-bold leading-tight text-white">Built for Indian GST</h2>
        <ul class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-3.5">
            @foreach ($compliance as $point)
                <li class="flex items-start gap-3 text-brand-50 leading-relaxed">
                    <svg class="mt-0.5 w-5 h-5 shrink-0 text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ $point }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Start --}}
    <section aria-labelledby="start-title" class="mt-8 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-6 sm:p-8 md:flex md:items-center md:justify-between md:gap-10">
        <div class="max-w-2xl">
            <h2 id="start-title" class="font-display text-2xl font-bold leading-tight text-gray-900">Start billing free</h2>
            <p class="mt-2 text-gray-600 leading-relaxed">Create your account and issue your first GST invoice in a few minutes. No card needed. Refer a friend and get lifetime free access.</p>
        </div>
        <div class="mt-6 md:mt-0 flex flex-wrap gap-3 md:shrink-0">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Go to dashboard</a>
            @else
                <a href="{{ route('register') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Create free account</a>
            @endauth
            <a href="{{ route('pages.pricing') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-white ring-1 ring-gray-300 hover:ring-gray-400 text-gray-800 font-semibold transition">See pricing</a>
            <a href="{{ route('pages.how-to-use') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-white ring-1 ring-gray-300 hover:ring-gray-400 text-gray-800 font-semibold transition">How to use</a>
        </div>
    </section>
</x-layouts.marketing>
