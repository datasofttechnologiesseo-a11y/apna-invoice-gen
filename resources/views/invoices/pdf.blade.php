<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 0; }
        .wrapper { padding: 24px; }
        h1, h2 { margin: 0; }
        .title { font-size: 20px; font-weight: bold; letter-spacing: 1px; }
        .small { font-size: 10px; }
        .muted { color: #6b7280; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .tr { text-align: right; }
        .tl { text-align: left; }
        .tc { text-align: center; }
        .bold { font-weight: bold; }
        .row { display: table; width: 100%; }
        .col { display: table-cell; vertical-align: top; }
        .col-half { width: 50%; }
        .bt { border-top: 1px solid #d1d5db; }
        .bb { border-bottom: 1px solid #d1d5db; }
        .bb2 { border-bottom: 2px solid #111827; }
        .p8 { padding: 8px 0; }
        .mt10 { margin-top: 10px; }
        .mt20 { margin-top: 20px; }
        .mt30 { margin-top: 30px; }
        .mt40 { margin-top: 40px; }
        .mb6 { margin-bottom: 6px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th { background: #f3f4f6; padding: 6px 4px; text-align: left; font-size: 10px; text-transform: uppercase; border-bottom: 1px solid #d1d5db; }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.totals { width: 100%; border-collapse: collapse; }
        table.totals td { padding: 4px 0; }
        table.totals tr.total td { border-top: 1px solid #111827; font-weight: bold; font-size: 13px; padding-top: 8px; }
        .terms { font-size: 10px; margin-top: 24px; padding-top: 8px; border-top: 1px solid #d1d5db; }
        .sig { text-align: right; margin-top: 40px; }
        .sig .box { display: inline-block; min-width: 180px; border-top: 1px solid #6b7280; padding-top: 4px; text-align: center; }
        .badge { display: inline-block; padding: 1px 6px; background: #f3f4f6; border-radius: 3px; font-size: 9px; }
    </style>
</head>
<body>
@php
    $c = $invoice->company;
    $cust = $invoice->customer;
    $currencySymbol = ['INR' => 'Rs. ', 'USD' => '$', 'EUR' => 'EUR ', 'GBP' => 'GBP '][$invoice->currency] ?? ($invoice->currency . ' ');
@endphp
<div class="wrapper">
    <table class="bb2" style="width:100%; padding-bottom: 10px;">
        <tr>
            <td style="vertical-align: top;">
                @if ($c->logo_path && file_exists(public_path('storage/' . $c->logo_path)))
                    <img src="{{ public_path('storage/' . $c->logo_path) }}" style="max-height: 60px; max-width: 200px;">
                @else
                    <div class="bold" style="font-size: 16px;">{{ $c->name }}</div>
                @endif
                <div class="small muted mt10">
                    {!! nl2br(e(trim($c->address_line1 . ($c->address_line2 ? ', ' . $c->address_line2 : '') . "\n" . trim(($c->city ?? '') . ($c->city && $c->state?->name ? ', ' : '') . ($c->state?->name ?? '') . ' ' . ($c->postal_code ?? '')) . "\n" . $c->country))) !!}
                </div>
                <div class="small mt10">
                    @if ($c->phone) Phone: {{ $c->phone }} @endif
                    @if ($c->email) · Email: {{ $c->email }} @endif
                </div>
                @if ($c->gstin)<div class="small mt10"><strong>GSTIN:</strong> {{ $c->gstin }}</div>@endif
            </td>
            <td class="tr" style="vertical-align: top;">
                <div class="title">TAX INVOICE</div>
                <div class="small mt10">
                    <strong>Invoice #:</strong> {{ str_starts_with($invoice->invoice_number, 'DRAFT-') ? 'DRAFT' : $invoice->invoice_number }}<br>
                    <strong>Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}<br>
                    @if ($invoice->due_date)<strong>Due:</strong> {{ $invoice->due_date->format('d M Y') }}<br>@endif
                    <strong>Place of supply:</strong> {{ $invoice->placeOfSupply?->name ?? '—' }}<br>
                    <strong>Reverse charge:</strong> {{ $invoice->reverse_charge ? 'Yes' : 'No' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="bb" style="width:100%; margin-top: 12px; padding-bottom: 10px;">
        <tr>
            <td class="col-half" style="vertical-align: top; padding-right: 20px;">
                <div class="small muted bold" style="text-transform: uppercase;">Bill to</div>
                <div class="bold mt10">{{ $cust->name }}</div>
                <div class="small">
                    {{ $cust->address_line1 }}{{ $cust->address_line2 ? ', ' . $cust->address_line2 : '' }}<br>
                    {{ trim(($cust->city ?? '') . ($cust->city && $cust->state?->name ? ', ' : '') . ($cust->state?->name ?? '') . ' ' . ($cust->postal_code ?? '')) }}<br>
                    {{ $cust->country }}
                </div>
                @if ($cust->gstin)<div class="small mt10"><strong>GSTIN:</strong> {{ $cust->gstin }}</div>@endif
                @if ($cust->phone || $cust->email)<div class="small mt10">{{ $cust->phone }}{{ $cust->phone && $cust->email ? ' · ' : '' }}{{ $cust->email }}</div>@endif
            </td>
            <td class="col-half" style="vertical-align: top;">
                @if ($invoice->notes)
                    <div class="small muted bold" style="text-transform: uppercase;">Notes</div>
                    <div class="small mt10" style="white-space: pre-line;">{{ $invoice->notes }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 24px;">#</th>
                <th>Description</th>
                <th style="width: 55px;">HSN/SAC</th>
                <th class="tr" style="width: 50px;">Qty</th>
                <th class="tr" style="width: 60px;">Rate</th>
                <th class="tr" style="width: 70px;">Amount</th>
                <th class="tr" style="width: 40px;">GST%</th>
                @if ($invoice->is_interstate)
                    <th class="tr" style="width: 60px;">IGST</th>
                @else
                    <th class="tr" style="width: 60px;">CGST</th>
                    <th class="tr" style="width: 60px;">SGST</th>
                @endif
                <th class="tr" style="width: 70px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $idx => $item)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="mono">{{ $item->hsn_sac }}</td>
                    <td class="tr mono">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="tr mono">{{ number_format((float) $item->rate, 2) }}</td>
                    <td class="tr mono">{{ number_format((float) $item->amount, 2) }}</td>
                    <td class="tr">{{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%</td>
                    @if ($invoice->is_interstate)
                        <td class="tr mono">{{ number_format((float) $item->igst_amount, 2) }}</td>
                    @else
                        <td class="tr mono">{{ number_format((float) $item->cgst_amount, 2) }}</td>
                        <td class="tr mono">{{ number_format((float) $item->sgst_amount, 2) }}</td>
                    @endif
                    <td class="tr mono bold">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 16px;">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 20px;">
                <div class="small"><strong>Amount in words:</strong></div>
                <div class="small" style="font-style: italic;">{{ $amountInWords }}</div>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="tr mono">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                    @if ($invoice->is_interstate)
                        <tr><td>IGST</td><td class="tr mono">{{ number_format((float) $invoice->total_igst, 2) }}</td></tr>
                    @else
                        <tr><td>CGST</td><td class="tr mono">{{ number_format((float) $invoice->total_cgst, 2) }}</td></tr>
                        <tr><td>SGST</td><td class="tr mono">{{ number_format((float) $invoice->total_sgst, 2) }}</td></tr>
                    @endif
                    @if ((float) $invoice->round_off != 0)
                        <tr><td>Round off</td><td class="tr mono">{{ number_format((float) $invoice->round_off, 2) }}</td></tr>
                    @endif
                    <tr class="total"><td>Grand Total</td><td class="tr mono">{{ $currencySymbol }}{{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
                    @if ((float) $invoice->paid_amount > 0)
                        <tr><td class="muted">Paid</td><td class="tr mono muted">{{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
                        <tr class="bt"><td class="bold">Balance</td><td class="tr mono bold">{{ $currencySymbol }}{{ number_format((float) $invoice->balance, 2) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if ($c->bank_name || $c->bank_account_number || $c->upi_id)
        <div class="mt20" style="padding: 10px; background: #f9fafb; border-radius: 4px;">
            <div class="bold muted small" style="text-transform: uppercase;">Bank details for payment</div>
            <table style="width: 100%; margin-top: 4px; font-size: 10px;">
                <tr>
                    @if ($c->bank_name)
                        <td style="padding-right: 12px;"><strong>Bank:</strong> {{ $c->bank_name }}</td>
                    @endif
                    @if ($c->bank_branch)
                        <td style="padding-right: 12px;"><strong>Branch:</strong> {{ $c->bank_branch }}</td>
                    @endif
                    @if ($c->bank_account_number)
                        <td style="padding-right: 12px;"><strong>A/c:</strong> <span class="mono">{{ $c->bank_account_number }}</span></td>
                    @endif
                </tr>
                <tr>
                    @if ($c->bank_ifsc)
                        <td style="padding-right: 12px;"><strong>IFSC:</strong> <span class="mono">{{ $c->bank_ifsc }}</span></td>
                    @endif
                    @if ($c->upi_id)
                        <td colspan="2"><strong>UPI:</strong> <span class="mono">{{ $c->upi_id }}</span></td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    @if ($invoice->terms)
        <div class="terms">
            <div class="bold muted" style="text-transform: uppercase;">Terms &amp; Conditions</div>
            <div style="white-space: pre-line;">{{ $invoice->terms }}</div>
        </div>
    @endif

    @if ($c->declaration)
        <div class="small mt20" style="padding-top: 8px; border-top: 1px solid #d1d5db; font-style: italic; color: #4b5563;">
            <strong>Declaration:</strong> {{ $c->declaration }}
        </div>
    @endif

    <div class="sig">
        @if ($c->signature_path && file_exists(public_path('storage/' . $c->signature_path)))
            <img src="{{ public_path('storage/' . $c->signature_path) }}" style="max-height: 40px; display: block; margin-left: auto;">
        @endif
        <div class="box">for {{ $c->name }}<br><span class="small muted">Authorised signatory</span></div>
    </div>

    <div class="small muted mt20 tc">This is a computer-generated invoice.</div>
</div>
</body>
</html>
