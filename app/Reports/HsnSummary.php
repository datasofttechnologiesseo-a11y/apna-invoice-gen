<?php

namespace App\Reports;

/**
 * HSN/SAC-wise summary of outward supplies (GSTR-1 Table 12): one row per
 * HSN + UQC + rate, split B2B / B2C as the portal has required since May
 * 2025. Net of credit notes, exactly as the GSTR-1 export reports Table 12.
 */
class HsnSummary extends Report
{
    public function group(): string
    {
        return 'GST';
    }

    public function title(): string
    {
        return 'HSN Summary (GSTR-1 Table 12)';
    }

    public function columns(): array
    {
        return [
            ['key' => 'type', 'label' => 'B2B / B2C'],
            ['key' => 'hsn', 'label' => 'HSN/SAC'],
            ['key' => 'description', 'label' => 'Description', 'width' => 30],
            ['key' => 'uqc', 'label' => 'UQC'],
            ['key' => 'qty', 'label' => 'Total Quantity', 'type' => 'qty'],
            ['key' => 'rate', 'label' => 'Rate %', 'type' => 'percent'],
            ['key' => 'value', 'label' => 'Total Value', 'type' => 'money', 'total' => true],
            ['key' => 'taxable', 'label' => 'Taxable Value', 'type' => 'money', 'total' => true],
            ['key' => 'igst', 'label' => 'Integrated Tax', 'type' => 'money', 'total' => true],
            ['key' => 'cgst', 'label' => 'Central Tax', 'type' => 'money', 'total' => true],
            ['key' => 'sgst', 'label' => 'State/UT Tax', 'type' => 'money', 'total' => true],
        ];
    }

    public function rows(): iterable
    {
        [$from, $to] = $this->dateRange();

        // The same Table 12 the GSTR-1 export files: issued invoices, net of
        // credit notes dated in the period, B2B by the GSTIN as issued.
        $hsn = app(\App\Services\Gstr1Builder::class)->build(
            $this->company,
            $from ?? \Illuminate\Support\Carbon::create(2017, 7, 1),
            $to ?? now(),
        )['hsn'];

        return collect($hsn)->map(fn ($r) => [
            'type' => $r['tab'],
            'hsn' => $r['hsn'] ?: '-',
            'description' => $r['description'],
            'uqc' => $r['uqc'] ?: 'OTH',
            'qty' => round((float) $r['qty'], 3),
            'rate' => (float) $r['rate'],
            'value' => round((float) $r['value'], 2),
            'taxable' => round((float) $r['taxable'], 2),
            'igst' => round((float) $r['igst'], 2),
            'cgst' => round((float) $r['cgst'], 2),
            'sgst' => round((float) $r['sgst'], 2),
        ])->values()->all();
    }
}
