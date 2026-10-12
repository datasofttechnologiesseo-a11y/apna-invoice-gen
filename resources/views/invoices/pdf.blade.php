@php
    // Tiny inline alias so the templates below stay readable: $inr(1234567) → "12,34,567"
    // (whole amounts without ".00", real paise kept; see inr()).
    $inr = fn ($n, $d = 2) => inr($n, $d);

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
    // The recipient as issued (snapshot), or the live customer on a draft.
    $cust = $invoice->billedCustomer();
    $currencySymbol = '₹';
    $invoiceNumber = $invoice->isDraft()
        ? 'DRAFT · ' . ($invoice->company->nextInvoiceNumber() ?? 'preview')
        : $invoice->invoice_number;

    $hasTransporter = $invoice->transporter_name || $invoice->vehicle_number || $invoice->eway_bill_number || $invoice->transporter_id || $invoice->transport_mode;

    $payableAmount = (float) ($invoice->balance ?? $invoice->grand_total);
    // A cancelled invoice is not owed: no QR and no "pay" wording on it.
    $isCancelled = $invoice->isCancelled();
    // UPI is for payers in India: no pay QR on an export invoice.
    $showUpiQr = $c->upi_id && $payableAmount > 0 && ! $isCancelled && ! $invoice->isExport();
    $qrDataUri = $showUpiQr
        ? \App\Support\UpiQr::svgDataUri($c->upi_id, $c->name, $payableAmount, $invoice->invoice_number ?: 'Invoice', 140)
        : null;

    // HSN-wise summary (Rule 46 best practice - required on GSTR-1 anyway)
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
    // Only render HSN summary when invoice has enough complexity to benefit - 2-item invoices don't need it
    $showHsnSummary = $hsnSummary->count() > 1 && collect($invoice->items ?? [])->count() >= 4;

    $jurisdictionCity = $c->city ?: 'India';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->documentTitle() }} {{ $invoice->invoice_number }}</title>
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

        /* Column HEADERS must adopt the same alignment as their cells. Without
           this, `table.items th { text-align: left }` out-specifies the .tr/.tc
           utility classes, so the numeric headers (Qty, Rate, GST%, Taxable) sat
           at the left of their columns while the numbers were right-aligned -
           making the data look like it was under the wrong heading. */
        table.items th.tr, table.hsn-summary th.tr { text-align: right; }
        table.items th.tc, table.hsn-summary th.tc { text-align: center; }

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
            margin-top: 8px;
            padding: 7px 9px;
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

        /* Cancelled: a stamp under the title and a faint mark across the page,
           so a printed copy can never pass for a live bill. */
        .cancel-stamp {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 10px;
            border: 2px solid {{ $print ? '#111111' : '#b91c1c' }};
            color: {{ $print ? '#111111' : '#b91c1c' }};
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-align: center;
        }
        .cancel-stamp .when { display: block; font-size: 8px; letter-spacing: 0.3px; text-transform: none; font-weight: normal; margin-top: 1px; }
        .cancel-mark {
            position: fixed;
            top: 40%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 96px;
            font-weight: bold;
            letter-spacing: 8px;
            color: {{ $print ? '#999999' : '#b91c1c' }};
            opacity: 0.13;
            transform: rotate(-28deg);
        }
    </style>
</head>
<body>

@php
    // CGST Rule 48(1): goods supplies require 3 copies (Original/Recipient,
    // Duplicate/Transporter, Triplicate/Supplier); services need only 2.
    // The controller passes an explicit $copyLabels list (honouring a caller's
    // copy selection). Fall back to the count-based default for any legacy
    // caller that only passes $copies (or neither - e.g. the email attachment).
    $copyLabels = $copyLabels ?? $invoice->copyLabels($copies ?? 1);
@endphp

@if ($isCancelled)
    <div class="cancel-mark">CANCELLED</div>
@endif

@foreach ($copyLabels as $copyIndex => $copyLabel)
<div class="page"@if ($copyIndex > 0) style="page-break-before: always;"@endif>

    {{-- ========== HEADER ========== --}}
    <table class="hero" style="width:100%;">
        <tr>
            <td style="vertical-align: top; width: 58%;">
                @php
                    // A wide wordmark sits above the business name; a square or
                    // tall emblem sits beside it, as on a printed letterhead.
                    $logo = \App\Support\BrandImage::fit($c->logo_path, 200, 52);
                    $logoBeside = $logo && $logo['ratio'] < 2.2;
                    if ($logoBeside) {
                        $logo = \App\Support\BrandImage::fit($c->logo_path, 110, 64);
                    }
                @endphp
                @if ($logo && ! $logoBeside)
                    <img src="{{ $logo['file'] }}" alt="{{ $c->name }} logo" width="{{ $logo['w'] }}" height="{{ $logo['h'] }}" style="width: {{ $logo['w'] }}px; height: {{ $logo['h'] }}px; margin-bottom: 6px;">
                @endif
                <table style="width: 100%;"><tr>
                @if ($logoBeside)
                    <td style="vertical-align: top; width: {{ $logo['w'] }}px; padding-right: 10px;">
                        <img src="{{ $logo['file'] }}" alt="{{ $c->name }} logo" width="{{ $logo['w'] }}" height="{{ $logo['h'] }}" style="width: {{ $logo['w'] }}px; height: {{ $logo['h'] }}px;">
                    </td>
                @endif
                <td style="vertical-align: top;">
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
                </tr></table>
            </td>
            @php
                // 3-way title resolved by the model - consistent across show
                // page, PDF, print view and email subject. Rules:
                //   • No GSTIN → "Invoice" (unregistered, no GST law applies)
                //   • Composition dealer → "Bill of Supply" (CGST Rule 49)
                //   • Otherwise → "Tax Invoice" (CGST Rule 46)
                $documentTitle = $invoice->documentTitle();
            @endphp
            <td style="vertical-align: top; text-align: right; width: 42%;">
                <div class="title">{{ $documentTitle }}</div>
                @if ($c->composition_dealer)
                    {{-- Mandatory under Section 31(3)(c) + Rule 49 - must
                         appear on every Bill of Supply issued by a composition
                         dealer, alongside the title. --}}
                    <div style="margin-top: 4px; padding: 4px 6px; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 3px; font-size: 8.5px; color: #78350f; line-height: 1.3;">
                        <strong>Composition taxable person, not eligible to collect tax on supplies.</strong>
                    </div>
                @endif
                @if ($isCancelled)
                    <div>
                        <span class="cancel-stamp">Cancelled<span class="when">on {{ $invoice->cancelled_at?->format('d M Y') }}{{ $invoice->cancellation_reason ? ' · ' . \Illuminate\Support\Str::limit($invoice->cancellation_reason, 60) : '' }}</span></span>
                    </div>
                @endif
                <div style="margin-top: 4px;">
                    <span class="copy-label">{{ $copyLabel }}</span>
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
                    {{-- Rule 46(p): every tax invoice states whether tax is payable on
                         reverse charge - "No" is a required statement, not an omission. --}}
                    @if ($invoice->reverse_charge)
                        <br><strong class="accent">Reverse charge: Yes - Section 9(3)/9(4)</strong>
                    @else
                        <br><strong>Reverse charge:</strong> No
                    @endif
                    @if ($invoice->isExport() && ($invoice->export_port_code || $invoice->shipping_bill_number))
                        <br><strong>Port:</strong> {{ $invoice->export_port_code ?: '-' }}
                        · <strong>Shipping bill:</strong> {{ $invoice->shipping_bill_number ?: '-' }}@if ($invoice->shipping_bill_date) dt. {{ $invoice->shipping_bill_date->format('d M Y') }}@endif
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
                @php
                    // A buyer outside India has no Indian state: print city and
                    // country only, not "Foreign Country · State 96". Empty
                    // lines are skipped so they do not print as gaps.
                    $custForeign = in_array($cust->state?->gst_code, ['96', '97'], true)
                        || ($cust->country && strcasecmp(trim($cust->country), 'India') !== 0);
                    $custStreet = trim(($cust->address_line1 ?? '') . ($cust->address_line2 ? ', ' . $cust->address_line2 : ''), ' ,');
                    $custPlace = $custForeign
                        ? trim(($cust->city ?? '') . ' ' . ($cust->postal_code ?? ''))
                        : trim(($cust->city ?? '') . ($cust->city && $cust->state?->name ? ', ' : '') . ($cust->state?->name ?? '') . ($cust->state?->gst_code ? ' · State ' . $cust->state->gst_code : '') . ' ' . ($cust->postal_code ?? ''));
                @endphp
                <div class="small muted" style="margin-top: 2px; line-height: 1.45;">
                    @if ($custStreet !== ''){{ $custStreet }}<br>@endif
                    @if ($custPlace !== ''){{ $custPlace }}<br>@endif
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
        // Only show the discount column if at least one line carries a discount -
        // keeps the invoice clean when not used.
        $hasDiscount = $invoice->items->sum(fn ($i) => (float) ($i->discount ?? 0)) > 0;
    @endphp
    <table class="items">
        <thead>
            <tr>
                <th style="width: 14px;">#</th>
                <th>Description</th>
                <th style="width: 46px;">HSN/SAC</th>
                <th class="tr" style="width: 40px;">Qty</th>
                <th class="tr" style="width: 48px;">Rate</th>
                @if ($hasDiscount)
                    <th class="tr" style="width: 44px; white-space: nowrap;">Discount</th>
                @endif
                <th class="tr" style="width: 56px; white-space: nowrap;">Taxable {{ $currencySymbol }}</th>
                {{-- Rate and tax side by side, so every line is one row: stacked
                     in one column they doubled each row's height and a long
                     invoice ran onto more pages. --}}
                <th class="tr" style="width: 30px; white-space: nowrap;">{{ $invoice->is_interstate ? 'IGST' : 'GST' }} %</th>
                <th class="tr" style="width: 50px; white-space: nowrap;">{{ $invoice->is_interstate ? 'IGST' : 'GST' }} {{ $currencySymbol }}</th>
                <th class="tr" style="width: 58px; white-space: nowrap;">Amount {{ $currencySymbol }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $idx => $item)
                @php
                    // Resolve what to show as the line identifier vs supplementary text.
                    //   • product linked + custom description differs → show both (product
                    //     name as bold heading, description as smaller gray subline)
                    //   • product linked + description matches product name (or is blank/junk)
                    //     → show product name only (avoid duplication)
                    //   • no product linked → show description as the identifier
                    $productName = $item->product?->name;
                    $descRaw = trim((string) $item->description);
                    $descIsJunk = $descRaw === '' || preg_match('/^0+(\.0+)?$/', $descRaw) === 1;
                    $primary = $productName ?: ($descIsJunk ? '' : $descRaw);
                    $secondary = ($productName && ! $descIsJunk && strcasecmp($descRaw, $productName) !== 0) ? $descRaw : null;
                @endphp
                <tr>
                    <td class="muted">{{ $idx + 1 }}</td>
                    <td>
                        <div style="font-weight: bold;">{{ $primary }}</div>
                        @if ($secondary)
                            <div style="font-size: 9px; color: #555; margin-top: 2px;">{{ $secondary }}</div>
                        @endif
                    </td>
                    <td class="mono x-small">{{ $item->hsn_sac }}</td>
                    <td class="tr mono">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="tr mono">{{ $inr($item->rate) }}</td>
                    @if ($hasDiscount)
                        <td class="tr mono">{{ (float) ($item->discount ?? 0) > 0 ? '-' . $inr($item->discount) : '-' }}</td>
                    @endif
                    @php $lineTax = (float) $item->cgst_amount + (float) $item->sgst_amount + (float) $item->igst_amount; @endphp
                    <td class="tr mono">{{ $inr($item->amount) }}</td>
                    <td class="tr mono">{{ rtrim(rtrim(inr($item->gst_rate), '0'), '.') }}{{ $invoice->reverse_charge ? ' RCM' : '' }}</td>
                    <td class="tr mono">{{ $invoice->reverse_charge ? '-' : $inr($lineTax) }}</td>
                    <td class="tr mono bold">{{ $inr($item->total) }}</td>
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
                        <th class="tr" style="width: 85px;">{{ $invoice->stateTaxLabel() }} {{ $currencySymbol }}</th>
                    @endif
                    <th class="tr" style="width: 85px;">Total tax {{ $currencySymbol }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hsnSummary as $row)
                    <tr>
                        <td class="mono">{{ $row['hsn'] }}</td>
                        <td class="tr mono">{{ $inr($row['taxable']) }}</td>
                        @if ($invoice->is_interstate)
                            <td class="tr mono">{{ $inr($row['igst']) }}</td>
                        @else
                            <td class="tr mono">{{ $inr($row['cgst']) }}</td>
                            <td class="tr mono">{{ $inr($row['sgst']) }}</td>
                        @endif
                        <td class="tr mono bold">{{ $inr($row['total_tax']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ========== SUMMARY ==========
         Two columns, the way most Indian tax invoices are laid out: what the
         buyer needs in order to pay (amount in words, bank, UPI) beside the
         totals, and the terms beside the signature. Stacked one under the
         other they left the space beside the totals empty and pushed an
         ordinary one-copy invoice onto a second page. --}}
    <table style="width: 100%; margin-top: 8px;" class="no-break">
        <tr>
            <td style="width: 57%; vertical-align: top; padding-right: 12px;">
                <div class="label">Amount in words (INR)</div>
                <div class="aiw-text">{{ $amountInWords }}</div>

                @if ($invoice->zeroRatedEndorsement())
                    {{-- Rule 46 proviso: an export / SEZ invoice carries this endorsement. --}}
                    <div style="margin-top: 6px; padding: 6px 9px; border: 1.5px solid #167c88; background: #ecfeff; border-radius: 3px;">
                        <div style="font-size: 9.5px; font-weight: bold; color: #134e4a; letter-spacing: 0.3px;">{{ $invoice->zeroRatedEndorsement() }}</div>
                        @if ($invoice->isUnderLut() && $invoice->company?->lut_number)
                            <div style="font-size: 9px; color: #134e4a; margin-top: 2px;">LUT / Bond No.: {{ $invoice->company->lut_number }}</div>
                        @endif
                    </div>
                @endif

                @if ($invoice->reverse_charge)
                    {{-- Section 9(3)/9(4) CGST + Rule 46(p): mandatory declaration
                         that the recipient is liable for tax on RCM supply. --}}
                    <div style="margin-top: 6px; padding: 6px 9px; border: 1.5px solid #b45309; background: #fef3c7; border-radius: 3px;">
                        <div style="font-size: 9px; font-weight: bold; color: #78350f; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Tax payable on reverse charge basis</div>
                        <div style="font-size: 9px; color: #78350f;">
                            GST on this supply is to be paid by the recipient directly to the
                            government under <strong>Section 9(3)/9(4) of the CGST Act 2017</strong>.
                            The supplier is not collecting tax on this invoice; the rate is shown
                            on each line for reference.
                        </div>
                    </div>
                @endif

                @if ($c->declaration)
                    <div class="note-card" style="margin-top: 6px;">
                        <div class="note-lbl">Declaration</div>
                        <div style="font-style: italic;">{{ $c->declaration }}</div>
                    </div>
                @endif

                {{-- Payment details (and the UPI QR while something is owed) --}}
                @if ($c->bank_name || $c->bank_account_number || $c->upi_id)
                    <div class="pay-box">
                        <div class="label">{{ $isCancelled ? 'Bank details' : 'Payment details' }}</div>
                        <table style="width: 100%;">
                            <tr>
                                <td style="vertical-align: top;">
                                    <table style="width: 100%; font-size: 9px; line-height: 1.5;">
                                        @if ($c->bank_account_name)
                                            <tr>
                                                <td class="x-small muted upper" style="width: 52px; padding-right: 6px;">Payee</td>
                                                <td class="bold">{{ $c->bank_account_name }}</td>
                                            </tr>
                                        @endif
                                        @if ($c->bank_name)
                                            <tr>
                                                <td class="x-small muted upper" style="width: 52px; padding-right: 6px;">Bank</td>
                                                <td class="bold">{{ $c->bank_name }}@if ($c->bank_branch), {{ $c->bank_branch }}@endif</td>
                                            </tr>
                                        @endif
                                        @if ($c->bank_account_number)
                                            <tr>
                                                <td class="x-small muted upper" style="padding-right: 6px;">A/c no.</td>
                                                <td class="mono bold">{{ $c->bank_account_number }}@if ($c->bankAccountTypeLabel())<span class="x-small muted"> ({{ $c->bankAccountTypeLabel() }})</span>@endif</td>
                                            </tr>
                                        @endif
                                        @if ($c->bank_ifsc)
                                            <tr>
                                                <td class="x-small muted upper" style="padding-right: 6px;">IFSC</td>
                                                <td class="mono bold">{{ $c->bank_ifsc }}</td>
                                            </tr>
                                        @endif
                                        @if ($c->upi_id)
                                            <tr>
                                                <td class="x-small muted upper" style="padding-right: 6px;">UPI</td>
                                                <td class="mono bold accent">{{ $c->upi_id }}</td>
                                            </tr>
                                        @endif
                                    </table>
                                </td>
                                @if ($showUpiQr)
                                    <td style="vertical-align: top; width: 78px; text-align: center; padding-left: 8px;">
                                        <div class="qr-box">
                                            <img src="{{ $qrDataUri }}" alt="UPI payment QR" style="width: 60px; height: 60px; display: block; margin: 0 auto;">
                                        </div>
                                        <div class="x-small bold accent" style="margin-top: 2px;">Scan & pay</div>
                                        <div class="x-small muted">{{ $currencySymbol }}{{ $inr($payableAmount) }}</div>
                                    </td>
                                @endif
                            </tr>
                        </table>
                    </div>
                @endif
            </td>
            <td style="width: 43%; vertical-align: top;">
                <div class="totals-box">
                    <table class="totals">
                        <tr>
                            <td class="muted">Taxable value</td>
                            <td class="tr mono">{{ $currencySymbol }}{{ $inr($invoice->subtotal) }}</td>
                        </tr>
                        @if ($invoice->reverse_charge)
                            {{-- RCM: tax shown as "0 (payable by recipient)" so the recipient sees the line was
                                 considered but not collected. --}}
                            @if ($invoice->is_interstate)
                                <tr><td class="muted">IGST <span class="x-small">(payable by recipient)</span></td><td class="tr mono x-small">{{ $currencySymbol }}0</td></tr>
                            @else
                                <tr><td class="muted">CGST <span class="x-small">(payable by recipient)</span></td><td class="tr mono x-small">{{ $currencySymbol }}0</td></tr>
                                <tr><td class="muted">{{ $invoice->stateTaxLabel() }} <span class="x-small">(payable by recipient)</span></td><td class="tr mono x-small">{{ $currencySymbol }}0</td></tr>
                            @endif
                        @elseif ($invoice->is_interstate)
                            <tr><td class="muted">IGST</td><td class="tr mono">{{ $currencySymbol }}{{ $inr($invoice->total_igst) }}</td></tr>
                        @else
                            <tr><td class="muted">CGST</td><td class="tr mono">{{ $currencySymbol }}{{ $inr($invoice->total_cgst) }}</td></tr>
                            <tr><td class="muted">{{ $invoice->stateTaxLabel() }}</td><td class="tr mono">{{ $currencySymbol }}{{ $inr($invoice->total_sgst) }}</td></tr>
                        @endif
                        @if ((float) $invoice->round_off != 0)
                            <tr><td class="muted x-small">Round off</td><td class="tr mono x-small">{{ $inr($invoice->round_off) }}</td></tr>
                        @endif
                        <tr class="total">
                            <td>Grand Total</td>
                            <td class="tr mono">{{ $currencySymbol }}{{ $inr($invoice->grand_total) }}</td>
                        </tr>
                        @if ((float) $invoice->paid_amount > 0 || (float) $invoice->credited_amount > 0)
                            @php
                                $balance = (float) $invoice->balance;
                                $balanceLabel = $balance < 0
                                    ? ((float) $invoice->credited_amount > 0 ? 'Refund due to customer' : 'Advance (overpaid)')
                                    : 'Balance due';
                                $balanceStr = ($balance < 0 ? '-' : '') . $currencySymbol . $inr(abs($balance));
                            @endphp
                            {{-- Every amount that moves the balance is listed, so the rows add up. --}}
                            @if ((float) $invoice->credited_amount > 0)
                                <tr>
                                    <td class="muted" style="padding-top: 5px;">Less credit notes</td>
                                    <td class="tr mono muted" style="padding-top: 5px;">{{ $currencySymbol }}{{ $inr($invoice->credited_amount) }}</td>
                                </tr>
                            @endif
                            @if ((float) $invoice->paid_amount > 0)
                                <tr>
                                    <td class="muted" style="padding-top: {{ (float) $invoice->credited_amount > 0 ? '2' : '5' }}px;">Paid</td>
                                    <td class="tr mono muted" style="padding-top: {{ (float) $invoice->credited_amount > 0 ? '2' : '5' }}px;">{{ $currencySymbol }}{{ $inr($invoice->paid_amount) }}</td>
                                </tr>
                            @endif
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

    {{-- ========== TERMS + SIGNATURE ========== --}}
    <table style="width: 100%; margin-top: 8px;" class="no-break">
        <tr>
            <td style="width: 57%; vertical-align: bottom; padding-right: 12px;">
                @if ($invoice->terms)
                    <div class="note-card" style="margin-top: 0;">
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
                <div class="x-small muted" style="margin-top: 6px;">
                    <strong>E. &amp; O.E.</strong> (Errors &amp; Omissions Excepted) · Subject to <strong>{{ $jurisdictionCity }}</strong> jurisdiction
                </div>
            </td>
            <td style="width: 43%; vertical-align: bottom; text-align: right;">
                {{-- "For <business>", the signature (or room to sign), then the
                     signatory line: the usual order on an Indian tax invoice. --}}
                @php $sig = \App\Support\BrandImage::fit($c->signature_path, 150, 46); @endphp
                <table style="width: 190px; margin-left: auto;">
                    <tr><td style="text-align: center; font-size: 9px; font-weight: bold;">For {{ $c->name }}</td></tr>
                    <tr><td style="height: 50px; vertical-align: bottom; text-align: center;">
                        @if ($sig)
                            <img src="{{ $sig['file'] }}" alt="Authorised signature" width="{{ $sig['w'] }}" height="{{ $sig['h'] }}" style="width: {{ $sig['w'] }}px; height: {{ $sig['h'] }}px;">
                        @endif
                    </td></tr>
                    <tr><td class="x-small muted" style="text-align: center; padding-top: 3px; border-top: 1px solid {{ $t['divider'] }};">Authorised signatory</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- One footer line. The byline is the free-tier trade (every invoice
         reaches another business owner); dompdf renders the link as a real
         clickable annotation, attributable via UTM. --}}
    <div class="foot">This is a computer-generated invoice.@if (! $invoice->company->signature_path) It does not require a physical signature.@endif
        <a href="https://apnainvoice.com/?utm_source=invoice_pdf&utm_medium=pdf&utm_campaign=byline" style="color: #9ca3af; text-decoration: none;">· Made free with Apna Invoice · apnainvoice.com</a></div>
</div>
@endforeach

</body>
</html>
