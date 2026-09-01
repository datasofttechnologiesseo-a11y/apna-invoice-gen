@php
    $appName = config('seo.name', 'Apna Invoice');

    $tasks = [
        [
            'title' => 'Create & issue a GST invoice',
            'steps' => [
                ['t' => 'Sign up in seconds', 'd' => 'Continue with Google, or enter your mobile and verify it with the one-time code we send. No credit card.'],
                ['t' => 'Add your business once', 'd' => 'GSTIN, state and logo. Your letterhead is generated on every invoice automatically.'],
                ['t' => 'Add the line items', 'd' => 'Pick a customer, then type description, HSN/SAC, quantity, rate and GST%. CGST, SGST and IGST are calculated for you.'],
                ['t' => 'Issue & download', 'd' => 'Tap Issue. The invoice is locked, gets a permanent number, and the GST invoice PDF is ready.'],
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
            'tip' => 'Outstanding invoices can send automatic WhatsApp and email reminders, so you chase payments less.',
        ],
        [
            'title' => 'Month-end reports your CA actually asks for',
            'steps' => [
                ['t' => 'GSTR-1 export', 'd' => 'Download a GSTR-1-friendly CSV of B2B and B2C invoices for the period.'],
                ['t' => 'GSTR-3B summary', 'd' => 'A pre-filled summary with output tax, ITC and net payable, laid out like the form.'],
                ['t' => 'Receivables aging', 'd' => 'See who owes you, bucketed by how overdue they are.'],
                ['t' => 'Profit & loss', 'd' => 'Accrual, cash and GST views, plus an expenses CSV and one-click backup.'],
            ],
            'tip' => 'Every report exports as both PDF (to review) and CSV (for spreadsheets, your accountant or the GST portal).',
        ],
        [
            'title' => 'Quotations and credit notes, the compliant way',
            'steps' => [
                ['t' => 'Send a quotation', 'd' => 'Create a price proposal (proforma) and share it just like an invoice.'],
                ['t' => 'Convert in one click', 'd' => 'When the customer accepts, turn the quotation into a tax invoice instantly.'],
                ['t' => 'Issue a credit note', 'd' => 'Raise a Section 34 credit note against any issued invoice, with reason codes.'],
                ['t' => 'Stay audit-ready', 'd' => 'Quotations use a separate FY-aware series and stay out of GST returns until converted.'],
            ],
            'tip' => 'We compute the Section 34(2) credit-note deadline per invoice and block late issuance, so you never file a worthless credit note.',
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
        'quotationdCost' => ['@type' => 'MonetaryAmount', 'currency' => 'INR', 'value' => '0'],
        'step' => array_map(fn ($i, $s) => [
            '@type' => 'HowToStep',
            'position' => $i + 1,
            'name' => $s['t'],
            'text' => $s['d'],
        ], array_keys($tasks[0]['steps']), $tasks[0]['steps']),
    ];
@endphp

<x-layouts.marketing
    title="How to Make a GST Invoice Free"
    eyebrow="Learn the tool"
    lead="Pick a task and follow the exact steps - create and issue a GST invoice, share it and get paid, pull month-end reports, or raise quotations and credit notes. Simple language, no jargon. You can download the full handbook too."
    description="Make and issue a GST invoice in 60 seconds, share it on WhatsApp, collect payment by UPI and export GSTR-1 and GSTR-3B. A free step-by-step guide for India."
    keywords="how to make GST invoice, how to create GST invoice online free, how to use invoice software India, GST billing steps, GSTR-1 export how to"
    type="article"
    :json-ld="[$howToSchema]">

    <p>
        {{ $appName }} is built to be simple enough to use without help - but here is exactly how each job is done, start to
        finish. Everything below is free during beta.
    </p>

    <h2 id="downloads">Download the manuals</h2>
    <p>
        Prefer to read offline, print a copy for your shop, or send one to your accountant? Both guides are free PDFs
        and need no sign-up.
    </p>

    <div class="not-prose my-6 grid gap-4 sm:grid-cols-2">
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
               class="group flex gap-4 p-5 rounded-2xl bg-white ring-1 ring-gray-200 hover:ring-brand-300 hover:shadow-card transition no-underline">
                <span class="flex-shrink-0 w-11 h-11 rounded-xl bg-brand-50 ring-1 ring-brand-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/>
                    </svg>
                </span>
                <span class="min-w-0">
                    <span class="block font-display font-bold text-gray-900 group-hover:text-brand-700 transition">{{ $doc['name'] }}</span>
                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 mt-0.5">{{ $doc['meta'] }}</span>
                    <span class="block text-sm text-gray-600 mt-1.5 leading-relaxed">{{ $doc['blurb'] }}</span>
                </span>
            </a>
        @endforeach
    </div>


    @foreach ($tasks as $task)
        <h2>{{ $task['title'] }}</h2>
        <ol>
            @foreach ($task['steps'] as $step)
                <li><strong>{{ $step['t'] }}</strong> - {{ $step['d'] }}</li>
            @endforeach
        </ol>
        <p><em>Tip:</em> {{ $task['tip'] }}</p>
    @endforeach

    <h2>Try it now</h2>
    <p>
        <a href="{{ route('register') }}">Create your free account</a> and issue your first invoice in about 60 seconds,
        or run the numbers first with the <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>. See the
        full <a href="{{ route('pages.features') }}">feature list</a> and <a href="{{ route('pages.pricing') }}">pricing</a>.
    </p>
</x-layouts.marketing>
