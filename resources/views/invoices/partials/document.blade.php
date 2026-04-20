@php
    $c = $invoice->company;
    $cust = $invoice->customer;
    $currencySymbol = '₹';
@endphp

<div class="p-8 text-gray-900 invoice-doc">
    <div class="flex justify-between items-start pb-6 border-b-2 border-gray-800">
        <div>
            @if ($c->logo_path && file_exists(public_path('storage/' . $c->logo_path)))
                <img src="{{ asset('storage/' . $c->logo_path) }}" alt="{{ $c->name }} logo" style="max-height: 70px; max-width: 220px;">
            @endif
            <h1 class="{{ $c->logo_path ? 'mt-2' : '' }}" style="font-size: 22px; font-weight: 700; margin: 0;">{{ $c->name }}</h1>
            <div class="text-sm text-gray-600 mt-2">
                {{ $c->address_line1 }}@if ($c->address_line2), {{ $c->address_line2 }}@endif<br>
                {{ $c->city }}{{ $c->city && $c->state?->name ? ', ' : '' }}{{ $c->state?->name }}@if ($c->state?->gst_code) ({{ $c->state->gst_code }})@endif {{ $c->postal_code }}<br>
                {{ $c->country }}
                @if ($c->phone) · {{ $c->phone }} @endif
                @if ($c->email) · {{ $c->email }} @endif
            </div>
            @if ($c->gstin)
                <div class="text-sm mt-1"><strong>GSTIN:</strong> {{ $c->gstin }}</div>
            @endif
            @if ($c->state?->gst_code)
                <div class="text-sm"><strong>State code:</strong> <span class="font-mono">{{ $c->state->gst_code }}</span></div>
            @endif
        </div>
        <div class="text-right">
            <h2 class="text-2xl font-bold tracking-wide">TAX INVOICE</h2>
            <div class="text-sm mt-2">
                <div><strong>Invoice #:</strong> {{ $invoice->isDraft() ? 'Not yet issued (preview: ' . $invoice->company->nextInvoiceNumber() . ')' : $invoice->invoice_number }}</div>
                <div><strong>Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}</div>
                @if ($invoice->due_date)
                    <div><strong>Due:</strong> {{ $invoice->due_date->format('d M Y') }}</div>
                @endif
                <div><strong>Place of supply:</strong> {{ $invoice->placeOfSupply?->name ?? '—' }}@if ($invoice->placeOfSupply?->gst_code) ({{ $invoice->placeOfSupply->gst_code }})@endif</div>
            </div>
        </div>
    </div>

    @php
        $hasTransporter = $invoice->transporter_name || $invoice->vehicle_number || $invoice->eway_bill_number || $invoice->transporter_id || $invoice->transport_mode;
    @endphp

    <div class="grid grid-cols-2 gap-8 py-6 border-b">
        <div>
            <div class="text-xs uppercase text-gray-500 font-semibold">Bill to</div>
            <div class="mt-1"><strong>{{ $cust->name }}</strong></div>
            <div class="text-sm text-gray-700">
                {{ $cust->address_line1 }}@if ($cust->address_line2), {{ $cust->address_line2 }}@endif<br>
                {{ $cust->city }}{{ $cust->city && $cust->state?->name ? ', ' : '' }}{{ $cust->state?->name }}@if ($cust->state?->gst_code) ({{ $cust->state->gst_code }})@endif {{ $cust->postal_code }}<br>
                {{ $cust->country }}
            </div>
            @if ($cust->gstin)<div class="text-sm mt-1"><strong>GSTIN:</strong> {{ $cust->gstin }}</div>@endif
            @if ($cust->state?->gst_code)<div class="text-sm"><strong>State code:</strong> <span class="font-mono">{{ $cust->state->gst_code }}</span></div>@endif
            @if ($cust->phone)<div class="text-sm">{{ $cust->phone }} @if ($cust->email) · {{ $cust->email }} @endif</div>@endif
        </div>
        <div class="text-sm">
            @if ($hasTransporter)
                <div class="text-xs uppercase text-gray-500 font-semibold">Transporter details</div>
                <table class="mt-1 w-full text-sm">
                    @if ($invoice->transporter_name)
                        <tr><td class="py-0.5 pr-3 text-gray-600">Transporter</td><td class="py-0.5">{{ $invoice->transporter_name }}</td></tr>
                    @endif
                    @if ($invoice->transporter_id)
                        <tr><td class="py-0.5 pr-3 text-gray-600">Transporter ID</td><td class="py-0.5 font-mono">{{ $invoice->transporter_id }}</td></tr>
                    @endif
                    @if ($invoice->vehicle_number)
                        <tr><td class="py-0.5 pr-3 text-gray-600">Vehicle no.</td><td class="py-0.5 font-mono">{{ $invoice->vehicle_number }}</td></tr>
                    @endif
                    @if ($invoice->transport_mode)
                        <tr><td class="py-0.5 pr-3 text-gray-600">Mode</td><td class="py-0.5">{{ $invoice->transport_mode }}</td></tr>
                    @endif
                    @if ($invoice->eway_bill_number)
                        <tr><td class="py-0.5 pr-3 text-gray-600">E-way bill</td><td class="py-0.5 font-mono">{{ $invoice->eway_bill_number }}</td></tr>
                    @endif
                </table>
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
                <th class="px-2 py-2 text-right">GST%</th>
                <th class="px-2 py-2 text-right">Amount</th>
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
                    <td class="px-2 py-2 text-right">{{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%</td>
                    <td class="px-2 py-2 text-right font-mono font-medium">{{ number_format((float) $item->amount, 2) }}</td>
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

    @php
        $payableAmount = (float) ($invoice->balance ?? $invoice->grand_total);
        $showUpiQr = $c->upi_id && $payableAmount > 0;
        $qrDataUri = $showUpiQr
            ? \App\Support\UpiQr::svgDataUri($c->upi_id, $c->name, $payableAmount, $invoice->invoice_number ?: 'Invoice', 180)
            : null;
    @endphp

    @if ($c->bank_name || $c->bank_account_number || $c->upi_id)
        <div class="mt-6 p-4 bg-gray-50 rounded text-sm">
            <div class="text-xs font-semibold uppercase text-gray-500">Bank details for payment</div>
            <div class="mt-2 flex gap-4 items-start">
                <div class="flex-1 grid grid-cols-2 gap-y-1 gap-x-6 text-xs">
                    @if ($c->bank_name)
                        <div><strong>Bank:</strong> {{ $c->bank_name }}</div>
                    @endif
                    @if ($c->bank_branch)
                        <div><strong>Branch:</strong> {{ $c->bank_branch }}</div>
                    @endif
                    @if ($c->bank_account_number)
                        <div><strong>A/c:</strong> <span class="font-mono">{{ $c->bank_account_number }}</span></div>
                    @endif
                    @if ($c->bank_ifsc)
                        <div><strong>IFSC:</strong> <span class="font-mono">{{ $c->bank_ifsc }}</span></div>
                    @endif
                    @if ($c->upi_id)
                        <div class="col-span-2"><strong>UPI:</strong> <span class="font-mono">{{ $c->upi_id }}</span></div>
                    @endif
                </div>
                @if ($showUpiQr)
                    <div class="pl-4 border-l border-gray-200 text-center">
                        <img src="{{ $qrDataUri }}" alt="UPI QR" style="width: 84px; height: 84px; display: block;">
                        <div class="mt-1 text-xs text-gray-600">Scan to pay {{ $currencySymbol }}{{ number_format($payableAmount, 2) }}</div>
                        <div class="text-[10px] text-gray-500">Any UPI app · GPay · PhonePe · Paytm</div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($invoice->terms)
        <div class="mt-6 pt-4 border-t text-sm">
            <div class="font-semibold uppercase text-xs text-gray-600 mb-1">Terms &amp; Conditions</div>
            <div class="whitespace-pre-line text-gray-700">{{ $invoice->terms }}</div>
        </div>
    @endif

    @if ($invoice->notes)
        <div class="mt-4 text-sm">
            <div class="font-semibold uppercase text-xs text-gray-600 mb-1">Notes</div>
            <div class="whitespace-pre-line text-gray-700">{{ $invoice->notes }}</div>
        </div>
    @endif

    <div class="mt-10 flex justify-between items-end text-sm">
        <div>
            <div class="text-xs text-gray-500">This is a computer-generated invoice.</div>
        </div>
        <div class="text-center">
            @if ($c->signature_path && file_exists(public_path('storage/' . $c->signature_path)))
                <img src="{{ asset('storage/' . $c->signature_path) }}" alt="Signature" style="max-height: 50px;">
            @endif
            <div class="border-t border-gray-400 pt-1 mt-8 w-48">for {{ $c->name }}<br><span class="text-xs text-gray-500">Authorised signatory</span></div>
        </div>
    </div>
</div>
