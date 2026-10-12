@php
    $c = $invoice->company;
    // The recipient as issued (snapshot), or the live customer on a draft.
    $cust = $invoice->billedCustomer();
    $currencySymbol = '₹';

    // Document title is decided by Invoice::documentTitle() - keeps the
    // print view, the PDF, the show page heading and the email subject all
    // showing the same string. Three-way: "Invoice" (no GSTIN),
    // "Bill of Supply" (composition dealer, Rule 49) or "Tax Invoice"
    // (registered + regular scheme, Rule 46).
    $documentTitle = $invoice->documentTitle();
    $isBillOfSupply = $documentTitle === 'Bill of Supply';

    // Only render the discount column when at least one line carries one.
    $hasDiscount = $invoice->items->sum(fn ($i) => (float) ($i->discount ?? 0)) > 0;
    // Only render the Product column when at least one line is linked to a
    // saved product. Cash-bill style invoices with all-custom lines don't need
    // the column at all - saves horizontal room for Description.
    $hasProducts = $invoice->items->contains(fn ($i) => $i->product_id !== null && $i->product?->name);
@endphp

<div class="p-4 sm:p-6 print:p-8 text-gray-900 invoice-doc">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 pb-6 border-b-2 border-gray-800 print:flex-row print:justify-between">
        @php
            // A wide wordmark sits above the business name; a square or tall
            // emblem sits beside it, as on a printed letterhead.
            $logo = \App\Support\BrandImage::fit($c->logo_path, 240, 64);
            $logoBeside = $logo && $logo['ratio'] < 2.2;
            if ($logoBeside) {
                $logo = \App\Support\BrandImage::fit($c->logo_path, 130, 76);
            }
        @endphp
        <div class="{{ $logoBeside ? 'flex items-start gap-4' : '' }}">
            @if ($logo)
                <img src="{{ $logo['url'] }}" alt="{{ $c->name }} logo" width="{{ $logo['w'] }}" height="{{ $logo['h'] }}" class="shrink-0 {{ $logoBeside ? '' : 'mb-3' }}" style="width: {{ $logo['w'] }}px; height: {{ $logo['h'] }}px;">
            @endif
            <div>
            <h1 style="font-size: 22px; font-weight: 700; margin: 0;">{{ $c->name }}</h1>
            <div class="text-sm text-gray-600 mt-2">
                {{ $c->address_line1 }}@if ($c->address_line2), {{ $c->address_line2 }}@endif<br>
                {{ $c->city }}{{ $c->city && $c->state?->name ? ', ' : '' }}{{ $c->state?->name }}@if ($c->state?->gst_code) ({{ $c->state->gst_code }})@endif {{ $c->postal_code }}<br>
                {{ $c->country }}
                @if ($c->phone) · {{ $c->phone }} @endif
                @if ($c->email) · {{ $c->email }} @endif
            </div>
            <div class="text-sm mt-1">
                @if ($c->gstin)<strong>GSTIN:</strong> <span class="font-mono">{{ $c->gstin }}</span>@endif
                @if ($c->gstin && $c->pan) · @endif
                @if ($c->pan)<strong>PAN:</strong> <span class="font-mono">{{ $c->pan }}</span>@endif
            </div>
            @if ($c->state?->gst_code)
                <div class="text-sm"><strong>State code:</strong> <span class="font-mono">{{ $c->state->gst_code }}</span></div>
            @endif
            </div>
        </div>
        <div class="sm:text-right print:text-right">
            <h2 class="text-2xl font-bold tracking-wide uppercase">{{ $documentTitle }}</h2>
            @if ($invoice->isCancelled())
                {{-- A cancelled invoice must never look payable, on screen or printed. --}}
                <div class="mt-2 inline-block text-center px-3 py-1 border-2 border-danger-700 text-danger-700 rounded">
                    <div class="text-lg font-extrabold tracking-[0.2em] uppercase leading-tight">Cancelled</div>
                    <div class="text-xs">on {{ $invoice->cancelled_at?->format('d M Y') }}</div>
                </div>
            @endif
            @if ($c->composition_dealer)
                <div class="mt-2 inline-block text-left px-2.5 py-1.5 bg-accent-50 border border-accent-300 rounded text-xs leading-snug text-accent-900 max-w-xs">
                    <strong>Composition taxable person, not eligible to collect tax on supplies.</strong>
                </div>
            @elseif ($isBillOfSupply)
                <div class="mt-2 text-xs italic text-gray-500">Issued under Section 31(3)(c) · exempt supply</div>
            @endif
            <div class="text-sm mt-2">
                <div><strong>{{ $documentTitle }} #:</strong> {{ $invoice->isDraft() ? 'Not yet issued (preview: ' . $invoice->company->nextInvoiceNumber() . ')' : $invoice->invoice_number }}</div>
                <div><strong>Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}</div>
                @if ($invoice->due_date)
                    <div><strong>Due:</strong> {{ $invoice->due_date->format('d M Y') }}</div>
                @endif
                <div><strong>Place of supply:</strong> {{ $invoice->placeOfSupply?->name ?? '-' }}@if ($invoice->placeOfSupply?->gst_code) ({{ $invoice->placeOfSupply->gst_code }})@endif</div>
                @if ($invoice->reverse_charge)
                    <div class="text-accent-700"><strong>Reverse charge applicable</strong> · Section 9(3)/9(4)</div>
                @endif
                @if ($invoice->isZeroRated())
                    <div class="mt-1 text-brand-800 text-xs font-semibold uppercase tracking-wide">{{ $invoice->zeroRatedEndorsement() }}</div>
                    @if ($invoice->isUnderLut() && $invoice->company?->lut_number)
                        <div class="text-xs"><strong>LUT / Bond No.:</strong> {{ $invoice->company->lut_number }}</div>
                    @endif
                    @if ($invoice->isExport() && ($invoice->export_port_code || $invoice->shipping_bill_number))
                        <div class="text-xs"><strong>Port:</strong> {{ $invoice->export_port_code ?: '-' }} · <strong>Shipping bill:</strong> {{ $invoice->shipping_bill_number ?: '-' }}@if ($invoice->shipping_bill_date) dt. {{ $invoice->shipping_bill_date->format('d M Y') }}@endif</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @php
        $hasTransporter = $invoice->transporter_name || $invoice->vehicle_number || $invoice->eway_bill_number || $invoice->transporter_id || $invoice->transport_mode;
        $hasShipTo = $invoice->hasSeparateShipTo();
        $shipState = $invoice->shipToState ?? null;
        $cols = 1 + ($hasShipTo ? 1 : 0) + ($hasTransporter ? 1 : 0);
        $gridCols = $cols === 3 ? 'md:grid-cols-3' : ($cols === 2 ? 'md:grid-cols-2' : 'md:grid-cols-1');
    @endphp

    <div class="grid grid-cols-1 {{ $gridCols }} gap-6 py-6 border-b">
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
        @if ($hasShipTo)
            <div class="md:border-l md:pl-6">
                <div class="text-xs uppercase text-gray-500 font-semibold">Ship to</div>
                @if ($invoice->ship_to_name)
                    <div class="mt-1"><strong>{{ $invoice->ship_to_name }}</strong></div>
                @endif
                <div class="text-sm text-gray-700">
                    @if ($invoice->ship_to_address_line1){{ $invoice->ship_to_address_line1 }}@endif
                    @if ($invoice->ship_to_address_line2), {{ $invoice->ship_to_address_line2 }}@endif
                    @if ($invoice->ship_to_address_line1)<br>@endif
                    {{ trim(($invoice->ship_to_city ?? '') . ($invoice->ship_to_city && $shipState?->name ? ', ' : '') . ($shipState?->name ?? '') . ($shipState?->gst_code ? ' (' . $shipState->gst_code . ')' : '') . ' ' . ($invoice->ship_to_postal_code ?? '')) }}
                </div>
                @if ($invoice->ship_to_gstin)
                    <div class="text-sm mt-1"><strong>GSTIN:</strong> {{ $invoice->ship_to_gstin }}</div>
                @endif
            </div>
        @endif
        <div class="text-sm {{ $hasShipTo ? 'md:border-l md:pl-6' : '' }}">
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

    {{-- Item and its note in one column (as on the PDF), the GST rate over the
         tax: fits the invoice page's main column without sideways scrolling.
         Scrolls sideways on a phone rather than pushing the page wider. --}}
    <div class="mt-6 overflow-x-auto print:overflow-visible">
    <table class="w-full text-sm min-w-[520px] print:min-w-0">
        <thead>
            <tr class="bg-gray-100 text-gray-700 text-left text-xs">
                <th class="px-2 py-2 font-semibold">#</th>
                <th class="px-2 py-2 font-semibold">Description</th>
                <th class="px-2 py-2 font-semibold">HSN/SAC</th>
                <th class="px-2 py-2 font-semibold text-right">Qty</th>
                <th class="px-2 py-2 font-semibold text-right">Rate</th>
                @if ($hasDiscount)
                    <th class="px-2 py-2 font-semibold text-right">Discount</th>
                @endif
                <th class="px-2 py-2 font-semibold text-right">Taxable value</th>
                <th class="px-2 py-2 font-semibold text-right">{{ $invoice->is_interstate ? 'IGST' : 'CGST + ' . $invoice->stateTaxLabel() }}</th>
                <th class="px-2 py-2 font-semibold text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $idx => $item)
                @php
                    // The saved item's name in bold, with the line's own note under
                    // it when it says something different. A blank or legacy "0"
                    // description falls back to the item name.
                    $productName = $hasProducts ? $item->product?->name : null;
                    $desc = trim((string) $item->description);
                    $looksLikeJunk = $desc === '' || preg_match('/^0+(\.0+)?$/', $desc) === 1;
                    $primary = $productName ?: ($looksLikeJunk ? ($item->product?->name ?? '') : $desc);
                    $secondary = ($productName && ! $looksLikeJunk && strcasecmp($desc, $productName) !== 0) ? $desc : null;
                    $lineTax = (float) $item->cgst_amount + (float) $item->sgst_amount + (float) $item->igst_amount;
                @endphp
                <tr class="border-b align-top">
                    <td class="px-2 py-2 text-gray-600">{{ $idx + 1 }}</td>
                    <td class="px-2 py-2">
                        <div class="font-medium text-gray-900">{{ $primary }}</div>
                        @if ($secondary)
                            <div class="text-xs text-gray-600 mt-0.5">{{ $secondary }}</div>
                        @endif
                    </td>
                    <td class="px-2 py-2 font-mono text-xs">{{ $item->hsn_sac }}</td>
                    <td class="px-2 py-2 text-right font-mono whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="px-2 py-2 text-right font-mono">{{ inr($item->rate) }}</td>
                    @if ($hasDiscount)
                        <td class="px-2 py-2 text-right font-mono">{{ (float) ($item->discount ?? 0) > 0 ? '-' . inr($item->discount) : '–' }}</td>
                    @endif
                    {{-- Taxable value is the Rule 46 figure; Amount is what the
                         line actually costs the buyer, GST included. Under reverse
                         charge no GST is collected, so the two are equal. --}}
                    <td class="px-2 py-2 text-right font-mono">{{ inr($item->amount) }}</td>
                    <td class="px-2 py-2 text-right font-mono text-gray-700">
                        <div class="text-xs text-gray-600">{{ rtrim(rtrim(inr($item->gst_rate), '0'), '.') }}%</div>
                        @if ($invoice->reverse_charge)
                            <span class="text-xs text-gray-600" title="Payable by the recipient under reverse charge">RCM</span>
                        @else
                            {{ inr($lineTax) }}
                        @endif
                    </td>
                    <td class="px-2 py-2 text-right font-mono font-medium">{{ inr($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 sm:gap-8 mt-6 print:grid-cols-2">
        <div class="text-sm">
            <strong>Amount in words:</strong><br>
            <em>{{ $amountInWords }}</em>

            @if ($invoice->reverse_charge)
                {{-- Section 9(3)/9(4) CGST + Rule 46(p): mandatory declaration that
                     the recipient is liable for tax on RCM supply. --}}
                <div class="mt-3 p-3 bg-accent-50 border border-accent-300 rounded text-xs leading-relaxed text-accent-900">
                    <div class="font-bold uppercase tracking-wide mb-1">Tax payable on reverse charge basis</div>
                    GST on this supply is to be paid by the recipient directly to the government under
                    <strong>Section 9(3)/9(4) of the CGST Act 2017</strong>. The supplier is not collecting
                    tax on this invoice. The applicable GST rate is shown in the line items for reference.
                </div>
            @endif

            @if ($c->declaration)
                <div class="mt-3 pl-3 border-l-2 border-gray-300 text-xs text-gray-700">
                    <div class="font-semibold text-xs text-gray-500 mb-1">Declaration</div>
                    <em>{{ $c->declaration }}</em>
                </div>
            @endif
        </div>

        <div class="text-sm">
            <table class="w-full">
                <tr><td class="py-1">Subtotal</td><td class="py-1 text-right font-mono">{{ inr($invoice->subtotal) }}</td></tr>
                @if ($invoice->reverse_charge)
                    @if ($invoice->is_interstate)
                        <tr><td class="py-1 text-gray-600 text-xs">IGST <span class="italic">(payable by recipient)</span></td><td class="py-1 text-right font-mono text-xs">{{ $currencySymbol }}0</td></tr>
                    @else
                        <tr><td class="py-1 text-gray-600 text-xs">CGST <span class="italic">(payable by recipient)</span></td><td class="py-1 text-right font-mono text-xs">{{ $currencySymbol }}0</td></tr>
                        <tr><td class="py-1 text-gray-600 text-xs">{{ $invoice->stateTaxLabel() }} <span class="italic">(payable by recipient)</span></td><td class="py-1 text-right font-mono text-xs">{{ $currencySymbol }}0</td></tr>
                    @endif
                @elseif ($invoice->is_interstate)
                    <tr><td class="py-1">IGST</td><td class="py-1 text-right font-mono">{{ inr($invoice->total_igst) }}</td></tr>
                @else
                    <tr><td class="py-1">CGST</td><td class="py-1 text-right font-mono">{{ inr($invoice->total_cgst) }}</td></tr>
                    <tr><td class="py-1">{{ $invoice->stateTaxLabel() }}</td><td class="py-1 text-right font-mono">{{ inr($invoice->total_sgst) }}</td></tr>
                @endif
                @if ((float) $invoice->round_off != 0)
                    <tr><td class="py-1">Round off</td><td class="py-1 text-right font-mono">{{ inr($invoice->round_off) }}</td></tr>
                @endif
                <tr class="border-t"><td class="py-1.5 font-bold">Grand total</td><td class="py-1.5 text-right font-mono font-bold">{{ $currencySymbol }}{{ inr($invoice->grand_total) }}</td></tr>
                @if ((float) $invoice->paid_amount > 0 || (float) $invoice->credited_amount > 0)
                    {{-- Every amount that moves the balance is listed, so the rows add up. --}}
                    @if ((float) $invoice->credited_amount > 0)
                        <tr><td class="py-1 text-gray-600">Less credit notes</td><td class="py-1 text-right font-mono text-gray-600">{{ $currencySymbol }}{{ inr($invoice->credited_amount) }}</td></tr>
                    @endif
                    @if ((float) $invoice->paid_amount > 0)
                        <tr><td class="py-1 text-gray-600">Paid</td><td class="py-1 text-right font-mono text-gray-600">{{ $currencySymbol }}{{ inr($invoice->paid_amount) }}</td></tr>
                    @endif
                    @php
                        // Negative balance = customer overpaid; show sign BEFORE the currency
                        // symbol (Indian accounting convention) and relabel as "Advance" so
                        // readers don't mistake it for an outstanding amount.
                        $balance = (float) $invoice->balance;
                        // Negative with a credit note behind it = refund owed to the customer.
                        $balanceLabel = $balance < 0
                            ? ((float) $invoice->credited_amount > 0 ? 'Refund due to customer' : 'Advance (overpaid)')
                            : 'Balance';
                        $balanceStr = ($balance < 0 ? '-' : '') . $currencySymbol . inr(abs($balance));
                    @endphp
                    <tr class="border-t"><td class="py-1 font-semibold">{{ $balanceLabel }}</td><td class="py-1 text-right font-mono font-semibold">{{ $balanceStr }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    @php
        $payableAmount = (float) ($invoice->balance ?? $invoice->grand_total);
        // No QR and no "pay" wording on a cancelled invoice: it is not owed.
        $showUpiQr = $c->upi_id && $payableAmount > 0 && ! $invoice->isCancelled();
        $qrDataUri = $showUpiQr
            ? \App\Support\UpiQr::svgDataUri($c->upi_id, $c->name, $payableAmount, $invoice->invoice_number ?: 'Invoice', 180)
            : null;
    @endphp

    @if ($c->bank_name || $c->bank_account_number || $c->upi_id)
        <div class="mt-6 p-4 bg-gray-50 rounded text-sm">
            <div class="text-xs font-semibold uppercase text-gray-500">{{ $invoice->isCancelled() ? 'Bank details' : 'Bank details for payment' }}</div>
            <div class="mt-2 flex gap-4 items-start">
                <div class="flex-1 grid grid-cols-2 gap-y-1 gap-x-6 text-xs">
                    @if ($c->bank_account_name)
                        <div class="col-span-2"><strong>Payee:</strong> {{ $c->bank_account_name }}</div>
                    @endif
                    @if ($c->bank_name)
                        <div><strong>Bank:</strong> {{ $c->bank_name }}</div>
                    @endif
                    @if ($c->bank_branch)
                        <div><strong>Branch:</strong> {{ $c->bank_branch }}</div>
                    @endif
                    @if ($c->bank_account_number)
                        <div><strong>A/c:</strong> <span class="font-mono">{{ $c->bank_account_number }}</span>@if ($c->bankAccountTypeLabel()) <span class="text-gray-500">({{ $c->bankAccountTypeLabel() }})</span>@endif</div>
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
                        <div class="mt-1 text-xs text-gray-600">Scan to pay {{ $currencySymbol }}{{ inr($payableAmount) }}</div>
                        <div class="text-xs text-gray-500">Any UPI app</div>
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

    <div class="mt-10 flex flex-wrap justify-between items-end gap-6 text-sm">
        <div class="text-xs text-gray-500">
            <div><strong>E. &amp; O.E.</strong> (Errors &amp; Omissions Excepted)</div>
            <div class="mt-0.5">Subject to <strong>{{ $c->city ?: 'India' }}</strong> jurisdiction</div>
            <div class="mt-2">This is a computer-generated invoice.</div>
        </div>
        {{-- "For <business>", the signature (or room to sign), then the signatory line. --}}
        @php $sig = \App\Support\BrandImage::fit($c->signature_path, 170, 54); @endphp
        <div class="w-52 text-center">
            <div class="font-semibold text-gray-900">For {{ $c->name }}</div>
            <div class="h-[60px] flex items-end justify-center">
                @if ($sig)
                    <img src="{{ $sig['url'] }}" alt="Authorised signature" width="{{ $sig['w'] }}" height="{{ $sig['h'] }}" style="width: {{ $sig['w'] }}px; height: {{ $sig['h'] }}px;">
                @endif
            </div>
            <div class="border-t border-gray-400 pt-1 mt-1 text-xs text-gray-500">Authorised signatory</div>
        </div>
    </div>
</div>
