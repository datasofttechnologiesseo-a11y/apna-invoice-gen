<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Expense Statement · {{ $company->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 0; }

        .hdr { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .hdr-table { width: 100%; border-collapse: collapse; }
        .hdr-table td { vertical-align: top; padding: 0; }
        .co-name { font-size: 16px; font-weight: bold; }
        .co-meta { font-size: 9px; color: #555; margin-top: 2px; }
        .doc-title { font-size: 14px; font-weight: bold; letter-spacing: 1px; text-align: right; }
        .period-line { font-size: 10px; text-align: right; color: #444; }
        .gen-line { font-size: 8.5px; color: #888; text-align: right; }

        .filters { background: #f5f5f5; border: 1px solid #ddd; padding: 6px 8px; margin-bottom: 8px; font-size: 9px; color: #555; }
        .filters strong { color: #111; }

        .summary-cards { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .summary-cards td { width: 25%; padding: 0 4px; }
        .summary-card { border: 1px solid #ccc; padding: 8px 10px; }
        .summary-card .lbl { font-size: 8px; color: #777; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-card .val { font-size: 14px; font-weight: bold; margin-top: 2px; font-family: DejaVu Sans Mono, monospace; }

        h3 { font-size: 10.5px; text-transform: uppercase; letter-spacing: 1px; margin: 12px 0 4px 0; color: #111; border-bottom: 1px solid #999; padding-bottom: 2px; }

        .cat-table, .data-table { width: 100%; border-collapse: collapse; }
        .cat-table th, .data-table th { background: #111; color: #fff; padding: 4px 5px; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.3px; }
        .cat-table th.r, .data-table th.r { text-align: right; }
        .cat-table td, .data-table td { padding: 3px 5px; border-bottom: 1px solid #e0e0e0; font-size: 8.5px; vertical-align: top; }
        .cat-table td.r, .data-table td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .cat-table tr.tot td, .data-table tr.tot td { border-top: 2px solid #111; border-bottom: 2px solid #111; font-weight: bold; background: #f5f5f5; padding: 6px; }
        .data-table { margin-top: 4px; }
        .data-table .desc { color: #111; }
        .data-table .vendor { color: #555; font-size: 8px; display: block; margin-top: 1px; }
        .data-table .ref { color: #888; font-size: 8px; display: block; margin-top: 1px; }

        .words { margin-top: 8px; padding: 8px 10px; background: #fafafa; border: 1px solid #ddd; }
        .words .lbl { font-size: 8px; color: #777; text-transform: uppercase; letter-spacing: 0.5px; }
        .words .val { font-style: italic; font-size: 10px; color: #111; margin-top: 2px; }

        .sign { margin-top: 28px; }
        .sign-table { width: 100%; border-collapse: collapse; }
        .sign-table td { vertical-align: bottom; padding: 0; font-size: 9px; }
        .sign-block { border-top: 1px solid #777; padding-top: 4px; min-width: 180px; text-align: center; }

        .footer { text-align: center; font-size: 7.5px; color: #999; margin-top: 14px; padding-top: 4px; border-top: 1px solid #eee; }
    </style>
</head>
<body>

    {{-- ─── Header ─── --}}
    <div class="hdr">
        <table class="hdr-table">
            <tr>
                <td style="width: 60%;">
                    <div class="co-name">{{ $company->name }}</div>
                    <div class="co-meta">
                        @if ($company->address_line1){{ $company->address_line1 }}@endif
                        @if ($company->city), {{ $company->city }}@endif
                        @if ($company->state), {{ $company->state->name ?? '' }}@endif
                        @if ($company->postal_code) - {{ $company->postal_code }}@endif
                    </div>
                    @if ($company->gstin)
                        <div class="co-meta"><strong>GSTIN:</strong> {{ $company->gstin }}@if ($company->pan) · <strong>PAN:</strong> {{ $company->pan }}@endif</div>
                    @endif
                </td>
                <td style="width: 40%;">
                    <div class="doc-title">EXPENSE STATEMENT</div>
                    <div class="period-line"><strong>{{ $periodLabel }}</strong></div>
                    <div class="period-line">{{ $periodStart->format('d M Y') }} to {{ $periodEnd->format('d M Y') }}</div>
                    <div class="gen-line">Generated: {{ now()->format('d M Y, H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if ($filters['category'] || $filters['search'])
        <div class="filters">
            Filters applied:
            @if ($filters['category'])
                <strong>Category:</strong> {{ config('expense_categories.' . $filters['category'] . '.label', $filters['category']) }}
            @endif
            @if ($filters['search'])
                @if ($filters['category']) · @endif
                <strong>Search:</strong> "{{ $filters['search'] }}"
            @endif
        </div>
    @endif

    {{-- ─── Summary cards ─── --}}
    <table class="summary-cards">
        <tr>
            <td><div class="summary-card"><div class="lbl">Total Entries</div><div class="val">{{ number_format($summary['count']) }}</div></div></td>
            <td><div class="summary-card"><div class="lbl">Taxable Value</div><div class="val">Rs. {{ number_format($summary['taxable'], 2) }}</div></div></td>
            <td><div class="summary-card"><div class="lbl">GST / ITC</div><div class="val">Rs. {{ number_format($summary['gst'], 2) }}</div></div></td>
            <td><div class="summary-card"><div class="lbl">Total Cash Out</div><div class="val">Rs. {{ number_format($summary['cash_out'], 2) }}</div></div></td>
        </tr>
    </table>

    {{-- ─── Category breakdown ─── --}}
    @if ($byCategory->isNotEmpty())
        <h3>Category-wise summary</h3>
        <table class="cat-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="r" style="width: 60px;">Entries</th>
                    <th class="r" style="width: 100px;">Taxable (Rs.)</th>
                    <th class="r" style="width: 100px;">GST (Rs.)</th>
                    <th class="r" style="width: 110px;">Total (Rs.)</th>
                    <th class="r" style="width: 60px;">Share</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byCategory as $cat)
                    <tr>
                        <td>{{ $cat['label'] }}</td>
                        <td class="r">{{ $cat['count'] }}</td>
                        <td class="r">{{ number_format($cat['taxable'], 2) }}</td>
                        <td class="r">{{ $cat['gst'] > 0 ? number_format($cat['gst'], 2) : '—' }}</td>
                        <td class="r">{{ number_format($cat['taxable'] + $cat['gst'], 2) }}</td>
                        <td class="r">{{ $summary['taxable'] > 0 ? number_format($cat['taxable'] / $summary['taxable'] * 100, 1) . '%' : '—' }}</td>
                    </tr>
                @endforeach
                <tr class="tot">
                    <td>TOTAL</td>
                    <td class="r">{{ $summary['count'] }}</td>
                    <td class="r">{{ number_format($summary['taxable'], 2) }}</td>
                    <td class="r">{{ number_format($summary['gst'], 2) }}</td>
                    <td class="r">{{ number_format($summary['cash_out'], 2) }}</td>
                    <td class="r">100%</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- ─── Detailed entries ─── --}}
    <h3>Detailed entries ({{ $summary['count'] }})</h3>
    @if ($rows->isEmpty())
        <div style="text-align: center; padding: 30px; color: #888; border: 1px dashed #ccc;">No expenses recorded for this period.</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20px;">#</th>
                    <th style="width: 52px;">Date</th>
                    <th style="width: 70px;">Category</th>
                    <th>Particulars / Vendor / Ref</th>
                    <th style="width: 60px;" class="r">Taxable</th>
                    <th style="width: 50px;" class="r">GST</th>
                    <th style="width: 62px;" class="r">Total</th>
                    <th style="width: 40px;">Mode</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $e)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $e->entry_date->format('d-M-Y') }}</td>
                        <td>{{ config('expense_categories.' . $e->category . '.label', ucfirst($e->category)) }}</td>
                        <td>
                            <span class="desc">{{ $e->description }}</span>
                            @if ($e->vendor_name)<span class="vendor">Vendor: {{ $e->vendor_name }}</span>@endif
                            @if ($e->reference_number)<span class="ref">Ref: {{ $e->reference_number }}</span>@endif
                        </td>
                        <td class="r">{{ number_format((float) $e->amount, 2) }}</td>
                        <td class="r">{{ (float) $e->gst_amount > 0 ? number_format((float) $e->gst_amount, 2) : '—' }}</td>
                        <td class="r" style="font-weight: bold;">{{ number_format((float) $e->amount + (float) $e->gst_amount, 2) }}</td>
                        <td style="text-transform: uppercase; font-size: 8px;">{{ $e->payment_method ?: '—' }}</td>
                    </tr>
                @endforeach
                <tr class="tot">
                    <td colspan="4" style="text-align: right;">GRAND TOTAL</td>
                    <td class="r">{{ number_format($summary['taxable'], 2) }}</td>
                    <td class="r">{{ number_format($summary['gst'], 2) }}</td>
                    <td class="r">{{ number_format($summary['cash_out'], 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="words">
            <div class="lbl">Amount in words (total cash out)</div>
            <div class="val">{{ $summaryWords }}</div>
        </div>
    @endif

    {{-- ─── Sign-off ─── --}}
    <div class="sign">
        <table class="sign-table">
            <tr>
                <td style="width: 50%;">
                    <div style="font-size: 8.5px; color: #666;">
                        Notes: This is a computer-generated expense statement. Figures shown are taxable value and GST as recorded against each entry. Total cash out = taxable + GST. Subject to verification of underlying invoices and cash memos. E&amp;OE.
                    </div>
                </td>
                <td style="width: 50%; text-align: right;">
                    <div class="sign-block" style="display: inline-block;">
                        <div style="font-size: 9px; color: #444; padding-bottom: 24px;">For <strong>{{ $company->name }}</strong></div>
                        <div style="font-size: 8.5px; color: #888;">Authorised Signatory</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">{{ $company->name }} · Expense Statement · Page printed {{ now()->format('d M Y') }}</div>

</body>
</html>
