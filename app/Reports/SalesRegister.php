<?php

namespace App\Reports;

/**
 * Sales register: every invoice on the Invoices screen, with the GST split a
 * CA needs per row. Same filters as the list (status, search, from/to).
 * Drafts carry no number and no tax liability, so they are left out unless
 * the list itself is filtered to drafts.
 */
class SalesRegister extends Report
{
    public function title(): string
    {
        return 'Sales Register';
    }

    public function columns(): array
    {
        return [
            ['key' => 'number', 'label' => 'Invoice No.'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer', 'width' => 28],
            ['key' => 'gstin', 'label' => 'GSTIN', 'width' => 18],
            ['key' => 'pos', 'label' => 'Place of Supply', 'width' => 18],
            ['key' => 'taxable', 'label' => 'Taxable', 'type' => 'money', 'total' => true],
            ['key' => 'cgst', 'label' => 'CGST', 'type' => 'money', 'total' => true],
            ['key' => 'sgst', 'label' => 'SGST/UTGST', 'type' => 'money', 'total' => true],
            ['key' => 'igst', 'label' => 'IGST', 'type' => 'money', 'total' => true],
            ['key' => 'total', 'label' => 'Invoice Value', 'type' => 'money', 'total' => true],
            ['key' => 'received', 'label' => 'Received', 'type' => 'money', 'total' => true],
            ['key' => 'credited', 'label' => 'Credit Notes', 'type' => 'money', 'total' => true],
            ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'total' => true],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();
        $status = $this->request->query('status');

        $query = $this->company->invoices()
            ->with(['customer', 'placeOfSupply', 'company'])
            ->when($status, function ($q, $s) {
                if ($s === 'outstanding') {
                    return $q->whereIn('status', ['final', 'partially_paid'])->where('balance', '>', 0);
                }
                return $q->where('status', $s);
            }, fn ($q) => $q->where('status', '!=', 'draft'))
            ->when($this->request->query('search'), fn ($q, $s) => $q->search($s))
            ->when($from, fn ($q, $d) => $q->where('invoice_date', '>=', $d->toDateString()))
            ->when($to, fn ($q, $d) => $q->where('invoice_date', '<=', $d->toDateString()))
            ->orderBy('invoice_date')
            ->orderBy('id');

        foreach ($query->lazy() as $inv) {
            $cust = $inv->billedCustomer();
            $cancelled = $inv->status === 'cancelled';
            yield [
                '_url' => route('invoices.show', $inv),
                'number' => $inv->invoice_number ?: 'Draft #' . $inv->id,
                'date' => $inv->invoice_date,
                'customer' => $cust?->name,
                'gstin' => $cust?->gstin,
                'pos' => $inv->placeOfSupply ? $inv->placeOfSupply->name . ' (' . $inv->placeOfSupply->gst_code . ')' : null,
                // Cancelled invoices stay on the register (no gaps in the series)
                // but carry no value.
                'taxable' => $cancelled ? 0 : (float) $inv->subtotal,
                'cgst' => $cancelled ? 0 : (float) $inv->total_cgst,
                'sgst' => $cancelled ? 0 : (float) $inv->total_sgst,
                'igst' => $cancelled ? 0 : (float) $inv->total_igst,
                'total' => $cancelled ? 0 : (float) $inv->grand_total,
                'received' => (float) $inv->paid_amount,
                'credited' => (float) $inv->credited_amount,
                'balance' => $cancelled ? 0 : (float) $inv->balance,
                'due' => $inv->due_date,
                'status' => ucwords(str_replace('_', ' ', $inv->status)),
            ];
        }
    }

    protected function filterLabels(): array
    {
        $labels = parent::filterLabels();
        if ($s = $this->request->query('status')) {
            $labels[] = 'Status: ' . ucwords(str_replace('_', ' ', $s));
        }

        return $labels;
    }
}
