<?php

namespace App\Reports;

class QuotationRegister extends Report
{
    public function title(): string
    {
        return 'Quotations';
    }

    public function columns(): array
    {
        return [
            ['key' => 'number', 'label' => 'Quote No.'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'valid', 'label' => 'Valid Until', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer', 'width' => 28],
            ['key' => 'subject', 'label' => 'Subject', 'width' => 30],
            ['key' => 'taxable', 'label' => 'Taxable', 'type' => 'money', 'total' => true],
            ['key' => 'tax', 'label' => 'GST', 'type' => 'money', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'total' => true],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();
        $s = $this->request->query('search');

        $query = $this->company->quotations()
            ->with('customer')
            ->when($this->request->query('status'), function ($q, $st) {
                if ($st === 'expired') {
                    return $q->whereIn('status', ['draft', 'sent'])->whereNotNull('valid_until')->where('valid_until', '<', now()->toDateString());
                }
                return $q->where('status', $st);
            })
            ->when($s, fn ($q) => $q->where(function ($w) use ($s) {
                $w->whereLiteralLike('quote_number', $s)
                    ->orWhereHas('customer', fn ($c) => $c->whereLiteralLike('name', $s));
            }))
            ->when($from, fn ($q, $d) => $q->whereDate('quote_date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('quote_date', '<=', $d))
            ->orderBy('quote_date')
            ->orderBy('id');

        foreach ($query->lazy() as $q) {
            yield [
                '_url' => route('quotations.show', $q),
                'number' => $q->quote_number ?: 'Draft #' . $q->id,
                'date' => $q->quote_date,
                'valid' => $q->valid_until,
                'customer' => $q->customer?->name,
                'subject' => $q->subject,
                'taxable' => (float) $q->subtotal,
                'tax' => (float) $q->total_tax,
                'total' => (float) $q->grand_total,
                'status' => ucfirst($q->effectiveStatus()),
            ];
        }
    }
}
