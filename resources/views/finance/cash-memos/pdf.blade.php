<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cash Memo {{ $memo->memo_number }}</title>
    <style>
        @page { margin: 14mm 12mm; }
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
        .seller-box { border: 1px solid #ccc; background: #fafafa; padding: 10px 12px; margin-bottom: 14px; }
        .seller-box .name { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
        .seller-box .addr { color: #444; font-size: 10.5px; }
        .seller-box .meta { font-size: 9.5px; color: #555; margin-top: 4px; }
        .seller-box .meta span { margin-right: 14px; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .items thead th { background: #111; color: #fff; padding: 6px 6px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .items thead th.r { text-align: right; }
        .items tbody td { padding: 6px 6px; border-bottom: 1px solid #e0e0e0; font-size: 10.5px; vertical-align: top; }
        .items tbody td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .items tbody td.mono { font-family: DejaVu Sans Mono, monospace; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 3px 6px; font-size: 11px; }
        .totals-table td.k { color: #555; }
        .totals-table td.v { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .totals-table tr.sub { border-top: 1px solid #ccc; }
        .totals-table tr.grand td { border-top: 2px solid #111; font-size: 14px; font-weight: bold; padding: 6px; }
        .totals-table tr.grand td.k { color: #111; }
        .words-box .lbl { font-size: 9px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 4px; }
        .words-box .words { font-style: italic; color: #111; font-size: 11px; }
        .notes-box .lbl { font-size: 9px; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-top: 8px; margin-bottom: 4px; }
        .notes-box .notes { color: #444; font-size: 10.5px; }
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .footer-table td { vertical-align: top; padding: 0; font-size: 10px; }
        .signature-block { float: right; padding-top: 4px; border-top: 1px solid #777; min-width: 180px; text-align: center; }
        .signature-block .for-line { font-size: 10px; color: #444; }
        .signature-block .auth-line { font-size: 9px; color: #888; padding-top: 28px; }
        .footer-disclaim { text-align: center; font-size: 8.5px; color: #999; margin-top: 18px; padding-top: 6px; border-top: 1px solid #eee; }
    </style>
</head>
<body>

    <div class="doc-title">CASH MEMO</div>
    @if ($memo->company->gstin)
        <div class="gstin-line">GST Reg. No. {{ $memo->company->gstin }}</div>
    @endif

    {{-- Top: Buyer (left) + Memo metadata (right) --}}
    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <div class="label">Issued by</div>
                <div class="value-strong">{{ $memo->company->name }}</div>
                @if ($memo->company->address_line1)
                    <div>{{ $memo->company->address_line1 }}</div>
                @endif
                @if ($memo->company->address_line2)
                    <div>{{ $memo->company->address_line2 }}</div>
                @endif
                <div>
                    {{ $memo->company->city }}{{ $memo->company->state ? ', ' . $memo->company->state->name : '' }}{{ $memo->company->postal_code ? ' - ' . $memo->company->postal_code : '' }}
                </div>
                @if ($memo->company->phone)
                    <div style="font-size: 10px; color: #555;">Phone: {{ $memo->company->phone }}</div>
                @endif
                @if ($memo->company->email)
                    <div style="font-size: 10px; color: #555;">Email: {{ $memo->company->email }}</div>
                @endif
                @if ($memo->company->gstin)
                    <div style="font-size: 10px; margin-top: 3px;">GSTIN: <strong>{{ $memo->company->gstin }}</strong></div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <table class="meta-grid" style="width: auto; margin-left: auto;">
                    <tr><td class="k">Memo No.</td><td class="v">{{ $memo->memo_number }}</td></tr>
                    <tr><td class="k">Date</td><td class="v">{{ $memo->memo_date->format('d M Y') }}</td></tr>
                    <tr><td class="k">Payment</td><td class="v" style="text-transform: uppercase;">{{ $memo->payment_mode }}</td></tr>
                    @if ($memo->reference_number)
                        <tr><td class="k">Reference</td><td class="v">{{ $memo->reference_number }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Purchased From --}}
    <div class="seller-box">
        <div class="label">Purchased From</div>
        <div class="name">{{ $memo->seller_name }}</div>
        @if ($memo->seller_address)
            <div class="addr">{{ $memo->seller_address }}</div>
        @endif
        @if ($memo->seller_state || $memo->seller_gstin || $memo->seller_phone)
            <div class="meta">
                @if ($memo->seller_state)<span>State: <strong>{{ $memo->seller_state }}</strong></span>@endif
                @if ($memo->seller_gstin)<span>GSTIN: <strong>{{ $memo->seller_gstin }}</strong></span>@endif
                @if ($memo->seller_phone)<span>Phone: {{ $memo->seller_phone }}</span>@endif
            </div>
        @endif
    </div>

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 24px;">#</th>
                <th>Particulars</th>
                <th style="width: 70px;">HSN/SAC</th>
                <th style="width: 60px;" class="r">Qty</th>
                <th style="width: 70px;" class="r">Rate (Rs.)</th>
                <th style="width: 90px;" class="r">Amount (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($memo->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="mono">{{ $item->hsn_sac ?: '—' }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}@if ($item->unit) <span style="color:#888;">{{ $item->unit }}</span>@endif</td>
                    <td class="r">{{ number_format((float) $item->rate, 2) }}</td>
                    <td class="r" style="font-weight: bold;">{{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Words + Totals --}}
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 16px;">
                <div class="words-box">
                    <div class="lbl">Amount in words</div>
                    <div class="words">{{ $memo->amount_in_words }}</div>
                </div>
                @if ($memo->notes)
                    <div class="notes-box">
                        <div class="lbl">Notes</div>
                        <div class="notes">{{ $memo->notes }}</div>
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="totals-table">
                    <tr><td class="k">Subtotal</td><td class="v">Rs. {{ number_format((float) $memo->subtotal, 2) }}</td></tr>
                    @if ((float) $memo->discount > 0)
                        <tr><td class="k">Discount</td><td class="v" style="color:#a00;">- Rs. {{ number_format((float) $memo->discount, 2) }}</td></tr>
                    @endif
                    <tr class="sub"><td class="k" style="color:#111; font-weight: bold;">Taxable value</td><td class="v" style="font-weight: bold;">Rs. {{ number_format((float) $memo->taxable_value, 2) }}</td></tr>
                    @if ((float) $memo->total_cgst > 0)
                        <tr><td class="k">CGST</td><td class="v">Rs. {{ number_format((float) $memo->total_cgst, 2) }}</td></tr>
                        <tr><td class="k">SGST</td><td class="v">Rs. {{ number_format((float) $memo->total_sgst, 2) }}</td></tr>
                    @endif
                    @if ((float) $memo->total_igst > 0)
                        <tr><td class="k">IGST</td><td class="v">Rs. {{ number_format((float) $memo->total_igst, 2) }}</td></tr>
                    @endif
                    @if ((float) $memo->round_off != 0)
                        <tr><td class="k" style="font-size: 9px; color: #888;">Round off</td><td class="v" style="font-size: 9px; color: #888;">{{ ((float) $memo->round_off >= 0 ? '+ ' : '- ') }}Rs. {{ number_format(abs((float) $memo->round_off), 2) }}</td></tr>
                    @endif
                    <tr class="grand"><td class="k">Grand Total</td><td class="v">Rs. {{ number_format((float) $memo->grand_total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Footer: signature --}}
    <table class="footer-table">
        <tr>
            <td style="width: 60%;">
                <div style="color: #666; font-size: 9.5px;">Received with thanks. Goods/services once sold are not returnable except as per agreement.</div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="signature-block">
                    <div class="for-line">For <strong>{{ $memo->company->name }}</strong></div>
                    <div class="auth-line">Authorised Signatory</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-disclaim">This is a computer-generated cash memo. E&amp;OE.</div>

</body>
</html>
