<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>GSTR-3B Summary — {{ $periodLabel }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 0; }

        .doc-title { text-align: center; font-size: 18px; font-weight: bold; letter-spacing: 2px; padding-bottom: 4px; border-bottom: 2px solid #111; margin-bottom: 8px; }
        .doc-sub { text-align: center; font-size: 10px; color: #555; margin-bottom: 12px; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { vertical-align: top; padding: 0; font-size: 10px; }
        .label { font-size: 8.5px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .value-strong { font-weight: bold; font-size: 12px; color: #111; }

        .section { margin-bottom: 12px; }
        .section-head { background: #111; color: #fff; padding: 6px 10px; font-size: 10px; font-weight: bold; }
        .section-head .num { font-size: 8px; color: #ccc; text-transform: uppercase; letter-spacing: 1px; }
        .section-head .title { font-size: 11px; }

        table.gstr { width: 100%; border-collapse: collapse; }
        table.gstr thead th { background: #f0f0f0; padding: 5px 6px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; border-bottom: 1px solid #999; }
        table.gstr thead th.r { text-align: right; }
        table.gstr tbody td { padding: 5px 6px; border-bottom: 1px solid #e5e5e5; font-size: 9.5px; vertical-align: top; }
        table.gstr tbody td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        table.gstr tbody tr.highlight td { background: #fafafa; }
        table.gstr tbody tr.total td { background: #f0f0f0; font-weight: bold; border-top: 2px solid #111; }
        table.gstr tbody td .label-sub { font-size: 8px; color: #777; }
        table.gstr tbody td.muted { color: #aaa; }

        .total-banner { padding: 12px 14px; background: #111; color: #fff; margin-top: 8px; }
        .total-banner table { width: 100%; }
        .total-banner td { color: #fff; padding: 0; vertical-align: middle; }
        .total-banner .lbl { font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; color: #ccc; }
        .total-banner .lbl-sub { font-size: 8px; color: #aaa; margin-top: 2px; }
        .total-banner .amt { font-size: 18px; font-weight: bold; text-align: right; font-family: DejaVu Sans Mono, monospace; }

        .note { margin-top: 10px; padding: 6px 8px; border-left: 3px solid #888; background: #f7f7f7; font-size: 8.5px; font-style: italic; color: #555; }

        .footer { margin-top: 14px; padding-top: 4px; border-top: 1px solid #ddd; text-align: center; font-size: 8px; color: #888; }
    </style>
</head>
<body>

    <div class="doc-title">GSTR-3B SUMMARY</div>
    <div class="doc-sub">Filing month: <strong>{{ $periodLabel }}</strong> · {{ $periodStart->format('d M Y') }} – {{ $periodEnd->format('d M Y') }} · Computed from books</div>

    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <div class="label">Taxpayer</div>
                <div class="value-strong">{{ $company->name }}</div>
                @if ($company->gstin)
                    <div style="font-size: 9.5px; margin-top: 2px;">GSTIN: <strong>{{ $company->gstin }}</strong></div>
                @endif
                @if ($company->state)
                    <div style="font-size: 9.5px;">State: {{ $company->state->name }} ({{ $company->state->gst_code }})</div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="label">Generated</div>
                <div style="font-size: 9.5px; margin-top: 2px;">{{ now()->format('d M Y · H:i') }}</div>
                <div style="font-size: 9.5px;">From {{ $invoiceCount }} {{ \Illuminate\Support\Str::plural('invoice', $invoiceCount) }} · {{ $expenseCount }} {{ \Illuminate\Support\Str::plural('expense', $expenseCount) }} · {{ $cashMemoCount }} cash {{ \Illuminate\Support\Str::plural('memo', $cashMemoCount) }}</div>
                <div style="font-size: 9px; margin-top: 4px; color: #555;">Filing due: <strong>{{ $periodEnd->copy()->addMonth()->day(20)->format('d M Y') }}</strong></div>
            </td>
        </tr>
    </table>

    {{-- Section 3.1 --}}
    <div class="section">
        <div class="section-head">
            <span class="num">Section 3.1</span><br>
            <span class="title">Details of Outward Supplies and Inward Supplies Liable to Reverse Charge</span>
        </div>
        <table class="gstr">
            <thead>
                <tr>
                    <th>Nature of supplies</th>
                    <th class="r">Taxable value (₹)</th>
                    <th class="r">IGST (₹)</th>
                    <th class="r">CGST (₹)</th>
                    <th class="r">SGST (₹)</th>
                    <th class="r">Cess (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="highlight">
                    <td><strong>(a)</strong> Outward taxable supplies <span class="label-sub">(other than zero rated, nil rated and exempted)</span></td>
                    <td class="r">{{ number_format($outward['taxable'], 2) }}</td>
                    <td class="r">{{ number_format($outward['igst'], 2) }}</td>
                    <td class="r">{{ number_format($outward['cgst'], 2) }}</td>
                    <td class="r">{{ number_format($outward['sgst'], 2) }}</td>
                    <td class="r muted">0.00</td>
                </tr>
                <tr>
                    <td><strong>(b)</strong> Outward taxable supplies <span class="label-sub">(zero rated)</span></td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">0.00</td>
                </tr>
                <tr>
                    <td><strong>(c)</strong> Other outward supplies <span class="label-sub">(Nil rated, exempted)</span></td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">—</td>
                    <td class="r muted">—</td>
                    <td class="r muted">—</td>
                    <td class="r muted">—</td>
                </tr>
                <tr class="highlight">
                    <td><strong>(d)</strong> Inward supplies <span class="label-sub">(liable to reverse charge)</span></td>
                    <td class="r">{{ number_format($rcm_outward['taxable'], 2) }}</td>
                    <td class="r">{{ number_format($rcm_outward['igst'], 2) }}</td>
                    <td class="r">{{ number_format($rcm_outward['cgst'], 2) }}</td>
                    <td class="r">{{ number_format($rcm_outward['sgst'], 2) }}</td>
                    <td class="r muted">0.00</td>
                </tr>
                <tr>
                    <td><strong>(e)</strong> Non-GST outward supplies</td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">—</td>
                    <td class="r muted">—</td>
                    <td class="r muted">—</td>
                    <td class="r muted">—</td>
                </tr>
            </tbody>
        </table>
        <div class="note">
            Rows (b), (c), (e) are zero because the app does not yet track exports / nil-rated / non-GST supplies. If applicable, fill manually on the GST portal.
        </div>
    </div>

    {{-- Section 4 --}}
    <div class="section">
        <div class="section-head">
            <span class="num">Section 4</span><br>
            <span class="title">Eligible ITC</span>
        </div>
        <table class="gstr">
            <thead>
                <tr>
                    <th>Details</th>
                    <th class="r">IGST (₹)</th>
                    <th class="r">CGST (₹)</th>
                    <th class="r">SGST (₹)</th>
                    <th class="r">Cess (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="highlight">
                    <td><strong>(A)(5)</strong> All other ITC <span class="label-sub">(from your expenses + cash memos)</span></td>
                    <td class="r">{{ number_format($itc['igst'], 2) }}</td>
                    <td class="r">{{ number_format($itc['cgst'], 2) }}</td>
                    <td class="r">{{ number_format($itc['sgst'], 2) }}</td>
                    <td class="r muted">0.00</td>
                </tr>
                <tr>
                    <td><strong>(B)</strong> ITC reversed</td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">0.00</td>
                    <td class="r muted">—</td>
                </tr>
                <tr class="total">
                    <td>(C) Net ITC available <span class="label-sub">(A − B)</span></td>
                    <td class="r">{{ number_format($itc['igst'], 2) }}</td>
                    <td class="r">{{ number_format($itc['cgst'], 2) }}</td>
                    <td class="r">{{ number_format($itc['sgst'], 2) }}</td>
                    <td class="r muted">0.00</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Section 6.1 --}}
    <div class="section">
        <div class="section-head">
            <span class="num">Section 6.1</span><br>
            <span class="title">Payment of Tax</span>
        </div>
        <table class="gstr">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="r">IGST (₹)</th>
                    <th class="r">CGST (₹)</th>
                    <th class="r">SGST (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total tax payable <span class="label-sub">(from outward supplies)</span></td>
                    <td class="r">{{ number_format($outward['igst'], 2) }}</td>
                    <td class="r">{{ number_format($outward['cgst'], 2) }}</td>
                    <td class="r">{{ number_format($outward['sgst'], 2) }}</td>
                </tr>
                <tr>
                    <td>Less: ITC available</td>
                    <td class="r">−{{ number_format($itc['igst'], 2) }}</td>
                    <td class="r">−{{ number_format($itc['cgst'], 2) }}</td>
                    <td class="r">−{{ number_format($itc['sgst'], 2) }}</td>
                </tr>
                <tr class="total">
                    <td>Net cash payable</td>
                    <td class="r">{{ number_format($netCash['igst'], 2) }}</td>
                    <td class="r">{{ number_format($netCash['cgst'], 2) }}</td>
                    <td class="r">{{ number_format($netCash['sgst'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="total-banner">
            <table>
                <tr>
                    <td>
                        <div class="lbl">Total cash to deposit (PMT-06)</div>
                        <div class="lbl-sub">IGST + CGST + SGST · pay on the GST portal</div>
                    </td>
                    <td class="amt">Rs. {{ number_format($netCash['total'], 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="note">
        <strong>Disclaimer:</strong> This statement is computed automatically from your books. Verify against your GSTR-1 filings and ITC ledger (GSTR-2B reconciliation) before submitting GSTR-3B on the GST portal. Apna Invoice does not file returns directly.
    </div>

    <div class="footer">
        Generated by <strong>Apna Invoice</strong> for {{ $company->name }}. Filing due: {{ $periodEnd->copy()->addMonth()->day(20)->format('d M Y') }}. <strong>E. &amp; O.E.</strong>
    </div>

</body>
</html>
