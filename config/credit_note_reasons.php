<?php

/**
 * Section 34 of the CGST Act lists the situations in which a supplier MAY
 * (for some) and MUST (for tax-reducing adjustments) issue a credit note.
 * We map each to a user-friendly label shown in the picker.
 */

return [
    'sales_return' => [
        'label' => 'Sales return / goods returned',
        'hint' => 'Customer returned goods — full or partial.',
    ],
    'rate_correction' => [
        'label' => 'Rate / quantity correction',
        'hint' => 'Invoice charged a higher rate or wrong quantity; adjust down.',
    ],
    'post_sale_discount' => [
        'label' => 'Post-sale discount',
        'hint' => 'Discount agreed after invoice issue (e.g. volume rebate).',
    ],
    'deficient_service' => [
        'label' => 'Deficient service',
        'hint' => 'Service not delivered to the agreed standard.',
    ],
    'other' => [
        'label' => 'Other',
        'hint' => 'Describe the adjustment in the notes.',
    ],
];
