@php
    $appName = config('seo.name', 'Apna Invoice');

    // Everything here is in the product today, and nothing is held back
    // for a paid tier during beta.
    $included = [
        'Unlimited GST invoices, customers and items',
        'CGST, SGST, UTGST and IGST worked out for you',
        'Your logo and signature on five PDF styles',
        'WhatsApp, email and link sharing, with UPI QR',
        'Quotations, credit notes, receipts and reminders',
        'Purchases, expenses, cash memos and stock',
        'Bank statement matching',
        'GSTR-1 JSON, GSTR-2B match and GSTR-3B',
        'Profit and loss, aging and ' . count(config('reports.registry', [])) . ' reports in Excel',
        'Several businesses under one login',
        'Team access for your staff and CA',
        'Backups to download, or by email every week',
    ];

    // The usual alternative for a small business: a register, Excel or a
    // Word template. Plain facts, no competitor names or prices.
    $compare = [
        ['GST split', 'Worked out by hand for each bill', 'From the customer\'s state, on every line'],
        ['Invoice numbers', 'Easy to skip or repeat one', 'Next number in the series, never repeated'],
        ['Sharing', 'Print, scan or type a message', 'WhatsApp, email or a link, with a UPI QR'],
        ['Who owes you', 'Checked against the bank by hand', 'Balance on every invoice, aging report, reminders'],
        ['GSTR-1', 'Typed into the portal invoice by invoice', 'JSON file to upload, Excel for the offline tool'],
        ['Input tax credit', 'Totalled from supplier bills', 'From purchase bills, matched with GSTR-2B'],
        ['Your CA', 'Gets a folder of files every month', 'Logs in with the Accountant role'],
    ];

    $origin = rtrim(config('app.url'), '/');
    $offerSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        '@id' => $origin . '#software',
        'name' => $appName,
        'description' => 'Free GST invoice generator and billing software for Indian MSMEs, SMEs, startups, freelancers and CAs.',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Any (web-based)',
        'url' => $origin . '/',
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'INR',
            'availability' => 'https://schema.org/InStock',
            'url' => $origin . '/pricing',
        ],
        'publisher' => [
            '@type' => 'Organization',
            '@id' => $origin . '#organization',
            'name' => config('seo.organization.name'),
            'url' => config('seo.organization.url'),
        ],
    ];
@endphp

<x-layouts.marketing
    title="Pricing: free GST invoice software for India"
    eyebrow="Pricing"
    lead="Free during beta, for shops, traders, service businesses, freelancers and CAs across India. No card, no limit on invoices, nothing locked."
    description="Apna Invoice pricing: free during beta, with unlimited GST invoices, customers and PDF exports. No card, no feature locks. Beta users keep the free plan."
    keywords="free GST invoice software, free billing software India pricing, free invoice generator no card, GST invoicing free plan"
    type="website"
    :json-ld="[$offerSchema]">

    <div class="rounded-xl bg-white ring-1 ring-gray-200 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 sm:flex sm:items-end sm:justify-between sm:gap-8">
            <div>
                <p class="text-sm font-semibold text-brand-700">Free plan</p>
                <p class="mt-3 flex items-baseline gap-2">
                    <span class="font-display text-5xl font-extrabold tracking-tight text-gray-900">₹0</span>
                    <span class="text-gray-600">a month, during beta</span>
                </p>
                <p class="mt-3 text-gray-700 max-w-md">
                    When paid plans come for larger businesses, everyone who joined during beta keeps this plan.
                </p>
            </div>
            <div class="mt-6 sm:mt-0 sm:w-60 shrink-0">
                @auth
                    <a href="{{ route('dashboard') }}" class="flex items-center justify-center min-h-[48px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Go to dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="flex items-center justify-center min-h-[48px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Create free account</a>
                @endauth
                <p class="mt-2.5 text-center text-sm text-gray-500">No card needed</p>
            </div>
        </div>
        <div class="p-6 sm:p-8 bg-gray-50 border-t border-gray-200">
            <h2 data-toc="skip" class="text-sm font-semibold text-gray-900 !mt-0">What you get</h2>
            <ul class="mt-4 grid sm:grid-cols-2 gap-x-8 gap-y-3 text-[15px] text-gray-700">
                @foreach ($included as $i)
                    <li class="flex items-start gap-2.5 leading-snug">
                        <svg class="w-5 h-5 shrink-0 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ $i }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-5 text-sm text-gray-600">
                See every feature with screens on the <a href="{{ route('pages.features') }}" class="font-semibold text-brand-700 hover:underline">features page</a>.
            </p>
        </div>
    </div>

    <div class="mt-6 rounded-lg border-l-[3px] border-accent-500 bg-accent-50/70 px-5 py-4 text-gray-700">
        <p class="font-semibold text-gray-900">Free for life when a friend joins</p>
        <p class="mt-1">
            Share your referral code from the app. When a friend signs up with it and issues their first invoice, your
            account stays free for life, whatever plans come later.
        </p>
    </div>

    <h2>Compared with Excel or a bill book</h2>
    <div class="mt-4 overflow-x-auto rounded-xl ring-1 ring-gray-200">
        <table class="w-full min-w-[560px] text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">Task</span></th>
                    <th scope="col" class="px-4 py-3 font-semibold">Excel or a bill book</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-brand-800">{{ $appName }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($compare as [$task, $manual, $app])
                    <tr>
                        <th scope="row" class="px-4 py-3 text-left font-semibold text-gray-900 whitespace-nowrap">{{ $task }}</th>
                        <td class="px-4 py-3 text-gray-600">{{ $manual }}</td>
                        <td class="px-4 py-3 text-gray-900">{{ $app }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>Is it really free?</h2>
    <p>
        Yes. There is no card at sign-up, no charge per invoice and no feature behind a paywall. Paid plans for larger
        businesses will come later. If you join during beta, the free plan stays yours for everything it covers today.
    </p>

    <h2>Is there a limit on invoices or users?</h2>
    <p>
        No. Bill as many customers as you like, add more than one business, and give your staff and CA their own logins.
    </p>

    <h2>Who is it for?</h2>
    <p>
        Shops, wholesalers, traders, manufacturers, service businesses, freelancers and CAs across India, registered
        under GST or not. Composition dealers get a bill of supply. Exports and SEZ supplies are billed in ₹; billing in
        a foreign currency is not available yet.
    </p>

    <h2>What if I pay for something later and change my mind?</h2>
    <p>
        Nothing is chargeable during beta, so there is nothing to refund today. When paid plans arrive, the
        <a href="{{ route('pages.refund') }}">refund policy</a> sets out how cancellations and refunds work.
    </p>

    <h2>Get started</h2>
    <p>
        <a href="{{ route('register') }}">Create your free account</a> and issue your first GST invoice today. To see how
        it works first, read the <a href="{{ route('pages.how-to-use') }}">step-by-step guide</a> or the
        <a href="{{ route('pages.faq') }}">FAQ</a>.
    </p>
</x-layouts.marketing>
