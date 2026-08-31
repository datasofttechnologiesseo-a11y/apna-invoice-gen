@php
    $appName = config('seo.name', 'Apna Invoice');

    $tasks = [
        [
            'title' => 'Create & issue a GST invoice',
            'steps' => [
                ['t' => 'Sign up in seconds', 'd' => 'Continue with Google, or enter your mobile and verify it with the one-time code we send. No credit card.'],
                ['t' => 'Add your business once', 'd' => 'GSTIN, state and logo. Your letterhead is generated on every invoice automatically.'],
                ['t' => 'Add the line items', 'd' => 'Pick a customer, then type description, HSN/SAC, quantity, rate and GST%. CGST/SGST/IGST is worked out for you.'],
                ['t' => 'Issue & download', 'd' => 'Tap Issue: the invoice locks, gets a permanent number, and the GST-compliant PDF is ready.'],
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
    title="How to Use Apna Invoice: Make a GST Invoice Free"
    eyebrow="Learn the tool"
    lead="Pick a task and follow the exact steps — create and issue a GST invoice, share it and get paid, pull month-end reports, or raise quotations and credit notes. No jargon, no manual."
    description="How to use Apna Invoice: create & issue a GST invoice in 60 seconds, share on WhatsApp, collect via UPI, export GSTR-1/3B. Free step-by-step guide for India."
    keywords="how to make GST invoice, how to create GST invoice online free, how to use invoice software India, GST billing steps, GSTR-1 export how to"
    type="article"
    :json-ld="[$howToSchema]">

    <p>
        {{ $appName }} is built so you never need a manual — but here's exactly how each job is done, start to finish.
        Everything below is free during beta.
    </p>

    @foreach ($tasks as $task)
        <h2>{{ $task['title'] }}</h2>
        <ol>
            @foreach ($task['steps'] as $step)
                <li><strong>{{ $step['t'] }}</strong> — {{ $step['d'] }}</li>
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
