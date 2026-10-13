<?php

namespace App\Reports;

use App\Models\Payment;

/** Money received from customers, receipt by receipt, with TDS deducted. */
class ReceiptRegister extends Report
{
    public function title(): string
    {
        return 'Receipts Register';
    }

    public function columns(): array
    {
        return [
            ['key' => 'number', 'label' => 'Receipt No.'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer', 'width' => 28],
            ['key' => 'invoice', 'label' => 'Invoice'],
            ['key' => 'method', 'label' => 'Mode'],
            ['key' => 'reference', 'label' => 'Reference', 'width' => 18],
            ['key' => 'amount', 'label' => 'Amount Received', 'type' => 'money', 'total' => true],
            ['key' => 'tds', 'label' => 'TDS Deducted', 'type' => 'money', 'total' => true],
            ['key' => 'tds_section', 'label' => 'TDS Section'],
        ];
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();
        $s = $this->request->query('search');
        $methods = config('payment_methods.methods', []);

        $query = Payment::where('company_id', $this->company->id)
            ->with('invoice.customer')
            ->when($this->request->query('method'), fn ($q, $m) => $q->where('method', $m))
            ->when($s, fn ($q) => $q->where(function ($w) use ($s) {
                $w->whereLiteralLike('receipt_number', $s)
                    ->orWhereLiteralLike('reference_number', $s)
                    ->orWhereHas('invoice', fn ($i) => $i->whereLiteralLike('invoice_number', $s)
                        ->orWhereHas('customer', fn ($c) => $c->whereLiteralLike('name', $s)));
            }))
            ->when($from, fn ($q, $d) => $q->whereDate('received_at', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('received_at', '<=', $d))
            ->orderBy('received_at')
            ->orderBy('id');

        foreach ($query->lazy() as $p) {
            yield [
                '_url' => route('payments.receipt', $p),
                'number' => $p->receipt_number,
                'date' => $p->received_at,
                'customer' => $p->invoice?->customer?->name,
                'invoice' => $p->invoice?->invoice_number,
                'method' => $methods[$p->method]['label'] ?? ucfirst((string) $p->method),
                'reference' => $p->reference_number,
                'amount' => (float) $p->amount,
                'tds' => (float) ($p->tds_amount ?? 0),
                'tds_section' => $p->tds_section,
            ];
        }
    }
}
