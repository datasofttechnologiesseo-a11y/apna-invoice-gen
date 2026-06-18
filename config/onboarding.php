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
            'heading' => 'Welcome aboard!',
            'lines' => [
                'Thanks for signing up. Apna Invoice helps you create GST-compliant invoices in about 60 seconds, free during beta.',
                'Whenever you are ready, your first invoice is just a few taps away. We handle the CGST, SGST and IGST automatically.',
            ],
            'cta' => 'Create your first invoice',
            'cta_route' => 'invoices.create',
        ],
        'day1' => [
            'after_days' => 1,
            'subject' => 'Your first GST invoice is 60 seconds away',
            'heading' => 'Ready to send your first invoice?',
            'lines' => [
                'Add a customer, type one line item, and tap Issue. We work out the tax split for you based on the customer state.',
                'You can share the invoice on WhatsApp or email straight from the next screen.',
            ],
            'cta' => 'Create your first invoice',
            'cta_route' => 'invoices.create',
        ],
        'day3' => [
            'after_days' => 3,
            'subject' => 'Need a hand with your first invoice?',
            'heading' => 'We are here to help',
            'lines' => [
                'Setting up your business details once means every invoice after that takes under a minute.',
                'Our short how-to guide walks you through it step by step.',
            ],
            'cta' => 'See how it works',
            'cta_route' => 'help',
        ],
        'day7' => [
            'after_days' => 7,
            'subject' => 'Still here whenever you are ready',
            'heading' => 'Your free account is waiting',
            'lines' => [
                'No pressure and no cost. Whenever you have an invoice to send, Apna Invoice is ready in your browser.',
                'Unlimited invoices, GST-ready, and made for Indian businesses.',
            ],
            'cta' => 'Create your first invoice',
            'cta_route' => 'invoices.create',
        ],
    ],
];
