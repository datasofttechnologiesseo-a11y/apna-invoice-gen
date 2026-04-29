@php
    $styles = config('invoice_styles');
    $styleKey = $style ?? ($invoice->style ?? 'classic');
    $t = $styles[$styleKey] ?? $styles['classic'];

    // "Print" / ink-saver mode: neutralise filled backgrounds and coloured rules
    // so users spend less toner / ink when printing the downloaded PDF. The
    // on-screen preview stays colourful; only the download view flips this on.
    $print = $print ?? false;
    if ($print) {
        $t = array_merge($t, [
            'accent' => '#111111',
            'accent_soft' => '#ffffff',
            'title_color' => '#111111',
            'top_rule' => '1px solid #111111',
            'header_rule' => '1px solid #111111',
            'table_head_bg' => '#ffffff',
            'table_head_color' => '#111111',
            'table_row_border' => '#cccccc',
            'divider' => '#bbbbbb',
            'totals_border' => '1px solid #111111',
            'total_rule' => '1px solid #111111',
            'total_color' => '#111111',
            'pill_bg' => '#ffffff',
            'pill_color' => '#111111',
            'pill_border' => '1px solid #111111',
            'body_color' => '#111111',
            'muted' => '#555555',
        ]);
    }

    $c = $invoice->company;
    $cust = $invoice->customer;
    $currencySymbol = '₹';
    $invoiceNumber = $invoice->isDraft()
        ? 'DRAFT · ' . ($invoice->company->nextInvoiceNumber() ?? 'preview')
        : $invoice->invoice_number;

    $hasTransporter = $invoice->transporter_name || $invoice->vehicle_number || $invoice->eway_bill_number || $invoice->transporter_id || $invoice->transport_mode;

    $payableAmount = (float) ($invoice->balance ?? $invoice->grand_total);
    $showUpiQr = $c->upi_id && $payableAmount > 0;
    $qrDataUri = $showUpiQr
        ? \App\Support\UpiQr::svgDataUri($c->upi_id, $c->name, $payableAmount, $invoice->invoice_number ?: 'Invoice', 140)
        : null;

    // HSN-wise summary (Rule 46 best practice — required on GSTR-1 anyway)
    $hsnSummary = collect($invoice->items ?? [])
        ->groupBy('hsn_sac')
        ->map(function ($items, $hsn) use ($invoice) {
            $items = collect($items);
            $taxable = $items->sum(fn ($i) => (float) $i->amount);
            $cgst = $items->sum(fn ($i) => (float) $i->cgst_amount);
            $sgst = $items->sum(fn ($i) => (float) $i->sgst_amount);
            $igst = $items->sum(fn ($i) => (float) $i->igst_amount);
            return [
                'hsn' => $hsn,
                'taxable' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'total_tax' => $cgst + $sgst + $igst,
            ];
        })
        ->values();
    // Only render HSN summary when invoice has enough complexity to benefit — 2-item invoices don't need it
    $showHsnSummary = $hsnSummary->count() > 1 && collect($invoice->items ?? [])->count() >= 4;

    $jurisdictionCity = $c->city ?: 'India';
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
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: {{ $t['body_color'] }}; line-height: 1.45; }
        .page { padding: 10mm 11mm 8mm; border-top: {{ $t['top_rule'] }}; }

        h1, h2, h3 { margin: 0; }
        .title { font-size: {{ $t['title_size'] }}; font-weight: 700; letter-spacing: {{ $t['title_letter_spacing'] }}; text-transform: uppercase; color: {{ $t['title_color'] }}; line-height: 1; }
        .co-name { font-weight: 700; font-size: 15px; color: {{ $t['body_color'] }}; }
        .small { font-size: 9px; }
        .x-small { font-size: 8.5px; }
        .muted { color: {{ $t['muted'] }}; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .tr { text-align: right; }
        .tl { text-align: left; }
        .tc { text-align: center; }
        .bold { font-weight: bold; }
        .accent { color: {{ $t['accent'] }}; }
        .upper { text-transform: uppercase; letter-spacing: 0.8px; }
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
        .copy-label {
            display: inline-block;
            padding: 2px 8px;
            border: 1px dashed {{ $t['accent'] }};
            color: {{ $t['accent'] }};
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        /* Header */
        .hero { padding-bottom: 8px; border-bottom: {{ $t['header_rule'] }}; }

        /* Parties */
        .parties { margin-top: 10px; padding-bottom: 8px; border-bottom: 1px solid {{ $t['divider'] }}; }

        /* Items table */
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th {
            background: {{ $t['table_head_bg'] }};
            color: {{ $t['table_head_color'] }};
            padding: 6px 5px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
            border-top: 1px solid {{ $t['divider'] }};
            border-bottom: 1px solid {{ $t['divider'] }};
        }
        table.items td {
            padding: 5px 5px;
            border-bottom: 1px solid {{ $t['table_row_border'] }};
            vertical-align: top;
            font-size: 10px;
        }
        table.items tr:last-child td { border-bottom: 1px solid {{ $t['divider'] }}; }

        /* HSN summary */
        table.hsn-summary { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.hsn-summary th {
            background: {{ $t['accent_soft'] }};
            color: {{ $t['accent'] }};
            padding: 5px 6px;
            text-align: left;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
            border-top: 1px solid {{ $t['divider'] }};
            border-bottom: 1px solid {{ $t['divider'] }};
        }
        table.hsn-summary td {
            padding: 4px 6px;
            border-bottom: 1px solid {{ $t['table_row_border'] }};
            font-size: 9px;
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

        .aiw-text { font-style: italic; font-size: 10px; color: {{ $t['body_color'] }}; margin-top: 2px; line-height: 1.4; }

        .pay-box {
            margin-top: 10px;
            padding: 9px 11px;
            border: 1px solid {{ $t['divider'] }};
            border-radius: 3px;
        }
        .qr-box { text-align: center; padding: 3px; background: #fff; border: 1px solid {{ $t['divider'] }}; border-radius: 3px; }

        .note-card {
            margin-top: 6px;
            padding: 6px 9px;
            border-left: 2px solid {{ $t['divider'] }};
            font-size: 9px;
            line-height: 1.45;
        }
        .note-card .note-lbl { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: {{ $t['muted'] }}; margin-bottom: 2px; }

        .sig-wrap { margin-top: 12px; }
        .sig-box {
            display: inline-block;
            min-width: 160px;
            padding-top: 4px;
            border-top: 1px solid {{ $t['divider'] }};
            text-align: center;
            font-weight: bold;
            font-size: 9px;
        }

        .foot {
            margin-top: 8px;
            padding-top: 4px;
            border-top: 1px solid {{ $t['divider'] }};
            text-align: center;
            color: {{ $t['muted'] }};
            font-size: 8px;
            letter-spacing: 0.3px;
        }

        .no-break { page-break-inside: avoid; }
    </style>
</head>
<body>

<div class="page">

    {{-- ========== HEADER ========== --}}
    <table class="hero" style="width:100%;">
        <tr>
            <td style="vertical-align: top; width: 58%;">
                @if ($c->logo_path && file_exists(public_path('storage/' . $c->logo_path)))
                    <img src="{{ public_path('storage/' . $c->logo_path) }}" alt="{{ $c->name }} logo" style="max-height: 40px; max-width: 180px; margin-bottom: 4px;">
                @endif
                <div class="co-name">{{ $c->name }}</div>
                <div class="small muted" style="margin-top: 3px; line-height: 1.45;">
                    {!! nl2br(e(trim($c->address_line1 . ($c->address_line2 ? ', ' . $c->address_line2 : '') . "\n" . trim(($c->city ?? '') . ($c->city && $c->state?->name ? ', ' : '') . ($c->state?->name ?? '') . ($c->state?->gst_code ? ' · State ' . $c->state->gst_code : '') . ' ' . ($c->postal_code ?? '')) . "\n" . $c->country))) !!}
                </div>
                <div class="x-small muted" style="margin-top: 3px;">
                    @if ($c->phone){{ $c->phone }}@endif
                    @if ($c->phone && $c->email) · @endif
                    @if ($c->email){{ $c->email }}@endif
                    @if ($c->website) · {{ $c->website }}@endif
                </div>
                <div class="x-small" style="margin-top: 3px;">
                    @if ($c->gstin)
                        <span class="muted upper">GSTIN</span> <span class="mono bold">{{ $c->gstin }}</span>
                    @endif
                    @if ($c->gstin && $c->pan)
                        <span class="muted"> · </span>
                    @endif
                    @if ($c->pan)
                        <span class="muted upper">PAN</span> <span class="mono bold">{{ $c->pan }}</span>
                    @endif
                </div>
            </td>
            @php
                // Section 31(3)(c) + Rule 49: Bill of Supply when supplier is a
                // composition dealer OR when no GST is charged (exempt supply).
                $isBillOfSupply = $c->composition_dealer || (float) ($invoice->total_tax ?? 0) === 0.0;
                $documentTitle = $isBillOfSupply ? 'Bill of Supply' : 'Tax Invoice';
            @endphp
            <td style="vertical-align: top; text-align: right; width: 42%;">
                <div class="title">{{ $documentTitle }}</div>
                @if ($c->composition_dealer)
                    <div style="margin-top: 4px; padding: 4px 6px; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 3px; font-size: 8.5px; color: #78350f; line-height: 1.3;">
                        <strong>Composition taxable person, not eligible to collect tax on supplies.</strong>
                    </div>
                @elseif ($isBillOfSupply)
                    <div style="margin-top: 4px; font-size: 8.5px; color: #555;"><em>Issued under Section 31(3)(c) — exempt supply</em></div>
                @endif
                <div style="margin-top: 4px;">
                    <span class="copy-label">Original for Recipient</span>
                </div>
                <div style="margin-top: 6px;">
                    <span class="pill">#{{ $invoiceNumber }}</span>
                </div>
                <div class="x-small muted" style="margin-top: 6px; line-height: 1.6;">
                    <strong>Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}
                    @if ($invoice->due_date) · <strong>Due:</strong> {{ $invoice->due_date->format('d M Y') }}@endif
                    @if ($invoice->placeOfSupply?->name)
                        <br><strong>Place of supply:</strong> {{ $invoice->placeOfSupply->name }}@if ($invoice->placeOfSupply->gst_code) ({{ $invoice->placeOfSupply->gst_code }})@endif
                    @endif
                    @if ($invoice->reverse_charge)
                        <br><strong class="accent">Reverse charge applicable — Section 9(3)/9(4)</strong>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ========== BILL TO + TRANSPORTER ========== --}}
    @php
        $hasShipTo = $invoice->hasSeparateShipTo();
        $shipState = $invoice->shipToState ?? null;
        // Layout: one column when no transporter+no ship-to; two when exactly
        // one exists; three when both (bill / ship / transporter).
        $cols = 1 + ($hasShipTo ? 1 : 0) + ($hasTransporter ? 1 : 0);
        $colWidth = round(100 / $cols, 2) . '%';
    @endphp

    <table class="parties" style="width:100%;">
        <tr>
            <td style="vertical-align: top; width: {{ $colWidth }}; padding-right: 14px;">
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
            @if ($hasShipTo)
                <td style="vertical-align: top; width: {{ $colWidth }}; padding: 0 14px; border-left: 1px solid {{ $t['divider'] }};">
                    <div class="label">Ship to</div>
                    @if ($invoice->ship_to_name)
                        <div class="bold" style="font-size: 11px;">{{ $invoice->ship_to_name }}</div>
                    @endif
                    <div class="small muted" style="margin-top: 2px; line-height: 1.45;">
                        @if ($invoice->ship_to_address_line1){{ $invoice->ship_to_address_line1 }}@endif
                        @if ($invoice->ship_to_address_line2), {{ $invoice->ship_to_address_line2 }}@endif
                        @if ($invoice->ship_to_address_line1)<br>@endif
                        {{ trim(($invoice->ship_to_city ?? '') . ($invoice->ship_to_city && $shipState?->name ? ', ' : '') . ($shipState?->name ?? '') . ($shipState?->gst_code ? ' · State ' . $shipState->gst_code : '') . ' ' . ($invoice->ship_to_postal_code ?? '')) }}
                    </div>
                    @if ($invoice->ship_to_gstin)
                        <div class="x-small" style="margin-top: 3px;"><span class="muted upper">GSTIN</span> <span class="mono bold">{{ $invoice->ship_to_gstin }}</span></div>
                    @endif
                </td>
            @endif
            @if ($hasTransporter)
                <td style="vertical-align: top; width: {{ $colWidth }}; padding-left: 14px; border-left: 1px solid {{ $t['divider'] }};">
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
    @php
        // Only show the discount column if at least one line carries a discount —
        // keeps the invoice clean when not used.
        $hasDiscount = $invoice->items->sum(fn ($i) => (float) ($i->discount ?? 0)) > 0;
    @endphp
    <table class="items">
        <thead>
            <tr>
                <th style="width: 22px;">#</th>
                <th>Description of goods / services</th>
                <th style="width: 58px;">HSN/SAC</th>
                <th class="tr" style="width: 50px;">Qty</th>
                <th class="tr" style="width: 60px;">Rate</th>
                @if ($hasDiscount)
                    <th class="tr" style="width: 60px;">Discount</th>
                @endif
                <th class="tr" style="width: 38px;">GST%</th>
                <th class="tr" style="width: 80px;">Taxable {{ $currencySymbol }}</th>
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
                    @if ($hasDiscount)
                        <td class="tr mono">{{ (float) ($item->discount ?? 0) > 0 ? '-' . number_format((float) $item->discount, 2) : '—' }}</td>
                    @endif
                    <td class="tr">{{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%</td>
                    <td class="tr mono bold">{{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ========== HSN-wise summary (required for GSTR-1; only when multiple HSNs) ========== --}}
    @if ($showHsnSummary)
        <table class="hsn-summary no-break">
            <thead>
                <tr>
                    <th>HSN/SAC</th>
                    <th class="tr" style="width: 85px;">Taxable {{ $currencySymbol }}</th>
                    @if ($invoice->is_interstate)
                        <th class="tr" style="width: 85px;">IGST {{ $currencySymbol }}</th>
                    @else
                        <th class="tr" style="width: 85px;">CGST {{ $currencySymbol }}</th>
                        <th class="tr" style="width: 85px;">SGST {{ $currencySymbol }}</th>
                    @endif
                    <th class="tr" style="width: 85px;">Total tax {{ $currencySymbol }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hsnSummary as $row)
                    <tr>
                        <td class="mono">{{ $row['hsn'] }}</td>
                        <td class="tr mono">{{ number_format($row['taxable'], 2) }}</td>
                        @if ($invoice->is_interstate)
                            <td class="tr mono">{{ number_format($row['igst'], 2) }}</td>
                        @else
                            <td class="tr mono">{{ number_format($row['cgst'], 2) }}</td>
                            <td class="tr mono">{{ number_format($row['sgst'], 2) }}</td>
                        @endif
                        <td class="tr mono bold">{{ number_format($row['total_tax'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ========== AMOUNT IN WORDS + TOTALS ========== --}}
    <table style="width: 100%; margin-top: 10px;" class="no-break">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 14px;">
                <div class="label">Amount in words (INR)</div>
                <div class="aiw-text">{{ $amountInWords }}</div>

                @if ($invoice->reverse_charge)
                    {{-- Section 9(3)/9(4) CGST + Rule 46(p): mandatory declaration
                         that the recipient is liable for tax on RCM supply. --}}
                    <div style="margin-top: 8px; padding: 8px 10px; border: 1.5px solid #b45309; background: #fef3c7; border-radius: 3px;">
                        <div style="font-size: 9px; font-weight: bold; color: #78350f; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Tax payable on reverse charge basis</div>
                        <div style="font-size: 9.5px; color: #78350f;">
                            GST on this supply is to be paid by the recipient (you) directly to the
                            government under <strong>Section 9(3)/9(4) of the CGST Act 2017</strong>.
                            The supplier is not collecting tax on this invoice. The applicable
                            GST rate is shown in the line items for your reference.
                        </div>
                    </div>
                @endif

                @if ($c->declaration)
                    <div class="note-card" style="margin-top: 8px;">
                        <div class="note-lbl">Declaration</div>
                        <div style="font-style: italic;">{{ $c->declaration }}</div>
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <div class="totals-box">
                    <table class="totals">
                        <tr>
                            <td class="muted">Taxable value</td>
                            <td class="tr mono">{{ $currencySymbol }}{{ number_format((float) $invoice->subtotal, 2) }}</td>
                        </tr>
                        @if ($invoice->reverse_charge)
                            {{-- RCM: tax shown as "0.00 (RCM)" so recipient sees the line was
                                 considered but not collected. --}}
                            @if ($invoice->is_interstate)
                                <tr><td class="muted">IGST <span class="x-small">(payable by recipient)</span></td><td class="tr mono x-small">{{ $currencySymbol }}0.00</td></tr>
                            @else
                                <tr><td class="muted">CGST <span class="x-small">(payable by recipient)</span></td><td class="tr mono x-small">{{ $currencySymbol }}0.00</td></tr>
                                <tr><td class="muted">SGST <span class="x-small">(payable by recipient)</span></td><td class="tr mono x-small">{{ $currencySymbol }}0.00</td></tr>
                            @endif
                        @elseif ($invoice->is_interstate)
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
                            @php
                                $balance = (float) $invoice->balance;
                                $balanceLabel = $balance < 0 ? 'Advance (overpaid)' : 'Balance due';
                                $balanceStr = ($balance < 0 ? '-' : '') . $currencySymbol . number_format(abs($balance), 2);
                            @endphp
                            <tr>
                                <td class="muted" style="padding-top: 6px;">Paid</td>
                                <td class="tr mono muted" style="padding-top: 6px;">{{ $currencySymbol }}{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="bold" style="padding-top: 4px; border-top: 1px solid {{ $t['divider'] }};">{{ $balanceLabel }}</td>
                                <td class="tr mono bold accent" style="padding-top: 4px; border-top: 1px solid {{ $t['divider'] }};">{{ $balanceStr }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ========== PAYMENT DETAILS + UPI QR ========== --}}
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
                            <div class="x-small muted">Any UPI app</div>
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

    {{-- ========== SIGNATURE ========== --}}
    <div class="sig-wrap no-break">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: bottom;">
                    <div class="x-small muted">
                        <strong>E. &amp; O.E.</strong> (Errors &amp; Omissions Excepted)
                    </div>
                    <div class="x-small muted" style="margin-top: 1px;">
                        Subject to <strong>{{ $jurisdictionCity }}</strong> jurisdiction
                    </div>
                </td>
                <td style="vertical-align: top; text-align: right;">
                    @if ($c->signature_path && file_exists(public_path('storage/' . $c->signature_path)))
                        <img src="{{ public_path('storage/' . $c->signature_path) }}" alt="Authorised signature" style="max-height: 32px; margin-bottom: 2px;"><br>
                    @endif
                    <div class="sig-box">
                        For {{ $c->name }}
                        <div class="x-small muted" style="font-weight: normal; margin-top: 1px;">Authorised signatory</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="foot">This is a computer-generated invoice and does not require a physical signature.</div>
</div>

</body>
</html>
