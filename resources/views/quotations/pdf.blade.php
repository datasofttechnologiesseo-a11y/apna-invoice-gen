@php
    // Indian (lakh/crore) money formatter - $inr(1234567) → "12,34,567.00"
    $inr = fn ($n, $d = 2) => \App\Support\IndianNumber::format($n, $d);

    $c = $quotation->company;
    $cust = $quotation->customer;
    $number = $quotation->quote_number ?: 'DRAFT · ' . ($c->nextQuoteNumber() ?? 'preview');

    // Ink-saver mode is the DEFAULT. The PDF is mostly downloaded to print and
    // sign, so we should not burn toner on a teal/amber design unless the
    // operator explicitly asked for color (?color=1).
    //
    // Palette: in print mode everything resolves to greys + a single dark
    // accent. In color mode we use the brand teal + amber stripe.
    $print = $print ?? true;
    $palette = $print ? [
        'accent'        => '#111111',
        'accent_soft'   => '#f5f5f5',  // very light grey for header bands (low ink)
        'title'         => '#111111',
        'rule'          => '#cccccc',
        'rule_strong'   => '#888888',
        'tbl_head_bg'   => '#f0f0f0',
        'tbl_head_color'=> '#111111',
        'grand_bg'      => '#222222',
        'grand_color'   => '#ffffff',
        'subject_bar'   => '#666666',
        'subject_bg'    => '#fafafa',
        'bank_border'   => '#888888',
        'bank_bg'       => '#fafafa',
        'disc_border'   => '#888888',
        'disc_bg'       => '#fafafa',
        'disc_color'    => '#444444',
        'sig_line'      => '#999999',
    ] : [
        'accent'        => '#0f766e',
        'accent_soft'   => '#f0fdfa',
        'title'         => '#0f766e',
        'rule'          => '#d4d4d4',
        'rule_strong'   => '#0f766e',
        'tbl_head_bg'   => '#0f766e',
        'tbl_head_color'=> '#ffffff',
        'grand_bg'      => '#0f766e',
        'grand_color'   => '#ffffff',
        'subject_bar'   => '#0f766e',
        'subject_bg'    => '#f0fdfa',
        'bank_border'   => '#0f766e',
        'bank_bg'       => '#f0fdfa',
        'disc_border'   => '#b45309',
        'disc_bg'       => '#fffbeb',
        'disc_color'    => '#78350f',
        'sig_line'      => '#999999',
    ];

    // Indian convention: prefix B2B (registered) customer names with "M/s".
    // Skip for B2C (no GSTIN) - looks odd on individual buyers.
    $custNameDisplay = ($cust?->gstin ? 'M/s ' : '') . ($cust?->name ?? '-');

    // Parse Terms into a numbered list when the user has typed lines that
    // already start with "1." / "1)" - we render them as a clean ordered list.
    // Otherwise we fall back to whitespace-preserved free text.
    $termLines = [];
    if ($quotation->terms) {
        foreach (preg_split('/\r?\n/', $quotation->terms) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Strip "1." / "1)" / "(1)" prefix so we re-number consistently.
            $line = preg_replace('/^\(?\d+\)?[\.\)]\s*/', '', $line);
            $termLines[] = $line;
        }
    }

    $hasBankDetails = $c->bank_name && $c->bank_account_number && $c->bank_ifsc;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $number }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; line-height: 1.45; }
        .page { padding: 11mm 12mm 9mm; border-top: 2px solid {{ $palette['accent'] }}; }
        h1, h2, h3 { margin: 0; }
        .title { font-size: 24px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: {{ $palette['title'] }}; line-height: 1; }
        .co-name { font-weight: 700; font-size: 14px; }
        .small { font-size: 9px; }
        .x-small { font-size: 8.5px; }
        .muted { color: #666; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .tr { text-align: right; }
        .tl { text-align: left; }
        .tc { text-align: center; }
        .bold { font-weight: bold; }
        .upper { text-transform: uppercase; letter-spacing: 0.8px; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; padding-top: 2mm; }
        .header-table td { vertical-align: top; padding: 0; }
        .label { display: inline-block; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 2px; }

        .meta { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        .meta td { padding: 4px 6px; border: 1px solid {{ $palette['rule'] }}; vertical-align: top; }
        .meta .lbl { background: {{ $palette['accent_soft'] }}; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.6px; color: #555; width: 22%; }

        .parties { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        .parties td { vertical-align: top; padding: 6px 8px; border: 1px solid {{ $palette['rule'] }}; width: 50%; }

        .subject-row { margin-top: 4mm; padding: 5px 8px; border-left: 3px solid {{ $palette['subject_bar'] }}; background: {{ $palette['subject_bg'] }}; font-size: 11px; }
        .subject-row .lbl { color: #555; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.8px; font-weight: bold; margin-right: 4px; }

        .intro { margin-top: 3mm; font-size: 10px; line-height: 1.5; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        table.items th { background: {{ $palette['tbl_head_bg'] }}; color: {{ $palette['tbl_head_color'] }}; padding: 6px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid {{ $palette['rule_strong'] }}; }
        table.items td { border: 1px solid {{ $palette['rule'] }}; padding: 6px; vertical-align: top; }

        .totals { width: 55%; border-collapse: collapse; margin-left: auto; margin-top: 3mm; }
        .totals td { padding: 4px 8px; border: 1px solid {{ $palette['rule'] }}; }
        .totals tr.grand td { background: {{ $palette['grand_bg'] }}; color: {{ $palette['grand_color'] }}; font-weight: bold; font-size: 12px; }

        .terms-block { margin-top: 5mm; padding: 5mm 6mm; border: 1px solid {{ $palette['rule'] }}; background: {{ $palette['accent_soft'] }}; }
        .terms-block ol { margin: 4px 0 0; padding-left: 18px; }
        .terms-block ol li { margin-bottom: 2px; font-size: 9px; line-height: 1.5; }

        .bank-block { margin-top: 3mm; padding: 4mm 6mm; border: 1px dashed {{ $palette['bank_border'] }}; background: {{ $palette['bank_bg'] }}; }
        .bank-grid { width: 100%; border-collapse: collapse; margin-top: 3px; font-size: 9px; }
        .bank-grid td { padding: 2px 6px; vertical-align: top; }
        .bank-grid td.lbl { color: #555; font-weight: bold; width: 25%; }

        .disclaimer { margin-top: 4mm; padding: 4mm 6mm; border: 1px dashed {{ $palette['disc_border'] }}; background: {{ $palette['disc_bg'] }}; color: {{ $palette['disc_color'] }}; font-size: 9px; }

        .acceptance { margin-top: 5mm; border: 1px solid {{ $palette['rule_strong'] }}; padding: 0; }
        .acceptance .head { background: {{ $palette['accent_soft'] }}; padding: 4px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; color: #555; font-weight: bold; }
        .acceptance .body { padding: 6mm 6mm 4mm; font-size: 9px; }
        .acceptance .signrow { width: 100%; margin-top: 8mm; border-collapse: collapse; }
        .acceptance .signrow td { width: 50%; vertical-align: bottom; padding-top: 14mm; }
        .acceptance .signrow .line { border-top: 1px solid {{ $palette['sig_line'] }}; padding-top: 3px; font-size: 8.5px; color: #444; }

        .closing { margin-top: 8mm; }
        .closing-line { font-style: italic; font-size: 10px; color: #444; }
        .sig { margin-top: 14mm; width: 100%; border-collapse: collapse; }
        .sig td { vertical-align: bottom; }
        .sig .line { border-top: 1px solid {{ $palette['sig_line'] }}; padding-top: 3px; font-size: 9px; color: #444; }
    </style>
</head>
<body>
<div class="page">
    {{-- ─── Letterhead ─────────────────────────────────────────────────── --}}
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                @if ($c->logo_path && file_exists(public_path('storage/' . $c->logo_path)))
                    <img src="{{ public_path('storage/' . $c->logo_path) }}" alt="{{ $c->name }} logo" style="max-height: 40px; max-width: 180px; margin-bottom: 4px;">
                @endif
                <div class="co-name">{{ $c->name }}</div>
                <div class="small muted">
                    @if ($c->address_line1){{ $c->address_line1 }}@endif
                    @if ($c->address_line2), {{ $c->address_line2 }}@endif
                    @if ($c->city)<br>{{ $c->city }}@if ($c->state), {{ $c->state->name }}@endif @if ($c->postal_code) - {{ $c->postal_code }}@endif @endif
                    @if ($c->phone)<br>Phone: {{ $c->phone }}@endif
                    @if ($c->email) · {{ $c->email }}@endif
                    @if ($c->gstin)<br>GSTIN: <span class="mono">{{ $c->gstin }}</span>@endif
                    @if ($c->pan) · PAN: <span class="mono">{{ $c->pan }}</span>@endif
                </div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="title">Quotation</div>
                <div class="small muted" style="margin-top: 4px;">Price proposal · Not a tax invoice</div>
            </td>
        </tr>
    </table>

    {{-- ─── Quote meta ─────────────────────────────────────────────────── --}}
    <table class="meta">
        <tr>
            <td class="lbl">Quote no.</td>
            <td class="mono bold">{{ $number }}</td>
            <td class="lbl">Date</td>
            <td>{{ $quotation->quote_date?->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="lbl">Valid until</td>
            <td>{{ $quotation->valid_until?->format('d M Y') ?? '-' }}</td>
            <td class="lbl">Tax mode</td>
            <td>{{ $quotation->is_interstate ? 'Inter-state (IGST)' : 'Intra-state (CGST + SGST)' }}</td>
        </tr>
        @if ($quotation->reference)
            <tr>
                <td class="lbl">Customer ref.</td>
                <td colspan="3">{{ $quotation->reference }}</td>
            </tr>
        @endif
        @if ($quotation->delivery_period)
            <tr>
                <td class="lbl">Delivery</td>
                <td colspan="3">{{ $quotation->delivery_period }}</td>
            </tr>
        @endif
    </table>

    {{-- ─── Bill-to / Place of supply ──────────────────────────────────── --}}
    <table class="parties">
        <tr>
            <td>
                <div class="label">Quoted to</div>
                <div class="bold">{{ $custNameDisplay }}</div>
                <div class="small muted">
                    @if ($cust?->billing_address_line1){{ $cust->billing_address_line1 }}@endif
                    @if ($cust?->billing_address_line2), {{ $cust->billing_address_line2 }}@endif
                    @if ($cust?->billing_city)<br>{{ $cust->billing_city }}@if ($cust?->state), {{ $cust->state->name }}@endif @if ($cust?->billing_postal_code) - {{ $cust->billing_postal_code }}@endif @endif
                    @if ($cust?->phone)<br>Phone: {{ $cust->phone }}@endif
                    @if ($cust?->email) · {{ $cust->email }}@endif
                    @if ($cust?->gstin)<br>GSTIN: <span class="mono">{{ $cust->gstin }}</span>@endif
                </div>
            </td>
            <td>
                <div class="label">Place of supply</div>
                <div class="bold">{{ $cust?->state?->name ?? '-' }}</div>
                <div class="small muted" style="margin-top: 4px;">
                    Tax determined by your state ({{ $c->state?->name ?? '-' }}) vs the customer's state. Place of supply is informational on this quotation; the formal tax invoice will record it on GSTR-1.
                </div>
            </td>
        </tr>
    </table>

    {{-- ─── Subject + intro ───────────────────────────────────────────── --}}
    @if ($quotation->subject)
        <div class="subject-row">
            <span class="lbl">Sub:</span>{{ $quotation->subject }}
        </div>
    @endif

    <div class="intro">
        Dear Sir / Madam,<br>
        @if ($quotation->reference)
            With reference to your enquiry ({{ $quotation->reference }}), we are pleased to submit our best quotation as below:
        @else
            We are pleased to submit our best quotation for the items / services listed below:
        @endif
    </div>

    {{-- ─── Line items ─────────────────────────────────────────────────── --}}
    <table class="items">
        <thead>
            <tr>
                <th class="tl" style="width: 5%;">S.No</th>
                <th class="tl">Description</th>
                <th class="tl" style="width: 11%;">HSN/SAC</th>
                <th class="tr" style="width: 9%;">Qty</th>
                <th class="tr" style="width: 11%;">Rate (₹)</th>
                <th class="tr" style="width: 9%;">Disc</th>
                <th class="tr" style="width: 8%;">GST%</th>
                <th class="tr" style="width: 12%;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $i => $item)
                @php
                    // Same product-name / description merge logic as the invoice PDF.
                    $productName = $item->product?->name ?? null;
                    $descRaw = trim((string) $item->description);
                    $descIsJunk = $descRaw === '' || preg_match('/^0+(\.0+)?$/', $descRaw) === 1;
                    $primary = $productName ?: ($descIsJunk ? '' : $descRaw);
                    $secondary = ($productName && ! $descIsJunk && strcasecmp($descRaw, $productName) !== 0) ? $descRaw : null;
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <div style="font-weight: 600;">{{ $primary }}</div>
                        @if ($secondary)
                            <div style="font-size: 9px; color: #555; margin-top: 2px;">{{ $secondary }}</div>
                        @endif
                    </td>
                    <td class="mono">{{ $item->hsn_sac }}</td>
                    <td class="tr">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="tr mono">{{ $inr($item->rate) }}</td>
                    <td class="tr mono">{{ $inr($item->discount) }}</td>
                    <td class="tr">{{ rtrim(rtrim(inr($item->gst_rate), '0'), '.') }}%</td>
                    <td class="tr mono">{{ $inr($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ─── Totals ─────────────────────────────────────────────────────── --}}
    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="tr mono">₹ {{ $inr($quotation->subtotal) }}</td>
        </tr>
        @if (! $quotation->is_interstate)
            <tr>
                <td>CGST</td>
                <td class="tr mono">₹ {{ $inr($quotation->total_cgst) }}</td>
            </tr>
            <tr>
                <td>SGST</td>
                <td class="tr mono">₹ {{ $inr($quotation->total_sgst) }}</td>
            </tr>
        @else
            <tr>
                <td>IGST</td>
                <td class="tr mono">₹ {{ $inr($quotation->total_igst) }}</td>
            </tr>
        @endif
        @if ((float) $quotation->round_off !== 0.0)
            <tr>
                <td class="muted">Round-off</td>
                <td class="tr mono muted">₹ {{ $inr($quotation->round_off) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Grand total</td>
            <td class="tr mono">₹ {{ $inr($quotation->grand_total) }}</td>
        </tr>
    </table>

    <div class="small muted" style="margin-top: 3mm;"><strong>In words:</strong> {{ $amountInWords }}</div>

    {{-- ─── Terms & Conditions ─────────────────────────────────────────── --}}
    @if (! empty($termLines) || $quotation->notes)
        <div class="terms-block">
            @if (! empty($termLines))
                <div class="label">Terms &amp; conditions</div>
                <ol>
                    @foreach ($termLines as $t)
                        <li>{{ $t }}</li>
                    @endforeach
                </ol>
            @endif
            @if ($quotation->notes)
                <div class="label" style="margin-top: 4mm;">Notes</div>
                <div class="small" style="white-space: pre-line;">{{ $quotation->notes }}</div>
            @endif
        </div>
    @endif

    {{-- ─── Bank details (only if company has them - for advance payment) ──── --}}
    @if ($hasBankDetails)
        <div class="bank-block">
            <div class="label">Bank details for advance payment</div>
            <table class="bank-grid">
                <tr>
                    <td class="lbl">Beneficiary</td>
                    <td>{{ $c->name }}</td>
                    <td class="lbl">A/c no.</td>
                    <td class="mono">{{ $c->bank_account_number }}</td>
                </tr>
                <tr>
                    <td class="lbl">Bank</td>
                    <td>{{ $c->bank_name }}@if ($c->bank_branch), {{ $c->bank_branch }}@endif</td>
                    <td class="lbl">IFSC</td>
                    <td class="mono">{{ $c->bank_ifsc }}</td>
                </tr>
                @if ($c->upi_id)
                    <tr>
                        <td class="lbl">UPI</td>
                        <td colspan="3" class="mono">{{ $c->upi_id }}</td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    {{-- ─── Disclaimer ─────────────────────────────────────────────────── --}}
    <div class="disclaimer">
        <strong>This is a quotation, not a tax invoice.</strong>
        No GST is collected at this stage. Prices and tax shown are indicative of what will appear on the tax invoice if you accept this quote. A formal GST tax invoice will be issued on confirmation of the order.
    </div>

    {{-- ─── Closing & signatory ────────────────────────────────────────── --}}
    <div class="closing">
        <div class="closing-line">Yours faithfully,</div>
        <table class="sig">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;" class="tr">
                    <div class="line">Authorized signatory</div>
                    <div class="x-small muted" style="margin-top: 2px;">For {{ $c->name }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ─── Acceptance block (sign-back) ───────────────────────────────── --}}
    <div class="acceptance">
        <div class="head">Acceptance - to confirm this order, please sign &amp; return</div>
        <div class="body">
            <div>I/We accept this quotation and request you to proceed with the order on the terms set out above.</div>
            <table class="signrow">
                <tr>
                    <td>
                        <div class="line">Signature &amp; seal</div>
                    </td>
                    <td class="tr">
                        <div class="line">Date</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Viral loop: quotations reach prospects while they're comparing vendors -
         a high-attention reader. One tasteful, clickable byline (DomPDF renders
         <a href> as a real PDF link annotation). --}}
    <div style="margin-top: 6mm; text-align: center; font-size: 8px; color: #9ca3af;"><a href="https://apnainvoice.com/?utm_source=quotation_pdf&utm_medium=pdf&utm_campaign=byline" style="color: #9ca3af; text-decoration: none;">Made free with Apna Invoice · apnainvoice.com - free GST invoicing for Indian businesses</a></div>
</div>
</body>
</html>
