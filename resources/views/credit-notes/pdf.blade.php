@php
    $invoice = $creditNote->invoice;
    $c = $invoice->company;
    $cust = $invoice->customer;
    $print = $print ?? true;
    // Palette — black/white for print-friendly PDF.
    $accent = $print ? '#111111' : '#7c2d12';
    $divider = $print ? '#cccccc' : '#e5e7eb';
    $muted = '#555555';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Credit Note {{ $creditNote->credit_note_number }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; line-height: 1.45; }
        .page { padding: 10mm 11mm 8mm; border-top: 1px solid {{ $accent }}; }
        h1, h2, h3 { margin: 0; }
        .title { font-size: 22px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: {{ $accent }}; line-height: 1; }
        .co-name { font-weight: 700; font-size: 15px; }
        .small { font-size: 9px; }
        .x-small { font-size: 8.5px; }
        .muted { color: {{ $muted }}; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .bold { font-weight: bold; }
        .tr { text-align: right; }
        .tc { text-align: center; }
        .upper { text-transform: uppercase; letter-spacing: 0.8px; }
        .label {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: {{ $muted }};
            margin-bottom: 3px;
        }
        .pill {
            display: inline-block;
            padding: 3px 8px;
            border: 1px solid {{ $accent }};
            color: {{ $accent }};
            border-radius: 3px;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: 0.4px;
        }
        .hero { padding-bottom: 8px; border-bottom: 1px solid {{ $accent }}; }
        .section { margin-top: 12px; padding-top: 8px; border-top: 1px solid {{ $divider }}; }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 3px 6px; font-size: 10px; vertical-align: top; }
        .totals-box { padding: 10px 12px; border: 1px solid {{ $accent }}; border-radius: 3px; margin-top: 10px; }
        table.totals { width: 100%; border-collapse: collapse; }
        table.totals td { padding: 3px 0; font-size: 10px; }
        table.totals tr.total td {
            border-top: 1px solid {{ $accent }};
            font-weight: 700;
            font-size: 13px;
            padding-top: 7px;
            color: {{ $accent }};
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        table.totals tr.total td:last-child { font-size: 14px; text-transform: none; letter-spacing: 0; }
        .aiw-text { font-style: italic; font-size: 10px; margin-top: 2px; line-height: 1.4; }
        .note-card {
            margin-top: 8px;
            padding: 8px 10px;
            border-left: 2px solid {{ $divider }};
            font-size: 9.5px;
        }
        .sig-box {
            display: inline-block;
            min-width: 160px;
            padding-top: 4px;
            border-top: 1px solid {{ $divider }};
            text-align: center;
            font-weight: bold;
            font-size: 9px;
        }
        .foot {
            margin-top: 10px;
            padding-top: 4px;
            border-top: 1px solid {{ $divider }};
            text-align: center;
            color: {{ $muted }};
            font-size: 8px;
            letter-spacing: 0.3px;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- HEADER --}}
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
                <div class="x-small" style="margin-top: 3px;">
                    @if ($c->gstin)<span class="muted upper">GSTIN</span> <span class="mono bold">{{ $c->gstin }}</span>@endif
                    @if ($c->gstin && $c->pan)<span class="muted"> · </span>@endif
                    @if ($c->pan)<span class="muted upper">PAN</span> <span class="mono bold">{{ $c->pan }}</span>@endif
                </div>
            </td>
            <td style="vertical-align: top; text-align: right; width: 42%;">
                <div class="title">Credit Note</div>
                <div style="margin-top: 6px;">
                    <span class="pill">#{{ $creditNote->credit_note_number }}</span>
                </div>
                <div class="x-small muted" style="margin-top: 6px; line-height: 1.6;">
                    <strong>Date:</strong> {{ $creditNote->credit_note_date?->format('d M Y') }}<br>
                    <strong>Against invoice:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>Invoice date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}
                </div>
            </td>
        </tr>
    </table>

    {{-- RECIPIENT --}}
    <div class="section">
        <div class="label">Credit to</div>
        <div class="bold" style="font-size: 11px;">{{ $cust->name }}</div>
        <div class="small muted" style="margin-top: 2px; line-height: 1.45;">
            {{ $cust->address_line1 }}{{ $cust->address_line2 ? ', ' . $cust->address_line2 : '' }}<br>
            {{ trim(($cust->city ?? '') . ($cust->city && $cust->state?->name ? ', ' : '') . ($cust->state?->name ?? '') . ($cust->state?->gst_code ? ' · State ' . $cust->state->gst_code : '') . ' ' . ($cust->postal_code ?? '')) }}<br>
            {{ $cust->country }}
        </div>
        @if ($cust->gstin)
            <div class="x-small" style="margin-top: 3px;"><span class="muted upper">GSTIN</span> <span class="mono bold">{{ $cust->gstin }}</span></div>
        @endif
    </div>

    {{-- BREAKDOWN --}}
    <div class="section">
        <div class="label">Credit details</div>
        <table class="kv" style="border: 1px solid {{ $divider }}; border-radius: 3px;">
            <tr>
                <td class="x-small muted" style="width: 40%;">Reason</td>
                <td class="bold">{{ $creditNote->reasonLabel() }}</td>
            </tr>
            <tr>
                <td class="x-small muted">Taxable value (pro-rated)</td>
                <td class="mono">₹ {{ number_format((float) $creditNote->taxable_value, 2) }}</td>
            </tr>
            @if ($invoice->is_interstate)
                <tr>
                    <td class="x-small muted">IGST reduced</td>
                    <td class="mono">₹ {{ number_format((float) $creditNote->total_igst, 2) }}</td>
                </tr>
            @else
                <tr>
                    <td class="x-small muted">CGST reduced</td>
                    <td class="mono">₹ {{ number_format((float) $creditNote->total_cgst, 2) }}</td>
                </tr>
                <tr>
                    <td class="x-small muted">SGST reduced</td>
                    <td class="mono">₹ {{ number_format((float) $creditNote->total_sgst, 2) }}</td>
                </tr>
            @endif
        </table>
    </div>

    {{-- TOTAL --}}
    <div class="totals-box">
        <table class="totals">
            <tr class="total">
                <td>Total credit</td>
                <td class="tr mono">₹ {{ number_format((float) $creditNote->amount, 2) }}</td>
            </tr>
        </table>
        <div class="aiw-text muted"><strong>In words:</strong> {{ $amountInWords }}</div>
    </div>

    @if ($creditNote->notes)
        <div class="note-card">
            <div class="label" style="margin-bottom: 2px;">Notes</div>
            {{ $creditNote->notes }}
        </div>
    @endif

    {{-- SIGNATORY --}}
    <div class="section" style="text-align: right;">
        @if ($c->signature_path && file_exists(public_path('storage/' . $c->signature_path)))
            <img src="{{ public_path('storage/' . $c->signature_path) }}" alt="signature" style="max-height: 45px; max-width: 150px;"><br>
        @endif
        <div class="sig-box">for {{ $c->name }}<br><span class="muted">Authorised signatory</span></div>
    </div>

    <div class="foot">
        <strong>E. &amp; O.E.</strong> · Subject to {{ $c->city ?: 'India' }} jurisdiction · Generated by {{ config('app.name') }}
    </div>

</div>
</body>
</html>
