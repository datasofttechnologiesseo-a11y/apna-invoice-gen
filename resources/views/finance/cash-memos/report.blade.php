<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cash Memo Statement — {{ $label }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 0; }

        .doc-title { text-align: center; font-size: 18px; font-weight: bold; letter-spacing: 2px; padding-bottom: 4px; border-bottom: 2px solid #111; margin-bottom: 10px; }
        .doc-sub   { text-align: center; font-size: 10px; color: #555; margin-top: 0; margin-bottom: 12px; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { vertical-align: top; padding: 0; font-size: 10px; }
        .label { font-size: 8.5px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .value-strong { font-weight: bold; font-size: 12px; color: #111; }

        .summary-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary-grid td { width: 25%; padding: 8px 10px; vertical-align: top; border: 1px solid #ddd; background: #fafafa; }
        .summary-grid td.h { font-size: 8.5px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; padding-bottom: 2px; }
        .summary-grid td.v { font-size: 14px; font-weight: bold; color: #111; padding-top: 0; }
        .summary-grid td.v small { font-size: 9px; color: #555; font-weight: normal; }

        .totals-banner { background: #111; color: #fff; padding: 10px 12px; margin-bottom: 14px; }
        .totals-banner table { width: 100%; }
        .totals-banner td { color: #fff; padding: 0; font-size: 10px; vertical-align: middle; }
        .totals-banner .lbl { font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; color: #ccc; }
        .totals-banner .amt { font-size: 18px; font-weight: bold; text-align: right; }

        table.memos { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.memos thead th {
            background: #111; color: #fff; padding: 6px 5px; text-align: left;
            font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        table.memos thead th.r { text-align: right; }
        table.memos tbody td { padding: 5px; border-bottom: 1px solid #e5e5e5; font-size: 9.5px; vertical-align: top; }
        table.memos tbody td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        table.memos tbody td.mono { font-family: DejaVu Sans Mono, monospace; }
        table.memos tbody tr:nth-child(even) td { background: #fafafa; }
        table.memos tfoot td { padding: 6px 5px; border-top: 2px solid #111; font-weight: bold; font-size: 10px; }
        table.memos tfoot td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }

        table.bymode { width: 60%; margin: 0 auto 12px auto; border-collapse: collapse; }
        table.bymode td { padding: 4px 8px; font-size: 10px; border-bottom: 1px solid #eee; }
        table.bymode td.lbl { color: #555; text-transform: uppercase; font-size: 8.5px; letter-spacing: 0.5px; width: 40%; }
        table.bymode td.cnt { text-align: center; width: 25%; }
        table.bymode td.amt { text-align: right; font-family: DejaVu Sans Mono, monospace; font-weight: bold; }

        .words-box { padding: 8px 10px; border-left: 3px solid #111; background: #f7f7f7; margin: 10px 0; font-size: 10px; }
        .words-box .lbl { font-size: 8px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 3px; }
        .words-box .words { font-style: italic; color: #111; }

        .footer { margin-top: 18px; padding-top: 6px; border-top: 1px solid #ddd; text-align: center; font-size: 8.5px; color: #888; }
        .footer strong { color: #444; }

        .empty { text-align: center; padding: 40px 20px; background: #fafafa; border: 1px dashed #ccc; font-size: 11px; color: #888; }
    </style>
</head>
<body>

    <div class="doc-title">CASH MEMO STATEMENT</div>
    <div class="doc-sub">Period: {{ $label }} ({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }})</div>

    {{-- Company / context --}}
    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <div class="label">Issued by</div>
                <div class="value-strong">{{ $company->name }}</div>
                @if ($company->address_line1)
                    <div>{{ $company->address_line1 }}@if ($company->address_line2), {{ $company->address_line2 }}@endif</div>
                @endif
                <div>
                    {{ $company->city }}{{ $company->state ? ', ' . $company->state->name : '' }}{{ $company->postal_code ? ' - ' . $company->postal_code : '' }}
                </div>
                @if ($company->gstin)
                    <div style="margin-top: 3px;">GSTIN: <strong>{{ $company->gstin }}</strong></div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="label">For your CA</div>
                <div style="font-size: 10px; margin-top: 2px;">Generated {{ now()->format('d M Y · H:i') }}</div>
                <div style="font-size: 10px;">{{ $summary['count'] }} memo{{ $summary['count'] === 1 ? '' : 's' }} in this period</div>
            </td>
        </tr>
    </table>

    @if ($summary['count'] === 0)
        <div class="empty">
            No cash memos found in this period.<br>
            Adjust the date filter on the Cash Memos page and re-export.
        </div>
    @else
        {{-- Headline totals banner --}}
        <div class="totals-banner">
            <table>
                <tr>
                    <td><div class="lbl">Total purchases (this period)</div></td>
                    <td class="amt">Rs. {{ number_format($summary['grand_total'], 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Per-metric summary cards --}}
        <table class="summary-grid">
            <tr>
                <td class="h">Memos</td>
                <td class="h">Taxable value</td>
                <td class="h">Total GST (input)</td>
                <td class="h">Grand total</td>
            </tr>
            <tr>
                <td class="v">{{ $summary['count'] }}</td>
                <td class="v">Rs. {{ number_format($summary['taxable'], 2) }}<br><small>net of GST</small></td>
                <td class="v">Rs. {{ number_format($summary['total_tax'], 2) }}<br><small>CGST {{ number_format($summary['cgst'], 0) }} · SGST {{ number_format($summary['sgst'], 0) }} · IGST {{ number_format($summary['igst'], 0) }}</small></td>
                <td class="v">Rs. {{ number_format($summary['grand_total'], 2) }}<br><small>incl. GST</small></td>
            </tr>
        </table>

        {{-- Amount in words --}}
        <div class="words-box">
            <div class="lbl">Total in words</div>
            <div class="words">{{ $summaryWords }}</div>
        </div>

        {{-- Per-mode breakdown (only if more than one mode used) --}}
        @if ($byMode->count() > 1)
            <div class="label" style="text-align: center; margin-top: 4px;">Breakdown by payment mode</div>
            <table class="bymode">
                @foreach ($byMode as $mode => $b)
                    <tr>
                        <td class="lbl">{{ strtoupper($mode) }}</td>
                        <td class="cnt">{{ $b['count'] }} memo{{ $b['count'] === 1 ? '' : 's' }}</td>
                        <td class="amt">Rs. {{ number_format($b['total'], 2) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        {{-- Detailed list --}}
        <table class="memos">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 9%;">Date</th>
                    <th style="width: 11%;">Memo No.</th>
                    <th>Seller</th>
                    <th style="width: 8%;">Mode</th>
                    <th class="r" style="width: 10%;">Taxable Rs.</th>
                    <th class="r" style="width: 10%;">GST Rs.</th>
                    <th class="r" style="width: 12%;">Total Rs.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $idx => $m)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $m->memo_date->format('d-M-Y') }}</td>
                        <td class="mono">{{ $m->memo_number }}</td>
                        <td>
                            <div style="font-weight: bold;">{{ $m->seller_name }}</div>
                            @if ($m->seller_gstin)
                                <div style="font-size: 8.5px; color: #666;">GSTIN {{ $m->seller_gstin }}</div>
                            @endif
                        </td>
                        <td style="text-transform: uppercase; font-size: 9px;">{{ $m->payment_mode }}</td>
                        <td class="r">{{ number_format((float) $m->taxable_value, 2) }}</td>
                        <td class="r">{{ number_format((float) $m->total_cgst + (float) $m->total_sgst + (float) $m->total_igst, 2) }}</td>
                        <td class="r" style="font-weight: bold;">{{ number_format((float) $m->grand_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="r">TOTAL</td>
                    <td class="r">{{ number_format($summary['taxable'], 2) }}</td>
                    <td class="r">{{ number_format($summary['total_tax'], 2) }}</td>
                    <td class="r">{{ number_format($summary['grand_total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="footer">
        Generated by <strong>Apna Invoice</strong> · This is a computer-generated statement compiled from {{ $summary['count'] }} cash memo{{ $summary['count'] === 1 ? '' : 's' }} in your books. <strong>E. &amp; O.E.</strong>
    </div>

</body>
</html>
