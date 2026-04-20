<?php

/**
 * Indian GST rate slabs as billable on a tax invoice.
 *
 * Sources: CBIC Rate Notifications (01/2017-CT(R) onward), 56th GST Council
 * rate rationalisation (Sep 2025, "GST 2.0"). The legacy 12% and 28% slabs
 * remain valid for transitional supplies, so both legacy and post-2.0 rates
 * are listed.
 *
 * Each entry: value (numeric, for math), label (shown in dropdown), note (tooltip).
 */

return [

    'rates' => [
        ['value' => 0,     'label' => '0% · Nil / Exempt',               'note' => 'Unbranded staples, fresh produce, salt, education/healthcare services'],
        ['value' => 0.10,  'label' => '0.10% · Merchant export',         'note' => 'Notification 40/2017 — supply to a registered merchant exporter'],
        ['value' => 0.25,  'label' => '0.25% · Rough diamonds',          'note' => 'Cut-and-polished precious and semi-precious stones (rough)'],
        ['value' => 1,     'label' => '1% · Composition scheme',         'note' => 'Composition taxpayers (traders) and brick kilns, notified small supplies'],
        ['value' => 1.5,   'label' => '1.5% · Affordable housing',       'note' => 'Under-construction affordable residential (effective after 1/3rd land abatement)'],
        ['value' => 3,     'label' => '3% · Gold, silver, jewellery',    'note' => 'Precious metals, gemstones, jewellery'],
        ['value' => 5,     'label' => '5% · Merit rate',                 'note' => 'Essentials: tea, coffee, sugar, edible oils, coal, economy transport, branded food'],
        ['value' => 7.5,   'label' => '7.5% · Under-construction housing','note' => 'Under-construction non-affordable residential (effective rate)'],
        ['value' => 12,    'label' => '12% · Standard (legacy)',         'note' => 'Butter, ghee, processed food, mobiles, some services — being phased in GST 2.0'],
        ['value' => 18,    'label' => '18% · Standard rate',             'note' => 'Default slab — most goods and services'],
        ['value' => 28,    'label' => '28% · De-merit (legacy)',         'note' => 'Automobiles, ACs, high-end electronics — being phased in GST 2.0'],
        ['value' => 40,    'label' => '40% · Sin / Luxury (GST 2.0)',    'note' => 'Tobacco, pan-masala, aerated drinks, ultra-luxury (plus applicable compensation cess)'],
    ],

    /** Allowed values for validation. */
    'allowed_values' => [0, 0.10, 0.25, 1, 1.5, 3, 5, 7.5, 12, 18, 28, 40],

];
