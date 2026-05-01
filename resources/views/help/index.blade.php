<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">How to use Apna Invoice</h2>
            <p class="text-sm text-gray-500 mt-1">A quick tour of every feature, in the order you'll actually use them.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Downloadable user guide deck — for users who prefer slides over a long page,
                 or want to share with their team / CA. --}}
            <div class="bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 rounded-2xl shadow-card text-white p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="shrink-0 w-12 h-12 rounded-xl bg-white/15 ring-1 ring-white/20 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-widest text-accent-300">Quick start</div>
                    <h3 class="mt-0.5 font-display text-lg font-extrabold">Download the 17-slide getting-started deck</h3>
                    <p class="mt-1 text-sm text-brand-100">A step-by-step PowerPoint walkthrough — sign up to first paid invoice — perfect for sharing with your team or your CA.</p>
                </div>
                <a href="{{ asset('downloads/apna-invoice-getting-started.pptx') }}" download
                   class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-lg shadow-sm transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Download .pptx
                </a>
            </div>

            <div class="grid lg:grid-cols-[220px_1fr] gap-8">

            {{-- Mobile TOC — a collapsible <details> so users on phones can jump to sections without endless scrolling --}}
            <details class="lg:hidden bg-white rounded-xl shadow-card ring-1 ring-gray-100 p-4 text-sm">
                <summary class="cursor-pointer font-semibold text-gray-900 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider font-bold text-gray-500">Jump to section</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <nav class="mt-3 space-y-1.5">
                    <a href="#setup" class="block py-1.5 text-gray-700 hover:text-brand-700">1. Set up your business</a>
                    <a href="#customers" class="block py-1.5 text-gray-700 hover:text-brand-700">2. Add customers</a>
                    <a href="#products" class="block py-1.5 text-gray-700 hover:text-brand-700">3. Save products</a>
                    <a href="#invoice" class="block py-1.5 text-gray-700 hover:text-brand-700">4. Create an invoice</a>
                    <a href="#finalize" class="block py-1.5 text-gray-700 hover:text-brand-700">5. Finalize &amp; share</a>
                    <a href="#payments" class="block py-1.5 text-gray-700 hover:text-brand-700">6. Record payments</a>
                    <a href="#purchases" class="block py-1.5 text-gray-700 hover:text-brand-700">7. Purchases &amp; expenses</a>
                    <a href="#dashboard" class="block py-1.5 text-gray-700 hover:text-brand-700">8. Track progress</a>
                    <a href="#reports" class="block py-1.5 text-gray-700 hover:text-brand-700">9. Reports for your CA</a>
                    <a href="#faq" class="block py-1.5 text-gray-700 hover:text-brand-700">10. FAQ</a>
                    <a href="#scope" class="block py-1.5 text-gray-700 hover:text-brand-700">11. What we don't cover</a>
                </nav>
            </details>

            {{-- Sticky TOC on lg+ — unchanged from before --}}
            <aside class="hidden lg:block lg:sticky lg:top-6 self-start">
                <div class="bg-white rounded-xl shadow-card ring-1 ring-gray-100 p-4 text-sm">
                    <div class="text-xs uppercase tracking-wider font-bold text-gray-500 mb-2">On this page</div>
                    <nav class="space-y-1.5">
                        <a href="#setup" class="block text-gray-700 hover:text-brand-700">1. Set up your business</a>
                        <a href="#customers" class="block text-gray-700 hover:text-brand-700">2. Add customers</a>
                        <a href="#products" class="block text-gray-700 hover:text-brand-700">3. Save products</a>
                        <a href="#invoice" class="block text-gray-700 hover:text-brand-700">4. Create an invoice</a>
                        <a href="#finalize" class="block text-gray-700 hover:text-brand-700">5. Finalize &amp; share</a>
                        <a href="#payments" class="block text-gray-700 hover:text-brand-700">6. Record payments</a>
                        <a href="#purchases" class="block text-gray-700 hover:text-brand-700">7. Purchases &amp; expenses</a>
                        <a href="#dashboard" class="block text-gray-700 hover:text-brand-700">8. Track progress</a>
                        <a href="#reports" class="block text-gray-700 hover:text-brand-700">9. Reports for your CA</a>
                        <a href="#faq" class="block text-gray-700 hover:text-brand-700">10. FAQ</a>
                        <a href="#scope" class="block text-gray-700 hover:text-brand-700">11. What we don't cover</a>
                    </nav>
                </div>
            </aside>

            <div class="space-y-8">
                @php
                    $sections = [
                        [
                            'id' => 'setup',
                            'n' => 1,
                            'title' => 'Set up your business profile',
                            'time' => '3 min',
                            'desc' => 'Your GSTIN, PAN, address and state live here — they print on every invoice as the letterhead. Also set your invoice-number prefix and upload a logo &amp; signature for that professional look.',
                            'cta' => ['label' => 'Open Company settings', 'href' => route('company.edit')],
                            'tips' => [
                                '<strong>State matters.</strong> Your company state decides whether an invoice is intrastate (CGST + SGST) or interstate (IGST). Always set it.',
                                '<strong>Invoice prefix.</strong> Use <code>{FY}</code> in your prefix — e.g. <code>INV/{FY}/</code> renders as <code>INV/26-27/0001</code> and auto-resets on 1 April. The receipt-number series is separate and runs continuously per company.',
                                '<strong>Bank details &amp; UPI</strong> in settings auto-render a QR on every invoice, so customers can pay with one tap.',
                                '<strong>Composition dealer?</strong> Tick that box in settings — every document automatically prints "Bill of Supply" instead of "Tax Invoice" and includes the Section 31(3)(c) declaration.',
                            ],
                        ],
                        [
                            'id' => 'customers',
                            'n' => 2,
                            'title' => 'Add your customers',
                            'time' => '1 min per customer',
                            'desc' => 'Save a customer once with GSTIN, address, state, email and mobile — then just pick them from a dropdown on every invoice.',
                            'cta' => ['label' => 'Add a customer', 'href' => route('customers.create')],
                            'tips' => [
                                '<strong>GSTIN is validated.</strong> We check the 15-digit format and the state code prefix matches the selected state.',
                                '<strong>Mobile numbers</strong> are searchable from the Invoices list — helpful when a customer calls to ask about a bill.',
                                '<strong>Can\'t delete a customer?</strong> That\'s intentional — customers with invoices stay on the books for GST audit.',
                            ],
                        ],
                        [
                            'id' => 'products',
                            'n' => 3,
                            'title' => 'Save products / services (optional but worth it)',
                            'time' => '1 min per item',
                            'desc' => 'Add the things you sell — name, HSN/SAC, unit, default rate, GST%. On the invoice form, picking a product auto-fills all those fields so one customer &amp; two clicks = an invoice.',
                            'cta' => ['label' => 'Add a product', 'href' => route('products.create')],
                            'tips' => [
                                '<strong>HSN vs SAC.</strong> Goods use HSN (4/6/8 digits). Services use SAC, which starts with 99.',
                                '<strong>UQC units.</strong> We list the exact CBIC-notified codes (NOS, KGS, LTR…) so your GSTR-1 reconciles cleanly.',
                                '<strong>Archived, not deleted.</strong> Products that were ever invoiced get archived — history stays intact.',
                            ],
                        ],
                        [
                            'id' => 'invoice',
                            'n' => 4,
                            'title' => 'Create an invoice',
                            'time' => '~30 seconds once set up',
                            'desc' => 'Click <em>New invoice</em>, pick a template (or start blank), choose the customer, add line items (or pick from your products), save. We auto-compute CGST/SGST vs IGST based on the customer\'s state.',
                            'cta' => ['label' => 'Start a new invoice', 'href' => route('invoices.templates')],
                            'tips' => [
                                '<strong>Draft vs Final.</strong> Everything starts as a draft — you can edit freely. Finalizing assigns the legal invoice number and locks the amounts.',
                                '<strong>Five template styles.</strong> Classic Navy, Executive Maroon, Minimal Slate, Mercantile Forest, and Heritage Burgundy — pick whichever matches your brand. The GST format is identical; only the colour and rule weight differ.',
                                '<strong>Per-line discount (Section 15(3)).</strong> Discount is captured pre-tax — the discount column only renders on the PDF when at least one line has one, so service-only invoices stay clean.',
                                '<strong>Transporter &amp; e-way bill</strong> details are optional — only fill them if you\'re shipping goods.',
                                '<strong>Reverse charge</strong> tick-box is for RCM supplies under Section 9(3)/9(4) of the CGST Act. We zero out the tax and print the Rule 46(p) declaration automatically.',
                            ],
                        ],
                        [
                            'id' => 'finalize',
                            'n' => 5,
                            'title' => 'Finalize, download, share & amend',
                            'time' => 'instant',
                            'desc' => 'Open the invoice, click <em>Finalize</em> — we assign the next number in your series (auto-reset on 1 April when you use the <span class="font-mono">{FY}</span> format). Then <em>Download PDF</em> for an ink-saving version, <em>Email</em> it (attaches the PDF automatically), tap the <em>WhatsApp</em> button for a pre-filled message, or <em>Copy link</em> for a 30-day signed public URL. For returns / rate corrections / post-sale discounts, use <em>Issue credit note</em> — the adjustment is GSTR-1-compliant and auto-reduces the invoice balance.',
                            'tips' => [
                                '<strong>Why ink-saver by default?</strong> When you download for printing, you don\'t want coloured table headers burning toner. The on-screen version stays colourful, and the 🎨 button next to Download PDF gives you the full-colour file if you need it.',
                                '<strong>WhatsApp share</strong> uses <span class="font-mono">wa.me</span> deep links — no extra setup, just works from any phone.',
                                '<strong>Locked fields.</strong> After finalize, you can still edit notes, terms, due date and transporter — but not amounts, items, or customer. (GST best practice: issue a credit note instead of silently editing.)',
                                '<strong>Cancel instead of delete.</strong> A wrong finalized invoice is cancelled with a short reason, preserving the audit trail. The public link stops working the moment you cancel.',
                            ],
                        ],
                        [
                            'id' => 'payments',
                            'n' => 6,
                            'title' => 'Record payments & issue receipts',
                            'time' => '20 seconds per payment',
                            'desc' => 'On a finalized invoice, fill the <em>Record a payment</em> form — amount, method (UPI / NEFT / Cash / Cheque…), date, reference. We generate a sequential receipt number, update the balance, and give you a printable receipt PDF.',
                            'tips' => [
                                '<strong>Part payments.</strong> Enter ₹1,000 today, ₹2,000 next week — the balance recomputes automatically.',
                                '<strong>TDS deducted by your customer?</strong> Use the TDS fields on the payment form — section (e.g. 194C, 194J, 194Q) and rate. The deducted amount is stored against the receipt so your CA can match it to Form 26AS.',
                                '<strong>Chasing dues?</strong> Open <em>Finance → Aging</em> for a bucketed view of who owes what (current / 31–60 / 61–90 / 91+ days), sorted biggest-first. Or click the <em>Outstanding</em> card on the dashboard for the full unpaid-invoices list.',
                                '<strong>Reverse a payment</strong> if you entered it wrong. The receipt number stays in the log (auditable) but the balance is restored.',
                                '<strong>UPI / cheque ref</strong> goes on the receipt PDF — customers love seeing their own txn ID on the proof of payment.',
                            ],
                        ],
                        [
                            'id' => 'purchases',
                            'n' => 7,
                            'title' => 'Track purchases & expenses',
                            'time' => 'as bills come in',
                            'desc' => 'Sales aren\'t the whole story — to get a real P&amp;L, capture money going out too. Apna Invoice gives you two purpose-built tools: <strong>Cash memos</strong> for documented cash purchases (the vendor doesn\'t issue you a tax invoice, so you generate one in your own books) and <strong>Expenses</strong> for everything else — rent, salaries, utilities, marketing, software.',
                            'cta' => ['label' => 'Open Finance', 'href' => route('finance.index')],
                            'tips' => [
                                '<strong>Cash memo (purchase voucher).</strong> Records a purchase from a vendor with name, GSTIN, line items and HSN/SAC. The PDF is laid out as a professional Indian cash memo — seller letterhead at the top, "Bill To" with your details below, signature block. Same module shows your purchase history.',
                                '<strong>Expenses.</strong> Logged with date, vendor, amount, GST input, and category (Rent, Salaries, Utilities, Marketing, etc.). Category colours flow into the dashboard P&amp;L so you can see where money goes at a glance.',
                                '<strong>Both feed the P&amp;L.</strong> Cash memos and expenses both subtract from revenue in the Finance dashboard — accrual, cash and GST views all stay in sync.',
                                '<strong>GST input captured.</strong> The GST portion is stored separately on each expense for ITC reconciliation when your CA files GSTR-3B.',
                            ],
                        ],
                        [
                            'id' => 'dashboard',
                            'n' => 8,
                            'title' => 'Track progress on the dashboard',
                            'time' => 'glance',
                            'desc' => 'The dashboard shows two numbers that actually matter: <strong>Bills issued</strong> (lifetime + this month) and <strong>Payments received</strong> (lifetime + this month). Plus outstanding, drafts, and monthly P&amp;L.',
                            'cta' => ['label' => 'Go to Dashboard', 'href' => route('dashboard')],
                            'tips' => [
                                '<strong>Outstanding</strong> = everything finalized but not yet fully paid. It\'s your money-to-collect number.',
                                '<strong>P&amp;L (3 views).</strong> Accrual (invoice-date), cash (when money actually moved), and GST (collected vs paid) — switch with one click. A 30-second health check.',
                                '<strong>Customer ledger.</strong> On any customer, click <em>Ledger</em> to see a Dr/Cr running balance — every invoice, payment and credit note in date order. Useful for monthly statements.',
                                'Click any card to drill into the underlying list.',
                            ],
                        ],
                        [
                            'id' => 'reports',
                            'n' => 9,
                            'title' => 'Reports for your CA (and yourself)',
                            'time' => 'monthly · sometimes weekly',
                            'desc' => 'Apna Invoice generates four reports your CA actually asks for at month-end — all one click from the Dashboard\'s <em>Reports &amp; exports</em> panel, or the <em>Finance</em> tabs. Every report exports as both PDF (for review) and CSV (for Excel / Tally / GST portal upload).',
                            'cta' => ['label' => 'Open Reports panel', 'href' => route('dashboard')],
                            'tips' => [
                                '<strong>Receivables Aging</strong> (<em>Finance → Aging</em>). Bucketed view of every unpaid invoice — Current (≤30 days) / 31–60 / 61–90 / 91+ — aggregated per customer and sorted biggest-first. The single most-asked weekly question for any business owner: "who owes me what?". Send the PDF to a recovery agent, or the CSV to a CA.',
                                '<strong>GSTR-3B Summary</strong> (<em>Finance → GSTR-3B</em>). Computed from your books for the selected month — Section 3.1 (outward + RCM), Section 4 (eligible ITC from purchases &amp; expenses), Section 6.1 (net cash payable). The big "Total cash to deposit" headline is the figure you pay via PMT-06. We don\'t auto-file; the PDF mirrors the GST portal form so transposing is easy.',
                                '<strong>GSTR-1 CSV</strong> (<em>Invoices → Export → GSTR-1</em>). UTF-8 BOM, B2B + B2C split, place of supply, taxable value, CGST/SGST/IGST/cess columns. Drop into the GST portal\'s offline tool, or your CA imports it.',
                                '<strong>Cash memo bulk export</strong> (<em>Finance → Cash Memos → Download PDF/CSV</em>). One CA-ready statement covering every cash purchase in the period — totals, payment-mode breakdown, amount in words. Email it instead of forwarding 30 individual memos.',
                                '<strong>Expenses CSV</strong> &amp; <strong>Full data backup</strong> are also one click away from the Dashboard\'s <em>Reports</em> panel — the backup is a ZIP of every invoice, customer, payment and expense as CSVs, ready for any other tool.',
                                '<strong>Books locked = audit-defensible.</strong> Once you set <em>Books locked until</em> in Company settings (e.g. 31 Mar after FY close), all the reports above stay accurate — no one can backdate edits into the locked period.',
                            ],
                        ],
                        [
                            'id' => 'faq',
                            'n' => 10,
                            'title' => 'Frequently asked',
                            'desc' => null,
                            'faq' => [
                                ['q' => 'Is my data secure?', 'a' => 'Yes — all data sits in Indian jurisdiction, each invoice/customer/payment is scoped to your user &amp; company, and we never share it. Deletion of data you own is permanent.'],
                                ['q' => 'Can I run multiple businesses?', 'a' => 'Yes. Use the <em>Companies</em> section to add more than one, each with its own GSTIN, invoice series and customers. Switch between them using the dropdown at the top of the page.'],
                                ['q' => 'What if I need to cancel a finalized invoice?', 'a' => 'Open the invoice and click <strong>Cancel invoice</strong>. You\'ll be asked for a short reason — this is stored on the invoice so the audit trail stays complete. Cancelled invoices keep their invoice number (never reused), stop accepting further payments, and the 30-day public share link is revoked. If you need to refund money already collected, issue a credit note.'],
                                ['q' => 'How do I export data for my CA / GSTR-1 / GSTR-3B filing?', 'a' => 'See <strong>Section 9 — Reports for your CA</strong>. Quick links: <em>Invoices → Export → GSTR-1 CSV</em> for outward supplies (B2B/B2C, place of supply, CGST/SGST/IGST split), and <em>Finance → GSTR-3B</em> for the monthly summary computed from your books (sections 3.1, 4, 6.1 with PDF + CSV). All exports are UTF-8 with BOM so Excel opens them cleanly.'],
                                ['q' => 'How do I see who owes me money — and how old the dues are?', 'a' => 'Open <strong>Finance → Aging</strong>. You\'ll get four buckets — Current (≤30 days) / 31–60 / 61–90 / 91+ — with a per-customer breakdown sorted by largest outstanding first. Click any customer to drill into their ledger. Anything in the 91+ bucket is highlighted as urgent (recovery probability drops sharply beyond 90 days). Export the whole report as PDF or CSV to send to a recovery agent or your CA.'],
                                ['q' => 'Do I have to file GSTR-3B from Apna Invoice?', 'a' => 'No — Apna Invoice <strong>computes</strong> the GSTR-3B summary from your invoices, expenses and cash memos, but doesn\'t auto-submit to the GSTN portal. Open <em>Finance → GSTR-3B</em>, pick the month, verify against your GSTR-1 filings and ITC ledger, then either you or your CA submits on the GST portal. The PDF is laid out to mirror the GSTR-3B form so transposing is straightforward.'],
                                ['q' => 'How do I close books at year-end so old invoices can\'t be edited?', 'a' => 'In <strong>Company settings → Books locked until</strong>, set a date (e.g. 31 March). After that date is locked, the app blocks editing/deleting any invoice, payment, expense, cash memo or credit note dated on or before it. The audit trail logs the lock — auditors love this.'],
                                ['q' => 'Can I see a running statement for a single customer?', 'a' => 'Yes. Open <strong>Customers</strong>, click any name, then <em>Ledger</em>. You\'ll see every invoice, payment and credit note for that customer in date order with a running Dr/Cr balance — perfect for sending a monthly statement or chasing dues.'],
                                ['q' => 'Can customers pay via UPI directly?', 'a' => 'If you\'ve added your UPI ID in Company settings, every invoice PDF carries a UPI QR — customer scans, pays, done.'],
                                ['q' => 'How do I back up my data?', 'a' => 'Go to <strong>Backups</strong> (in your profile menu) and either download a ZIP right now or enable weekly auto-backup — every Sunday morning we\'ll email a ZIP of all your invoices, customers, products, payments and expenses as CSVs.'],
                                ['q' => 'How do referrals work?', 'a' => 'Every account gets a unique referral code (like <span class="font-mono">AI-K4X9</span>). Open <strong>Refer a friend</strong> to copy your code, share via WhatsApp or email, and track who signed up using it. Pending / Rewarded status is tracked so you always know where your referrals stand.'],
                            ],
                        ],
                    ];
                @endphp

                @foreach ($sections as $s)
                    <section id="{{ $s['id'] }}" class="scroll-mt-24">
                        <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-6 sm:p-8">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 shrink-0 rounded-full bg-gradient-to-br from-brand-700 to-accent-600 text-white font-display font-extrabold flex items-center justify-center">{{ $s['n'] }}</div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-display text-xl sm:text-2xl font-extrabold text-gray-900">{{ $s['title'] }}</h3>
                                    @if (! empty($s['time']))
                                        <div class="text-xs uppercase tracking-wider text-gray-400 mt-0.5 font-semibold">{{ $s['time'] }}</div>
                                    @endif
                                    @if (! empty($s['desc']))
                                        <p class="mt-3 text-gray-700 leading-relaxed">{!! $s['desc'] !!}</p>
                                    @endif

                                    @if (! empty($s['tips']))
                                        <ul class="mt-4 space-y-2">
                                            @foreach ($s['tips'] as $tip)
                                                <li class="flex gap-3 text-sm text-gray-700">
                                                    <svg class="w-5 h-5 shrink-0 text-money-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.58l-1.3-1.3a1 1 0 10-1.4 1.42l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                                                    <span>{!! $tip !!}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if (! empty($s['faq']))
                                        <div class="mt-4 space-y-3">
                                            @foreach ($s['faq'] as $f)
                                                <details class="group border border-gray-200 rounded-lg">
                                                    <summary class="cursor-pointer list-none p-4 flex items-center justify-between font-semibold text-gray-900 hover:bg-gray-50">
                                                        <span>{!! $f['q'] !!}</span>
                                                        <svg class="w-5 h-5 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                    </summary>
                                                    <div class="px-4 pb-4 text-sm text-gray-700 leading-relaxed">{!! $f['a'] !!}</div>
                                                </details>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if (! empty($s['cta']))
                                        <a href="{{ $s['cta']['href'] }}" class="mt-5 inline-flex items-center gap-1.5 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                                            {{ $s['cta']['label'] }}
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>
                @endforeach

                {{-- OUT OF SCOPE — honesty about what we don't do, so users self-select correctly. --}}
                <section id="scope" class="scroll-mt-24">
                    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 sm:p-8">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 shrink-0 rounded-full bg-amber-100 text-amber-700 font-display font-extrabold flex items-center justify-center">11</div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-display text-xl sm:text-2xl font-extrabold text-amber-900">What Apna Invoice doesn't (yet) cover</h3>
                                <p class="mt-2 text-amber-800 leading-relaxed">
                                    We're built for small-to-mid Indian businesses doing domestic GST-taxable supplies.
                                    A few Indian GST scenarios are <strong>intentionally out of scope</strong> today — if any
                                    describe your workflow, we probably aren't the right tool yet.
                                </p>
                                <ul class="mt-4 space-y-3 text-sm text-amber-900">
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-amber-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>E-invoicing (IRN / signed QR via NIC IRP).</strong> Mandatory since Aug 2023 for businesses with aggregate turnover above <strong>₹5 crore</strong>. We don't generate IRNs — if you're in this bracket you'll need a GSP/ASP integration too.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-amber-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>Auto-filing to the GSTN portal.</strong> We don't submit returns directly. We <em>do</em> compute and export both <strong>GSTR-1</strong> (B2B/B2C CSV with HSN summary, Table 12 format) and <strong>GSTR-3B</strong> (sections 3.1 / 4 / 6.1 as PDF + CSV) — your CA imports / transcribes the figures and submits on the GST portal. No GSP/ASP integration.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-amber-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>Exports, SEZ supplies, multi-currency.</strong> Invoices meant for export under LUT / Bond, SEZ unit supplies, or billing in USD/EUR/AED etc. We hard-code INR and the standard domestic tax-invoice format.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-amber-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>Compensation cess.</strong> Demerit goods (tobacco, aerated drinks, luxury automobiles) attract GST + a separate compensation cess. We support the GST part but don't have a cess line today.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-amber-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>E-way bill auto-generation.</strong> We capture the E-way bill number if you have one, but we don't call the ewaybillgst.gov.in API to generate it. For goods movement above ₹50,000 you'll still need to generate the EWB there.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-amber-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>TCS under Section 52 (e-commerce operators).</strong> We support <strong>TDS deducted by your customers</strong> (Section 194-x &amp; 51) on incoming payments, but not TCS that you'd collect as an e-commerce platform.</span>
                                    </li>
                                </ul>
                                <div class="mt-5 p-4 bg-white/60 rounded-lg border border-amber-200">
                                    <div class="text-xs uppercase tracking-wider font-bold text-amber-800">What we DO cover</div>
                                    <p class="mt-2 text-sm text-amber-900 leading-relaxed">
                                        Domestic tax invoices with CGST+SGST / IGST auto-split · HSN/SAC with UQC units ·
                                        FY-reset invoice numbering · <strong>Composition scheme + auto Bill of Supply</strong> · <strong>Per-line discount (Section 15(3))</strong> · <strong>TDS deduction tracking (Section 194-x &amp; 51)</strong> ·
                                        Credit notes (Section 34) · Reverse charge ·
                                        Place of supply &amp; separate ship-to · Partial payments with receipt numbering · <strong>Cash memo / purchase voucher</strong> · <strong>Customer ledger (Dr/Cr running balance)</strong> ·
                                        Invoice cancellation with audit trail · Transporter / e-way bill number capture · <strong>Books period lock (FY close protection)</strong> · <strong>Activity log (who-did-what)</strong> · <strong>GSTR-1 CSV export</strong> ·
                                        Multi-company / multi-GSTIN · UPI QR on invoice · Amount in words (Indian lakhs/crores).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="bg-gradient-to-br from-brand-900 to-accent-900 text-white rounded-2xl p-6 sm:p-8 shadow-brand">
                    <h3 class="font-display text-xl font-extrabold">Still stuck?</h3>
                    <p class="mt-2 text-brand-100 text-sm leading-relaxed">
                        WhatsApp or call us — fastest way to get unstuck during business hours (9.30 am – 7 pm IST, Mon–Sat).
                        We reply to every support email too, usually within a business day.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ config('seo.contact.whatsapp_url') }}?text={{ urlencode('Hi Apna Invoice team — I need help with…') }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-[#25D366] hover:bg-[#1ebe5b] text-white text-sm font-semibold rounded-lg shadow-sm">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            WhatsApp · {{ config('seo.contact.phone_display') }}
                        </a>
                        <a href="tel:{{ config('seo.contact.phone_e164') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold rounded-lg ring-1 ring-white/20">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            Call
                        </a>
                        <a href="{{ route('pages.contact') }}" class="inline-flex items-center gap-2 px-4 py-2 text-accent-300 hover:text-accent-200 text-sm font-semibold underline">
                            Email / contact form →
                        </a>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
