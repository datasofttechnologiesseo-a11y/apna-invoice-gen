@php
    $c = $invoice->company;
    $cust = $invoice->customer;
    $currencySymbol = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'][$invoice->currency] ?? ($invoice->currency . ' ');
@endphp

<div class="p-8 text-gray-900 invoice-doc">
    <div class="flex justify-between items-start pb-6 border-b-2 border-gray-800">
        <div>
            @if ($c->logo_path)
                <img src="{{ public_path('storage/' . $c->logo_path) }}" alt="Logo" style="max-height: 70px; max-width: 220px;">
            @else
                <h1 style="font-size: 22px; font-weight: 700; margin: 0;">{{ $c->name }}</h1>
            @endif
            <div class="text-sm text-gray-600 mt-2">
                {{ $c->address_line1 }}@if ($c->address_line2), {{ $c->address_line2 }}@endif<br>
                {{ $c->city }}{{ $c->city && $c->state?->name ? ', ' : '' }}{{ $c->state?->name }} {{ $c->postal_code }}<br>
                {{ $c->country }}
                @if ($c->phone) · {{ $c->phone }} @endif
                @if ($c->email) · {{ $c->email }} @endif
            </div>
            @if ($c->gstin)
                <div class="text-sm mt-1"><strong>GSTIN:</strong> {{ $c->gstin }}</div>
            @endif
        </div>
        <div class="text-right">
            <h2 class="text-2xl font-bold tracking-wide">TAX INVOICE</h2>
            <div class="text-sm mt-2">
                <div><strong>Invoice #:</strong> {{ str_starts_with($invoice->invoice_number, 'DRAFT-') ? '—' : $invoice->invoice_number }}</div>
                <div><strong>Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}</div>
                @if ($invoice->due_date)
                    <div><strong>Due:</strong> {{ $invoice->due_date->format('d M Y') }}</div>
                @endif
                <div><strong>Place of supply:</strong> {{ $invoice->placeOfSupply?->name ?? '—' }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-8 py-6 border-b">
        <div>
            <div class="text-xs uppercase text-gray-500 font-semibold">Bill to</div>
            <div class="mt-1"><strong>{{ $cust->name }}</strong></div>
            <div class="text-sm text-gray-700">
                {{ $cust->address_line1 }}@if ($cust->address_line2), {{ $cust->address_line2 }}@endif<br>
                {{ $cust->city }}{{ $cust->city && $cust->state?->name ? ', ' : '' }}{{ $cust->state?->name }} {{ $cust->postal_code }}<br>
                {{ $cust->country }}
            </div>
            @if ($cust->gstin)<div class="text-sm mt-1"><strong>GSTIN:</strong> {{ $cust->gstin }}</div>@endif
            @if ($cust->phone)<div class="text-sm">{{ $cust->phone }} @if ($cust->email) · {{ $cust->email }} @endif</div>@endif
        </div>
        <div class="text-sm">
            @if ($invoice->notes)
                <div class="text-xs uppercase text-gray-500 font-semibold">Notes</div>
                <div class="mt-1 whitespace-pre-line">{{ $invoice->notes }}</div>
            @endif
        </div>
    </div>

    <table class="w-full mt-6 text-sm">
        <thead>
            <tr class="bg-gray-100 text-gray-700 text-left">
                <th class="px-2 py-2">#</th>
                <th class="px-2 py-2">Description</th>
                <th class="px-2 py-2">HSN/SAC</th>
                <th class="px-2 py-2 text-right">Qty</th>
                <th class="px-2 py-2 text-right">Rate</th>
                <th class="px-2 py-2 text-right">Amount</th>
                <th class="px-2 py-2 text-right">GST%</th>
                @if ($invoice->is_interstate)
                    <th class="px-2 py-2 text-right">IGST</th>
                @else
                    <th class="px-2 py-2 text-right">CGST</th>
                    <th class="px-2 py-2 text-right">SGST</th>
                @endif
                <th class="px-2 py-2 text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $idx => $item)
                <tr class="border-b">
                    <td class="px-2 py-2">{{ $idx + 1 }}</td>
                    <td class="px-2 py-2">{{ $item->description }}</td>
                    <td class="px-2 py-2 font-mono">{{ $item->hsn_sac }}</td>
                    <td class="px-2 py-2 text-right font-mono">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="px-2 py-2 text-right font-mono">{{ number_format((float) $item->rate, 2) }}</td>
                    <td class="px-2 py-2 text-right font-mono">{{ number_format((float) $item->amount, 2) }}</td>
                    <td class="px-2 py-2 text-right">{{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%</td>
                    @if ($invoice->is_interstate)
                        <td class="px-2 py-2 text-right font-mono">{{ number_format((float) $item->igst_amount, 2) }}</td>
                    @else
                        <td class="px-2 py-2 text-right font-mono">{{ number_format((float) $item->cgst_amount, 2) }}</td>
                        <td class="px-2 py-2 text-right font-mono">{{ number_format((float) $item->sgst_amount, 2) }}</td>
                    @endif
                    <td class="px-2 py-2 text-right font-mono font-medium">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="grid grid-cols-2 gap-8 mt-6">
        <div class="text-sm">
            <strong>Amount in words:</strong><br>
            <em>{{ $amountInWords }}</em>
        </div>

        <div class="text-sm">
            <table class="w-full">
                <tr><td class="py-1">Subtotal</td><td class="py-1 text-right font-mono">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                @if ($invoice->is_interstate)
                    <tr><td class="py-1">IGST</td><td class="py-1 text-right font-mono">{{ number_format((float) $invoice->total_igst, 2) }}</td></tr>
                @else
                    <tr><td class="py-1">CGST</td><td class="py-1 text-right font-mono">{{ number_format((float) $invoice->total_cgst, 2) }}</td></tr>
                    <tr><td class="py-1">SGST</td><td class="py-1 text-right font-mono">{{ number_format((float) $invoice->total_sgst, 2) }}</td></tr>
                @endif
                @if ((float) $invoice->round_off != 0)
                    <tr><td class="py-1">Round off</td><td class="py-1 text-right font-mono">{{ number_format((float) $invoice->round_off, 2) }}</td></tr>
                @endif
                <tr class="border-t"><td class="py-1.5 font-bold">Grand Total</td><td class="py-1.5 text-right font-mono font-bold">{{ $currencySymbol }}{{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
                @if ((float) $invoice->paid_amount > 0)
                    <tr><td class="py-1 text-gray-600">Paid</td><td class="py-1 text-right font-mono text-gray-600">{{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
                    <tr class="border-t"><td class="py-1 font-semibold">Balance</td><td class="py-1 text-right font-mono font-semibold">{{ $currencySymbol }}{{ number_format((float) $invoice->balance, 2) }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    @if ($invoice->terms)
        <div class="mt-10 pt-4 border-t text-sm">
            <div class="font-semibold uppercase text-xs text-gray-600 mb-1">Terms &amp; Conditions</div>
            <div class="whitespace-pre-line text-gray-700">{{ $invoice->terms }}</div>
        </div>
    @endif

    <div class="mt-10 flex justify-between items-end text-sm">
        <div>
            <div class="text-xs text-gray-500">This is a computer-generated invoice.</div>
        </div>
        <div class="text-center">
            @if ($c->signature_path)
                <img src="{{ public_path('storage/' . $c->signature_path) }}" alt="Signature" style="max-height: 50px;">
            @endif
            <div class="border-t border-gray-400 pt-1 mt-8 w-48">for {{ $c->name }}<br><span class="text-xs text-gray-500">Authorised signatory</span></div>
        </div>
    </div>
</div>
