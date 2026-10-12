<?php

namespace App\Reports;

use App\Models\CreditNote;

/** Credit notes issued (s.34), with the original invoice each one corrects. */
class CreditNoteRegister extends Report
{
    public function title(): string
    {
        return 'Credit Notes';
    }

    public function columns(): array
    {
        return [
            ['key' => 'number', 'label' => 'Credit Note No.'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'invoice', 'label' => 'Against Invoice'],
            ['key' => 'invoice_date', 'label' => 'Invoice Date', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer', 'width' => 28],
            ['key' => 'gstin', 'label' => 'GSTIN', 'width' => 18],
            ['key' => 'reason', 'label' => 'Reason', 'width' => 22],
            ['key' => 'taxable', 'label' => 'Taxable', 'type' => 'money', 'total' => true],
            ['key' => 'cgst', 'label' => 'CGST', 'type' => 'money', 'total' => true],
            ['key' => 'sgst', 'label' => 'SGST/UTGST', 'type' => 'money', 'total' => true],
            ['key' => 'igst', 'label' => 'IGST', 'type' => 'money', 'total' => true],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'total' => true],
        ];
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();
        $s = $this->request->query('search');
        $reasons = config('credit_note_reasons', []);

        $query = CreditNote::where('company_id', $this->company->id)
            ->with(['invoice.customer'])
            ->when($s, fn ($q) => $q->where(function ($w) use ($s) {
                $w->where('credit_note_number', 'like', "%{$s}%")
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$s}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
            }))
            ->when($from, fn ($q, $d) => $q->whereDate('credit_note_date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('credit_note_date', '<=', $d))
            ->orderBy('credit_note_date')
            ->orderBy('id');

        foreach ($query->lazy() as $cn) {
            $cust = $cn->invoice?->billedCustomer();
            $reason = $reasons[$cn->reason] ?? null;
            yield [
                '_url' => route('credit-notes.pdf', $cn),
                'number' => $cn->credit_note_number,
                'date' => $cn->credit_note_date,
                'invoice' => $cn->invoice?->invoice_number,
                'invoice_date' => $cn->invoice?->invoice_date,
                'customer' => $cust?->name,
                'gstin' => $cust?->gstin,
                'reason' => is_array($reason) ? ($reason['label'] ?? $cn->reason) : ($reason ?? ucwords(str_replace('_', ' ', (string) $cn->reason))),
                'taxable' => (float) $cn->taxable_value,
                'cgst' => (float) $cn->total_cgst,
                'sgst' => (float) $cn->total_sgst,
                'igst' => (float) $cn->total_igst,
                'amount' => (float) $cn->amount,
            ];
        }
    }
}
