<?php

/*
| Every exportable list, by the key used in /reports/{key}/{format}. Each
| class is an App\Reports\Report; ReportExporter renders it as Excel, CSV,
| PDF or a print page.
*/

return [
    'registry' => [
        'sales-register' => App\Reports\SalesRegister::class,
        'purchase-register' => App\Reports\PurchaseRegister::class,
        'quotations' => App\Reports\QuotationRegister::class,
        'credit-notes' => App\Reports\CreditNoteRegister::class,
        'receipts' => App\Reports\ReceiptRegister::class,
        'vendor-payments' => App\Reports\VendorPaymentRegister::class,
        'customers' => App\Reports\CustomerList::class,
        'products' => App\Reports\ProductList::class,
        'vendors' => App\Reports\VendorList::class,
        'receivables-aging' => App\Reports\ReceivablesAging::class,
        'payables-aging' => App\Reports\PayablesAging::class,
        'hsn-summary' => App\Reports\HsnSummary::class,
        'stock-summary' => App\Reports\StockSummary::class,
    ],
];
