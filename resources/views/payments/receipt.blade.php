@php
    $c = $payment->invoice->company;
    $cust = $payment->invoice->customer;
    $inv = $payment->invoice;
    $methodLabel = config('payment_methods.methods.' . $payment->method . '.label', ucfirst($payment->method));
    $amountInWords = \App\Support\NumberToWords::indianRupees((float) $payment->amount, 'INR');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt {{ $payment->receipt_number }}</title>
<style>
    @page { size: A4; margin: 16mm 14mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Helvetica, sans-serif; color: #111; font-size: 12px; line-height: 1.5; margin: 0; }
    .muted { color: #666; }
    .small { font-size: 10px; }
    .xs { font-size: 9px; }
    h1.title { font-size: 22px; font-weight: 700; letter-spacing: 2px; margin: 0; }
    h2.company { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
    .hr { border: 0; border-top: 2px solid #111; margin: 10px 0; }
    .row { display: table; width: 100%; table-layout: fixed; }
    .col { display: table-cell; vertical-align: top; }
    .right { text-align: right; }
    .center { text-align: center; }
    table.meta td { padding: 3px 0; }
    .amount-box {
        margin: 18px 0;
        padding: 14px 18px;
        border: 2px solid #111;
        border-radius: 4px;
    }
    .amount-big { font-size: 26px; font-weight: 700; }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv th, table.kv td { padding: 6px 8px; text-align: left; font-size: 12px; }
    table.kv tr { border-bottom: 1px solid #e5e5e5; }
    table.kv th { background: #f7f7f7; width: 32%; font-weight: 600; color: #333; }
    .footer { margin-top: 28px; display: table; width: 100%; }
    .footer .col { padding-top: 50px; }
    .sign { border-top: 1px solid #111; padding-top: 4px; font-size: 10px; }
    .stamp {
        margin-top: 18px;
        display: inline-block;
        padding: 6px 14px;
        border: 2px solid #047857;
        color: #047857;
        font-weight: 700;
        letter-spacing: 2px;
        transform: rotate(-6deg);
        border-radius: 4px;
    }
</style>
</head>
<body>

<div class="row">
    <div class="col" style="width: 65%;">
        @if ($c->logo_path && file_exists(public_path('storage/' . $c->logo_path)))
            <img src="{{ public_path('storage/' . $c->logo_path) }}" alt="logo" style="max-height: 60px; max-width: 220px;"><br>
        @endif
        <h2 class="company">{{ $c->name }}</h2>
        <div class="small muted">
            {{ $c->address_line1 }}@if ($c->address_line2), {{ $c->address_line2 }}@endif<br>
            {{ $c->city }}{{ $c->city && $c->state?->name ? ', ' : '' }}{{ $c->state?->name }} {{ $c->postal_code }}<br>
            {{ $c->country }}
            @if ($c->phone) &middot; {{ $c->phone }} @endif
            @if ($c->email) &middot; {{ $c->email }} @endif
        </div>
        <div class="small" style="margin-top: 2px;">
            @if ($c->gstin)<strong>GSTIN:</strong> {{ $c->gstin }}@endif
            @if ($c->gstin && $c->pan) &middot; @endif
            @if ($c->pan)<strong>PAN:</strong> {{ $c->pan }}@endif
        </div>
    </div>
    <div class="col right" style="width: 35%;">
        <h1 class="title">RECEIPT</h1>
        <table class="meta right" style="width: 100%; margin-top: 6px;">
            <tr><td class="muted small">Receipt no.</td><td><strong>{{ $payment->receipt_number }}</strong></td></tr>
            <tr><td class="muted small">Date</td><td>{{ $payment->received_at?->format('d M Y') }}</td></tr>
            <tr><td class="muted small">Against invoice</td><td>{{ $inv->invoice_number ?? 'Draft #' . $inv->id }}</td></tr>
        </table>
    </div>
</div>

<hr class="hr">

<div class="row">
    <div class="col" style="width: 55%;">
        <div class="xs muted" style="text-transform: uppercase; letter-spacing: 1px;">Received from</div>
        <div style="font-size: 14px; font-weight: 700; margin-top: 4px;">{{ $cust->name }}</div>
        <div class="small muted">
            @if ($cust->address_line1){{ $cust->address_line1 }}<br>@endif
            @if ($cust->address_line2){{ $cust->address_line2 }}<br>@endif
            {{ $cust->city }}{{ $cust->city && $cust->state?->name ? ', ' : '' }}{{ $cust->state?->name }} {{ $cust->postal_code }}
        </div>
        <div class="small" style="margin-top: 2px;">
            @if ($cust->gstin)<strong>GSTIN:</strong> {{ $cust->gstin }}@endif
            @if ($cust->phone) &middot; {{ $cust->phone }} @endif
        </div>
    </div>
    <div class="col" style="width: 45%;">
        <div class="xs muted" style="text-transform: uppercase; letter-spacing: 1px;">Invoice summary</div>
        <table class="kv" style="margin-top: 4px;">
            <tr><th>Invoice no.</th><td>{{ $inv->invoice_number ?? 'Draft' }}</td></tr>
            <tr><th>Invoice date</th><td>{{ $inv->invoice_date?->format('d M Y') }}</td></tr>
            <tr><th>Invoice total</th><td>&#8377; {{ number_format((float) $inv->grand_total, 2) }}</td></tr>
            <tr><th>Previously paid</th><td>&#8377; {{ number_format((float) $inv->paid_amount - (float) $payment->amount, 2) }}</td></tr>
            <tr><th>Balance after this payment</th><td><strong>&#8377; {{ number_format((float) $inv->balance, 2) }}</strong></td></tr>
        </table>
    </div>
</div>

<div class="amount-box">
    <div class="xs muted" style="text-transform: uppercase; letter-spacing: 1px;">Amount received</div>
    <div class="amount-big">&#8377; {{ number_format((float) $payment->amount, 2) }}</div>
    <div class="small muted" style="margin-top: 4px;">{{ $amountInWords }}</div>
</div>

<table class="kv">
    <tr>
        <th>Payment method</th>
        <td>{{ $methodLabel }}</td>
    </tr>
    @if ($payment->reference_number)
        <tr>
            <th>Reference / Txn ID</th>
            <td style="font-family: DejaVu Sans Mono, monospace;">{{ $payment->reference_number }}</td>
        </tr>
    @endif
    @if ($payment->notes)
        <tr>
            <th>Notes</th>
            <td>{{ $payment->notes }}</td>
        </tr>
    @endif
</table>

@if ((float) $inv->balance <= 0)
    <div class="center">
        <span class="stamp">PAID IN FULL</span>
    </div>
@endif

<div class="footer">
    <div class="col" style="width: 60%;">
        <div class="xs muted">Issued via {{ config('app.name', 'Apna Invoice') }} &middot; This is a computer-generated receipt.</div>
    </div>
    <div class="col right" style="width: 40%;">
        @if ($c->signature_path && file_exists(public_path('storage/' . $c->signature_path)))
            <img src="{{ public_path('storage/' . $c->signature_path) }}" alt="signature" style="max-height: 50px; max-width: 160px;"><br>
        @endif
        <div class="sign">Authorised signatory, {{ $c->name }}</div>
    </div>
</div>

</body>
</html>
