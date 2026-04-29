<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Expense Voucher · {{ $expense->id }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; }

        .doc-title { text-align: center; font-size: 22px; font-weight: bold; letter-spacing: 2px; padding-bottom: 6px; border-bottom: 2px solid #111; margin-bottom: 14px; }
        .gstin-line { text-align: center; font-size: 10px; color: #555; margin-top: 2px; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { vertical-align: top; padding: 0; }
        .label { font-size: 9px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .value-strong { font-weight: bold; font-size: 13px; color: #111; }

        .meta-grid { width: 100%; }
        .meta-grid td { padding: 1px 0; font-size: 10.5px; }
        .meta-grid td.k { color: #777; text-align: right; padding-right: 8px; text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px; }
        .meta-grid td.v { font-weight: bold; }

        .vendor-box { border: 1px solid #ccc; background: #fafafa; padding: 10px 12px; margin-bottom: 14px; }
        .vendor-box .name { font-size: 13px; font-weight: bold; margin-bottom: 2px; }

        .desc-box { border: 1px solid #ddd; padding: 12px; margin-bottom: 14px; }
        .desc-box .desc-text { font-size: 12px; color: #111; margin-top: 4px; }
        .cat-pill { display: inline-block; padding: 3px 8px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 3px; }

        .totals-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .totals-table td { padding: 5px 8px; font-size: 11px; }
        .totals-table td.k { color: #555; }
        .totals-table td.v { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .totals-table tr.sub { border-top: 1px solid #ccc; }
        .totals-table tr.grand td { border-top: 2px solid #111; border-bottom: 2px solid #111; font-size: 14px; font-weight: bold; padding: 8px; background: #f5f5f5; }

        .words-box { padding: 8px 10px; background: #fafafa; border: 1px solid #ddd; margin-bottom: 12px; }
        .words-box .lbl { font-size: 9px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .words-box .val { font-style: italic; font-size: 11px; color: #111; margin-top: 2px; }

        .notes-box { font-size: 10.5px; color: #444; margin-bottom: 12px; }
        .notes-box .lbl { font-size: 9px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 3px; }

        .footer-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .footer-table td { vertical-align: top; padding: 0; font-size: 10px; }
        .signature-block { float: right; padding-top: 4px; border-top: 1px solid #777; min-width: 180px; text-align: center; }
        .signature-block .for-line { font-size: 10px; color: #444; }
        .signature-block .auth-line { font-size: 9px; color: #888; padding-top: 28px; }
        .footer-disclaim { text-align: center; font-size: 8.5px; color: #999; margin-top: 18px; padding-top: 6px; border-top: 1px solid #eee; }
    </style>
</head>
<body>

    <div class="doc-title">EXPENSE VOUCHER</div>
    @if ($expense->company->gstin)
        <div class="gstin-line">GST Reg. No. {{ $expense->company->gstin }}</div>
    @endif

    {{-- Issuer (your company) + voucher meta --}}
    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <div class="label">Issued by</div>
                <div class="value-strong">{{ $expense->company->name }}</div>
                @if ($expense->company->address_line1)<div>{{ $expense->company->address_line1 }}</div>@endif
                @if ($expense->company->address_line2)<div>{{ $expense->company->address_line2 }}</div>@endif
                <div>
                    {{ $expense->company->city }}{{ $expense->company->state ? ', ' . $expense->company->state->name : '' }}{{ $expense->company->postal_code ? ' - ' . $expense->company->postal_code : '' }}
                </div>
                @if ($expense->company->phone)<div style="font-size: 10px; color: #555;">Phone: {{ $expense->company->phone }}</div>@endif
                @if ($expense->company->email)<div style="font-size: 10px; color: #555;">Email: {{ $expense->company->email }}</div>@endif
                @if ($expense->company->gstin)<div style="font-size: 10px; margin-top: 3px;">GSTIN: <strong>{{ $expense->company->gstin }}</strong></div>@endif
            </td>
            <td style="width: 40%; text-align: right;">
                <table class="meta-grid" style="width: auto; margin-left: auto;">
                    <tr><td class="k">Voucher No.</td><td class="v">EV-{{ str_pad((string) $expense->id, 5, '0', STR_PAD_LEFT) }}</td></tr>
                    <tr><td class="k">Date</td><td class="v">{{ $expense->entry_date->format('d M Y') }}</td></tr>
                    <tr><td class="k">Payment</td><td class="v" style="text-transform: uppercase;">{{ $expense->payment_method ?: '—' }}</td></tr>
                    @if ($expense->reference_number)
                        <tr><td class="k">Reference</td><td class="v">{{ $expense->reference_number }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Paid To --}}
    @if ($expense->vendor_name)
        <div class="vendor-box">
            <div class="label">Paid To</div>
            <div class="name">{{ $expense->vendor_name }}</div>
        </div>
    @endif

    {{-- Description + Category --}}
    <div class="desc-box">
        <div class="label">Particulars</div>
        @php $cfg = config('expense_categories.' . $expense->category, ['label' => ucfirst($expense->category), 'color' => '#6b7280']); @endphp
        <span class="cat-pill" style="background: {{ $cfg['color'] }}25; color: {{ $cfg['color'] }};">{{ $cfg['label'] }}</span>
        <div class="desc-text">{{ $expense->description }}</div>
    </div>

    {{-- Totals --}}
    <table class="totals-table">
        <tr><td class="k">Taxable amount</td><td class="v">Rs. {{ number_format((float) $expense->amount, 2) }}</td></tr>
        @if ((float) $expense->gst_amount > 0)
            <tr><td class="k">GST / Input Tax Credit</td><td class="v">Rs. {{ number_format((float) $expense->gst_amount, 2) }}</td></tr>
        @endif
        <tr class="grand">
            <td class="k">Total Cash Out</td>
            <td class="v">Rs. {{ number_format((float) $expense->amount + (float) $expense->gst_amount, 2) }}</td>
        </tr>
    </table>

    {{-- Amount in words --}}
    <div class="words-box">
        <div class="lbl">Amount in words</div>
        <div class="val">{{ $amountInWords }}</div>
    </div>

    {{-- Notes --}}
    @if ($expense->notes)
        <div class="notes-box">
            <div class="lbl">Notes</div>
            <div>{{ $expense->notes }}</div>
        </div>
    @endif

    {{-- Footer: signature --}}
    <table class="footer-table">
        <tr>
            <td style="width: 60%;">
                <div style="color: #666; font-size: 9.5px;">This is a computer-generated expense voucher maintained for internal accounting records. Subject to verification of underlying invoices, receipts and bank/UPI/card statements. E&amp;OE.</div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="signature-block">
                    <div class="for-line">For <strong>{{ $expense->company->name }}</strong></div>
                    <div class="auth-line">Authorised Signatory</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-disclaim">{{ $expense->company->name }} · Expense Voucher EV-{{ str_pad((string) $expense->id, 5, '0', STR_PAD_LEFT) }} · Generated {{ now()->format('d M Y') }}</div>

</body>
</html>
