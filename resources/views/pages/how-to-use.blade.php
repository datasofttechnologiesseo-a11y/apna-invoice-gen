@php
    $appName = config('seo.name', 'Apna Invoice');

    $tasks = [
        [
            'title' => 'Create and issue a GST invoice',
            'steps' => [
                ['t' => 'Sign up', 'd' => 'Continue with Google, or enter your mobile and verify it with the one-time code we send. No credit card.'],
                ['t' => 'Add your business once', 'd' => 'GSTIN, state and logo. Your letterhead is generated on every invoice automatically.'],
                ['t' => 'Add the line items', 'd' => 'Pick a customer, then type description, HSN/SAC, quantity, rate and GST%. CGST, SGST and IGST are calculated for you.'],
                ['t' => 'Issue and download', 'd' => 'Tap Issue. The invoice is locked, gets a permanent number, and the GST invoice PDF is ready.'],
            ],
            'tip' => 'GST splits automatically: the same state prints CGST + SGST, a different state prints IGST, based on the customer\'s state.',
        ],
        [
            'title' => 'Share the invoice and collect payment',
            'steps' => [
                ['t' => 'Share on WhatsApp', 'd' => 'One tap opens WhatsApp with a pre-filled message and the invoice link.'],
                ['t' => 'Email or public link', 'd' => 'Email the PDF, or copy a secure 30-day public link the customer can open without logging in.'],
                ['t' => 'Scan-and-pay UPI QR', 'd' => 'Add your UPI ID in settings and a payment QR prints on every invoice PDF.'],
                ['t' => 'Record the payment', 'd' => 'Mark full or part payment (with TDS). A payment receipt PDF is generated automatically.'],
            ],
            'tip' => 'Overdue invoices get automatic email reminders, and a WhatsApp follow-up is one tap away.',
        ],
        [
            'title' => 'Month-end reports your CA actually asks for',
            'steps' => [
                ['t' => 'GSTR-1 export', 'd' => 'Download the GSTR-1 JSON to upload on the GST portal, or the Excel file for the offline tool.'],
                ['t' => 'GSTR-3B summary', 'd' => 'A pre-filled summary with output tax, ITC and net payable, laid out like the form.'],
                ['t' => 'Receivables aging', 'd' => 'See who owes you, bucketed by how overdue they are.'],
                ['t' => 'Profit and loss', 'd' => 'Accrual, cash and GST views, net of credit notes, plus a full backup of your data.'],
            ],
            'tip' => 'Every report exports to Excel, CSV and PDF, in the format your accountant prefers.',
        ],
        [
            'title' => 'Quotations and credit notes, the compliant way',
            'steps' => [
                ['t' => 'Send a quotation', 'd' => 'Create a price proposal (proforma) and share it the same way as an invoice.'],
                ['t' => 'Convert in one click', 'd' => 'When the customer accepts, turn the quotation into a tax invoice.'],
                ['t' => 'Issue a credit note', 'd' => 'Raise a Section 34 credit note against any issued invoice, with reason codes.'],
                ['t' => 'Stay audit-ready', 'd' => 'Quotations use a separate FY-aware series and stay out of GST returns until converted.'],
            ],
            'tip' => 'The Section 34(2) credit-note deadline is worked out for each invoice, and credit notes after it are blocked.',
        ],
    ];

    // HowTo schema for the primary "create & issue" flow.
    $howToSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'How to create a GST invoice with ' . $appName,
        'description' => 'Create a GST-compliant tax invoice online for free in about 60 seconds, with automatic CGST/SGST/IGST, HSN/SAC codes, UPI QR and WhatsApp share.',
        'totalTime' => 'PT60S',
        'inLanguage' => 'en-IN',
        'estimatedCost' => ['@type' => 'MonetaryAmount', 'currency' => 'INR', 'value' => '0'],
        'step' => array_map(fn ($i, $s) => [
            '@type' => 'HowToStep',
            'position' => $i + 1,
            'name' => $s['t'],
            'text' => $s['d'],
        ], array_keys($tasks[0]['steps']), $tasks[0]['steps']),
    ];
@endphp

<x-layouts.marketing
    title="How to make a GST invoice for free"
    eyebrow="Learn the tool"
    lead="Step-by-step instructions to create and issue a GST invoice, share it and get paid, pull month-end reports, and raise quotations and credit notes. The full handbook is free to download."
    description="Make and issue a GST invoice in 60 seconds, share it on WhatsApp, collect payment by UPI and export GSTR-1 and GSTR-3B. A free step-by-step guide for India."
    keywords="how to make GST invoice, how to create GST invoice online free, how to use invoice software India, GST billing steps, GSTR-1 export how to"
    type="article"
    :json-ld="[$howToSchema]">

    <p>
        Each job below is set out start to finish. Everything is free during beta.
    </p>

    <h2 id="downloads">Download the manuals</h2>
    <p>
        Read offline, print a copy for the shop or send one to your accountant. Both guides are free PDFs and need
        no sign-up.
    </p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach ([
            [
                'href'  => route('pages.manuals.handbook'),
                'name'  => 'Apna Invoice Handbook',
                'meta'  => 'PDF · 12 chapters',
                'blurb' => 'The complete manual. Every feature explained in plain English, from your first bill to your GST returns, with a glossary at the back.',
            ],
            [
                'href'  => route('pages.manuals.quick-start'),
                'name'  => 'Quick Start Guide',
                'meta'  => 'PDF · 1 page',
                'blurb' => 'Your first GST invoice in five steps. Made to be printed and kept next to the billing counter.',
            ],
        ] as $doc)
            <a href="{{ $doc['href'] }}"
               class="group flex gap-4 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-gray-300 hover:shadow transition">
                <svg class="mt-0.5 w-6 h-6 shrink-0 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2zM12 11v6m0 0l-2.5-2.5M12 17l2.5-2.5"/>
                </svg>
                <span class="min-w-0">
                    <span class="block font-semibold text-gray-900 group-hover:text-brand-700 transition-colors">{{ $doc['name'] }}</span>
                    <span class="block text-sm text-gray-500 mt-0.5">{{ $doc['meta'] }}</span>
                    <span class="block text-[15px] text-gray-600 mt-2 leading-relaxed">{{ $doc['blurb'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    @foreach ($tasks as $task)
        <h2>{{ $task['title'] }}</h2>
        <div class="mt-6">
            <ol>
                @foreach ($task['steps'] as $n => $step)
                    <li class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                        @unless ($loop->last)
                            <span class="absolute left-4 top-9 bottom-0 w-px -translate-x-1/2 bg-gray-200" aria-hidden="true"></span>
                        @endunless
                        <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white ring-1 ring-gray-300 text-sm font-semibold text-gray-900 tabular-nums" aria-hidden="true">{{ $n + 1 }}</span>
                        <div class="min-w-0 pt-0.5">
                            <p class="font-semibold text-gray-900">{{ $step['t'] }}</p>
                            <p class="mt-0.5 text-gray-600">{{ $step['d'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
        <div class="mt-6 flex gap-3 rounded-lg bg-brand-50 px-4 py-3.5 text-[15px] leading-relaxed text-gray-700">
            <svg class="mt-0.5 w-5 h-5 shrink-0 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p><span class="font-semibold text-gray-900">Tip:</span> {{ $task['tip'] }}</p>
        </div>
    @endforeach

    <h2>Try it now</h2>
    <p>
        <a href="{{ route('register') }}">Create your free account</a> and issue your first invoice in a few minutes,
        or run the numbers first with the <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>. See the
        full <a href="{{ route('pages.features') }}">feature list</a> and <a href="{{ route('pages.pricing') }}">pricing</a>.
    </p>
</x-layouts.marketing>
