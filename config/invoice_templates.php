<?php

/**
 * Invoice templates = a visual style pre-set + a GST-rate default.
 *
 * Curated to three high-clarity starting points so users aren't paralysed
 * by choice. Each template only sets the visual style + a sensible default
 * GST rate; line items always start with a single empty row that the user
 * fills in (or picks from their saved products).
 */

$emptyRow = fn (float $gstRate = 18) => [
    ['description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'gst_rate' => $gstRate],
];

return [
    'blank' => [
        'label' => 'Blank invoice',
        'tagline' => 'Start fresh with an empty form.',
        'description' => 'One empty line ready to fill. Best when none of the other templates fit your scenario.',
        'audience' => 'Any business that wants a clean slate',
        'style' => 'classic',
        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'gradient' => 'from-gray-500 to-gray-700',
        'tag' => 'Default',
        'currency' => null,
        'items' => $emptyRow(),
    ],

    'consulting' => [
        'label' => 'Consulting / Professional Services',
        'tagline' => 'Hourly or per-engagement billing.',
        'description' => 'For consultants, CAs, lawyers, agencies, freelancers, designers, developers, content creators, SaaS, AMC and support contracts - anyone billing for time, expertise or recurring services.',
        'audience' => 'Consultants · CAs · Lawyers · Agencies · Freelancers · SaaS',
        'style' => 'classic',
        'icon' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z',
        'gradient' => 'from-brand-600 to-brand-800',
        'tag' => 'Popular',
        'currency' => 'INR',
        'items' => $emptyRow(18),
    ],

    'product_sale' => [
        'label' => 'Product Sale (Goods)',
        'tagline' => 'For trading, retail, manufacturing & e-commerce.',
        'description' => 'Inventory-style line items with HSN codes for physical goods - covers traders, retailers, manufacturers, D2C brands, online sellers, restaurants, F&B, and rental of equipment or property.',
        'audience' => 'Traders · Retailers · Manufacturers · D2C · F&B · Rental',
        'style' => 'bold',
        'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'gradient' => 'from-accent-500 to-saffron-600',
        'tag' => 'Goods',
        'currency' => 'INR',
        'items' => $emptyRow(18),
    ],
];
