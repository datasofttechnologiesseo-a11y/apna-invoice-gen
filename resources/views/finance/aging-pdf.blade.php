<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receivables Aging — {{ $today->format('d M Y') }}</title>
    <style>
        @page { margin: 10mm 8mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111; margin: 0; }

        .doc-title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 2px; padding-bottom: 4px; border-bottom: 2px solid #111; margin-bottom: 8px; }
        .doc-sub { text-align: center; font-size: 9px; color: #555; margin-bottom: 10px; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta-table td { vertical-align: top; padding: 0; font-size: 9.5px; }
        .label { font-size: 8px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .value-strong { font-weight: bold; font-size: 11px; color: #111; }

        .summary-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .summary-grid td { width: 20%; padding: 6px 8px; vertical-align: top; border: 1px solid #ddd; }
        .summary-grid td.h { font-size: 7.5px; color: #666; text-transform: uppercase; letter-spacing: 0.8px; font-weight: bold; padding-bottom: 1px; }
        .summary-grid td.v { font-size: 13px; font-weight: bold; color: #111; padding-top: 0; }
        .summary-grid td.v small { font-size: 8px; color: #555; font-weight: normal; }
        .summary-grid td.bucket-total { background: #fff7ed; }
        .summary-grid td.bucket-current { background: #ecfdf5; }
        .summary-grid td.bucket-30 { background: #fffbeb; }
        .summary-grid td.bucket-60 { background: #fff7ed; }
        .summary-grid td.bucket-90 { background: #fef2f2; }

        table.aging { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.aging thead th { background: #111; color: #fff; padding: 5px 4px; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; }
        table.aging thead th.r { text-align: right; }
        table.aging tbody td { padding: 4px; border-bottom: 1px solid #e0e0e0; font-size: 9px; vertical-align: top; }
        table.aging tbody td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        table.aging tbody tr:nth-child(even) td { background: #fafafa; }
        table.aging tfoot td { padding: 5px 4px; border-top: 2px solid #111; font-weight: bold; font-size: 9.5px; }
        table.aging tfoot td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }

        .footer { margin-top: 12px; padding-top: 4px; border-top: 1px solid #ddd; text-align: center; font-size: 7.5px; color: #888; }
        .empty { text-align: center; padding: 30px 20px; background: #fafafa; border: 1px dashed #ccc; font-size: 11px; color: #888; }
    </style>
</head>
<body>

    <div class="doc-title">RECEIVABLES AGING REPORT</div>
    <div class="doc-sub">As on {{ $today->format('d M Y') }} · Bucketed by days past due (or invoice date if no due date set)</div>

    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <div class="label">Company</div>
                <div class="value-strong">{{ $company->name }}</div>
                @if ($company->gstin)
                    <div style="font-size: 9px; margin-top: 2px;">GSTIN: <strong>{{ $company->gstin }}</strong></div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="label">Generated</div>
                <div style="font-size: 9px; margin-top: 2px;">{{ now()->format('d M Y · H:i') }}</div>
                <div style="font-size: 9px;">{{ $summary['invoices'] }} open {{ \Illuminate\Support\Str::plural('invoice', $summary['invoices']) }} across {{ $summary['customers'] }} {{ \Illuminate\Support\Str::plural('customer', $summary['customers']) }}</div>
            </td>
        </tr>
    </table>

    @if ($summary['invoices'] === 0)
        <div class="empty">No outstanding receivables. Every finalized invoice is fully paid.</div>
    @else
        {{-- Bucket summary --}}
        <table class="summary-grid">
            <tr>
                <td class="h bucket-total">Total Outstanding</td>
                <td class="h bucket-current">Current (≤ 30 days)</td>
                <td class="h bucket-30">31 – 60 days</td>
                <td class="h bucket-60">61 – 90 days</td>
                <td class="h bucket-90">91+ days</td>
            </tr>
            <tr>
                <td class="v bucket-total">Rs. {{ number_format($summary['total'], 2) }}<br><small>{{ $summary['invoices'] }} {{ \Illuminate\Support\Str::plural('invoice', $summary['invoices']) }}</small></td>
                <td class="v bucket-current">Rs. {{ number_format($summary['current'], 2) }}<br><small>{{ $summary['total'] > 0 ? round(($summary['current'] / $summary['total']) * 100) : 0 }}% of total</small></td>
                <td class="v bucket-30">Rs. {{ number_format($summary['b30_60'], 2) }}<br><small>{{ $summary['total'] > 0 ? round(($summary['b30_60'] / $summary['total']) * 100) : 0 }}% of total</small></td>
                <td class="v bucket-60">Rs. {{ number_format($summary['b60_90'], 2) }}<br><small>{{ $summary['total'] > 0 ? round(($summary['b60_90'] / $summary['total']) * 100) : 0 }}% of total</small></td>
                <td class="v bucket-90">Rs. {{ number_format($summary['b90_plus'], 2) }}<br><small>{{ $summary['total'] > 0 ? round(($summary['b90_plus'] / $summary['total']) * 100) : 0 }}% — chase</small></td>
            </tr>
        </table>

        {{-- Customer-level table --}}
        <table class="aging">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 22%;">Customer</th>
                    <th style="width: 14%;">GSTIN</th>
                    <th class="r" style="width: 6%;">Inv.</th>
                    <th class="r" style="width: 6%;">Oldest</th>
                    <th class="r" style="width: 11%;">≤ 30 days</th>
                    <th class="r" style="width: 11%;">31 – 60</th>
                    <th class="r" style="width: 11%;">61 – 90</th>
                    <th class="r" style="width: 11%;">91+</th>
                    <th class="r" style="width: 13%;">Total Rs.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byCustomer as $idx => $c)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td><strong>{{ $c['name'] }}</strong></td>
                        <td style="font-family: DejaVu Sans Mono, monospace;">{{ $c['gstin'] ?? '—' }}</td>
                        <td class="r">{{ $c['invoice_count'] }}</td>
                        <td class="r">{{ $c['oldest_days'] }}d</td>
                        <td class="r">{{ $c['current'] > 0 ? number_format($c['current'], 2) : '—' }}</td>
                        <td class="r">{{ $c['b30_60'] > 0 ? number_format($c['b30_60'], 2) : '—' }}</td>
                        <td class="r">{{ $c['b60_90'] > 0 ? number_format($c['b60_90'], 2) : '—' }}</td>
                        <td class="r" style="color: #b91c1c;">{{ $c['b90_plus'] > 0 ? number_format($c['b90_plus'], 2) : '—' }}</td>
                        <td class="r" style="font-weight: bold;">{{ number_format($c['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="r">TOTAL</td>
                    <td class="r">{{ number_format($summary['current'], 2) }}</td>
                    <td class="r">{{ number_format($summary['b30_60'], 2) }}</td>
                    <td class="r">{{ number_format($summary['b60_90'], 2) }}</td>
                    <td class="r">{{ number_format($summary['b90_plus'], 2) }}</td>
                    <td class="r">{{ number_format($summary['total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="footer">
        Generated by <strong>Apna Invoice</strong> for {{ $company->name }}. Bucket cutoff is the invoice's <strong>due date</strong> when set, otherwise the invoice date. <strong>E. &amp; O.E.</strong>
    </div>

</body>
</html>
