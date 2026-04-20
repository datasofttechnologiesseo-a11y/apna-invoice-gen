@php
    $styles = config('invoice_styles');
    $styleKey = $style ?? 'classic';
    $t = $styles[$styleKey] ?? $styles['classic'];

    $c = $invoice->company;
    $cust = $invoice->customer;
    $currencySymbol = 'Rs. ';
    $invoiceNumber = $invoice->isDraft()
        ? 'DRAFT · ' . ($invoice->company->nextInvoiceNumber() ?? 'preview')
        : $invoice->invoice_number;

    $hasTransporter = $invoice->transporter_name || $invoice->vehicle_number || $invoice->eway_bill_number || $invoice->transporter_id || $invoice->transport_mode;

    $payableAmount = (float) ($invoice->balance ?? $invoice->grand_total);
    $showUpiQr = $c->upi_id && $payableAmount > 0;
    $qrDataUri = $showUpiQr
        ? \App\Support\UpiQr::svgDataUri($c->upi_id, $c->name, $payableAmount, $invoice->invoice_number ?: 'Invoice', 140)
        : null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: {{ $t['font_family'] }}; font-size: 10px; color: {{ $t['body_color'] }}; line-height: 1.4; }
        .page { padding: 14mm 14mm; border-top: {{ $t['top_rule'] }}; }

        h1, h2, h3 { margin: 0; }
        .title { font-size: {{ $t['title_size'] }}; font-weight: 700; letter-spacing: {{ $t['title_letter_spacing'] }}; text-transform: {{ $t['title_transform'] }}; color: {{ $t['title_color'] }}; line-height: 1; margin: 0; }
        .co-name { font-weight: 700; font-size: 14px; color: {{ $t['body_color'] }}; }
        .small { font-size: 9px; }
        .x-small { font-size: 8.5px; }
        .muted { color: {{ $t['muted'] }}; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .tr { text-align: right; }
        .tl { text-align: left; }
        .tc { text-align: center; }
        .bold { font-weight: bold; }
        .accent { color: {{ $t['accent'] }}; }
        .upper { text-transform: uppercase; letter-spacing: 0.6px; }
        .label {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: {{ $t['muted'] }};
            margin-bottom: 3px;
        }
        .pill {
            display: inline-block;
            padding: 3px 8px;
            background: {{ $t['pill_bg'] }};
            color: {{ $t['pill_color'] }};
            border: {{ $t['pill_border'] }};
            border-radius: 3px;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: 0.4px;
        }

        /* Header */
        .hero { padding-bottom: 10px; border-bottom: {{ $t['header_rule'] }}; }

        /* Meta strip */
        .meta-strip {
            padding: 6px 0;
            border-bottom: 1px solid {{ $t['divider'] }};
            margin-top: 10px;
            font-size: 9px;
        }
        .meta-strip td { padding-right: 14px; vertical-align: top; }
        .meta-strip .meta-lbl { color: {{ $t['muted'] }}; text-transform: uppercase; letter-spacing: 0.6px; font-size: 8px; }
        .meta-strip .meta-val { font-weight: bold; color: {{ $t['body_color'] }}; font-size: 10px; }

        /* Bill-to / transporter */
        .parties { margin-top: 12px; padding-bottom: 8px; border-bottom: 1px solid {{ $t['divider'] }}; }

        /* Items */
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th {
            background: {{ $t['table_head_bg'] }};
            color: {{ $t['table_head_color'] }};
            padding: 6px 5px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: bold;
            border-bottom: 1px solid {{ $t['divider'] }};
        }
        table.items td {
            padding: 6px 5px;
            border-bottom: 1px solid {{ $t['table_row_border'] }};
            vertical-align: top;
            font-size: 10px;
        }

        /* Totals */
        .totals-box {
            padding: 10px 12px;
            border: {{ $t['totals_border'] }};
            border-radius: 3px;
        }
        table.totals { width: 100%; border-collapse: collapse; }
        table.totals td { padding: 3px 0; font-size: 10px; }
        table.totals tr.total td {
            border-top: {{ $t['total_rule'] }};
            font-weight: 700;
            font-size: 12px;
            padding-top: 7px;
            color: {{ $t['total_color'] }};
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        table.totals tr.total td:last-child { font-size: 13px; text-transform: none; letter-spacing: 0; }

        /* Amount in words */
        .aiw-text { font-style: italic; font-size: 10px; color: {{ $t['body_color'] }}; margin-top: 2px; line-height: 1.4; }

        /* Bank + QR */
        .pay-box {
            margin-top: 12px;
            padding: 10px 12px;
            border: 1px solid {{ $t['divider'] }};
            border-radius: 3px;
        }
        .qr-box { text-align: center; padding: 3px; background: #fff; border: 1px solid {{ $t['divider'] }}; border-radius: 3px; }

        /* Terms / notes */
        .note-card {
            margin-top: 10px;
            padding: 8px 10px;
            border-left: 2px solid {{ $t['divider'] }};
            font-size: 9px;
            line-height: 1.5;
        }
        .note-card .note-lbl { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: {{ $t['muted'] }}; margin-bottom: 2px; }

        /* Signature */
        .sig-wrap { margin-top: 20px; }
        .sig-box {
            display: inline-block;
            min-width: 160px;
            padding-top: 4px;
            border-top: 1px solid {{ $t['divider'] }};
            text-align: center;
            font-weight: bold;
            font-size: 9px;
        }

        /* Footer */
        .foot {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px solid {{ $t['divider'] }};
            text-align: center;
            color: {{ $t['muted'] }};
            font-size: 8px;
            letter-spacing: 0.3px;
        }

        /* Avoid breaks inside key blocks */
        .no-break { page-break-inside: avoid; }
    </style>
</head>
<body>

<div class="page">

    {{-- ========== HEADER ========== --}}
    <table class="hero" style="width:100%;">
        <tr>
            <td style="vertical-align: top; width: 60%;">
                @if ($c->logo_path && file_exists(public_path('storage/' . $c->logo_path)))
                    <img src="{{ public_path('storage/' . $c->logo_path) }}" alt="{{ $c->name }} logo" style="max-height: 44px; max-width: 180px; margin-bottom: 4px;">
                @endif
                <div class="co-name">{{ $c->name }}</div>
                <div class="small muted" style="margin-top: 3px; line-height: 1.45;">
                    {!! nl2br(e(trim($c->address_line1 . ($c->address_line2 ? ', ' . $c->address_line2 : '') . "\n" . trim(($c->city ?? '') . ($c->city && $c->state?->name ? ', ' : '') . ($c->state?->name ?? '') . ($c->state?->gst_code ? ' · State ' . $c->state->gst_code : '') . ' ' . ($c->postal_code ?? '')) . "\n" . $c->country))) !!}
                </div>
                <div class="x-small muted" style="margin-top: 3px;">
                    @if ($c->phone){{ $c->phone }}@endif
                    @if ($c->phone && $c->email) · @endif
                    @if ($c->email){{ $c->email }}@endif
                </div>
                @if ($c->gstin)
                    <div class="x-small" style="margin-top: 3px;"><span class="muted upper">GSTIN</span> <span class="mono bold">{{ $c->gstin }}</span></div>
                @endif
            </td>
            <td style="vertical-align: top; text-align: right; width: 40%;">
                <div class="title">TAX INVOICE</div>
                <div style="margin-top: 6px;"><span class="pill">#{{ $invoiceNumber }}</span></div>
                <div class="x-small muted" style="margin-top: 6px; line-height: 1.6;">
                    <strong>Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}
                    @if ($invoice->due_date) · <strong>Due:</strong> {{ $invoice->due_date->format('d M Y') }}@endif
                    @if ($invoice->reverse_charge) · <strong>Reverse charge:</strong> Yes @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ========== META STRIP ========== --}}
    <table class="meta-strip" style="width:100%;">
        <tr>
            <td>
                <div class="meta-lbl">Place of supply</div>
                <div class="meta-val">{{ $invoice->placeOfSupply?->name ?? '—' }}@if ($invoice->placeOfSupply?->gst_code) ({{ $invoice->placeOfSupply->gst_code }})@endif</div>
            </td>
            <td>
                <div class="meta-lbl">Currency</div>
                <div class="meta-val">{{ $invoice->currency ?? 'INR' }}</div>
            </td>
            <td>
                <div class="meta-lbl">Tax treatment</div>
                <div class="meta-val">{{ $invoice->is_interstate ? 'IGST (interstate)' : 'CGST + SGST (intrastate)' }}</div>
            </td>
            <td style="text-align: right;">
                <div class="meta-lbl">Status</div>
                <div class="meta-val accent">{{ strtoupper(str_replace('_', ' ', $invoice->status ?? 'draft')) }}</div>
            </td>
        </tr>
    </table>

    {{-- ========== BILL TO + TRANSPORTER ========== --}}
    <table class="parties" style="width:100%;">
        <tr>
            <td style="vertical-align: top; width: {{ $hasTransporter ? '50%' : '100%' }}; padding-right: 14px;">
                <div class="label">Billed to</div>
                <div class="bold" style="font-size: 11px;">{{ $cust->name }}</div>
                <div class="small muted" style="margin-top: 2px; line-height: 1.45;">
                    {{ $cust->address_line1 }}{{ $cust->address_line2 ? ', ' . $cust->address_line2 : '' }}<br>
                    {{ trim(($cust->city ?? '') . ($cust->city && $cust->state?->name ? ', ' : '') . ($cust->state?->name ?? '') . ($cust->state?->gst_code ? ' · State ' . $cust->state->gst_code : '') . ' ' . ($cust->postal_code ?? '')) }}<br>
                    {{ $cust->country }}
                </div>
                @if ($cust->gstin)
                    <div class="x-small" style="margin-top: 3px;"><span class="muted upper">GSTIN</span> <span class="mono bold">{{ $cust->gstin }}</span></div>
                @endif
                @if ($cust->phone || $cust->email)
                    <div class="x-small muted" style="margin-top: 2px;">{{ $cust->phone }}{{ $cust->phone && $cust->email ? ' · ' : '' }}{{ $cust->email }}</div>
                @endif
            </td>
            @if ($hasTransporter)
                <td style="vertical-align: top; width: 50%; padding-left: 14px; border-left: 1px solid {{ $t['divider'] }};">
                    <div class="label">Transporter</div>
                    <table style="width: 100%; margin-top: 2px;">
                        @if ($invoice->transporter_name)
                            <tr><td class="x-small muted" style="padding: 1px 8px 1px 0; width: 85px;">Transporter</td><td class="small">{{ $invoice->transporter_name }}</td></tr>
                        @endif
                        @if ($invoice->transporter_id)
                            <tr><td class="x-small muted" style="padding: 1px 8px 1px 0;">Transporter ID</td><td class="small mono">{{ $invoice->transporter_id }}</td></tr>
                        @endif
                        @if ($invoice->vehicle_number)
                            <tr><td class="x-small muted" style="padding: 1px 8px 1px 0;">Vehicle no.</td><td class="small mono">{{ $invoice->vehicle_number }}</td></tr>
                        @endif
                        @if ($invoice->transport_mode)
                            <tr><td class="x-small muted" style="padding: 1px 8px 1px 0;">Mode</td><td class="small">{{ $invoice->transport_mode }}</td></tr>
                        @endif
                        @if ($invoice->eway_bill_number)
                            <tr><td class="x-small muted" style="padding: 1px 8px 1px 0;">E-way bill</td><td class="small mono">{{ $invoice->eway_bill_number }}</td></tr>
                        @endif
                    </table>
                </td>
            @endif
        </tr>
    </table>

    {{-- ========== ITEMS ========== --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 24px;">#</th>
                <th>Description</th>
                <th style="width: 58px;">HSN/SAC</th>
                <th class="tr" style="width: 50px;">Qty</th>
                <th class="tr" style="width: 60px;">Rate</th>
                <th class="tr" style="width: 40px;">GST</th>
                <th class="tr" style="width: 80px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $idx => $item)
                <tr>
                    <td class="muted">{{ $idx + 1 }}</td>
                    <td><div style="font-weight: 600;">{{ $item->description }}</div></td>
                    <td class="mono x-small">{{ $item->hsn_sac }}</td>
                    <td class="tr mono">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="tr mono">{{ number_format((float) $item->rate, 2) }}</td>
                    <td class="tr">{{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%</td>
                    <td class="tr mono bold">{{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ========== AMOUNT IN WORDS + TOTALS ========== --}}
    <table style="width: 100%; margin-top: 14px;" class="no-break">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 14px;">
                <div class="label">Amount in words</div>
                <div class="aiw-text">{{ $amountInWords }}</div>

                @if ($c->declaration)
                    <div class="note-card" style="margin-top: 10px;">
                        <div class="note-lbl">Declaration</div>
                        <div style="font-style: italic;">{{ $c->declaration }}</div>
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <div class="totals-box">
                    <table class="totals">
                        <tr>
                            <td class="muted">Subtotal</td>
                            <td class="tr mono">{{ $currencySymbol }}{{ number_format((float) $invoice->subtotal, 2) }}</td>
                        </tr>
                        @if ($invoice->is_interstate)
                            <tr><td class="muted">IGST</td><td class="tr mono">{{ $currencySymbol }}{{ number_format((float) $invoice->total_igst, 2) }}</td></tr>
                        @else
                            <tr><td class="muted">CGST</td><td class="tr mono">{{ $currencySymbol }}{{ number_format((float) $invoice->total_cgst, 2) }}</td></tr>
                            <tr><td class="muted">SGST</td><td class="tr mono">{{ $currencySymbol }}{{ number_format((float) $invoice->total_sgst, 2) }}</td></tr>
                        @endif
                        @if ((float) $invoice->round_off != 0)
                            <tr><td class="muted x-small">Round off</td><td class="tr mono x-small">{{ number_format((float) $invoice->round_off, 2) }}</td></tr>
                        @endif
                        <tr class="total">
                            <td>Grand Total</td>
                            <td class="tr mono">{{ $currencySymbol }}{{ number_format((float) $invoice->grand_total, 2) }}</td>
                        </tr>
                        @if ((float) $invoice->paid_amount > 0)
                            <tr>
                                <td class="muted" style="padding-top: 6px;">Paid</td>
                                <td class="tr mono muted" style="padding-top: 6px;">{{ $currencySymbol }}{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="bold" style="padding-top: 4px; border-top: 1px solid {{ $t['divider'] }};">Balance due</td>
                                <td class="tr mono bold accent" style="padding-top: 4px; border-top: 1px solid {{ $t['divider'] }};">{{ $currencySymbol }}{{ number_format((float) $invoice->balance, 2) }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ========== BANK DETAILS + UPI QR ========== --}}
    @if ($c->bank_name || $c->bank_account_number || $c->upi_id)
        <div class="pay-box no-break">
            <div class="label">Payment details</div>
            <table style="width: 100%; margin-top: 2px;">
                <tr>
                    <td style="vertical-align: top; {{ $showUpiQr ? 'width: 72%; padding-right: 12px;' : '' }}">
                        <table style="width: 100%; font-size: 9px; line-height: 1.6;">
                            @if ($c->bank_name)
                                <tr>
                                    <td class="x-small muted upper" style="width: 70px; padding-right: 8px;">Bank</td>
                                    <td class="bold">{{ $c->bank_name }}@if ($c->bank_branch), {{ $c->bank_branch }}@endif</td>
                                </tr>
                            @endif
                            @if ($c->bank_account_number)
                                <tr>
                                    <td class="x-small muted upper" style="padding-right: 8px;">A/c no.</td>
                                    <td class="mono bold">{{ $c->bank_account_number }}</td>
                                </tr>
                            @endif
                            @if ($c->bank_ifsc)
                                <tr>
                                    <td class="x-small muted upper" style="padding-right: 8px;">IFSC</td>
                                    <td class="mono bold">{{ $c->bank_ifsc }}</td>
                                </tr>
                            @endif
                            @if ($c->upi_id)
                                <tr>
                                    <td class="x-small muted upper" style="padding-right: 8px;">UPI</td>
                                    <td class="mono bold accent">{{ $c->upi_id }}</td>
                                </tr>
                            @endif
                        </table>
                    </td>
                    @if ($showUpiQr)
                        <td style="vertical-align: top; width: 28%; text-align: center;">
                            <div class="qr-box">
                                <img src="{{ $qrDataUri }}" alt="UPI payment QR" style="width: 63px; height: 63px; display: block; margin: 0 auto;">
                            </div>
                            <div class="x-small bold accent" style="margin-top: 3px;">Scan & pay {{ $currencySymbol }}{{ number_format($payableAmount, 2) }}</div>
                            <div class="x-small muted">GPay · PhonePe · Paytm</div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    {{-- ========== TERMS + NOTES ========== --}}
    @if ($invoice->terms)
        <div class="note-card">
            <div class="note-lbl">Terms &amp; conditions</div>
            <div style="white-space: pre-line;">{{ $invoice->terms }}</div>
        </div>
    @endif

    @if ($invoice->notes)
        <div class="note-card">
            <div class="note-lbl">Notes</div>
            <div style="white-space: pre-line;">{{ $invoice->notes }}</div>
        </div>
    @endif

    {{-- ========== SIGNATURE + FOOTER ========== --}}
    <div class="sig-wrap no-break">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: bottom;"></td>
                <td style="vertical-align: top; text-align: right;">
                    @if ($c->signature_path && file_exists(public_path('storage/' . $c->signature_path)))
                        <img src="{{ public_path('storage/' . $c->signature_path) }}" alt="Authorised signature" style="max-height: 32px; margin-bottom: 2px;">
                    @endif
                    <div class="sig-box">
                        for {{ $c->name }}
                        <div class="x-small muted" style="font-weight: normal; margin-top: 1px;">Authorised signatory</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="foot">Computer-generated invoice — no signature required.</div>
</div>

</body>
</html>
