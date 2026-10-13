<?php

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Purchase register: supplier invoices with input-tax split and payables.
 * The period is the month a bill is booked in, as GSTR-3B and the P&L count
 * it: its own date, or the later day it was entered when its month was
 * locked. The invoice date is still the date shown.
 */
class PurchaseRegister extends Report
{
    public function title(): string
    {
        return 'Purchase Register';
    }

    private ?bool $lateBooked = null;

    public function columns(): array
    {
        // "Booked on" only for a business that has booked a bill after its
        // own month was locked; the register is unchanged for everyone else.
        $this->lateBooked ??= $this->company->purchaseBills()->whereNotNull('booked_on')->exists();

        return array_values(array_filter([
            ['key' => 'number', 'label' => 'Vendor Invoice No.', 'width' => 18],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            $this->lateBooked ? ['key' => 'booked', 'label' => 'Booked on', 'type' => 'date'] : null,
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
        ]));
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();
        $status = $this->request->query('status');
        $s = $this->request->query('search');

        $query = $this->company->purchaseBills()
            ->with('vendor')
            ->when($s, fn ($q) => $q->where(function ($w) use ($s) {
                $w->whereLiteralLike('bill_number', $s)
                    ->orWhereHas('vendor', fn ($v) => $v->whereLiteralLike('name', $s));
            }))
            ->when($status === 'unpaid', fn ($q) => $q->where('balance', '>', 0))
            ->when($status === 'paid', fn ($q) => $q->where('balance', '<=', 0))
            ->when($status === 'overdue', fn ($q) => $q->where('balance', '>', 0)->whereDate('due_date', '<', today()))
            ->when($from, fn ($q, $d) => $q->where(DB::raw('COALESCE(booked_on, bill_date)'), '>=', $d->copy()->startOfDay()))
            ->when($to, fn ($q, $d) => $q->where(DB::raw('COALESCE(booked_on, bill_date)'), '<=', $d->copy()->endOfDay()))
            ->orderBy(DB::raw('COALESCE(booked_on, bill_date)'))
            ->orderBy('id');

        foreach ($query->lazy() as $bill) {
            yield [
                '_url' => route('purchases.show', $bill),
                'number' => $bill->bill_number,
                'date' => $bill->bill_date,
                'booked' => $bill->booked_on && ! $bill->booked_on->isSameDay($bill->bill_date) ? $bill->booked_on : null,
                'vendor' => $bill->vendor?->name,
                'gstin' => $bill->vendor?->gstin,
                'taxable' => (float) $bill->subtotal,
                'cgst' => (float) $bill->total_cgst,
                'sgst' => (float) $bill->total_sgst,
                'igst' => (float) $bill->total_igst,
                // Credit past the s.16(4) time limit is not claimable.
                'itc' => $bill->itc_eligible && ! $bill->isItcTimeBarred() ? (float) $bill->total_tax : 0,
                'rcm' => $bill->reverse_charge ? 'Yes' : 'No',
                'total' => (float) $bill->grand_total,
                'paid' => (float) $bill->paid_amount,
                'balance' => (float) $bill->balance,
                'due' => $bill->due_date,
            ];
        }
    }
}
