@php
    $appName = config('seo.name', 'Apna Invoice');

    $features = [
        ['title' => 'GST auto-detection', 'body' => "CGST + SGST for intra-state, IGST for inter-state. It's picked up from the customer's state automatically — no manual math, no wrong-state mistakes."],
        ['title' => 'One-click PDF', 'body' => 'Letterhead, logo, signature, HSN/SAC and amount in words (Indian format). Download or print a pixel-perfect, GST-compliant PDF every time, in five professional template styles.'],
        ['title' => 'Draft → Issued flow', 'body' => 'Edit drafts as much as you want. Issue to lock the number and make it legally final — atomic FY-aware numbering means no duplicates and no gaps.'],
        ['title' => 'Partial payments & TDS', 'body' => 'Record advance and part payments (with TDS), and track the balance at a glance. Status moves Final → Partially paid → Paid, and a receipt PDF is generated automatically.'],
        ['title' => 'Customer book', 'body' => 'Save customer details once — GSTIN, address, state — and reuse them across invoices. The GST tax mode auto-fills from the customer state.'],
        ['title' => 'Payment reminders', 'body' => 'Automatic email nudges at 0 / 3 / 7 / 15 / 30 days past due, or a one-tap WhatsApp follow-up. Chase payments less, get paid sooner.'],
        ['title' => 'Credit notes (GST Section 34)', 'body' => 'Raise credit notes against an issued invoice with reason codes, in the CBIC Section 34 format for returns, adjustments and disputes.'],
        ['title' => 'Quotations that convert', 'body' => 'Send a price proposal (proforma) and convert it to a tax invoice in one click when the customer accepts. Quotations use a separate FY-aware series.'],
        ['title' => 'Cash memos', 'body' => 'Issue cash memos for over-the-counter sales, with automatic CGST/SGST split and thermal-printer-friendly output.'],
        ['title' => 'Multi-GSTIN / multi-branch', 'body' => 'Run multiple companies or state branches from one login — each entity gets its own GSTIN, logo and numbering series, one click to switch.'],
        ['title' => 'Reports for your CA', 'body' => 'GSTR-1 CSV, a GSTR-3B summary, receivables aging and a P&L (accrual, cash and GST views) — everything your accountant asks for at month-end.'],
        ['title' => 'One-click backups', 'body' => 'Download a full ZIP of your invoices, customers and PDFs anytime, or schedule it to email itself monthly. Your data, your move — never locked in.'],
    ];
@endphp

<x-layouts.marketing
    title="Features: Free GST Invoicing Built for India"
    eyebrow="What's inside"
    lead="Everything you need to bill customers and get paid the way India does business — GST auto-split, WhatsApp share, credit notes, GSTR-1 exports — and nothing you don't."
    description="Apna Invoice features: auto CGST/SGST/IGST, one-click GST PDF, credit notes, quotations, cash memos, reminders, GSTR-1/3B reports & backups. Free for India."
    keywords="GST invoice software features, free billing software India features, GST invoice generator, credit note, quotation, GSTR-1 export, payment reminders">

    <p>
        No bloated ERP, no spreadsheet gymnastics — just the core tools to bill customers and get paid, built
        India-first. Every feature below is free during beta with no per-invoice or per-user limit.
    </p>

    <h2>Core invoicing &amp; billing</h2>
    <ul>
        @foreach ($features as $f)
            <li><strong>{{ $f['title'] }}</strong> — {{ $f['body'] }}</li>
        @endforeach
    </ul>

    <h2>Why not just Excel?</h2>
    <p>
        Most Indian SMEs still copy-paste an old invoice, fiddle with GST math and email the file. That means manual
        CGST/SGST/IGST calculations (one wrong state and the invoice is non-compliant), hunting for HSN/SAC codes,
        writing amounts in words by hand, FY-end numbering that trips up in audit, and <em>invoice_final_v3.xlsx</em>
        version chaos. {{ $appName }} does the GST split automatically, keeps HSN/SAC handy, writes proper Indian
        amount-in-words, resets numbering on 1 April, and shares a preview-ready PDF straight to WhatsApp.
    </p>

    <h2>Made in India, built for Bharat</h2>
    <ul>
        <li><strong>GST 2.0 ready</strong> — HSN/SAC codes and FY-aware numbering.</li>
        <li><strong>DPDP compliant</strong> — hosted on Indian servers, zero offshore data transfers.</li>
        <li><strong>36 states &amp; UTs</strong> pre-loaded, so place-of-supply is automatic.</li>
        <li><strong>₹ in lakhs &amp; crores</strong> — the Indian number system, everywhere.</li>
    </ul>

    <h2>Start billing free</h2>
    <p>
        <a href="{{ route('register') }}">Create your free account</a> and issue your first GST invoice in about 60
        seconds. See <a href="{{ route('pages.pricing') }}">what's included on the free plan</a>, or learn
        <a href="{{ route('pages.how-to-use') }}">how to use {{ $appName }}</a> step by step.
    </p>
</x-layouts.marketing>
