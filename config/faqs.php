<?php

/*
|--------------------------------------------------------------------------
| Frequently asked questions (single source of truth)
|--------------------------------------------------------------------------
| 'groups' is the full FAQ, rendered on /faq in sections with FAQPage
| JSON-LD built from every question. 'general' is the short list the home
| page shows; it is picked from the groups by key below, so the home page
| and /faq can never quote different answers for the same question.
|
| Answers are plain text (they also go into JSON-LD). Only claim what the
| product does today; for anything not available, say so and name the free
| government route where there is one.
*/

$groups = [
    [
        'id' => 'getting-started',
        'title' => 'Getting started',
        'items' => [
            'free' => [
                'q' => 'Is Apna Invoice really free?',
                'a' => 'Yes. It is free during beta, with no card and no limit on invoices, customers or users. Nothing is locked behind a paywall, and everyone who joins during beta keeps the free plan when paid plans start for larger businesses.',
            ],
            'gstin' => [
                'q' => 'Do I need a GSTIN to use it?',
                'a' => 'No. You can start with your business name and state, and add GSTIN, PAN, address, logo and bank details later. If you are registered, add your GSTIN before you bill so it prints on your invoices.',
            ],
            'signup' => [
                'q' => 'What do I need to sign up?',
                'a' => 'Continue with Google, or sign up with your name, mobile number, email and a password and confirm the one-time code sent to your email. Then add your business name and state, and your first customer if you have one handy. Your first invoice opens with that customer already filled in.',
            ],
            'switch' => [
                'q' => 'I keep my accounts in Excel or another software. Can I switch?',
                'a' => 'Yes. Import your customers, items and vendors from an Excel sheet, and keep your own invoice number series if you already have one. You can download all your data again at any time.',
            ],
            'referral' => [
                'q' => 'How do I get Apna Invoice free for life?',
                'a' => 'Share your referral code from the app. When a friend signs up with it and issues their first invoice, your account is free for life.',
            ],
        ],
    ],
    [
        'id' => 'invoices-and-gst',
        'title' => 'Invoices and GST',
        'items' => [
            'interstate' => [
                'q' => 'Can I bill a customer in another state?',
                'a' => 'Yes. GST follows the place of supply: CGST and SGST within your state, IGST when the customer is in another state. A wholesaler in Surat billing a shop in Bengaluru charges IGST, and the same bill to a shop in Vadodara gets CGST and SGST.',
            ],
            'rates' => [
                'q' => 'Which GST rates can I use?',
                'a' => '0%, 0.1%, 0.25%, 1%, 1.5%, 3%, 5%, 7.5%, 18% and 40%, plus 12% and 28% for older items. You choose the rate on each line, and for a saved item it fills in when you pick the item.',
            ],
            'hsn' => [
                'q' => 'Where do I find the HSN or SAC code?',
                'a' => 'Every line has an HSN/SAC field with a Find code link. It opens the official search on the GST portal, where you can look up the code and then type it in.',
            ],
            'composition' => [
                'q' => 'I am a composition dealer. Can I use it?',
                'a' => 'Yes. A composition dealer issues a bill of supply, not a tax invoice, and Apna Invoice makes it with the declaration the law requires.',
            ],
            'counter' => [
                'q' => 'Can I bill walk-in customers at the counter?',
                'a' => 'Yes. Choose a walk-in customer for B2C sales and print the bill on 80 mm thermal paper, as a kirana or medical store counter does. The full-page PDF is there as well.',
            ],
            'numbering' => [
                'q' => 'How are invoice numbers set?',
                'a' => 'By financial year, such as SE/26-27/0001, or in your own series. Numbers stay within the 16 characters allowed by Rule 46(b), and a number is fixed only when you issue the invoice, so there are no gaps or duplicates.',
            ],
            'cancel' => [
                'q' => 'Can I cancel or delete an invoice?',
                'a' => 'Only drafts can be deleted. An unpaid issued invoice can be cancelled with a reason; its number stays on record for GSTR-1 Table 13, and you are warned if that month\'s GSTR-1 is probably filed already. A paid invoice is corrected with a credit note.',
            ],
            'credit-note' => [
                'q' => 'How do I correct an invoice or take back goods?',
                'a' => 'Raise a credit note against the invoice under Section 34. The time limit for it is tracked, and for a sales return you can put the goods back into stock.',
            ],
            'einvoice' => [
                'q' => 'Does it generate e-invoices (IRN) or e-way bills?',
                'a' => 'No. If your turnover needs e-invoicing, generate the IRN on the government\'s free e-invoice portal. For an e-way bill, create it on the free e-way bill portal (ewaybillgst.gov.in) and type its number and the transporter on your invoice.',
            ],
            'export' => [
                'q' => 'Can I make export or SEZ invoices?',
                'a' => 'Yes, under LUT or with IGST, with the port code and shipping bill details. The invoice is in rupees, since billing in foreign currency is not available. Reverse charge invoices and a separate ship-to address are also supported.',
            ],
            'quotation' => [
                'q' => 'Can I send a quotation first?',
                'a' => 'Yes. Quotations have their own number series, and once the customer agrees you turn the quotation into an invoice in one click.',
            ],
        ],
    ],
    [
        'id' => 'payments-and-sharing',
        'title' => 'Payments and sharing',
        'items' => [
            'whatsapp' => [
                'q' => 'How do I send an invoice on WhatsApp?',
                'a' => 'Tap WhatsApp on the invoice and it opens with a ready message and the invoice link. If the customer has no mobile number saved, type it on the invoice page. You can also email the PDF or copy a link that works for 30 days.',
            ],
            'upi' => [
                'q' => 'Can customers pay me by UPI?',
                'a' => 'Yes. Add your UPI ID and your invoices carry a UPI QR code. The customer scans it in any UPI app and pays the balance due.',
            ],
            'part-payment' => [
                'q' => 'Can I record part payments and TDS?',
                'a' => 'Yes. Record each part payment as it comes in, with TDS if the customer deducted it. Each one gets a numbered receipt, and the invoice shows the balance still due.',
            ],
            'duplicate-payment' => [
                'q' => 'What happens if I enter a payment twice?',
                'a' => 'Apna Invoice stops it. A double tap on save records one payment, a second entry with the same UTR or cheque number is refused, and a payment can never be more than the balance due.',
            ],
            'reminders' => [
                'q' => 'Does it send payment reminders?',
                'a' => 'Yes. Email reminders go out on their own after the due date, and you can send a WhatsApp reminder in one tap. SMS reminders are not available.',
            ],
        ],
    ],
    [
        'id' => 'purchases-stock-bank',
        'title' => 'Purchases, stock and bank',
        'items' => [
            'purchases' => [
                'q' => 'Can I enter purchase bills and claim input tax credit?',
                'a' => 'Yes. Enter the supplier bill with items and GST, and the supplier\'s state is read from their GSTIN. Eligible credit goes to GSTR-3B, blocked credit under Section 17(5) stays out, and under Rule 37 credit is reversed when a supplier stays unpaid for 180 days and claimed again once you pay.',
            ],
            'cash-memo' => [
                'q' => 'How do I record a cash purchase with no bill?',
                'a' => 'Make a cash memo. It records the purchase in your own books when the seller gives no bill. Rent, salaries, electricity and other costs go in as expenses by category.',
            ],
            'stock' => [
                'q' => 'Does it keep track of stock?',
                'a' => 'Yes, for the items where you switch it on. Issued invoices reduce stock, purchases add to it, and cancellations and returns put it back. Items below their low-stock level show on the dashboard.',
            ],
            'barcode' => [
                'q' => 'Does it support barcode billing, batch, expiry or more than one godown?',
                'a' => 'No. There is no barcode or POS mode, no batch or expiry tracking and no separate godowns. Stock is one quantity per item, with adjustments and a full history.',
            ],
            'bank' => [
                'q' => 'Can I match my bank statement?',
                'a' => 'Yes. Download the statement from net banking as Excel or CSV, from HDFC, SBI, ICICI, Axis or any other bank, and upload it. Each line is matched with a receipt, supplier payment or expense of the same amount, and you can add a missing entry from the bank line.',
            ],
        ],
    ],
    [
        'id' => 'gst-returns',
        'title' => 'GST returns',
        'items' => [
            'gstr1' => [
                'q' => 'Can I file GSTR-1 from Apna Invoice?',
                'a' => 'No, filing is done on the GST portal by you or your CA. Apna Invoice prepares all GSTR-1 tables from your invoices and gives you a JSON file to upload on the portal, plus an Excel file for the offline tool.',
            ],
            'gstr3b' => [
                'q' => 'Does it work out GSTR-3B?',
                'a' => 'Yes. Tables 3.1, 4 and 6.1 are filled from your sales and purchases, with input tax credit set off in the Rule 88A order and unused credit carried to the next month. You then file it on the GST portal, by the 20th for monthly filers.',
            ],
            'gstr2b' => [
                'q' => 'How do I check my purchases against GSTR-2B?',
                'a' => 'Download GSTR-2B from the GST portal and upload it in Apna Invoice. Each supplier bill shows as matched, mismatched, missing or not yet in 2B, so you can follow up with the supplier before you claim credit.',
            ],
            'qrmp' => [
                'q' => 'I file quarterly under QRMP. Does it work for me?',
                'a' => 'Yes. Returns can be prepared monthly or quarterly, and the dashboard shows your due dates, including the 22nd or 24th for quarterly GSTR-3B depending on your state.',
            ],
            'ca' => [
                'q' => 'Can my CA see my books?',
                'a' => 'Yes. Add your CA to your team with the Accountant/CA role. They see your books and can download the reports and return files they need.',
            ],
            'tally' => [
                'q' => 'Can I export to Tally?',
                'a' => 'No, there is no Tally export. Sales and purchase registers, customer ledgers, the HSN summary and other reports download in Excel, CSV or PDF for your CA to work from.',
            ],
        ],
    ],
    [
        'id' => 'account-data-support',
        'title' => 'Account, data and support',
        'items' => [
            'phone' => [
                'q' => 'Can I use it on my phone?',
                'a' => 'Yes. It works in any browser on phone, tablet or computer, with the same account. To keep it on your home screen, tap Install on the bar that appears on Android, or on iPhone tap Share and then Add to Home Screen.',
            ],
            'play-store' => [
                'q' => 'Is there a Play Store app?',
                'a' => 'No, there is no Play Store or App Store app. Open apnainvoice.com on your phone and install it from the browser; it then opens from your home screen like any other app.',
            ],
            'hindi' => [
                'q' => 'Is it available in Hindi?',
                'a' => 'No. The screens are in English, and Hindi or other Indian languages are not available.',
            ],
            'multi-business' => [
                'q' => 'Can I run more than one business or GSTIN?',
                'a' => 'Yes. Add several businesses under one login and switch between them. Each one keeps its own invoices, customers and returns.',
            ],
            'staff' => [
                'q' => 'Can my staff make bills from their own login?',
                'a' => 'Yes. Give them the Billing staff role, keep Admin for yourself and Accountant/CA for your CA. The activity log shows who did what.',
            ],
            'data' => [
                'q' => 'Is my data safe? Can I download it?',
                'a' => 'Only the people you add to your team can open your books, and the activity log shows who changed what. You can download a full backup at any time or get one by email every week, and a books lock stops changes to years you have closed.',
            ],
            'recurring' => [
                'q' => 'Can I set up recurring invoices?',
                'a' => 'Not automatically. For a monthly bill, open last month\'s invoice and use Duplicate under More: it copies the customer and items into a new draft dated today.',
            ],
            'help' => [
                'q' => 'Where can I get help?',
                'a' => 'Use the help page inside the app, or download the free handbook and quick-start guide as PDF. For anything else, message us on WhatsApp, call or email; the details are on the contact page.',
            ],
        ],
    ],
];

// The eight questions the home page shows, in this order.
$homeKeys = ['free', 'gstin', 'interstate', 'gstr1', 'einvoice', 'phone', 'ca', 'data'];

$byKey = [];
foreach ($groups as $group) {
    $byKey += $group['items'];
}

return [

    'general' => array_map(fn ($key) => $byKey[$key], $homeKeys),

    'groups' => array_map(function ($group) {
        $group['items'] = array_values($group['items']);

        return $group;
    }, $groups),

];
