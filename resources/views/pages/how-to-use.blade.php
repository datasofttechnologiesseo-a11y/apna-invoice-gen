@php
    $appName = config('seo.name', 'Apna Invoice');
    $contact = config('seo.contact');
    $supportEmail = config('contacts.support');
    $pageUrl = rtrim(config('app.url'), '/') . route('pages.how-to-use', [], false);

    // The first-invoice path, used for the HowTo rich result. Same steps as
    // the first two sections of the page, in the words the app uses.
    $howToSteps = [
        ['Create your free account', 'Choose Continue with Google, or enter your name, mobile number, email and a password and type the 6-digit code sent to your email.', 'sign-up'],
        ['Set up your business', 'Enter the business name and state. GSTIN, address, logo, bank and UPI details are optional. Tap Save and continue.', 'sign-up'],
        ['Add the customer', 'Pick a saved customer or tap + New. The customer\'s state decides whether the invoice has CGST and SGST or IGST.', 'first-invoice'],
        ['Add the items', 'Type each item with its HSN or SAC code, quantity, rate and GST rate. Find code opens the HSN search on the GST portal.', 'first-invoice'],
        ['Save and issue', 'Tap Save and issue, then Issue invoice. The invoice gets the next number in your series and its amounts are locked.', 'first-invoice'],
        ['Share and record payment', 'Send it on WhatsApp or by email, or copy the link. When the customer pays, tap Record payment.', 'share-and-get-paid'],
    ];

    $howToSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'How to make a GST invoice with ' . $appName,
        'description' => 'Make a GST tax invoice online for free: set up your business, add the customer and items with HSN/SAC codes, issue the invoice with CGST, SGST or IGST worked out from the place of supply, then share it on WhatsApp and record the payment.',
        'inLanguage' => 'en-IN',
        'estimatedCost' => ['@type' => 'MonetaryAmount', 'currency' => 'INR', 'value' => '0'],
        'tool' => [['@type' => 'HowToTool', 'name' => 'A phone or computer with a web browser']],
        'step' => array_map(fn ($i, $s) => [
            '@type' => 'HowToStep',
            'position' => $i + 1,
            'name' => $s[0],
            'text' => $s[1],
            'url' => $pageUrl . '#' . $s[2],
        ], array_keys($howToSteps), $howToSteps),
    ];

    // Steps beside a phone screen sit in a grid, outside the page's prose
    // styles, so they carry the same list look themselves.
    $ol = 'list-decimal pl-5 space-y-2 marker:font-semibold marker:text-brand-600';
    $note = 'mt-6 rounded-lg bg-brand-50 px-4 py-3.5 text-[15px] leading-relaxed text-gray-700';
@endphp

<x-layouts.marketing
    title="How to make a GST invoice for free"
    eyebrow="How to use Apna Invoice"
    lead="A step-by-step guide for shop owners, traders and their accountants. From sign-up to your first invoice, payments, purchases and the monthly GST returns."
    description="Make a free GST invoice step by step: set up your business, issue and share on WhatsApp, record payments, match your bank and prepare GSTR-1 and 3B."
    keywords="how to make GST invoice, how to create GST invoice online free, GST billing software India how to use, GSTR-1 JSON download, GSTR-2B match, bank reconciliation"
    type="article"
    :json-ld="[$howToSchema]">

    <p>
        Apna Invoice works in the browser on your phone or computer. It is free during beta, with no card and no
        limit on invoices. Button names in this guide are in bold, exactly as they appear on screen.
    </p>
    <p>
        The screens shown are from a sample business, Sharma Electricals in Pune. To read on paper, the same steps
        are in the <a href="#downloads">free handbook</a>.
    </p>

    {{-- 1. Sign-up and setup --}}
    <h2 id="sign-up">Sign up and set up your business</h2>
    <ol>
        <li>On the <a href="{{ route('register') }}">sign-up page</a>, choose <strong>Continue with Google</strong>. Or fill in your name, mobile number, email and a password, and tap <strong>Create account</strong>.</li>
        <li>If you signed up with email, type the 6-digit code sent to your inbox and tap <strong>Verify and create account</strong>.</li>
        <li>On <strong>Set up your business</strong>, enter the business name and pick your state. The state decides whether a sale carries CGST and SGST or IGST.</li>
        <li>Add your GSTIN if you are registered. Leave it blank if you are not.</li>
        <li>Open <strong>Add more details</strong> for the address, logo, bank account, UPI ID and invoice prefix. All of it is optional and can be filled later in <strong>Company settings</strong>. Tap <strong>Save and continue</strong>.</li>
        <li>Add your first customer and tap <strong>Save customer</strong>, or tap <strong>Skip for now</strong>.</li>
    </ol>
    <p>
        Invoices are numbered per financial year, for example SE/26-27/0001. If you issued invoices in other software
        this year, enter the next running number in <strong>Company settings</strong> to keep the series continuous.
    </p>

    {{-- 2. First invoice --}}
    <h2 id="first-invoice">Make and issue your first invoice</h2>
    <div class="mt-4 grid gap-8 sm:grid-cols-[minmax(0,1fr)_14rem] sm:items-start">
        <ol class="{{ $ol }}">
            <li>Go to <strong>Invoices</strong> and tap <strong>New invoice</strong>. Right after setup, the first invoice opens with your customer already chosen.</li>
            <li>Under <strong>Customer</strong>, type a few letters of the name, or tap <strong>+ New</strong> to add one. The line below the name shows whether the bill is intra-state (CGST + SGST) or inter-state (IGST).</li>
            <li>Leave <strong>Invoice no.</strong> blank. The number is given when you issue.</li>
            <li>Under <strong>Items</strong>, type in the item box to pick a saved item or add a new one. Fill in HSN/SAC, quantity, rate and GST rate. If you are not sure of a code, <strong>Find code</strong> opens the HSN search on the GST portal.</li>
            <li>Tap <strong>Preview PDF</strong> to check it. A draft PDF is marked DRAFT, so it cannot be sent by mistake as the real bill.</li>
            <li>Tap <strong>Save and issue</strong>, then <strong>Issue invoice</strong> to confirm. The invoice gets its number, and the amounts, items and customer are locked.</li>
        </ol>
        <x-screen src="m-invoice-form.webp" :mobile="true"
                  alt="New invoice form on a phone with the customer chosen, the invoice number left blank and the Save draft and Save and issue buttons at the bottom"
                  caption="New invoice on a phone" />
    </div>
    <p>
        In the sample, Sharma Electricals in Pune bills Patil Constructions, also in Maharashtra, so the invoice shows
        CGST and SGST. The same bill to a contractor in Bengaluru would show IGST.
    </p>
    <div class="{{ $note }}">
        Until you issue it, the invoice is a draft. You can keep editing it, tap <strong>Save draft</strong> and come
        back later, or delete it. After issue, notes, terms, due date and transport details can still be changed.
    </div>
    <p>
        For a walk-in customer at the counter, fill in <strong>Paid amount</strong> on the invoice form and a
        receipt is issued with the invoice. On the issued invoice, <strong>More</strong> &rsaquo;
        <strong>Thermal print (80 mm)</strong> prints it on a counter printer.
    </p>

    {{-- 3. Share and get paid --}}
    <h2 id="share-and-get-paid">Share it and get paid</h2>
    <x-screen src="invoice-view.webp" url="apnainvoice.com/invoices" class="mt-5"
              alt="An issued tax invoice with the Share box (WhatsApp, Email, Copy link) and a NEFT receipt recorded against it"
              caption="An issued invoice with its share options and the payment received" />
    <ol>
        <li>Open the issued invoice. In the <strong>Share</strong> box, tap <strong>WhatsApp</strong>. WhatsApp opens with the message and the invoice link ready to send.</li>
        <li>If the customer has no mobile number saved, the same button asks for it. Type it and tap <strong>Save</strong>, and WhatsApp opens. The number is kept for next time.</li>
        <li>To send by email, tap <strong>Email</strong>, check the address and tap <strong>Send email</strong>. The PDF goes as an attachment.</li>
        <li><strong>Copy link</strong> gives a link the customer can open without logging in. It works for 30 days.</li>
        <li>When the money comes in, tap <strong>Record payment</strong>. Enter the amount, the method (UPI, NEFT, cash, cheque) and the date. For a part payment, enter only what came in.</li>
        <li>If the customer deducted TDS, open <strong>TDS deducted by customer</strong> on the same form and enter it. Each payment gets a numbered receipt, and <strong>Receipt PDF</strong> downloads it.</li>
    </ol>
    <p>
        Add your UPI ID in <strong>Company settings</strong> and a UPI QR prints on every invoice. The customer scans it
        with any UPI app and pays the balance.
    </p>
    <p>
        After the due date, unpaid invoices get email reminders on their own. On an overdue invoice, the WhatsApp
        message mentions the balance and how many days it is overdue, so the same button works as a reminder.
    </p>

    {{-- 4. Purchases --}}
    <h2 id="purchases">Record purchases and expenses</h2>
    <p>
        Enter supplier bills so that input tax credit reaches GSTR-3B and your profit and loss is right.
    </p>
    <ol>
        <li>Go to <strong>Purchase invoices</strong> and tap <strong>New purchase invoice</strong>.</li>
        <li>Pick the vendor, or tap <strong>+ New vendor</strong> and enter their GSTIN. The supplier's state is read from the GSTIN.</li>
        <li>Enter the supplier's bill number and date, then the items and GST as printed on their bill. Tap <strong>Record purchase invoice</strong>.</li>
        <li>For rent, electricity, transport and other running costs, go to <strong>Expenses</strong> and tap <strong>New expense</strong>.</li>
        <li>For a cash purchase where the seller gave no bill, go to <strong>Cash memos</strong> and tap <strong>New cash memo</strong>.</li>
    </ol>
    <p>
        Credit blocked under Section 17(5) is kept out of GSTR-3B. If a supplier bill stays unpaid for 180 days, its
        credit is reversed under Rule 37, and claimed again once you pay.
    </p>

    {{-- 5. Returns --}}
    <h2 id="month-end">Month-end: GSTR-1, 2B match and GSTR-3B</h2>
    <p>
        Apna Invoice prepares the returns from your invoices and bills. Filing is done on the GST portal, by you or
        your CA. For monthly filers, GSTR-1 is due by the 11th and GSTR-3B by the 20th. Under QRMP, GSTR-3B is due on
        the 22nd or 24th, depending on your state. The next due dates are shown on the dashboard.
    </p>

    <h3>GSTR-1</h3>
    <ol>
        <li>Go to <strong>GSTR-1</strong>. Pick the <strong>Return month</strong> and <strong>Filing frequency</strong> (monthly or quarterly) and tap <strong>Apply</strong>.</li>
        <li>Check the summary by table: B2B, B2C, exports, credit notes, HSN and documents issued. Drafts are left out. Cancelled invoices appear only in Table 13.</li>
        <li>Tap <strong>JSON for the portal</strong> and upload the file in GSTR-1 on the GST portal. If your CA uses the offline tool, send the Excel file from <strong>Export</strong> instead.</li>
    </ol>
    <x-screen src="gstr1.webp" url="apnainvoice.com/finance/gstr1" class="mt-6"
              alt="GSTR-1 summary for October 2026 with invoices issued, credit notes and the B2B and B2C tables"
              caption="GSTR-1 summary, with the JSON download at the top right" />

    <h3>GSTR-2B match</h3>
    <ol>
        <li>On the GST portal, open Returns &rsaquo; Returns Dashboard, pick the month and download GSTR-2B.</li>
        <li>In Apna Invoice, go to <strong>GSTR-2B match</strong>, choose the file (JSON, zip or Excel) and tap <strong>Upload and match</strong>.</li>
        <li>Each supplier bill shows as matched, mismatched, missing, or not yet in 2B. Follow up with the supplier on the missing ones before you claim the credit.</li>
    </ol>
    <p>The file is only compared with your purchase invoices. It is not sent anywhere.</p>

    <h3>GSTR-3B</h3>
    <ol>
        <li>Go to <strong>GSTR-3B</strong> for the same month.</li>
        <li>Table 3.1 shows the tax on your sales, table 4 the eligible ITC, and table 6.1 the tax to pay, with ITC set off in the Rule 88A order.</li>
        <li>Enter these figures in GSTR-3B on the portal, or download the PDF for your CA. Credit left unused carries to the next month.</li>
    </ol>
    <x-screen src="gstr3b.webp" url="apnainvoice.com/finance/gstr3b" class="mt-6"
              alt="GSTR-3B summary with tables 3.1, 4 and 6.1 and the net cash payable"
              caption="GSTR-3B summary, laid out like the form" />

    {{-- 6. Bank --}}
    <h2 id="bank">Match your bank statement</h2>
    <ol>
        <li>In net banking, open the account statement for the month and download it as Excel (.xlsx) or CSV. Any bank works: HDFC, SBI, ICICI, Axis and others.</li>
        <li>Go to <strong>Bank reconciliation</strong>. Type the account name, for example "HDFC current account", choose the file and tap <strong>Upload and match</strong>. Lines uploaded before are skipped.</li>
        <li>Each line with a receipt, supplier payment or expense of the same amount is matched. Cash entries are never matched to the bank.</li>
        <li>For a line left under <strong>To review</strong>, add the missing entry from the line itself, for example <strong>+ Add as expense</strong> for bank charges. Or tap <strong>Ignore line</strong>.</li>
    </ol>
    <x-screen src="bank.webp" url="apnainvoice.com/bank" class="mt-6"
              alt="Bank reconciliation with seven lines matched and one bank charge of ₹236 left to review"
              caption="Seven lines matched, one bank charge left to add as an expense" />

    {{-- 7. Corrections --}}
    <h2 id="correct-a-mistake">Correct a mistake</h2>
    <p>
        An issued invoice keeps its number for good. How you correct it depends on where it stands.
    </p>
    <h3>The invoice is still a draft</h3>
    <p>
        Open it and tap <strong>Edit</strong> to change anything. To throw it away, tap <strong>Delete draft</strong>.
        Only drafts can be deleted.
    </p>
    <h3>Issued, but not paid</h3>
    <p>
        Open the invoice and tap <strong>More</strong> &rsaquo; <strong>Cancel invoice</strong>. Type the reason and
        confirm with <strong>Cancel invoice</strong>. You can also cancel from the invoice list. The number stays on
        record and is reported in Table 13 of GSTR-1. If that month's GSTR-1 has probably been filed already, a
        warning tells you, so you can check with your CA first.
    </p>
    <p>
        Then make the correct invoice. <strong>More</strong> &rsaquo; <strong>Duplicate</strong> copies the customer
        and items into a new draft, so you only fix what was wrong.
    </p>
    <h3>Paid, or the goods came back</h3>
    <p>
        Use <strong>More</strong> &rsaquo; <strong>Issue credit note</strong>. The credit note reduces the tax in
        GSTR-1 and GSTR-3B, and for a sales return the stock can go back. The Section 34 time limit for each invoice is
        shown, and the option closes once it has passed.
    </p>

    {{-- 8. Phone --}}
    <h2 id="phone">Use it on your phone</h2>
    <p>
        There is no Play Store app. The website works in your phone's browser and can be added to the home screen,
        where it opens like an app.
    </p>
    <div class="mt-4 grid gap-8 sm:grid-cols-[minmax(0,1fr)_14rem] sm:items-start">
        <div>
            <h3 class="font-display text-lg font-bold leading-snug text-gray-900">Android</h3>
            <ol class="mt-2 {{ $ol }}">
                <li>Open apnainvoice.com in Chrome and log in.</li>
                <li>Tap <strong>Install</strong> on the <strong>Install Apna Invoice</strong> bar at the bottom of the screen.</li>
                <li>If the bar does not show, open the Chrome menu (three dots) and tap <strong>Add to Home screen</strong> or <strong>Install app</strong>.</li>
            </ol>
            <h3 class="mt-8 font-display text-lg font-bold leading-snug text-gray-900">iPhone</h3>
            <ol class="mt-2 {{ $ol }}">
                <li>Open apnainvoice.com in Safari and log in.</li>
                <li>Tap the Share button in Safari's toolbar.</li>
                <li>Choose <strong>Add to Home Screen</strong>, then <strong>Add</strong>.</li>
            </ol>
            <p class="mt-6">
                On a phone, the invoice form keeps <strong>Save draft</strong> and <strong>Save and issue</strong> at
                the bottom of the screen, with the grand total beside them.
            </p>
        </div>
        <x-screen src="m-dashboard.webp" :mobile="true"
                  alt="The dashboard on a phone with sales this month, amounts due, and GSTR-1 for October due on 11 Nov"
                  caption="Dashboard on a phone" />
    </div>

    {{-- 9. Team --}}
    <h2 id="team">Give your CA access</h2>
    <ol>
        <li>Go to <strong>Team</strong>, under Settings.</li>
        <li>Under <strong>Invite a team member</strong>, type their email and pick a role.</li>
        <li>Tap <strong>Send invitation</strong>. A pending invite can also be sent with <strong>Copy link</strong> or <strong>Share on WhatsApp</strong>.</li>
        <li>They log in with their own Apna Invoice account. Nobody needs to share a password.</li>
    </ol>
    <div class="mt-6 overflow-hidden rounded-xl ring-1 ring-gray-200">
        <table class="w-full text-left text-[15px]">
            <thead class="bg-gray-50 text-sm text-gray-600">
                <tr>
                    <th scope="col" class="px-4 py-2.5 font-semibold">Role</th>
                    <th scope="col" class="px-4 py-2.5 font-semibold">What they can do</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 align-top">
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Admin</th>
                    <td class="px-4 py-3 text-gray-700">Everything, except managing the team, backups and deleting the business.</td>
                </tr>
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Accountant / CA</th>
                    <td class="px-4 py-3 text-gray-700">Sales, purchases, expenses, GST returns, banking and reports. Cannot change company settings or the team.</td>
                </tr>
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Billing staff</th>
                    <td class="px-4 py-3 text-gray-700">Quotations, invoices, payments, customers and items. Cannot see purchases, accounts or GST, or cancel and delete.</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p>
        Changes made by team members are recorded in the activity log. A CA with several clients can be added to each
        business and switch between them from the business name at the top of the screen.
    </p>

    {{-- 10. Manuals (route names and anchor used elsewhere) --}}
    <h2 id="downloads">Download the handbook</h2>
    <p>
        Print a copy for the shop or send one to your accountant. Both are free PDFs and need no sign-up.
    </p>
    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach ([
            [
                'href'  => route('pages.manuals.handbook'),
                'name'  => 'Apna Invoice Handbook',
                'meta'  => 'PDF · 12 chapters',
                'blurb' => 'The full manual, from your first bill to your GST returns, with a glossary.',
            ],
            [
                'href'  => route('pages.manuals.quick-start'),
                'name'  => 'Quick Start Guide',
                'meta'  => 'PDF · 1 page',
                'blurb' => 'Your first GST invoice in five steps, on one page to keep at the billing counter.',
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

    {{-- 11. Help --}}
    <h2 id="help">Stuck on a step</h2>
    <p>
        Message us on <a href="{{ $contact['whatsapp_url'] }}" target="_blank" rel="noopener">WhatsApp</a>, call
        <a href="tel:{{ $contact['phone_e164'] }}">{{ $contact['phone_display'] }}</a>, or write to
        <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>. Once you are logged in, the help page in the
        app covers each screen.
    </p>
    <p>
        To start, <a href="{{ route('register') }}">create your free account</a>, or work out a tax amount
        first with the <a href="{{ route('pages.gst-calculator') }}">free GST calculator</a>. The full
        <a href="{{ route('pages.features') }}">feature list</a> and <a href="{{ route('pages.pricing') }}">pricing</a>
        are on their own pages.
    </p>
</x-layouts.marketing>
