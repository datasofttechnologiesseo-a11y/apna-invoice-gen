<?php

namespace App\Reports;

/** Purchase register: supplier invoices with input-tax split and payables. */
class PurchaseRegister extends Report
{
    public function title(): string
    {
        return 'Purchase Register';
    }

    public function columns(): array
    {
        return [
            ['key' => 'number', 'label' => 'Vendor Invoice No.', 'width' => 18],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'vendor', 'label' => 'Vendor', 'width' => 28],
            ['key' => 'gstin', 'label' => 'Vendor GSTIN', 'width' => 18],
            ['key' => 'taxable', 'label' => 'Taxable', 'type' => 'money', 'total' => true],
            ['key' => 'cgst', 'label' => 'CGST', 'type' => 'money', 'total' => true],
            ['key' => 'sgst', 'label' => 'SGST/UTGST', 'type' => 'money', 'total' => true],
            ['key' => 'igst', 'label' => 'IGST', 'type' => 'money', 'total' => true],
            ['key' => 'itc', 'label' => 'ITC Claimable', 'type' => 'money', 'total' => true],
            ['key' => 'rcm', 'label' => 'Reverse Charge'],
            ['key' => 'total', 'label' => 'Payable', 'type' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'money', 'total' => true],
            ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'total' => true],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
        ];
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();
        $status = $this->request->query('status');
        $s = $this->request->query('search');

        $query = $this->company->purchaseBills()
            ->with('vendor')
            ->when($s, fn ($q) => $q->where(function ($w) use ($s) {
                $w->where('bill_number', 'like', "%{$s}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$s}%"));
            }))
            ->when($status === 'unpaid', fn ($q) => $q->where('balance', '>', 0))
            ->when($status === 'paid', fn ($q) => $q->where('balance', '<=', 0))
            ->when($status === 'overdue', fn ($q) => $q->where('balance', '>', 0)->whereDate('due_date', '<', today()))
            ->when($from, fn ($q, $d) => $q->whereDate('bill_date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('bill_date', '<=', $d))
            ->orderBy('bill_date')
            ->orderBy('id');

        foreach ($query->lazy() as $bill) {
            yield [
                '_url' => route('purchases.show', $bill),
                'number' => $bill->bill_number,
                'date' => $bill->bill_date,
                'vendor' => $bill->vendor?->name,
                'gstin' => $bill->vendor?->gstin,
                'taxable' => (float) $bill->subtotal,
                'cgst' => (float) $bill->total_cgst,
                'sgst' => (float) $bill->total_sgst,
                'igst' => (float) $bill->total_igst,
                'itc' => $bill->itc_eligible ? (float) $bill->total_tax : 0,
                'rcm' => $bill->reverse_charge ? 'Yes' : 'No',
                'total' => (float) $bill->grand_total,
                'paid' => (float) $bill->paid_amount,
                'balance' => (float) $bill->balance,
                'due' => $bill->due_date,
            ];
        }
    }
}
