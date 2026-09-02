<?php

/**
 * Automatic contextual internal links for blog posts.
 *
 * Blog traffic is only worth having if it reaches a page that does something.
 * The editor already asks the author for an internal link; this makes the
 * common ones happen without anyone remembering.
 *
 * The phrases below are the ones people ACTUALLY write. The first version of
 * this list was built from page titles - "GST invoice format", "invoice
 * format" - and matched nothing at all on a real article, because writers
 * say "GST invoice" and "tax invoice". A phrase list that reads well in
 * config and never fires is worse than none, because it looks done.
 *
 * How it behaves (see App\Services\Blog\InternalLinker):
 *  - Only the FIRST occurrence of each destination is linked. Repeating the
 *    same link down the page reads as stuffing and Google discounts it.
 *  - Text already inside a link, a heading, or code is never touched.
 *  - There is a cap per post, so a long article does not turn blue.
 *  - The earliest match wins; at the same position the longer phrase wins, so
 *    "GST invoice format" beats "GST invoice" on the same words.
 *  - Whole words only, so "GST" never matches inside "GSTIN".
 */
return [

    // Stop after this many automatic links in one post. Editorial links the
    // author wrote by hand do not count toward it.
    'max_per_post' => 6,

    // route name => phrases that should point at it, most specific first
    'targets' => [

        'pages.gst-invoice-format' => [
            'GST invoice format', 'tax invoice format', 'invoice format',
            'GST invoice template', 'GST invoice', 'tax invoice',
            'HSN code', 'SAC code', 'HSN or SAC', 'HSN/SAC',
        ],

        'pages.gst-calculator' => [
            'GST calculator', 'calculate GST', 'GST calculation',
            'reverse GST', 'GST inclusive', 'CGST and SGST', 'IGST',
        ],

        'pages.cash-memo-format' => [
            'cash memo format', 'cash memo', 'cash bill', 'bill of supply',
        ],

        'pages.credit-note-format' => [
            'credit note format', 'credit note',
        ],

        'pages.billing-software' => [
            'free billing software', 'billing software', 'invoicing software',
            'invoice software', 'accounting software',
        ],

        'pages.how-to-use' => [
            'how to make a GST invoice', 'create a GST invoice',
            'make an invoice', 'issue an invoice',
        ],

        'pages.pricing' => [
            'free plan', 'pricing',
        ],

        'pages.faq' => [
            'frequently asked questions',
        ],

        'pages.partners' => [
            'chartered accountant', 'CA partner',
        ],
    ],
];
