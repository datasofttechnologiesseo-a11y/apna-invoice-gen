<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">How to use Apna Invoice</h2>
            <p class="text-sm text-gray-500 mt-1">A quick tour of every feature, in the order you'll actually use them.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 grid lg:grid-cols-[220px_1fr] gap-8">

            {{-- Sticky TOC (hidden on <lg since it's a vertical space-eater) --}}
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
                        <a href="#dashboard" class="block text-gray-700 hover:text-brand-700">7. Track progress</a>
                        <a href="#faq" class="block text-gray-700 hover:text-brand-700">8. FAQ</a>
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
                                '<strong>Invoice prefix</strong> (e.g. <code>INV-2026</code>) and <strong>receipt prefix</strong> (e.g. <code>RCPT</code>) are sequential per company — reset when the financial year changes if you need clean series.',
                                '<strong>Bank details &amp; UPI</strong> in settings auto-render a QR on every invoice, so customers can pay with one tap.',
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
                                '<strong>Transporter &amp; e-way bill</strong> details are optional — only fill them if you\'re shipping goods.',
                                '<strong>Reverse charge</strong> tick-box is for RCM supplies under Section 9(3)/9(4) of the CGST Act.',
                            ],
                        ],
                        [
                            'id' => 'finalize',
                            'n' => 5,
                            'title' => 'Finalize, download &amp; share',
                            'time' => 'instant',
                            'desc' => 'Open the invoice, click <em>Finalize</em> — we assign the next number in your series. Then <em>Download PDF</em> for an ink-saving version, <em>Email</em> it (attaches the PDF automatically), tap the <em>WhatsApp</em> button for a pre-filled message, or <em>Copy link</em> for a 30-day signed public URL.',
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
                            'title' => 'Record payments &amp; issue receipts',
                            'time' => '20 seconds per payment',
                            'desc' => 'On a finalized invoice, fill the <em>Record a payment</em> form — amount, method (UPI / NEFT / Cash / Cheque…), date, reference. We generate a sequential receipt number, update the balance, and give you a printable receipt PDF.',
                            'tips' => [
                                '<strong>Part payments.</strong> Enter ₹1,000 today, ₹2,000 next week — the balance recomputes automatically.',
                                '<strong>Reverse a payment</strong> if you entered it wrong. The receipt number stays in the log (auditable) but the balance is restored.',
                                '<strong>UPI / cheque ref</strong> goes on the receipt PDF — customers love seeing their own txn ID on the proof of payment.',
                            ],
                        ],
                        [
                            'id' => 'dashboard',
                            'n' => 7,
                            'title' => 'Track progress on the dashboard',
                            'time' => 'glance',
                            'desc' => 'The dashboard shows two numbers that actually matter: <strong>Bills issued</strong> (lifetime + this month) and <strong>Payments received</strong> (lifetime + this month). Plus outstanding, drafts, and monthly P&amp;L.',
                            'cta' => ['label' => 'Go to Dashboard', 'href' => route('dashboard')],
                            'tips' => [
                                '<strong>Outstanding</strong> = everything finalized but not yet fully paid. It\'s your money-to-collect number.',
                                '<strong>P&amp;L</strong> uses income (accrual) minus expenses logged in Finance — a 30-second health check.',
                                'Click any card to drill into the underlying list.',
                            ],
                        ],
                        [
                            'id' => 'faq',
                            'n' => 8,
                            'title' => 'Frequently asked',
                            'desc' => null,
                            'faq' => [
                                ['q' => 'Is my data secure?', 'a' => 'Yes — all data sits in Indian jurisdiction, each invoice/customer/payment is scoped to your user &amp; company, and we never share it. Deletion of data you own is permanent.'],
                                ['q' => 'Can I run multiple businesses?', 'a' => 'Yes. Use the <em>Companies</em> section to add more than one, each with its own GSTIN, invoice series and customers. Switch between them using the dropdown at the top of the page.'],
                                ['q' => 'What if I need to cancel a finalized invoice?', 'a' => 'Open the invoice and click <strong>Cancel invoice</strong>. You\'ll be asked for a short reason — this is stored on the invoice so the audit trail stays complete. Cancelled invoices keep their invoice number (never reused), stop accepting further payments, and the 30-day public share link is revoked. If you need to refund money already collected, issue a credit note.'],
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

                <div class="bg-gradient-to-br from-brand-900 to-accent-900 text-white rounded-2xl p-6 sm:p-8 shadow-brand">
                    <h3 class="font-display text-xl font-extrabold">Still stuck?</h3>
                    <p class="mt-2 text-brand-100 text-sm leading-relaxed">
                        Drop us a line via the <a href="{{ route('pages.contact') }}" class="underline text-accent-300 font-semibold hover:text-accent-200">Contact</a> page.
                        We reply to every support email — usually within a business day.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
