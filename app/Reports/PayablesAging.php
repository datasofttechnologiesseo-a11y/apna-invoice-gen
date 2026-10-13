<?php

namespace App\Reports;

use App\Services\Aging;

/** What you owe vendors, in 0-30 / 31-60 / 61-90 / 90+ day buckets. */
class PayablesAging extends Report
{
    public function usesDates(): bool
    {
        return false;
    }

    public function group(): string
    {
        return 'Outstanding';
    }

    public function title(): string
    {
        return 'Payables Aging';
    }

    public function subtitle(): string
    {
        return 'As on ' . now()->format('d M Y') . ' · days past due date';
    }

    public function columns(): array
    {
        return Aging::columns('Vendor');
    }

    public function rows(): iterable
    {
        $bills = $this->company->purchaseBills()
            ->where('balance', '>', 0)
            ->with('vendor')
            ->get()
            ->map(fn ($bill) => [
                'party_id' => $bill->vendor_id,
                'party' => $bill->vendor?->name ?? '-',
                'gstin' => $bill->vendor?->gstin,
                'due' => $bill->due_date ?? $bill->bill_date,
                'balance' => (float) $bill->balance,
            ]);

        return Aging::byParty($bills);
    }
}
