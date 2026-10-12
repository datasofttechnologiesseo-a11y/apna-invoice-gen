<?php

namespace App\Reports;

use App\Services\Aging;

/** Who owes you, by customer, in 0-30 / 31-60 / 61-90 / 90+ day buckets. */
class ReceivablesAging extends Report
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
        return 'Receivables Aging';
    }

    public function subtitle(): string
    {
        return 'As on ' . now()->format('d M Y') . ' · days past due date';
    }

    public function columns(): array
    {
        return Aging::columns('Customer');
    }

    public function rows(): iterable
    {
        $invoices = $this->company->invoices()
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->with('customer')
            ->get()
            ->map(fn ($inv) => [
                'party_id' => $inv->customer_id,
                'party' => $inv->customer?->name ?? '-',
                'gstin' => $inv->customer?->gstin,
                'due' => $inv->due_date ?? $inv->invoice_date,
                'balance' => (float) $inv->balance,
            ]);

        return Aging::byParty($invoices);
    }
}
