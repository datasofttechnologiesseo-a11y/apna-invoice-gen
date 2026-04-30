<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
            <div>
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ $memo->memo_number }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $memo->memo_date->format('d M Y') }} · ₹{{ number_format((float) $memo->grand_total, 2) }}</p>
            </div>
            @php
                $waMsg = "*Cash Memo " . $memo->memo_number . "*\n"
                    . "Date: " . $memo->memo_date->format('d M Y') . "\n"
                    . "From: " . $memo->seller_name . "\n"
                    . "Bill To: " . $memo->company->name . "\n"
                    . "Amount: ₹" . number_format((float) $memo->grand_total, 2) . "\n"
                    . "Payment: " . strtoupper($memo->payment_mode);
                $waUrl = 'https://wa.me/?text=' . rawurlencode($waMsg);
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('finance.cash-memos.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← All memos</a>
                <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2 bg-[#25D366] hover:bg-[#1ebe5b] text-white font-semibold rounded-lg text-sm" title="Share details via WhatsApp">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    WhatsApp
                </a>
                <a href="{{ route('finance.cash-memos.pdf', $memo) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-sm" title="Download as PDF file">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    PDF
                </a>
                <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg text-sm" title="Print only the cash memo">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print
                </button>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 print:hidden">
            <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{{ session('status') }}</div>
        </div>
    @endif

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 print:hidden">
        @include('finance.partials.tabs')
    </div>

    <div class="py-6 print:py-0">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 print:px-0 print:max-w-none">
            <div class="bg-white shadow-lg sm:rounded-lg p-8 sm:p-10 print:shadow-none print:rounded-none print:p-6 cash-memo-doc">

                {{-- ─── Seller letterhead (issuer) ─── --}}
                <div class="text-center border-b-2 border-gray-900 pb-3 mb-3">
                    <div class="text-xl font-bold tracking-wide text-gray-900">{{ $memo->seller_name }}</div>
                    @if ($memo->seller_address)
                        <div class="text-sm text-gray-700 whitespace-pre-line mt-1">{{ $memo->seller_address }}</div>
                    @endif
                    @if ($memo->seller_state || $memo->seller_phone || $memo->seller_gstin)
                        <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-gray-600 mt-1">
                            @if ($memo->seller_state)<span><strong>State:</strong> {{ $memo->seller_state }}</span>@endif
                            @if ($memo->seller_phone)<span><strong>Phone:</strong> {{ $memo->seller_phone }}</span>@endif
                            @if ($memo->seller_gstin)<span><strong>GSTIN:</strong> <span class="font-mono">{{ $memo->seller_gstin }}</span></span>@endif
                        </div>
                    @endif
                </div>

                {{-- ─── Document title ─── --}}
                <div class="text-center border-b border-gray-300 pb-2 mb-5">
                    <div class="text-base font-bold tracking-[0.3em] text-gray-900 uppercase">Cash Memo</div>
                </div>

                {{-- ─── Bill To (buyer) + Memo metadata ─── --}}
                <div class="grid grid-cols-2 gap-6 mb-6 text-sm">
                    <div>
                        <div class="text-[10px] uppercase text-gray-500 tracking-wider font-bold mb-1">Bill To</div>
                        <div class="font-semibold text-gray-900 text-base">{{ $memo->company->name }}</div>
                        @if ($memo->company->address_line1)
                            <div class="text-gray-700">{{ $memo->company->address_line1 }}</div>
                        @endif
                        @if ($memo->company->address_line2)
                            <div class="text-gray-700">{{ $memo->company->address_line2 }}</div>
                        @endif
                        <div class="text-gray-700">
                            {{ $memo->company->city }}{{ $memo->company->state ? ', ' . $memo->company->state->name : '' }}{{ $memo->company->postal_code ? ' - ' . $memo->company->postal_code : '' }}
                        </div>
                        @if ($memo->company->phone)
                            <div class="text-gray-600 text-xs mt-1">Phone: {{ $memo->company->phone }}</div>
                        @endif
                        @if ($memo->company->email)
                            <div class="text-gray-600 text-xs">Email: {{ $memo->company->email }}</div>
                        @endif
                        @if ($memo->company->gstin)
                            <div class="text-gray-700 text-xs mt-1">GSTIN: <span class="font-mono">{{ $memo->company->gstin }}</span></div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-sm">
                            <div class="text-gray-500 text-right text-xs uppercase tracking-wider">Memo No.</div>
                            <div class="font-mono font-semibold text-gray-900">{{ $memo->memo_number }}</div>
                            <div class="text-gray-500 text-right text-xs uppercase tracking-wider">Date</div>
                            <div class="font-semibold text-gray-900">{{ $memo->memo_date->format('d M Y') }}</div>
                            <div class="text-gray-500 text-right text-xs uppercase tracking-wider">Payment</div>
                            <div class="font-semibold text-gray-900 uppercase">{{ $memo->payment_mode }}</div>
                            @if ($memo->reference_number)
                                <div class="text-gray-500 text-right text-xs uppercase tracking-wider">Reference</div>
                                <div class="font-mono text-gray-900">{{ $memo->reference_number }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ─── Particulars ─── --}}
                <table class="w-full text-sm border-collapse mb-4">
                    <thead>
                        <tr class="bg-gray-900 text-white">
                            <th class="px-2 py-2 text-left text-xs uppercase font-semibold w-10">#</th>
                            <th class="px-2 py-2 text-left text-xs uppercase font-semibold">Particulars</th>
                            <th class="px-2 py-2 text-left text-xs uppercase font-semibold w-24">HSN/SAC</th>
                            <th class="px-2 py-2 text-right text-xs uppercase font-semibold w-20">Qty</th>
                            <th class="px-2 py-2 text-right text-xs uppercase font-semibold w-24">Rate (₹)</th>
                            <th class="px-2 py-2 text-right text-xs uppercase font-semibold w-28">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($memo->items as $i => $item)
                            <tr class="border-b border-gray-200">
                                <td class="px-2 py-2 text-gray-600">{{ $i + 1 }}</td>
                                <td class="px-2 py-2 text-gray-900">{{ $item->description }}</td>
                                <td class="px-2 py-2 font-mono text-gray-700">{{ $item->hsn_sac ?: '—' }}</td>
                                <td class="px-2 py-2 text-right font-mono">
                                    {{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}
                                    @if ($item->unit)<span class="text-gray-500 text-xs">{{ $item->unit }}</span>@endif
                                </td>
                                <td class="px-2 py-2 text-right font-mono">{{ number_format((float) $item->rate, 2) }}</td>
                                <td class="px-2 py-2 text-right font-mono font-semibold">{{ number_format((float) $item->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- ─── Totals + Amount in words ─── --}}
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div class="text-sm">
                        <div class="text-[10px] uppercase text-gray-500 tracking-wider font-bold mb-1">Amount in words</div>
                        <div class="text-gray-900 italic">{{ $memo->amount_in_words }}</div>
                        @if ($memo->notes)
                            <div class="mt-4">
                                <div class="text-[10px] uppercase text-gray-500 tracking-wider font-bold mb-1">Notes</div>
                                <div class="text-gray-700 text-sm whitespace-pre-line">{{ $memo->notes }}</div>
                            </div>
                        @endif
                    </div>
                    <div class="text-sm">
                        <table class="w-full">
                            <tbody>
                                <tr><td class="py-1 text-gray-600">Subtotal</td><td class="py-1 text-right font-mono">₹ {{ number_format((float) $memo->subtotal, 2) }}</td></tr>
                                @if ((float) $memo->discount > 0)
                                    <tr><td class="py-1 text-gray-600">Discount</td><td class="py-1 text-right font-mono text-red-700">− ₹ {{ number_format((float) $memo->discount, 2) }}</td></tr>
                                @endif
                                <tr class="border-t border-gray-200"><td class="py-1 font-medium">Taxable value</td><td class="py-1 text-right font-mono font-medium">₹ {{ number_format((float) $memo->taxable_value, 2) }}</td></tr>
                                @if ((float) $memo->total_cgst > 0)
                                    <tr><td class="py-1 text-gray-600">CGST</td><td class="py-1 text-right font-mono">₹ {{ number_format((float) $memo->total_cgst, 2) }}</td></tr>
                                    <tr><td class="py-1 text-gray-600">SGST</td><td class="py-1 text-right font-mono">₹ {{ number_format((float) $memo->total_sgst, 2) }}</td></tr>
                                @endif
                                @if ((float) $memo->total_igst > 0)
                                    <tr><td class="py-1 text-gray-600">IGST</td><td class="py-1 text-right font-mono">₹ {{ number_format((float) $memo->total_igst, 2) }}</td></tr>
                                @endif
                                @if ((float) $memo->round_off != 0)
                                    <tr><td class="py-1 text-gray-500 text-xs">Round off</td><td class="py-1 text-right font-mono text-xs text-gray-500">{{ ((float) $memo->round_off >= 0 ? '+ ' : '− ') }}₹ {{ number_format(abs((float) $memo->round_off), 2) }}</td></tr>
                                @endif
                                <tr class="border-t-2 border-gray-900"><td class="py-2 font-bold text-base">Grand Total</td><td class="py-2 text-right font-mono font-bold text-base">₹ {{ number_format((float) $memo->grand_total, 2) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ─── Footer: signature + received with thanks ─── --}}
                <div class="grid grid-cols-2 gap-6 mt-12 pt-4 text-sm">
                    <div>
                        <div class="text-gray-500 text-xs">Received with thanks. Goods/services once sold are not returnable except as per agreement.</div>
                    </div>
                    <div class="text-right">
                        <div class="border-t border-gray-400 pt-2 inline-block min-w-[180px]">
                            <div class="text-xs text-gray-600">For <strong>{{ $memo->seller_name }}</strong></div>
                            <div class="text-[10px] text-gray-400 mt-6">Authorised Signatory</div>
                        </div>
                    </div>
                </div>

                {{-- E&OE --}}
                <div class="text-center text-[10px] text-gray-400 mt-6 pt-2 border-t border-gray-100">
                    This is a computer-generated cash memo. E&amp;OE.
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            html, body { background: white !important; margin: 0 !important; padding: 0 !important; }

            /* Hide everything by default — navbar, topbar, tabs, footer, banners… */
            body * { visibility: hidden !important; }

            /* Then re-show only the memo document and its descendants */
            .cash-memo-doc, .cash-memo-doc * { visibility: visible !important; }

            /* Pull the memo to the top-left so it prints alone, not pushed down by hidden ancestors */
            .cash-memo-doc {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }
    </style>
</x-app-layout>
