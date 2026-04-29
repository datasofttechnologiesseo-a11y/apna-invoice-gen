<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
            <div>
                <x-breadcrumbs :items="[
                    ['label' => 'Customers', 'href' => route('customers.index')],
                    ['label' => $customer->name],
                    ['label' => 'Ledger'],
                ]" />
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight mt-1">{{ $customer->name }} · Ledger</h2>
                @if ($customer->gstin)
                    <p class="text-xs text-gray-500 mt-1">GSTIN: <span class="font-mono">{{ $customer->gstin }}</span></p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('customers.edit', $customer) }}" class="text-sm text-gray-500 hover:text-gray-700">Edit customer</a>
                <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print Statement
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6 print:py-0">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4 print:max-w-none">

            {{-- Print header (only when printing) --}}
            <div class="hidden print:block mb-3">
                <div class="text-center border-b-2 border-gray-900 pb-2 mb-3">
                    <div class="text-xl font-bold">{{ $company->name }}</div>
                    @if ($company->gstin)<div class="text-xs">GSTIN: {{ $company->gstin }}</div>@endif
                    <div class="text-base font-semibold mt-1">CUSTOMER LEDGER</div>
                    <div class="text-sm text-gray-700">{{ $customer->name }}@if ($customer->gstin) · GSTIN: {{ $customer->gstin }}@endif</div>
                    <div class="text-xs text-gray-500">Generated: {{ now()->format('d M Y H:i') }}</div>
                </div>
            </div>

            {{-- Summary cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Total Invoiced</div>
                    <div class="mt-1 text-xl font-bold text-gray-900 font-mono tabular-nums">₹{{ number_format($totals['invoiced'], 2) }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Received</div>
                    <div class="mt-1 text-xl font-bold text-emerald-700 font-mono tabular-nums">₹{{ number_format($totals['received'], 2) }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Credit Notes</div>
                    <div class="mt-1 text-xl font-bold text-blue-700 font-mono tabular-nums">₹{{ number_format($totals['credited'], 2) }}</div>
                </div>
                <div class="bg-white border-2 border-amber-300 bg-amber-50 rounded-lg p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-amber-800">Outstanding</div>
                    <div class="mt-1 text-xl font-bold {{ $totals['outstanding'] > 0 ? 'text-amber-800' : 'text-emerald-700' }} font-mono tabular-nums">₹{{ number_format($totals['outstanding'], 2) }}</div>
                </div>
            </div>

            {{-- Customer card --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-1">Bill to</div>
                    <div class="font-semibold text-gray-900">{{ $customer->name }}</div>
                    @if ($customer->address_line1)
                        <div class="text-gray-600">{{ $customer->address_line1 }}</div>
                    @endif
                    @if ($customer->address_line2)
                        <div class="text-gray-600">{{ $customer->address_line2 }}</div>
                    @endif
                    @if ($customer->city || $customer->state || $customer->postal_code)
                        <div class="text-gray-600">{{ $customer->city }}{{ $customer->state ? ', ' . $customer->state->name : '' }}{{ $customer->postal_code ? ' - ' . $customer->postal_code : '' }}</div>
                    @endif
                    @if ($customer->gstin)<div class="text-gray-700 mt-1">GSTIN: <span class="font-mono">{{ $customer->gstin }}</span></div>@endif
                    @if ($customer->phone)<div class="text-gray-600 text-xs">{{ $customer->phone }}</div>@endif
                    @if ($customer->email)<div class="text-gray-600 text-xs">{{ $customer->email }}</div>@endif
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-1">Issued by</div>
                    <div class="font-semibold text-gray-900">{{ $company->name }}</div>
                    @if ($company->gstin)<div class="text-gray-700">GSTIN: <span class="font-mono">{{ $company->gstin }}</span></div>@endif
                    <div class="text-gray-600 text-xs mt-2">Statement as of {{ now()->format('d M Y') }}</div>
                </div>
            </div>

            {{-- Ledger table --}}
            <div class="bg-white shadow sm:rounded-lg overflow-hidden print:shadow-none">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[700px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider print:bg-white">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Date</th>
                                <th class="px-4 py-3 text-left font-semibold">Reference</th>
                                <th class="px-4 py-3 text-left font-semibold">Particulars</th>
                                <th class="px-4 py-3 text-right font-semibold">Debit (₹)</th>
                                <th class="px-4 py-3 text-right font-semibold">Credit (₹)</th>
                                <th class="px-4 py-3 text-right font-semibold">Balance (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($entries as $e)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ \Illuminate\Support\Carbon::parse($e['date'])->format('d M Y') }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">
                                        @if ($e['type'] === 'invoice' && isset($e['invoice']))
                                            <a href="{{ route('invoices.show', $e['invoice']) }}" class="text-brand-700 hover:underline">{{ $e['ref'] }}</a>
                                        @else
                                            <span class="text-gray-700">{{ $e['ref'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        {{ $e['particulars'] }}
                                        <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 ml-1">{{ str_replace('_', ' ', $e['type']) }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono tabular-nums {{ $e['debit'] > 0 ? '' : 'text-gray-300' }}">
                                        {{ $e['debit'] > 0 ? number_format($e['debit'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono tabular-nums {{ $e['credit'] > 0 ? 'text-emerald-700' : 'text-gray-300' }}">
                                        {{ $e['credit'] > 0 ? number_format($e['credit'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono tabular-nums font-semibold {{ $e['balance'] > 0 ? 'text-amber-800' : ($e['balance'] < 0 ? 'text-emerald-700' : 'text-gray-500') }}">
                                        {{ number_format(abs($e['balance']), 2) }} {{ $e['balance'] < 0 ? 'Cr' : ($e['balance'] > 0 ? 'Dr' : '') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">No invoices or payments yet for this customer.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($entries->isNotEmpty())
                            <tfoot class="bg-gray-50 print:bg-white">
                                <tr class="font-bold border-t-2 border-gray-300">
                                    <td colspan="3" class="px-4 py-3 text-right text-xs uppercase tracking-wider text-gray-700">Closing balance</td>
                                    <td class="px-4 py-3 text-right font-mono tabular-nums">₹{{ number_format($totals['invoiced'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono tabular-nums text-emerald-700">₹{{ number_format($totals['received'] + $totals['credited'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono tabular-nums {{ $totals['outstanding'] > 0 ? 'text-amber-800' : 'text-emerald-700' }}">
                                        ₹{{ number_format($totals['outstanding'], 2) }} {{ $totals['outstanding'] > 0 ? 'Dr' : ($totals['outstanding'] < 0 ? 'Cr' : '') }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="text-xs text-gray-500 print:text-[8.5px]">
                <strong>Dr</strong> = customer owes us · <strong>Cr</strong> = we owe customer (advance) · E&amp;OE
            </div>
        </div>
    </div>

    <style>
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            body { background: white !important; font-size: 10px; }
            table { font-size: 9px !important; }
            th, td { padding: 4px 6px !important; }
        }
    </style>
</x-app-layout>
