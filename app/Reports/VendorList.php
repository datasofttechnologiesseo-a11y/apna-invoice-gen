<?php

namespace App\Reports;

/** Vendor master with what you owe each one today. */
class VendorList extends Report
{
    public function usesDates(): bool
    {
        return false;
    }

    public function group(): string
    {
        return 'Masters';
    }

    public function title(): string
    {
        return 'Vendors';
    }

    public function landscape(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'width' => 28],
            ['key' => 'gstin', 'label' => 'GSTIN', 'width' => 18],
            ['key' => 'state', 'label' => 'State', 'width' => 18],
            ['key' => 'phone', 'label' => 'Phone', 'width' => 16],
            ['key' => 'email', 'label' => 'Email', 'width' => 26],
            ['key' => 'address', 'label' => 'Address', 'width' => 36],
            ['key' => 'city', 'label' => 'City'],
            ['key' => 'bills', 'label' => 'Invoices', 'type' => 'number'],
            ['key' => 'payable', 'label' => 'Payable', 'type' => 'money', 'total' => true],
        ];
    }

    public function rows(): iterable
    {
        $query = $this->company->vendors()
            ->with('state')
            ->withCount('purchaseBills')
            ->withSum(['purchaseBills as payable' => fn ($q) => $q->where('balance', '>', 0)], 'balance')
            ->when($this->request->query('search'), fn ($q, $s) => $q->search($s))
            ->orderBy('name')
            ->orderBy('id'); // unique tie-break: lazy() pages by offset

        foreach ($query->lazy() as $v) {
            yield [
                '_url' => route('purchases.index', ['search' => $v->name]),
                'name' => $v->name,
                'gstin' => $v->gstin,
                'state' => $v->state ? $v->state->name . ' (' . $v->state->gst_code . ')' : null,
                'phone' => $v->phone,
                'email' => $v->email,
                'address' => collect([$v->address_line1, $v->address_line2])->filter()->implode(', '),
                'city' => $v->city,
                'bills' => (int) $v->purchase_bills_count,
                'payable' => (float) ($v->payable ?? 0),
            ];
        }
    }
}
