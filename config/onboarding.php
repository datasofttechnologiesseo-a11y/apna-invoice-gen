<?php

/**
 * Activation / retention email sequence.
 *
 * The "welcome" stage is sent immediately at sign-up. The day-1/3/7 nudges are
 * sent by the scheduled `emails:send-activation` command, and ONLY to users who
 * still haven't created their first invoice. As soon as a user creates one, the
 * remaining nudges stop (the command excludes anyone with an invoice).
 *
 * These are onboarding/service emails (helping you use the product you signed up
 * for), not promotional marketing, so they are sent regardless of the marketing
 * opt-in. Every email still carries a clear "why you're getting this" footer.
 */
return [
    'enabled' => env('ONBOARDING_EMAILS_ENABLED', true),

    'stages' => [
        'welcome' => [
            'after_days' => 0,
            'subject' => 'Welcome to Apna Invoice',
            'heading' => 'Welcome to Apna Invoice',
            'lines' => [
                'Thank you for registering with Apna Invoice. Your account is ready.',
                'You can make GST invoices, quotations and credit notes, record purchase bills, payments and stock, and see your GSTR-1 and GSTR-3B figures, all in one place.',
            ],
            'steps_intro' => 'To issue your first invoice:',
            'steps' => [
                'Add your business details and GSTIN.',
                'Add a customer and the items you sell.',
                'Create the invoice and share it on WhatsApp or email.',
            ],
            'cta' => 'Create your first invoice',
            'cta_route' => 'invoices.create',
        ],
        'day1' => [
            'after_days' => 1,
            'subject' => 'Create your first GST invoice',
            'heading' => 'Your first invoice',
            'lines' => [
                'Choose a customer, add a line item and click Issue. CGST and SGST, or IGST, are worked out from the place of supply.',
                'Once issued, the invoice can be shared on WhatsApp or email from the same page.',
            ],
            'cta' => 'Create your first invoice',
            'cta_route' => 'invoices.create',
        ],
        'day3' => [
            'after_days' => 3,
            'subject' => 'Help with your first invoice',
            'heading' => 'Help with your first invoice',
            'lines' => [
                'Your business details are entered once and printed on every invoice after that.',
                'The help page covers each step, from business setup to sharing the invoice.',
            ],
            'cta' => 'Open the help page',
            'cta_route' => 'help',
        ],
        'day7' => [
            'after_days' => 7,
            'subject' => 'Your Apna Invoice account',
            'heading' => 'Your account is ready when you are',
            'lines' => [
                'Your Apna Invoice account is set up. When you next have an invoice to send, sign in from any browser.',
                'Invoices follow Rule 46 of the CGST Rules and are ready for GSTR-1.',
            ],
            'cta' => 'Create your first invoice',
            'cta_route' => 'invoices.create',
        ],
    ],
];
