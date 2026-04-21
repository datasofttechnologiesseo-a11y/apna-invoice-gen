<?php

/**
 * Invoice templates = a visual style pre-set + a GST-rate default.
 *
 * Line-item sample data used to be pre-filled here, but it created
 * confusion — users would click "Use this template" and then have to
 * delete rows like "Product A — SKU-001" before filling in their own.
 * All templates now start with a single empty row; the user adds real
 * line items (or picks from their saved products).
 */

$emptyRow = fn (float $gstRate = 18) => [
    ['description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'gst_rate' => $gstRate],
];

return [
    'blank' => [
        'label' => 'Blank invoice',
        'tagline' => 'Start fresh with an empty form.',
        'description' => 'One empty line ready to fill. Best when none of the templates fit your exact scenario.',
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
        'description' => 'Perfect for consultants, CAs, lawyers, and agencies billing by the hour.',
        'audience' => 'Consultants · CAs · Lawyers · Agencies',
        'style' => 'classic',
        'icon' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z',
        'gradient' => 'from-brand-600 to-brand-800',
        'tag' => 'Popular',
        'currency' => 'INR',
        'items' => $emptyRow(18),
    ],

    'product_sale' => [
        'label' => 'Product Sale (Goods)',
        'tagline' => 'For trading, retail, and manufacturing.',
        'description' => 'Inventory-style line items with HSN codes for physical goods.',
        'audience' => 'Traders · Retailers · Manufacturers',
        'style' => 'bold',
        'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'gradient' => 'from-accent-500 to-saffron-600',
        'tag' => 'Goods',
        'currency' => 'INR',
        'items' => $emptyRow(18),
    ],

    'subscription' => [
        'label' => 'Subscription / Monthly Retainer',
        'tagline' => 'Recurring fee-based services.',
        'description' => 'SaaS, AMC, support contracts — single line with a flat monthly rate.',
        'audience' => 'SaaS · AMC · Support contracts',
        'style' => 'minimal',
        'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'gradient' => 'from-money-500 to-money-700',
        'tag' => 'Recurring',
        'currency' => 'INR',
        'items' => $emptyRow(18),
    ],

    'freelance' => [
        'label' => 'Freelance / Creative',
        'tagline' => 'Design, development, content.',
        'description' => 'For freelancers billing for creative deliverables or sprints.',
        'audience' => 'Designers · Developers · Content creators',
        'style' => 'minimal',
        'icon' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
        'gradient' => 'from-saffron-500 to-accent-700',
        'tag' => 'Creatives',
        'currency' => 'INR',
        'items' => $emptyRow(18),
    ],

    'rental' => [
        'label' => 'Rental / Lease',
        'tagline' => 'Property, equipment, or vehicle rental.',
        'description' => 'For commercial rental, equipment leasing, vehicle hire. GST at 18% with standard HSN for rental services.',
        'audience' => 'Landlords · Equipment leasing · Vehicle hire',
        'style' => 'classic',
        'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'gradient' => 'from-saffron-600 to-accent-600',
        'tag' => 'Rental',
        'currency' => 'INR',
        'items' => $emptyRow(18),
    ],

    'ecommerce' => [
        'label' => 'e-Commerce Sale',
        'tagline' => 'Online retail with multiple SKUs.',
        'description' => 'For D2C brands, online marketplaces, and e-commerce orders. Multiple SKU line items with shipping.',
        'audience' => 'D2C brands · Marketplaces · Online sellers',
        'style' => 'retail',
        'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        'gradient' => 'from-accent-600 to-brand-700',
        'tag' => 'Retail',
        'currency' => 'INR',
        // Most retail items fall in 5% / 12% / 18% slabs — default to 18
        // and let the user change per row.
        'items' => $emptyRow(18),
    ],

    'restaurant' => [
        'label' => 'Restaurant / Food Service',
        'tagline' => 'Food, beverage, and catering bills.',
        'description' => 'For restaurants, cafés, cloud kitchens, and caterers. 5% GST on food items (no ITC).',
        'audience' => 'Restaurants · Cafés · Cloud kitchens · Caterers',
        'style' => 'warm',
        'icon' => 'M3 3h18v6a3 3 0 11-6 0V3M9 3v6a3 3 0 11-6 0V3m12 18v-5a3 3 0 00-6 0v5',
        'gradient' => 'from-red-500 to-saffron-600',
        'tag' => 'F&B',
        'currency' => 'INR',
        // Food services are typically 5% (no ITC). Pre-select that slab
        // so the user doesn't have to change it on every line.
        'items' => $emptyRow(5),
    ],
];
