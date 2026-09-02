<?php

/**
 * Automatic contextual internal links for blog posts.
 *
 * Blog traffic is only worth having if it reaches a page that does something.
 * The editor already asks the author for an internal link; this makes the
 * common ones happen without anyone remembering.
 *
 * How it behaves (see App\Services\Blog\InternalLinker):
 *  - Only the FIRST occurrence of each phrase is linked. Repeating the same
 *    link down the page looks spammy and Google discounts it.
 *  - Text already inside a link, a heading, or a code block is never touched.
 *  - There is a cap per post, so a long article does not turn blue.
 *  - Longer phrases win: "GST invoice format" beats "invoice" on the same run
 *    of text.
 *
 * Phrases are matched case-insensitively on whole words. Keep them natural -
 * a phrase nobody would actually write is a phrase that never links.
 */
return [

    // Stop after this many automatic links in one post. Editorial links the
    // author wrote by hand do not count toward it.
    'max_per_post' => 6,

    // route name => phrases that should point at it
    'targets' => [
        'pages.gst-calculator' => [
            'GST calculator', 'GST calculation', 'calculate GST',
            'reverse GST', 'GST inclusive',
        ],
        'pages.gst-invoice-format' => [
            'GST invoice format', 'tax invoice format', 'invoice format',
            'GST invoice template',
        ],
        'pages.cash-memo-format' => [
            'cash memo format', 'cash memo', 'cash bill',
        ],
        'pages.credit-note-format' => [
            'credit note format', 'credit note',
        ],
        'pages.billing-software' => [
            'free billing software', 'billing software', 'invoicing software',
        ],
        'pages.how-to-use' => [
            'how to make a GST invoice', 'create a GST invoice',
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
