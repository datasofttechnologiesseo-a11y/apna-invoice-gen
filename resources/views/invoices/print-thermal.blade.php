{{--
    Thermal 80mm receipt layout.

    Designed for 80mm thermal printers (also works on 58mm - most browsers
    scale down). Used at retail counters (kirana, restaurants, salons) where
    A4 is overkill and the customer just needs a slip.

    Layout choices:
      - @page size: 80mm auto    → tells the printer driver the paper size
      - 76mm content width        → 2mm margin each side (most thermal heads bleed)
      - Mono font                 → aligned columns without a table engine
      - Dashed dividers           → universal "receipt" cue, parses on any printer
      - Auto-print on load        → kirana workflow: button → printer → done
      - No coloured fills         → thermal printers are monochrome anyway

    GST detail is COMPACT: subtotal + CGST+SGST (or IGST) + grand total only.
    Full HSN/SAC/rate breakdown stays on the A4 PDF for the customer/accountant
    record - the receipt is for "here, sir, your bill" delivery.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->documentTitle() }} {{ $invoice->invoice_number ?: '#' . $invoice->id }}</title>
    <style>
        /* Tell the printer this is 80mm paper. `auto` height lets the printer
           tear off at the actual content end (variable receipt length). */
        @page {
            size: 80mm auto;
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            /* Without this the browser strips the dashed dividers and the
               DRAFT box when printing. */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* 80mm paper, but the print head only reaches ~72mm. border-box makes
           the declared width the real outer width: 76mm outer - 2mm padding
           each side = 72mm of content, sitting inside a 2mm safe margin. */
        .receipt {
            box-sizing: border-box;
            width: 76mm;
            padding: 2mm 2mm 6mm 2mm;
            margin: 0 auto;
            font-family: 'Courier New', 'Liberation Mono', monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
        }
        .receipt * { box-sizing: border-box; }

        .center { text-align: center; }
        .right  { text-align: right; }
        .bold   { font-weight: 700; }
        /* Thermal heads print one bit per dot: any grey is dithered and comes
           out faint. Every class here is pure black, and nothing is smaller
           than 10px (~1.25mm at 203dpi), which is the floor for reliable
           reading on receipt paper. */
        .small  { font-size: 11px; color: #000; }
        .xs     { font-size: 10px; color: #000; }
        .biz    { font-size: 15px; font-weight: 700; letter-spacing: 0.3px; }
        .docnum { font-size: 13px; font-weight: 700; }
        .total  { font-size: 15px; font-weight: 700; }

        hr.dash {
            border: 0;
            border-top: 1px dashed #000;
            margin: 4px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 4px;
        }

        .row > :last-child { white-space: nowrap; }

        /* Item rows: name top line, qty×rate + amount bottom line.
           Two-line layout avoids ugly mid-word wraps on a narrow paper. */
        .item-name {
            word-break: break-word;
        }

        /* Print preview chrome - hidden when actually printing. */
        .no-print { display: none; }
        @media screen {
            body { background: #f3f4f6; padding: 16px; }
            /* Zoom the on-screen proof only. The printed output is unchanged:
               this rule never applies inside @media print. */
            .receipt {
                background: #fff;
                box-shadow: 0 1px 6px rgba(0,0,0,0.14);
                transform: scale(1.5);
                transform-origin: top center;
                border-radius: 2px;
            }
            .no-print {
                display: block;
                max-width: 80mm;
                margin: 0 auto 12px;
                text-align: center;
                font-family: system-ui, sans-serif;
            }
            .no-print button, .no-print a {
                display: inline-block;
                padding: 6px 12px;
                font-size: 12px;
                border-radius: 4px;
                text-decoration: none;
                cursor: pointer;
            }
            .no-print button {
                background: #0f766e;
                color: #fff;
                border: 0;
            }
            .no-print a {
                background: #e5e7eb;
                color: #111;
                margin-left: 6px;
            }
        }

        @media print {
            /* Belt and braces: no zoom, no shadow, no stray page margin. */
            body { background: #fff; padding: 0; }
            .receipt {
                transform: none;
                box-shadow: none;
                margin: 0;
                width: 76mm;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <div style="font-size:11px; color:#4b5563; margin-bottom:6px;">
        Preview shown enlarged - prints at true 80&nbsp;mm width.
    </div>
    <button onclick="window.print()">Print receipt</button>
    <a href="{{ route('invoices.show', $invoice) }}">Back</a>
    <a href="{{ route('invoices.print', $invoice) }}">A4 view</a>
</div>

@php
    $c = $invoice->company;
    $cust = $invoice->customer;
    $reverseCharge = (bool) $invoice->reverse_charge;
@endphp

<div class="receipt">
    {{-- HEADER: business name + minimal address. Thermal users don't want a logo
         block (slow to print, wastes paper) - text-only is intentional. --}}
    <div class="center biz">{{ $c->name }}</div>
    @if ($c->address_line1 || $c->city)
        <div class="center small">
            {{ trim(($c->address_line1 ?? '') . ($c->city ? ', ' . $c->city : '')) }}
            @if ($c->state?->name) ({{ $c->state->name }}) @endif
        </div>
    @endif
    @if ($c->gstin)
        <div class="center small">GSTIN: {{ $c->gstin }}</div>
    @endif
    @if ($c->phone)
        <div class="center small">Ph: {{ $c->phone }}</div>
    @endif

    <hr class="dash">

    <div class="center docnum">{{ strtoupper($invoice->documentTitle()) }}</div>
    <div class="row small">
        <span>No: <span class="bold">{{ $invoice->invoice_number ?: 'DRAFT #' . $invoice->id }}</span></span>
        <span>{{ $invoice->invoice_date?->format('d/m/Y') }}</span>
    </div>
    @if ($invoice->due_date && (float) $invoice->balance > 0)
        <div class="small">Due: {{ $invoice->due_date->format('d/m/Y') }}</div>
    @endif

    <hr class="dash">

    {{-- CUSTOMER (only if useful) --}}
    @if ($cust)
        <div class="bold">{{ $cust->name }}</div>
        @if ($cust->phone)
            <div class="small">{{ $cust->phone }}</div>
        @endif
        @if ($cust->gstin)
            <div class="small">GSTIN: {{ $cust->gstin }}</div>
        @endif
        @if ($cust->state?->name)
            <div class="xs">State: {{ $cust->state->name }}
                @if ($cust->state?->gst_code) ({{ $cust->state->gst_code }}) @endif
            </div>
        @endif
        <hr class="dash">
    @endif

    {{-- ITEMS --}}
    @foreach ($invoice->items as $item)
        @php
            $qty = (float) $item->quantity;
            $rate = (float) $item->rate;
            $disc = (float) ($item->discount ?? 0);
            $gst = (float) ($item->gst_rate ?? 0);
            $amt = (float) $item->amount;
            $qtyStr = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
        @endphp
        @php
            // Same display fallback as the A4 PDF - render product name if the
            // saved description is blank or just "0" from a legacy save.
            $desc = trim((string) $item->description);
            $looksLikeJunk = $desc === '' || preg_match('/^0+(\.0+)?$/', $desc) === 1;
            $shown = $looksLikeJunk && $item->product?->name ? $item->product->name : $item->description;
        @endphp
        <div class="item-name">{{ $shown }}</div>
        <div class="row xs">
            <span>
                {{ $qtyStr }}{{ $item->unit ? ' ' . $item->unit : '' }}
                &times; ₹{{ number_format($rate, 2) }}
                @if ($disc > 0) − ₹{{ number_format($disc, 2) }} @endif
                @if (! $reverseCharge && $gst > 0) @ {{ (float) $gst }}% GST @endif
            </span>
            <span class="bold" style="color:#000; font-size:12px;">₹{{ number_format($amt, 2) }}</span>
        </div>
    @endforeach

    <hr class="dash">

    {{-- TOTALS - order matters: subtotal → tax breakdown → grand total --}}
    <div class="row">
        <span>Subtotal</span>
        <span>₹{{ number_format((float) $invoice->subtotal, 2) }}</span>
    </div>

    @if ($reverseCharge)
        <div class="xs" style="margin-top:2px;">Reverse charge - GST payable by recipient (Sec 9(3)/9(4))</div>
    @else
        @if ($invoice->is_interstate)
            <div class="row small">
                <span>IGST</span>
                <span>₹{{ number_format((float) $invoice->total_igst, 2) }}</span>
            </div>
        @else
            <div class="row small">
                <span>CGST</span>
                <span>₹{{ number_format((float) $invoice->total_cgst, 2) }}</span>
            </div>
            <div class="row small">
                <span>SGST</span>
                <span>₹{{ number_format((float) $invoice->total_sgst, 2) }}</span>
            </div>
        @endif
    @endif

    @if ((float) ($invoice->round_off ?? 0) !== 0.0)
        <div class="row small">
            <span>Round-off</span>
            <span>₹{{ number_format((float) $invoice->round_off, 2) }}</span>
        </div>
    @endif

    <hr class="dash">

    <div class="row total">
        <span>TOTAL</span>
        <span>₹{{ number_format((float) $invoice->grand_total, 2) }}</span>
    </div>

    @if ((float) $invoice->paid_amount > 0)
        <div class="row small">
            <span>Paid</span>
            <span>₹{{ number_format((float) $invoice->paid_amount, 2) }}</span>
        </div>
        @if ((float) $invoice->balance > 0)
            <div class="row bold">
                <span>Balance</span>
                <span>₹{{ number_format((float) $invoice->balance, 2) }}</span>
            </div>
        @endif
    @endif

    <hr class="dash">

    <div class="xs center">{{ $amountInWords }}</div>

    {{-- UPI quick-pay hint - kirana receipts often show this. --}}
    @if ($c->upi_id)
        <hr class="dash">
        <div class="center small">Pay via UPI: <span class="bold">{{ $c->upi_id }}</span></div>
    @endif

    <hr class="dash">

    <div class="center xs">Thank you for your business!</div>
    @if ($c->website)
        <div class="center xs">{{ $c->website }}</div>
    @endif

    @if ($invoice->isDraft())
        <div class="center xs" style="margin-top:4px; border:1px dashed #000; padding:2px;">
            *** DRAFT - not a tax invoice ***
        </div>
    @endif
</div>

{{-- Auto-print on load - kirana counter workflow: click "Thermal print" →
     receipt prints → done. Short delay so the user can see the preview if
     they want to cancel. --}}
<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 350));
</script>

</body>
</html>
