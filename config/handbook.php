<?php

/**
 * Content for the downloadable Apna Invoice manuals.
 *
 * Kept as data rather than markup so the same source can be rendered to PDF,
 * and later to an on-site HTML page, without the two drifting apart. Block
 * types understood by resources/views/manuals/_blocks.blade.php:
 *
 *   p     — a paragraph                      ['type' => 'p', 'text' => '...']
 *   list  — bullet points                    ['type' => 'list', 'items' => [...]]
 *   steps — a numbered sequence              ['type' => 'steps', 'items' => [...]]
 *   note  — a labelled callout               ['type' => 'note', 'label' => '...', 'text' => '...']
 *   table — a data table                     ['type' => 'table', 'head' => [...], 'rows' => [[...]]]
 *
 * Written in plain English for readers who use English as a second language:
 * short sentences, no idioms, and every GST term explained the first time it
 * appears. Devanagari is deliberately avoided — the PDF font (DejaVu Sans)
 * has no Devanagari glyphs and would render empty boxes.
 */
return [

    'version' => '1.0',
    'updated' => '2026-08-31',

    'quick_start' => [
        'title' => 'Quick Start',
        'subtitle' => 'Your first GST invoice, in five steps',
        'steps' => [
            [
                'title' => 'Create your account',
                'text' => 'Enter your name, mobile number and email. We send a 6-digit code to your email — type it in to finish. No credit card is needed.',
            ],
            [
                'title' => 'Add your business',
                'text' => 'Only two things are needed: your business name and your state. If you have a GSTIN, type it and your state is filled in automatically. Everything else can wait.',
            ],
            [
                'title' => 'Add your customer',
                'text' => 'Name and state are enough. Add their GSTIN only if they are GST-registered. You can add a customer while making the invoice, without leaving the page.',
            ],
            [
                'title' => 'Enter what you sold',
                'text' => 'Description, HSN or SAC code, quantity, rate and GST rate. The tax is calculated for you: CGST and SGST inside your state, IGST for another state.',
            ],
            [
                'title' => 'Issue and send',
                'text' => 'Click Issue. The bill gets a permanent number and locks. Download the PDF, or send it on WhatsApp in one tap.',
            ],
        ],
        'footer' => 'Stuck at any step? Message us on WhatsApp. We help new businesses set up every day.',
    ],

    'chapters' => [

        [
            'title' => 'What this software does',
            'blocks' => [
                ['type' => 'p', 'text' => 'Apna Invoice makes GST bills for Indian businesses. You enter what you sold and to whom. The software works out the tax, gives the bill a proper number, and creates a PDF you can send on WhatsApp or email.'],
                ['type' => 'p', 'text' => 'It is built for people who are not accountants. You do not need to know which tax applies — the software decides that from your state and your customer\'s state.'],
                ['type' => 'list', 'items' => [
                    'Tax invoices — the normal GST bill for a sale.',
                    'Quotations — a price offer, which can become an invoice later.',
                    'Credit notes — when a sale is returned or reduced.',
                    'Cash memos — a simple bill for a cash sale.',
                    'Payment receipts — proof that money came in.',
                    'GST reports — GSTR-1 and GSTR-3B summaries for filing.',
                ]],
                ['type' => 'note', 'label' => 'Good to know', 'text' => 'You do not need a GST number to use Apna Invoice. If you are not registered, leave the GSTIN field empty and the software makes a simple bill of supply instead of a tax invoice.'],
            ],
        ],

        [
            'title' => 'Create your account',
            'blocks' => [
                ['type' => 'p', 'text' => 'Signing up takes about a minute. There is no credit card and nothing to install.'],
                ['type' => 'steps', 'items' => [
                    'Open the website and click Signup for Free.',
                    'Enter your name, mobile number, email and a password. The mobile number must be a 10-digit Indian number.',
                    'Accept the Terms and Privacy Policy, then click Create my free account.',
                    'We send a 6-digit code to your email. Type it in the box. It submits on its own once all six digits are in.',
                ]],
                ['type' => 'p', 'text' => 'You can also click Sign up with Google. That skips the password, but we still ask for your mobile number so our team can help you if you get stuck.'],
                ['type' => 'note', 'label' => 'If the code does not arrive', 'text' => 'Check your Spam and Promotions folders first — it can take up to a minute. You can ask for a new code after 30 seconds, up to 3 times. The code is valid for 10 minutes and you get 5 tries to enter it. If it still does not reach you, message us on WhatsApp and we will verify you by hand.'],
                ['type' => 'p', 'text' => 'Your account is created only after the code is confirmed, so nothing is saved until you finish this step.'],
            ],
        ],

        [
            'title' => 'Set up your business',
            'blocks' => [
                ['type' => 'p', 'text' => 'After signing up you land on a short setup form. Only two things are needed to start billing.'],
                ['type' => 'steps', 'items' => [
                    'Business name — this prints at the top of every invoice as your letterhead.',
                    'State — this decides whether a sale is taxed as CGST plus SGST, or as IGST.',
                ]],
                ['type' => 'p', 'text' => 'Everything else sits under Add more details and can be filled in later from Company settings: your address, PAN, logo, bank account, UPI ID and invoice number format.'],
                ['type' => 'p', 'text' => 'If you have a GSTIN, type it in the box. Your state is selected automatically from the first two digits, which are the state code. The address fields then become required, because GST Rule 46 says a tax invoice must carry the supplier\'s registered address.'],
                ['type' => 'note', 'label' => 'GST rule', 'text' => 'A GSTIN is always stored and shown in capital letters. Type it in small letters if you like — the software converts it for you, everywhere in the app and on every PDF.'],
                ['type' => 'p', 'text' => 'By default your bills are numbered like INV/2026-27/0001. The number resets to 0001 on 1 April each year, which is the accepted practice for a financial year series under Rule 46(a). You can change the prefix in Company settings.'],
                ['type' => 'p', 'text' => 'If you add a UPI ID, a scan-and-pay QR code is printed on every invoice PDF automatically. Customers scan it with any UPI app and pay you directly. Bank name, account number and IFSC appear in the payment block on the bill.'],
            ],
        ],

        [
            'title' => 'Customers and products',
            'blocks' => [
                ['type' => 'p', 'text' => 'Save a customer once and reuse them on every future bill. Only name and state are required. The state matters because it decides the tax split. Address, phone and email are optional.'],
                ['type' => 'p', 'text' => 'Add a GSTIN only if your customer is GST-registered. That makes the sale B2B, which is reported separately in GSTR-1. Without a GSTIN the sale is treated as B2C.'],
                ['type' => 'note', 'label' => 'Faster way', 'text' => 'You do not have to leave the invoice screen to add a customer. Start typing a name in the customer box and choose add new. A small window opens, you fill in name and state, and you are back on the invoice.'],
                ['type' => 'p', 'text' => 'Each customer also has a ledger — a running statement of every bill raised to them and every payment received. You can download it as a PDF if they ask for an account statement.'],
                ['type' => 'p', 'text' => 'Saving products is optional but saves typing. For anything you sell regularly, save it once with its name, HSN or SAC code, unit, price and GST rate. On future invoices, type the first few letters and the rest fills in. HSN codes are for goods and SAC codes are for services.'],
            ],
        ],

        [
            'title' => 'Make an invoice',
            'blocks' => [
                ['type' => 'p', 'text' => 'Click New invoice from anywhere in the app.'],
                ['type' => 'steps', 'items' => [
                    'Choose the customer. Start typing their name. The software reads their state from their record.',
                    'Check the dates. Invoice date defaults to today and due date to 30 days later.',
                    'Add your line items: description, HSN or SAC code, quantity, rate, discount if any, and the GST rate.',
                    'Watch the totals. Taxable value, tax and grand total update as you type.',
                    'Save. The bill is saved as a draft. Drafts can be edited freely and have no invoice number yet.',
                ]],
                ['type' => 'p', 'text' => 'The software compares your state with your customer\'s state to decide the tax:'],
                ['type' => 'table', 'head' => ['Situation', 'Tax applied', 'On ' . "\u{20B9}" . '10,000 at 18%'], 'rows' => [
                    ['Same state (Maharashtra to Maharashtra)', 'CGST + SGST', "\u{20B9}" . '900 + ' . "\u{20B9}" . '900'],
                    ['Different state (Maharashtra to Karnataka)', 'IGST', "\u{20B9}" . '1,800'],
                ]],
                ['type' => 'p', 'text' => 'The total tax is the same either way. Only the split changes, and it is decided for you.'],
                ['type' => 'note', 'label' => 'GST rule', 'text' => 'For goods, place of supply is normally where the goods are delivered. If you are shipping to a different address from the billing address, fill in the Ship to section. The consignee\'s state then drives the tax split, and you can record a consignee GSTIN there too.'],
            ],
        ],

        [
            'title' => 'Issue the invoice',
            'blocks' => [
                ['type' => 'p', 'text' => 'A draft is not a legal bill yet. To make it official, open it and click Issue. Three things happen at that moment: the invoice gets its permanent number from your series, the invoice is locked so amounts and items can no longer be edited, and the GST invoice PDF becomes available.'],
                ['type' => 'note', 'label' => 'Careful', 'text' => 'Once issued, an invoice cannot be edited or deleted. This is deliberate — GST law does not allow you to quietly change a bill you have already given a customer. If something is wrong, issue a credit note against it instead, or cancel it with a recorded reason.'],
                ['type' => 'table', 'head' => ['Status', 'Meaning'], 'rows' => [
                    ['Draft', 'Still being written. No number, fully editable, not a legal document.'],
                    ['Issued', 'Final and locked. Has a permanent number. Money not received yet.'],
                    ['Partly paid', 'Some payment received, some still outstanding.'],
                    ['Paid', 'Full amount received.'],
                    ['Cancelled', 'Cancelled with a reason. The number is retained and not reused.'],
                ]],
            ],
        ],

        [
            'title' => 'Share it and get paid',
            'blocks' => [
                ['type' => 'list', 'items' => [
                    'WhatsApp — opens WhatsApp with a ready message and the invoice link.',
                    'Email — sends the PDF to your customer\'s email address.',
                    'Public link — a secure link your customer can open without logging in. It works for 30 days.',
                    'Download PDF — save or print it. There is also a thermal-printer layout for shop counters.',
                ]],
                ['type' => 'p', 'text' => 'When money comes in, open the invoice and click Record payment. Enter the amount, the date and how it was paid. The invoice moves to Partly paid or Paid on its own, and a numbered payment receipt is created that you can send to the customer.'],
                ['type' => 'p', 'text' => 'Part payments are fully supported. Record each one as it arrives and the balance updates every time.'],
            ],
        ],

        [
            'title' => 'Quotations, credit notes and cash memos',
            'blocks' => [
                ['type' => 'p', 'text' => 'A quotation is a price offer, not a bill. No GST is due on it and it does not appear in your returns. Make one the same way as an invoice. If the customer accepts, open it and click Convert to invoice. All the line items carry across into a new draft invoice. A quotation can be converted only once, which prevents the same job being billed twice.'],
                ['type' => 'p', 'text' => 'A credit note reduces or cancels a sale you have already billed. Use one when goods come back, when you overcharged, or when a bill is cancelled after being issued. Open the invoice and choose Create credit note. You can credit the whole invoice or part of it, and the tax is reversed in the same proportion.'],
                ['type' => 'note', 'label' => 'GST rule', 'text' => 'Under Section 34 of the CGST Act, a credit note affecting your tax liability must be issued by 30 November following the end of the financial year of the original invoice, or the date of filing the annual return, whichever is earlier. After that you can still give a commercial credit, but the tax cannot be reversed.'],
                ['type' => 'p', 'text' => 'A cash memo is a simple bill for an over-the-counter cash sale, common in shops. It has its own number series and can be printed on a normal or thermal printer. Find it under Finance, then Cash memos.'],
            ],
        ],

        [
            'title' => 'Expenses and input tax credit',
            'blocks' => [
                ['type' => 'p', 'text' => 'Recording what you spend lets the software show your real profit and your input tax credit.'],
                ['type' => 'steps', 'items' => [
                    'Go to Finance, then Expenses, and click add.',
                    'Enter the date, category, description and amount.',
                    'Enter the GST amount separately if the bill had GST on it.',
                    'Tick ITC eligible only if you can legally claim that credit.',
                ]],
                ['type' => 'note', 'label' => 'Careful', 'text' => 'Some expenses are blocked from input tax credit under Section 17(5) — staff food, personal vehicles and similar. Leave the ITC box unticked for those. Anything unticked is excluded from your GSTR-3B credit figure, which keeps your return honest.'],
            ],
        ],

        [
            'title' => 'Reports for your CA',
            'blocks' => [
                ['type' => 'p', 'text' => 'Everything your accountant needs sits under Finance and on the dashboard. All reports download as CSV, which opens in Excel and can be uploaded to the GST portal.'],
                ['type' => 'table', 'head' => ['Report', 'What it shows', 'When to use it'], 'rows' => [
                    ['GSTR-1', 'Every issued invoice for a period, split into B2B and B2C, with place of supply and an HSN summary.', 'Monthly or quarterly filing of outward supplies.'],
                    ['GSTR-3B', 'Outward supplies net of credit notes, input tax credit, and net GST payable.', 'Monthly summary return.'],
                    ['Receivables aging', 'Who owes you money and for how long, in 0-30, 30-60, 60-90 and 90+ day buckets.', 'Following up on overdue payments.'],
                    ['Profit and loss', 'Income minus expenses for the period, excluding GST.', 'Checking whether you are making money.'],
                    ['Expenses', 'All spending by category with GST input separated.', 'Books and ITC checks.'],
                ]],
                ['type' => 'note', 'label' => 'Good to know', 'text' => 'Reports run on the period you choose, and both the first and last day of that period are included. Billing on the last day of the month is normal in India, and those invoices are counted.'],
            ],
        ],

        [
            'title' => 'More than one business, and backups',
            'blocks' => [
                ['type' => 'p', 'text' => 'If you run several firms, each with its own GSTIN, you can keep them all in one login. Add each one under Companies. The business you are working in is shown at the top of the screen under Active company. Click it to switch. Every invoice, customer, product and report belongs to the active business only, so figures never mix between firms.'],
                ['type' => 'p', 'text' => 'Under Backup you can download a ZIP file containing CSV copies of your invoices, customers, products and payments. You can also have a backup emailed to you. Data is stored in India, sent over an encrypted connection, and encrypted where it rests.'],
                ['type' => 'note', 'label' => 'Tip', 'text' => 'Take a backup at the end of every financial year and keep the ZIP somewhere safe. It is a plain CSV file that any accountant or spreadsheet can read, even years later.'],
            ],
        ],

        [
            'title' => 'Quick reference',
            'blocks' => [
                ['type' => 'table', 'head' => ['Your situation', 'Make this'], 'rows' => [
                    ['Normal sale, you are GST-registered', 'Tax invoice'],
                    ['Normal sale, you are not GST-registered', 'Invoice (bill of supply)'],
                    ['Customer wants a price first', 'Quotation'],
                    ['Goods returned or you overcharged', 'Credit note'],
                    ['Cash sale across the counter', 'Cash memo'],
                    ['Money received against a bill', 'Record payment, which creates a receipt'],
                ]],
                ['type' => 'table', 'head' => ['Word', 'Plain meaning'], 'rows' => [
                    ['GSTIN', 'Your 15-character GST registration number.'],
                    ['HSN / SAC', 'Standard code for what you sell. HSN for goods, SAC for services.'],
                    ['CGST / SGST', 'The two halves of tax on a sale inside your own state.'],
                    ['IGST', 'Single tax on a sale to another state.'],
                    ['Place of supply', 'The state that decides which tax applies.'],
                    ['Taxable value', 'The amount before GST is added.'],
                    ['ITC', 'Input tax credit — GST you paid on purchases and can set off.'],
                    ['Outstanding', 'Money billed but not yet received.'],
                    ['Receivables aging', 'A list of unpaid bills grouped by how late they are.'],
                ]],
                ['type' => 'note', 'label' => 'One last thing', 'text' => 'Apna Invoice does not file your returns for you and is not a substitute for your accountant. It prepares correct documents and clean summaries so that filing is quick and cheap. Decisions about what to claim remain yours and your CA\'s.'],
            ],
        ],
    ],
];
