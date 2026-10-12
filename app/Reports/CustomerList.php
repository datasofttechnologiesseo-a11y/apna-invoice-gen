<?php

namespace App\Reports;

/** Customer master with what each one owes today. */
class CustomerList extends Report
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
        return 'Customers';
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
            ['key' => 'pin', 'label' => 'PIN'],
            ['key' => 'invoices', 'label' => 'Invoices', 'type' => 'number'],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money', 'total' => true],
        ];
    }

    public function rows(): iterable
    {
        $query = $this->company->customers()
            ->with('state')
            ->withCount(['invoices as issued_count' => fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled'])])
            ->withSum(['invoices as outstanding' => fn ($q) => $q->whereIn('status', ['final', 'partially_paid'])->where('balance', '>', 0)], 'balance')
            ->when($this->request->query('search'), fn ($q, $s) => $q->search($s))
            ->orderBy('name')
            ->orderBy('id'); // unique tie-break: lazy() pages by offset

        foreach ($query->lazy() as $c) {
            yield [
                '_url' => route('customers.ledger', $c),
                'name' => $c->name,
                'gstin' => $c->gstin,
                'state' => $c->state ? $c->state->name . ' (' . $c->state->gst_code . ')' : null,
                'phone' => $c->phone,
                'email' => $c->email,
                'address' => collect([$c->address_line1, $c->address_line2])->filter()->implode(', '),
                'city' => $c->city,
                'pin' => $c->postal_code,
                'invoices' => (int) $c->issued_count,
                'outstanding' => (float) ($c->outstanding ?? 0),
            ];
        }
    }
}
