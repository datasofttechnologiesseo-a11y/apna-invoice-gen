<x-app-layout title="Help">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">How to use Apna Invoice</h1>
            <p class="text-sm text-gray-500 mt-1">From setting up your business to filing GST returns, in the order you will use each feature.</p>
        </div>
            <a href="{{ \App\Support\Team::can('dashboard') ? route('dashboard', ['tour' => 1]) : route('invoices.index', ['tour' => 1]) }}"
               class="self-start inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Take the tour
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Downloadable user guide deck, for users who prefer slides over a long page,
                 or want to share with their team / CA. --}}
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="shrink-0 w-12 h-12 rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold text-brand-700">Quick start</div>
                    <h2 class="mt-0.5 font-display text-lg font-bold text-gray-900">Getting started guide (17 slides)</h2>
                    <p class="mt-1 text-sm text-gray-600">PowerPoint guide from sign-up to the first paid invoice. Share it with your team or CA.</p>
                </div>
                <a href="{{ asset('downloads/apna-invoice-getting-started.pptx') }}" download
                   class="shrink-0 inline-flex items-center justify-center gap-2 h-10 px-4 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-gray-800 text-sm font-semibold rounded-md transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Download .pptx
                </a>
            </div>

            <div class="grid lg:grid-cols-[220px_1fr] gap-8">

            {{-- Mobile TOC, a collapsible <details> so users on phones can jump to sections without endless scrolling --}}
            <details class="lg:hidden bg-white rounded-xl ring-1 ring-gray-200 p-4 text-sm">
                <summary class="cursor-pointer font-semibold text-gray-900 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider font-bold text-gray-500">Jump to section</span>
                    <svg class="w-4 h-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <nav class="mt-3 space-y-1.5">
                    <a href="#setup" class="block py-1.5 text-gray-700 hover:text-brand-700">1. Set up your business</a>
                    <a href="#customers" class="block py-1.5 text-gray-700 hover:text-brand-700">2. Add customers</a>
                    <a href="#products" class="block py-1.5 text-gray-700 hover:text-brand-700">3. Items and stock</a>
                    <a href="#quotations" class="block py-1.5 text-gray-700 hover:text-brand-700">4. Send a quotation (optional)</a>
                    <a href="#invoice" class="block py-1.5 text-gray-700 hover:text-brand-700">5. Create an invoice</a>
                    <a href="#finalize" class="block py-1.5 text-gray-700 hover:text-brand-700">6. Issue &amp; share</a>
                    <a href="#payments" class="block py-1.5 text-gray-700 hover:text-brand-700">7. Record payments</a>
                    <a href="#purchases" class="block py-1.5 text-gray-700 hover:text-brand-700">8. Purchases &amp; expenses</a>
                    <a href="#bank" class="block py-1.5 text-gray-700 hover:text-brand-700">9. Bank reconciliation</a>
                    <a href="#gst" class="block py-1.5 text-gray-700 hover:text-brand-700">10. GST returns</a>
                    <a href="#reports" class="block py-1.5 text-gray-700 hover:text-brand-700">11. Reports for your CA</a>
                    <a href="#dashboard" class="block py-1.5 text-gray-700 hover:text-brand-700">12. Dashboard</a>
                    <a href="#team" class="block py-1.5 text-gray-700 hover:text-brand-700">13. Team access</a>
                    <a href="#faq" class="block py-1.5 text-gray-700 hover:text-brand-700">14. FAQ</a>
                    <a href="#scope" class="block py-1.5 text-gray-700 hover:text-brand-700">15. Not supported yet</a>
                </nav>
            </details>

            {{-- Sticky TOC on lg+, unchanged from before --}}
            <aside class="hidden lg:block lg:sticky lg:top-6 self-start">
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4 text-sm">
                    <div class="text-xs uppercase tracking-wider font-bold text-gray-500 mb-2">On this page</div>
                    <nav class="space-y-1.5">
                        <a href="#setup" class="block text-gray-700 hover:text-brand-700">1. Set up your business</a>
                        <a href="#customers" class="block text-gray-700 hover:text-brand-700">2. Add customers</a>
                        <a href="#products" class="block text-gray-700 hover:text-brand-700">3. Items and stock</a>
                        <a href="#quotations" class="block text-gray-700 hover:text-brand-700">4. Send a quotation (optional)</a>
                        <a href="#invoice" class="block text-gray-700 hover:text-brand-700">5. Create an invoice</a>
                        <a href="#finalize" class="block text-gray-700 hover:text-brand-700">6. Issue &amp; share</a>
                        <a href="#payments" class="block text-gray-700 hover:text-brand-700">7. Record payments</a>
                        <a href="#purchases" class="block text-gray-700 hover:text-brand-700">8. Purchases &amp; expenses</a>
                        <a href="#bank" class="block text-gray-700 hover:text-brand-700">9. Bank reconciliation</a>
                        <a href="#gst" class="block text-gray-700 hover:text-brand-700">10. GST returns</a>
                        <a href="#reports" class="block text-gray-700 hover:text-brand-700">11. Reports for your CA</a>
                        <a href="#dashboard" class="block text-gray-700 hover:text-brand-700">12. Dashboard</a>
                        <a href="#team" class="block text-gray-700 hover:text-brand-700">13. Team access</a>
                        <a href="#faq" class="block text-gray-700 hover:text-brand-700">14. FAQ</a>
                        <a href="#scope" class="block text-gray-700 hover:text-brand-700">15. Not supported yet</a>
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
                            'desc' => 'Your GSTIN, PAN, address and state print on every invoice as the letterhead. Set your invoice number prefix here, and upload a logo and signature.',
                            'cta' => ['label' => 'Open company settings', 'href' => route('company.edit')],
                            'tips' => [
                                '<strong>State.</strong> Your company state decides whether an invoice is intra-state (CGST + SGST) or inter-state (IGST).',
                                '<strong>Invoice prefix.</strong> Add <code>{FY}</code> to the prefix: <code>INV/{FY}/</code> prints as <code>INV/2026-27/0001</code> and restarts on 1 April. Receipt numbers run in a separate, continuous series for each company.',
                                '<strong>Bank details and UPI</strong> add a payment QR code to every invoice.',
                                '<strong>Composition dealer?</strong> Tick the option in settings. Documents then print "Bill of Supply" instead of "Tax Invoice", with the Section 31(3)(c) declaration.',
                            ],
                        ],
                        [
                            'id' => 'customers',
                            'n' => 2,
                            'title' => 'Add your customers',
                            'time' => '1 min per customer',
                            'desc' => 'Save a customer once with GSTIN, address, state, email and mobile, then pick them from the list on every invoice.',
                            'cta' => ['label' => 'Add customer', 'href' => route('customers.create')],
                            'tips' => [
                                '<strong>GSTIN check.</strong> The 15-character format is validated, and its state code must match the selected state.',
                                '<strong>Mobile numbers</strong> can be searched from the Invoices list, for when a customer calls about a bill.',
                                '<strong>Customers with invoices cannot be deleted.</strong> They stay on record for GST audit.',
                                '<strong>Import from Excel.</strong> Customers, items and vendors can be imported from an Excel or CSV file. Download the template from <em>Import</em> on each list, fill it in and upload; you see a preview before anything is saved.',
                            ],
                        ],
                        [
                            'id' => 'products',
                            'n' => 3,
                            'title' => 'Items and stock (optional)',
                            'time' => '1 min per item',
                            'desc' => 'Add the goods and services you sell with name, HSN/SAC, unit, default rate and GST rate. Picking an item on the invoice form fills in all of these.',
                            'cta' => ['label' => 'Add item', 'href' => route('products.create')],
                            'tips' => [
                                '<strong>HSN or SAC.</strong> Goods use HSN (4, 6 or 8 digits). Services use SAC, which starts with 99.',
                                '<strong>UQC units.</strong> Units use the CBIC-notified codes (NOS, KGS, LTR…) so GSTR-1 reconciles.',
                                '<strong>Archived, not deleted.</strong> An item already used on an invoice is archived when you delete it: the invoice keeps its line and the item is no longer offered on new invoices. To bring it back, open the <em>Archived</em> tab on Items and click Restore.',
                                '<strong>Stock (optional).</strong> Turn on <em>Track stock</em> on a goods item and enter the opening stock, a low stock level and the purchase price. For many items at once, fill the Opening Stock and Low Stock Alert columns in the Excel import.',
                                '<strong>How stock moves.</strong> An issued invoice takes stock out (a draft does not), and cancelling it puts the stock back. A purchase invoice brings stock in for every line linked to the item. For anything else, such as damage, a supplier return or a physical count, click <em>Stock</em> next to the item on the Items page to add or remove a quantity with a reason.',
                                '<strong>Checking stock.</strong> The top of the Items page shows stock value at purchase price and how many items are low or out of stock; click either to see those items. <em>Inventory → Stock Summary</em> gives opening, in, out, closing and value for any period, in Excel or PDF. The dashboard lists low-stock items, and the invoice form warns when a line is more than what is in stock.',
                            ],
                        ],
                        [
                            'id' => 'quotations',
                            'n' => 4,
                            'title' => 'Send a quotation first (optional)',
                            'time' => '30 seconds',
                            'desc' => 'For customers who want a price before the sale. A quotation shows the price with GST and converts to a tax invoice in one click once the customer confirms.',
                            'cta' => ['label' => 'New quotation', 'href' => route('quotations.create')],
                            'tips' => [
                                '<strong>Not a tax invoice.</strong> The PDF is titled "Quotation" and states that it is not a tax invoice. Quotations are not reported in GSTR-1 or GSTR-3B and are not affected by locked books.',
                                '<strong>Status.</strong> Draft → Sent → Accepted → Converted (or Declined / Expired). Quotations are valid for 30 days by default and expire automatically.',
                                '<strong>Convert to invoice.</strong> Click <em>Convert to invoice</em> on any quotation that is not declined. A draft invoice is created with the same items for you to review and issue.',
                                '<strong>Separate numbering.</strong> Quotations have their own series (default <span class="font-mono">QT-0001</span>), separate from invoice numbers.',
                            ],
                        ],
                        [
                            'id' => 'invoice',
                            'n' => 5,
                            'title' => 'Create an invoice',
                            'time' => '30 seconds once set up',
                            'desc' => 'Click <em>New invoice</em>. Pick a customer (or type a new name), add items (or type a new product) and save. CGST/SGST or IGST is set from the customer\'s state. For a starting layout, use the <em>Browse templates</em> link in the form.',
                            'cta' => ['label' => 'New invoice', 'href' => route('invoices.create')],
                            'tips' => [
                                '<strong>Draft and final.</strong> Every invoice starts as a draft and can be edited freely. Issuing it assigns the invoice number and locks the amounts.',
                                '<strong>PDF style.</strong> Pick Classic, Executive, Minimal, Mercantile or Heritage under <em>PDF style</em> on the invoice form. The GST format is the same in all of them; only the colours differ. Tick <em>Colour version</em> when downloading to see them.',
                                '<strong>Line discount (Section 15(3)).</strong> Discounts are applied before tax. The discount column prints only when at least one line has a discount.',
                                '<strong>Transporter and e-way bill</strong> details are optional. Fill them in only when shipping goods.',
                                '<strong>Reverse charge</strong> is for RCM supplies under Section 9(3)/9(4) of the CGST Act. No tax is charged and the Rule 46(p) declaration prints on the invoice.',
                            ],
                        ],
                        [
                            'id' => 'finalize',
                            'n' => 6,
                            'title' => 'Issue, download, share and edit',
                            'desc' => 'Open the invoice and click <em>Issue</em> to assign the next number in your series (it restarts on 1 April with the <span class="font-mono">{FY}</span> format). Then <em>Download PDF</em>, <em>Email</em> it with the PDF attached, send it on <em>WhatsApp</em> with a pre-filled message, or <em>Copy link</em> to share a public link valid for 30 days. For returns, rate corrections or discounts after the sale, use <em>Issue credit note</em>. The credit note is reported in GSTR-1 and reduces the invoice balance.',
                            'tips' => [
                                '<strong>PDF copies.</strong> Downloaded PDFs use an ink-saving print layout; tick <em>Colour version</em> for your template colours. Choose the copies to include: Original for Recipient, and the supplier and transporter copies.',
                                '<strong>WhatsApp sharing</strong> uses <span class="font-mono">wa.me</span> links and works from any phone without setup.',
                                '<strong>After issuing,</strong> you can still edit notes, terms, due date and transporter details, but not amounts, items or the customer. Use a credit note for corrections.',
                                '<strong>Cancel, do not delete.</strong> A wrong invoice is cancelled with a short reason, so the audit trail stays complete. Its public link stops working on cancellation.',
                            ],
                        ],
                        [
                            'id' => 'payments',
                            'n' => 7,
                            'title' => 'Record payments and issue receipts',
                            'time' => '20 seconds per payment',
                            'desc' => 'On an issued invoice, fill in <em>Record a payment</em>: amount, method (UPI, NEFT, cash, cheque…), date and reference. A sequential receipt number is assigned, the balance is updated and a printable receipt PDF is created.',
                            'tips' => [
                                '<strong>Part payments.</strong> Record ₹1,000 today and ₹2,000 next week; the balance updates each time.',
                                '<strong>TDS deducted by the customer?</strong> Enter the TDS section (e.g. 194C, 194J, 194Q) and rate on the payment form. The amount is stored with the receipt so your CA can match it with Form 26AS.',
                                '<strong>Following up dues.</strong> <em>Reports → Receivables Aging</em> groups unpaid amounts by age (current / 31–60 / 61–90 / 91+ days), largest first. The <em>Outstanding</em> card on the dashboard opens the list of unpaid invoices.',
                                '<strong>Reverse a payment</strong> entered by mistake. The receipt number stays in the log and the balance is restored.',
                                '<strong>UPI or cheque reference</strong> prints on the receipt PDF.',
                            ],
                        ],
                        [
                            'id' => 'purchases',
                            'n' => 8,
                            'title' => 'Track purchases and expenses',
                            'time' => 'as bills come in',
                            'desc' => 'Record money going out to get a true P&amp;L and claim input tax credit. Use <strong>Purchase invoices</strong> for supplier tax invoices, <strong>Cash memos</strong> for cash purchases where the seller gives no tax invoice (the voucher is made in your own books), and <strong>Expenses</strong> for everything else: rent, salaries, utilities, marketing, software.',
                            'cta' => ['label' => 'New purchase invoice', 'href' => route('purchases.create')],
                            'tips' => [
                                '<strong>Purchase invoices.</strong> Enter the vendor, their invoice number and date, items and GST. The vendor\'s state is read from their GSTIN, so CGST + SGST or IGST is set correctly. Unpaid amounts show under <em>Payments Made</em> and in Payables Aging until you record the payment.',
                                '<strong>Cash memo (purchase voucher).</strong> Records a purchase with the vendor\'s name, GSTIN, items and HSN/SAC. The PDF follows the usual Indian cash memo layout: seller letterhead at the top, your details under "Bill To", and a signature block. Past cash memos are listed in the same module.',
                                '<strong>Expenses.</strong> Recorded with date, vendor, amount, GST input and category (rent, salaries, utilities, marketing, etc.). Categories carry through to the dashboard and P&amp;L.',
                                '<strong>Both count in the P&amp;L.</strong> Cash memos and expenses are deducted from revenue in all three P&amp;L views: accrual, cash and GST.',
                                '<strong>GST input.</strong> The GST on each expense is stored separately for ITC reconciliation when GSTR-3B is filed.',
                            ],
                        ],
                        [
                            'id' => 'bank',
                            'n' => 9,
                            'title' => 'Bank reconciliation',
                            'time' => 'monthly',
                            'desc' => 'Download your account statement from net banking as Excel or CSV and upload it under <em>Banking → Bank Reconciliation</em>. Each bank line is matched to a receipt, vendor payment or expense of the same amount within a few days.',
                            'cta' => ['label' => 'Open bank reconciliation', 'href' => route('bank.index')],
                            'tips' => [
                                '<strong>Any bank.</strong> HDFC, SBI, ICICI, Axis, Kotak and others are read as downloaded, including statements with a single Amount and Dr/Cr column.',
                                '<strong>Unmatched lines</strong> show what is missing in your books, with buttons to record the receipt or expense straight from the bank line.',
                                '<strong>Overlapping statements are fine.</strong> Lines uploaded before are skipped.',
                            ],
                        ],
                        [
                            'id' => 'gst',
                            'n' => 10,
                            'title' => 'GST returns',
                            'time' => 'monthly or quarterly',
                            'desc' => 'Under <em>GST Returns</em>, choose the month or quarter. Figures come from your issued invoices, credit notes, purchase invoices, expenses and cash memos. Returns are prepared here and filed by you or your CA on the GST portal.',
                            'cta' => ['label' => 'Open GSTR-1', 'href' => route('finance.gstr1')],
                            'tips' => [
                                '<strong>GSTR-1.</strong> All tables (B2B, B2CL, B2CS, credit notes, exports, nil-rated, HSN summary and documents issued). Download the JSON and upload it on the portal under <em>Returns → GSTR-1 → Prepare offline → Upload</em>, or the Excel file for the GST offline tool.',
                                '<strong>GSTR-2B match.</strong> Download GSTR-2B from the portal (JSON, ZIP or Excel) and upload it here. Each supplier invoice is marked matched, amount mismatch, missing in your books, or not yet in 2B, so you claim only the credit you are entitled to.',
                                '<strong>GSTR-3B.</strong> Table 3.1 (outward supplies, zero-rated, nil-rated and reverse charge), Table 4 (ITC) and Table 6.1 (tax payable), with input tax credit set off in the order Rule 88A requires. Download as PDF or CSV.',
                                '<strong>Due dates</strong> are shown on each page: GSTR-1 by the 11th (13th for quarterly filers) and GSTR-3B by the 20th (22nd or 24th for quarterly filers, by state).',
                            ],
                        ],
                        [
                            'id' => 'reports',
                            'n' => 11,
                            'title' => 'Reports for your CA',
                            'time' => 'monthly',
                            'desc' => 'The month-end reports a CA asks for are in the dashboard\'s <em>Reports &amp; exports</em> panel, and under <em>Reports</em> and <em>GST Returns</em> in the menu. Reports export as PDF (for review) and CSV (for spreadsheets, accounting software or the GST portal).',
                            'cta' => ['label' => 'Open reports', 'href' => route('reports.index')],
                            'tips' => [
                                '<strong>Receivables Aging</strong> (<em>Reports → Receivables Aging</em>). Every unpaid invoice by age, current (up to 30 days) / 31–60 / 61–90 / 91+, totalled per customer and sorted largest first. Send the PDF to a recovery agent or the CSV to your CA.',
                                '<strong>GSTR-3B summary</strong> (<em>GST Returns → GSTR-3B</em>). Worked out from your books for the selected month: Section 3.1 (outward supplies and RCM), Section 4 (eligible ITC from purchases and expenses) and Section 6.1 (net cash payable). "Total cash to deposit" is the amount to pay through PMT-06. The return is not filed for you; the PDF follows the portal form so the figures are easy to copy.',
                                '<strong>GSTR-1</strong> (<em>GST Returns → GSTR-1</em>). JSON for upload on the portal, Excel for the GST offline tool, and a CSV of outward supplies for your CA.',
                                '<strong>Cash memo export</strong> (<em>Purchases → Cash Memos</em>, PDF or CSV). One statement for every cash purchase in the period, with totals, payment-mode breakdown and amount in words.',
                                '<strong>Expenses CSV</strong> and <strong>Full data backup</strong> are in the dashboard\'s <em>Reports &amp; exports</em> panel. The backup is a ZIP of every invoice, customer, payment and expense as CSV files.',
                                '<strong>Locked books.</strong> After you set <em>Lock books up to</em> in Company settings (e.g. 31 Mar after year-end), entries in the locked period cannot be changed, so the reports above stay as filed.',
                            ],
                        ],
                        [
                            'id' => 'dashboard',
                            'n' => 12,
                            'title' => 'The dashboard',
                            'desc' => 'The dashboard shows <strong>Bills issued</strong> and <strong>Payments received</strong> (lifetime and this month), with outstanding, drafts and this month\'s P&amp;L.',
                            'cta' => ['label' => 'Open dashboard', 'href' => route('dashboard')],
                            'tips' => [
                                '<strong>Outstanding</strong> is everything issued but not fully paid: the amount still to collect.',
                                '<strong>P&amp;L views.</strong> Accrual (by invoice date), cash (by payment date) and GST (collected vs paid).',
                                '<strong>Customer ledger.</strong> Click <em>Ledger</em> on any customer for a running Dr/Cr balance of invoices, payments and credit notes in date order. Useful for monthly statements.',
                                'Click any card to open the list behind it.',
                                '<strong>Search everything.</strong> Type in the search box at the top to find an invoice or customer, or a page or action such as GSTR-1 or "add customer". Press <kbd>/</kbd> to jump to it.',
                                '<strong>Help on every page.</strong> The <em>?</em> button at the top opens the part of this guide for the page you are on. <em>Take the tour</em> in the account menu shows where everything is.',
                            ],
                        ],
                        [
                            'id' => 'team',
                            'n' => 13,
                            'title' => 'Team access',
                            'desc' => 'Invite your accountant, CA or billing staff under <em>Settings → Team</em>. Each person signs in with their own account and works in your business with the role you give them.',
                            'cta' => ['label' => 'Open team', 'href' => route('team.index')],
                            'tips' => [
                                '<strong>Admin.</strong> Everything except managing the team, backups and deleting the business.',
                                '<strong>Accountant / CA.</strong> Sales, purchases, expenses, GST returns, banking and reports. Cannot change company settings or the team.',
                                '<strong>Billing staff.</strong> Quotations, invoices, payments received, customers and items. Cannot see purchases, accounts or GST, and cannot cancel or delete.',
                                '<strong>Activity log.</strong> Every change a team member makes is recorded with their name.',
                            ],
                        ],
                        [
                            'id' => 'faq',
                            'n' => 14,
                            'title' => 'Frequently asked questions',
                            'desc' => null,
                            'faq' => [
                                ['q' => 'Is my data secure?', 'a' => 'Yes. Data is kept separate for each account and company, and never sold or shared.'],
                                ['q' => 'Can I run more than one business?', 'a' => 'Yes. Add more firms under <em>Company</em>, each with its own GSTIN, invoice series and customers. Switch between them from the firm menu at the top of the page.'],
                                ['q' => 'What is the difference between a quotation and an invoice?', 'a' => 'A <strong>quotation</strong> is a price proposal sent <em>before</em> a sale. No GST is charged and nothing is reported. A <strong>tax invoice</strong> is the legal sale document: GST is charged, the invoice number is fixed and it is reported in GSTR-1 and GSTR-3B. When a customer accepts a quotation, click <em>Convert to invoice</em> to create a draft tax invoice with the same items.'],
                                ['q' => 'How do I cancel an issued invoice?', 'a' => 'Open the invoice, click <strong>Cancel invoice</strong> and enter a short reason. The reason is stored on the invoice for the audit trail. A cancelled invoice keeps its number (never reused), accepts no further payments, and its public link stops working. To refund money already collected, issue a credit note.'],
                                ['q' => 'How do I export data for my CA or for GSTR-1 and GSTR-3B?', 'a' => 'See <strong>Section 10, GST returns</strong>. <em>GST Returns → GSTR-1</em> gives the JSON to upload on the portal and the Excel file for the offline tool, and <em>GST Returns → GSTR-3B</em> gives the summary from your books (tables 3.1, 4 and 6.1, as PDF and CSV). All CSV files are UTF-8 with BOM so Excel opens them correctly.'],
                                ['q' => 'How do I see who owes me money, and for how long?', 'a' => 'Open <strong>Reports → Receivables Aging</strong>. It shows four buckets, current (up to 30 days) / 31–60 / 61–90 / 91+, with a per-customer breakdown, largest first. Click a customer to open their ledger. Amounts over 90 days are highlighted. Export the report as PDF or CSV for a recovery agent or your CA.'],
                                ['q' => 'Is GSTR-3B filed from Apna Invoice?', 'a' => 'No. Apna Invoice <strong>works out</strong> the GSTR-3B summary from your invoices, expenses and cash memos but does not file it. Open <em>GST Returns → GSTR-3B</em>, choose the month, check it against your GSTR-1 and ITC ledger, then file on the GST portal yourself or through your CA. The PDF follows the GSTR-3B form layout.'],
                                ['q' => 'How do I close the books at year-end?', 'a' => 'In <strong>Company settings → Lock books up to</strong>, set a date (e.g. 31 March). Invoices, payments, expenses, cash memos and credit notes dated on or before it can then no longer be edited or deleted. The lock is recorded in the activity log.'],
                                ['q' => 'Can I see a running statement for one customer?', 'a' => 'Yes. Open <strong>Customers</strong> and click <em>Ledger</em> next to the name. It lists every invoice, payment and credit note for that customer in date order with a running Dr/Cr balance, ready to send as a monthly statement.'],
                                ['q' => 'Can customers pay by UPI?', 'a' => 'Yes. Add your UPI ID in Company settings and every invoice PDF carries a UPI QR code for the customer to scan and pay.'],
                                ['q' => 'How do I back up my data?', 'a' => 'Open <strong>Settings → Backups</strong> in the menu. Download a ZIP now, or turn on the weekly backup: every Sunday morning a ZIP of your invoices, customers, products, payments and expenses (as CSV files) is emailed to you.'],
                                ['q' => 'How do referrals work?', 'a' => 'Every account has a referral code (like <span class="font-mono">AI-K4X9</span>). Open <strong>Refer a friend</strong> to copy the code and share it on WhatsApp or by email. When a friend signs up with your code and issues their first invoice, you get <strong>lifetime free access</strong> to Apna Invoice. One friend is enough.'],
                            ],
                        ],
                    ];
                @endphp

                @foreach ($sections as $s)
                    <section id="{{ $s['id'] }}" class="scroll-mt-24">
                        <div class="bg-white rounded-2xl ring-1 ring-gray-200 p-6 sm:p-8">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 shrink-0 rounded-full bg-brand-700 text-white font-display font-extrabold flex items-center justify-center">{{ $s['n'] }}</div>
                                <div class="flex-1 min-w-0">
                                    <h2 class="font-display text-xl sm:text-2xl font-extrabold text-gray-900">{{ $s['title'] }}</h2>
                                    @if (! empty($s['time']))
                                        <div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5 font-semibold">{{ $s['time'] }}</div>
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
                                                        <svg class="w-5 h-5 text-gray-500 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
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

                {{-- OUT OF SCOPE, honesty about what we don't do, so users self-select correctly. --}}
                <section id="scope" class="scroll-mt-24">
                    <div class="bg-accent-50 border border-accent-200 rounded-2xl p-6 sm:p-8">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 shrink-0 rounded-full bg-accent-100 text-accent-700 font-display font-extrabold flex items-center justify-center">15</div>
                            <div class="flex-1 min-w-0">
                                <h2 class="font-display text-xl sm:text-2xl font-extrabold text-accent-900">Not supported yet</h2>
                                <p class="mt-2 text-accent-800 leading-relaxed">
                                    Apna Invoice is built for small and mid-sized Indian businesses.
                                    The following GST cases are <strong>not supported</strong> at present.
                                </p>
                                <ul class="mt-4 space-y-3 text-sm text-accent-900">
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-accent-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>E-invoicing (IRN and signed QR from the NIC IRP).</strong> Mandatory since August 2023 for businesses with aggregate turnover above <strong>₹5 crore</strong>. IRNs are not generated here. If you are above this limit, generate them free on the government e-invoice portal (einvoice1.gst.gov.in).</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-accent-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>Filing returns on the GST portal.</strong> Returns are not filed from the app. <strong>GSTR-1</strong> (portal JSON and offline-tool Excel) and <strong>GSTR-3B</strong> (tables 3.1, 4 and 6.1 as PDF and CSV) are prepared for you or your CA to file free on the portal.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-accent-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>Multi-currency billing.</strong> All invoices, including export and SEZ invoices, are billed in INR. Billing in USD, EUR, AED or other currencies is not available.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-accent-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>Compensation cess.</strong> Goods such as tobacco, aerated drinks and luxury cars attract a compensation cess on top of GST. GST is supported; there is no cess line yet.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-accent-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>E-way bill generation.</strong> You can record an e-way bill number, but it is not generated from ewaybillgst.gov.in. For goods movement above ₹50,000, generate the e-way bill on that portal.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <svg class="w-5 h-5 shrink-0 text-accent-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span><strong>TCS under Section 52 (e-commerce operators).</strong> <strong>TDS deducted by your customers</strong> (Section 194-x and 51) is supported on payments received; TCS collected as an e-commerce operator is not.</span>
                                    </li>
                                </ul>
                                <div class="mt-5 p-4 bg-white/60 rounded-lg border border-accent-200">
                                    <div class="text-xs uppercase tracking-wider font-bold text-accent-800">Supported</div>
                                    <p class="mt-2 text-sm text-accent-900 leading-relaxed">
                                        Tax invoices with CGST + SGST or IGST · Export and SEZ invoices (with IGST or under LUT) · HSN/SAC with UQC units ·
                                        Invoice numbering that restarts each financial year · <strong>Composition scheme with Bill of Supply</strong> · <strong>Line discount (Section 15(3))</strong> · <strong>TDS deduction tracking (Section 194-x and 51)</strong> ·
                                        Credit notes (Section 34) · Reverse charge ·
                                        Place of supply and separate ship-to address · Part payments with receipt numbering · <strong>Cash memo / purchase voucher</strong> · <strong>Customer ledger (Dr/Cr running balance)</strong> ·
                                        Invoice cancellation with audit trail · Transporter and e-way bill number · <strong>Books lock after year-end</strong> · <strong>Activity log</strong> ·
                                        <strong>GSTR-1 JSON and Excel</strong> · <strong>GSTR-2B matching</strong> · <strong>GSTR-3B with Rule 88A set-off</strong> · <strong>Purchase invoices and payments made</strong> ·
                                        <strong>Bank reconciliation</strong> · <strong>Stock tracking</strong> · <strong>Team access with roles</strong> · Import from Excel ·
                                        Multiple companies and GSTINs · UPI QR on invoices · Amount in words (lakh/crore).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="bg-white ring-1 ring-gray-200 rounded-xl p-6 sm:p-8">
                    <h2 class="font-display text-xl font-bold text-gray-900">Contact support</h2>
                    <p class="mt-2 text-gray-600 text-sm leading-relaxed">
                        WhatsApp or call us between 9.30 am and 7 pm IST, Monday to Saturday.
                        Emails are usually answered within one business day.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ config('seo.contact.whatsapp_url') }}?text={{ urlencode('Hi Apna Invoice team, I need help with…') }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 h-10 px-4 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-[#0f7540] text-sm font-semibold rounded-md">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            WhatsApp · {{ config('seo.contact.phone_display') }}
                        </a>
                        <a href="tel:{{ config('seo.contact.phone_e164') }}"
                           class="inline-flex items-center gap-2 h-10 px-4 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-gray-800 text-sm font-semibold rounded-md">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            Call
                        </a>
                        <a href="{{ route('pages.contact') }}" class="inline-flex items-center gap-2 h-10 px-2 text-brand-700 hover:text-brand-800 text-sm font-semibold underline">
                            Email or contact form
                        </a>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
