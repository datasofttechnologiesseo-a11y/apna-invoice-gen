<?php

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
        'items' => [
            ['description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'gst_rate' => 18],
        ],
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
        'items' => [
            ['description' => 'Consulting services', 'hsn_sac' => '998313', 'quantity' => 40, 'unit' => 'hrs', 'rate' => 2500, 'gst_rate' => 18],
            ['description' => 'Project management & coordination', 'hsn_sac' => '998311', 'quantity' => 1, 'unit' => 'pkg', 'rate' => 10000, 'gst_rate' => 18],
        ],
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
        'items' => [
            ['description' => 'Product A — SKU-001', 'hsn_sac' => '8471', 'quantity' => 10, 'unit' => 'pcs', 'rate' => 1500, 'gst_rate' => 18],
            ['description' => 'Product B — SKU-002', 'hsn_sac' => '8471', 'quantity' => 5, 'unit' => 'pcs', 'rate' => 3000, 'gst_rate' => 18],
        ],
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
        'items' => [
            ['description' => 'Monthly subscription — ' . now()->format('F Y'), 'hsn_sac' => '998313', 'quantity' => 1, 'unit' => 'month', 'rate' => 15000, 'gst_rate' => 18],
        ],
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
        'items' => [
            ['description' => 'UI/UX design — landing page', 'hsn_sac' => '998314', 'quantity' => 1, 'unit' => 'project', 'rate' => 35000, 'gst_rate' => 18],
            ['description' => 'Frontend development (2 sprints)', 'hsn_sac' => '998314', 'quantity' => 2, 'unit' => 'sprint', 'rate' => 50000, 'gst_rate' => 18],
        ],
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
        'items' => [
            ['description' => 'Office space rental — ' . now()->format('F Y'), 'hsn_sac' => '997212', 'quantity' => 1, 'unit' => 'month', 'rate' => 75000, 'gst_rate' => 18],
            ['description' => 'Maintenance & common area charges', 'hsn_sac' => '997212', 'quantity' => 1, 'unit' => 'month', 'rate' => 8000, 'gst_rate' => 18],
        ],
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
        'items' => [
            ['description' => 'Cotton T-shirt (M, Blue) — SKU-TSH-001', 'hsn_sac' => '6109', 'quantity' => 3, 'unit' => 'pcs', 'rate' => 599, 'gst_rate' => 5],
            ['description' => 'Denim Jeans (32, Black) — SKU-JNS-002', 'hsn_sac' => '6203', 'quantity' => 1, 'unit' => 'pcs', 'rate' => 1499, 'gst_rate' => 12],
            ['description' => 'Shipping & handling', 'hsn_sac' => '996812', 'quantity' => 1, 'unit' => 'order', 'rate' => 99, 'gst_rate' => 18],
        ],
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
        'items' => [
            ['description' => 'Main course dishes (selection)', 'hsn_sac' => '996331', 'quantity' => 4, 'unit' => 'plate', 'rate' => 350, 'gst_rate' => 5],
            ['description' => 'Beverages', 'hsn_sac' => '996331', 'quantity' => 4, 'unit' => 'glass', 'rate' => 120, 'gst_rate' => 5],
            ['description' => 'Service charge', 'hsn_sac' => '996331', 'quantity' => 1, 'unit' => 'bill', 'rate' => 94, 'gst_rate' => 5],
        ],
    ],
];
