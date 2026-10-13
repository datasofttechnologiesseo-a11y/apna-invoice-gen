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

    /**
     * The open invoices by age, then what you owe the customer back (a credit
     * note larger than what was left to pay) and the net, which is the
     * customer's ledger closing. Leaving the refund out made the report
     * disagree with the ledger by exactly that amount.
     */
    public function columns(): array
    {
        return array_merge(Aging::columns('Customer'), [
            ['key' => 'refund_due', 'label' => 'Refund due', 'type' => 'money', 'total' => true],
            ['key' => 'net', 'label' => 'Net (ledger)', 'type' => 'money', 'total' => true],
        ]);
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
        $rows = collect(Aging::byParty($invoices))->keyBy('party');

        // Refunds owed: issued invoices whose credit notes exceed what was
        // left to pay (a negative balance).
        $refunds = $this->company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->where('balance', '<', 0)
            ->with('customer')
            ->get()
            ->groupBy('customer_id');
        foreach ($refunds as $group) {
            $customer = $group->first()->customer;
            $name = $customer?->name ?? '-';
            $row = $rows->get($name) ?? array_merge(
                ['party' => $name, 'gstin' => $customer?->gstin, 'count' => 0, 'oldest' => 0, 'total' => 0.0],
                array_fill_keys(array_keys(Aging::BUCKETS), 0.0),
            );
            $row['refund_due'] = round((float) $group->sum('balance'), 2);
            $rows->put($name, $row);
        }

        return $rows->map(function (array $row) {
            $row['refund_due'] = $row['refund_due'] ?? 0.0;
            $row['net'] = round($row['total'] + $row['refund_due'], 2);

            return $row;
        })->values()->all();
    }
}
