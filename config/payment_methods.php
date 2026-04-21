<?php

/**
 * Payment methods commonly used in Indian business transactions.
 * Store the code; display the label on receipts.
 */

return [

    'methods' => [
        'cash'    => ['label' => 'Cash',    'needs_reference' => false],
        'upi'     => ['label' => 'UPI',     'needs_reference' => true],
        'neft'    => ['label' => 'NEFT',    'needs_reference' => true],
        'rtgs'    => ['label' => 'RTGS',    'needs_reference' => true],
        'imps'    => ['label' => 'IMPS',    'needs_reference' => true],
        'cheque'  => ['label' => 'Cheque',  'needs_reference' => true],
        'card'    => ['label' => 'Card',    'needs_reference' => true],
        'other'   => ['label' => 'Other',   'needs_reference' => false],
    ],

];
