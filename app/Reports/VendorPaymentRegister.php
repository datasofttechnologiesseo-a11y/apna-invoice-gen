<?php

namespace App\Reports;

use App\Models\PurchasePayment;

/** Money paid to vendors against purchase invoices. */
class VendorPaymentRegister extends Report
{
    public function title(): string
    {
        return 'Vendor Payments';
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Paid On', 'type' => 'date'],
            ['key' => 'vendor', 'label' => 'Vendor', 'width' => 28],
            ['key' => 'invoice', 'label' => 'Vendor Invoice No.', 'width' => 18],
            ['key' => 'method', 'label' => 'Mode'],
            ['key' => 'reference', 'label' => 'Reference / UTR', 'width' => 18],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'total' => true],
        ];
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();
        $methods = config('payment_methods.methods', []);

        $query = PurchasePayment::where('company_id', $this->company->id)
            ->with('purchaseBill.vendor')
            ->when($from, fn ($q, $d) => $q->whereDate('paid_on', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('paid_on', '<=', $d))
            ->orderBy('paid_on')
            ->orderBy('id');

        foreach ($query->lazy() as $p) {
            yield [
                '_url' => $p->purchaseBill ? route('purchases.show', $p->purchaseBill) : null,
                'date' => $p->paid_on,
                'vendor' => $p->purchaseBill?->vendor?->name,
                'invoice' => $p->purchaseBill?->bill_number,
                'method' => $methods[$p->method]['label'] ?? ucfirst((string) $p->method),
                'reference' => $p->reference_number,
                'amount' => (float) $p->amount,
            ];
        }
    }
}
