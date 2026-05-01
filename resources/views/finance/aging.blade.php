<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Receivables Aging</h2>
                <p class="text-sm text-gray-500 mt-1">As on {{ $today->format('d M Y') }} · {{ $company->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <a href="{{ route('finance.aging.export.pdf') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded shadow-sm"
                   title="Download a landscape A4 aging report — perfect for sending to a recovery team or your CA">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download PDF
                </a>
                <a href="{{ route('finance.aging.export.csv') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h13M3 7h13v6m0 0H3"/></svg>
                    Excel / CSV
                </a>
                <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold rounded shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8 print:py-0">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Finance section tabs (hidden in print) --}}
            <div class="print:hidden">
                @include('finance.partials.tabs')
            </div>

            {{-- Empty state — celebrated, not warned --}}
            @if ($summary['invoices'] === 0)
                <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-12 text-center">
                    <div class="w-16 h-16 mx-auto rounded-full bg-money-100 text-money-700 flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="mt-4 font-display text-xl font-extrabold text-gray-900">All clear — nothing outstanding!</h3>
                    <p class="mt-2 text-gray-500 max-w-md mx-auto">Every finalized invoice is fully paid. When customers fall behind, this page will show you who and by how much.</p>
                    <a href="{{ route('invoices.index') }}" class="mt-6 inline-flex items-center gap-1.5 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-lg">View all invoices →</a>
                </div>
            @else

                {{-- ─── Headline summary tiles ─── --}}
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    <div class="p-5 rounded-xl bg-gradient-to-br from-accent-50 to-saffron-50 ring-1 ring-accent-200">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-accent-800">Total outstanding</div>
                        <div class="mt-1 font-display text-xl sm:text-2xl font-extrabold text-accent-900 tabular-nums">₹{{ number_format($summary['total'], 2) }}</div>
                        <div class="mt-1 text-xs text-accent-700">{{ $summary['invoices'] }} {{ Str::plural('invoice', $summary['invoices']) }} · {{ $summary['customers'] }} {{ Str::plural('customer', $summary['customers']) }}</div>
                    </div>
                    <div class="p-5 rounded-xl bg-money-50 ring-1 ring-money-200">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-money-800">Current (≤ 30 days)</div>
                        <div class="mt-1 font-display text-xl sm:text-2xl font-extrabold text-money-900 tabular-nums">₹{{ number_format($summary['current'], 2) }}</div>
                        <div class="mt-1 text-xs text-money-700">{{ $summary['total'] > 0 ? round(($summary['current'] / $summary['total']) * 100) : 0 }}% of total</div>
                    </div>
                    <div class="p-5 rounded-xl bg-amber-50 ring-1 ring-amber-200">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-amber-800">31 – 60 days</div>
                        <div class="mt-1 font-display text-xl sm:text-2xl font-extrabold text-amber-900 tabular-nums">₹{{ number_format($summary['b30_60'], 2) }}</div>
                        <div class="mt-1 text-xs text-amber-700">{{ $summary['total'] > 0 ? round(($summary['b30_60'] / $summary['total']) * 100) : 0 }}% of total</div>
                    </div>
                    <div class="p-5 rounded-xl bg-orange-50 ring-1 ring-orange-200">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-orange-800">61 – 90 days</div>
                        <div class="mt-1 font-display text-xl sm:text-2xl font-extrabold text-orange-900 tabular-nums">₹{{ number_format($summary['b60_90'], 2) }}</div>
                        <div class="mt-1 text-xs text-orange-700">{{ $summary['total'] > 0 ? round(($summary['b60_90'] / $summary['total']) * 100) : 0 }}% of total</div>
                    </div>
                    <div class="p-5 rounded-xl bg-red-50 ring-1 ring-red-200">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-red-800">91+ days</div>
                        <div class="mt-1 font-display text-xl sm:text-2xl font-extrabold text-red-900 tabular-nums">₹{{ number_format($summary['b90_plus'], 2) }}</div>
                        <div class="mt-1 text-xs text-red-700">{{ $summary['total'] > 0 ? round(($summary['b90_plus'] / $summary['total']) * 100) : 0 }}% — chase / write-off</div>
                    </div>
                </div>

                {{-- ─── Action context (hidden in print) ─── --}}
                @if ($summary['b90_plus'] > 0)
                    <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-900 print:hidden">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 shrink-0 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
                            <div>
                                <strong>You have ₹{{ number_format($summary['b90_plus'], 2) }} overdue by 90+ days.</strong>
                                These need urgent follow-up — beyond 90 days, recovery probability drops sharply. Consider sending payment reminders or an issuing-credit-note conversation.
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ─── Customer-level breakdown table (desktop) ─── --}}
                <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden hidden md:block printable">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <h3 class="font-display font-bold text-gray-900">By customer</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Sorted by total outstanding (chase the biggest first)</p>
                        </div>
                        <span class="text-xs text-gray-500">{{ $byCustomer->count() }} {{ Str::plural('customer', $byCustomer->count()) }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-5 py-3 text-left font-semibold">Customer</th>
                                    <th class="px-5 py-3 text-right font-semibold">Invoices</th>
                                    <th class="px-5 py-3 text-right font-semibold">Oldest</th>
                                    <th class="px-5 py-3 text-right font-semibold text-money-700">Current</th>
                                    <th class="px-5 py-3 text-right font-semibold text-amber-700">31–60</th>
                                    <th class="px-5 py-3 text-right font-semibold text-orange-700">61–90</th>
                                    <th class="px-5 py-3 text-right font-semibold text-red-700">91+</th>
                                    <th class="px-5 py-3 text-right font-semibold">Total Outstanding</th>
                                    <th class="px-5 py-3 text-right font-semibold print:hidden"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($byCustomer as $c)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3">
                                            <div class="font-medium text-gray-900">{{ $c['name'] }}</div>
                                            @if ($c['gstin'])
                                                <div class="text-xs text-gray-500 font-mono">{{ $c['gstin'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-700">{{ $c['invoice_count'] }}</td>
                                        <td class="px-5 py-3 text-right font-mono tabular-nums {{ $c['oldest_days'] > 90 ? 'text-red-700 font-semibold' : ($c['oldest_days'] > 60 ? 'text-orange-700' : ($c['oldest_days'] > 30 ? 'text-amber-700' : 'text-gray-600')) }}">
                                            {{ $c['oldest_days'] }}d
                                        </td>
                                        <td class="px-5 py-3 text-right font-mono tabular-nums text-money-700">{{ $c['current'] > 0 ? '₹' . number_format($c['current'], 2) : '—' }}</td>
                                        <td class="px-5 py-3 text-right font-mono tabular-nums text-amber-700">{{ $c['b30_60'] > 0 ? '₹' . number_format($c['b30_60'], 2) : '—' }}</td>
                                        <td class="px-5 py-3 text-right font-mono tabular-nums text-orange-700">{{ $c['b60_90'] > 0 ? '₹' . number_format($c['b60_90'], 2) : '—' }}</td>
                                        <td class="px-5 py-3 text-right font-mono tabular-nums text-red-700 font-semibold">{{ $c['b90_plus'] > 0 ? '₹' . number_format($c['b90_plus'], 2) : '—' }}</td>
                                        <td class="px-5 py-3 text-right font-mono font-bold tabular-nums text-gray-900">₹{{ number_format($c['total'], 2) }}</td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap print:hidden">
                                            <a href="{{ route('customers.ledger', $c['customer_id']) }}" class="text-money-700 hover:underline text-xs font-semibold">Ledger →</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-900">
                                <tr>
                                    <td class="px-5 py-3 font-bold uppercase text-xs text-gray-700" colspan="3">TOTAL</td>
                                    <td class="px-5 py-3 text-right font-mono font-bold tabular-nums text-money-700">₹{{ number_format($summary['current'], 2) }}</td>
                                    <td class="px-5 py-3 text-right font-mono font-bold tabular-nums text-amber-700">₹{{ number_format($summary['b30_60'], 2) }}</td>
                                    <td class="px-5 py-3 text-right font-mono font-bold tabular-nums text-orange-700">₹{{ number_format($summary['b60_90'], 2) }}</td>
                                    <td class="px-5 py-3 text-right font-mono font-bold tabular-nums text-red-700">₹{{ number_format($summary['b90_plus'], 2) }}</td>
                                    <td class="px-5 py-3 text-right font-mono font-bold tabular-nums text-gray-900 text-base">₹{{ number_format($summary['total'], 2) }}</td>
                                    <td class="px-5 py-3 print:hidden"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- ─── Mobile cards: one per customer ─── --}}
                <div class="md:hidden space-y-2 print:hidden">
                    @foreach ($byCustomer as $c)
                        <div class="bg-white rounded-xl ring-1 ring-gray-100 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">{{ $c['name'] }}</div>
                                    @if ($c['gstin'])
                                        <div class="text-xs text-gray-500 font-mono">{{ $c['gstin'] }}</div>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-display text-lg font-extrabold text-gray-900 tabular-nums">₹{{ number_format($c['total']) }}</div>
                                    <div class="text-[10px] text-gray-500">{{ $c['invoice_count'] }} {{ Str::plural('invoice', $c['invoice_count']) }} · oldest {{ $c['oldest_days'] }}d</div>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-4 gap-1 text-[10px]">
                                <div class="px-2 py-1 rounded bg-money-50 text-money-800 text-center">
                                    <div class="font-semibold">≤30d</div>
                                    <div class="font-mono mt-0.5">{{ $c['current'] > 0 ? '₹' . number_format($c['current']) : '—' }}</div>
                                </div>
                                <div class="px-2 py-1 rounded bg-amber-50 text-amber-800 text-center">
                                    <div class="font-semibold">31–60</div>
                                    <div class="font-mono mt-0.5">{{ $c['b30_60'] > 0 ? '₹' . number_format($c['b30_60']) : '—' }}</div>
                                </div>
                                <div class="px-2 py-1 rounded bg-orange-50 text-orange-800 text-center">
                                    <div class="font-semibold">61–90</div>
                                    <div class="font-mono mt-0.5">{{ $c['b60_90'] > 0 ? '₹' . number_format($c['b60_90']) : '—' }}</div>
                                </div>
                                <div class="px-2 py-1 rounded bg-red-50 text-red-800 text-center">
                                    <div class="font-semibold">91+</div>
                                    <div class="font-mono mt-0.5">{{ $c['b90_plus'] > 0 ? '₹' . number_format($c['b90_plus']) : '—' }}</div>
                                </div>
                            </div>
                            <a href="{{ route('customers.ledger', $c['customer_id']) }}" class="mt-3 inline-block text-xs text-money-700 font-semibold hover:underline">View ledger →</a>
                        </div>
                    @endforeach
                </div>

                {{-- ─── Invoice-level detail (collapsed by default, opens for power users) ─── --}}
                <details class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 print:hidden group">
                    <summary class="px-5 py-4 cursor-pointer flex items-center justify-between font-semibold text-gray-900 hover:bg-gray-50 rounded-2xl">
                        <span>Show invoice-level breakdown ({{ $rows->count() }} {{ Str::plural('invoice', $rows->count()) }})</span>
                        <svg class="w-5 h-5 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="overflow-x-auto border-t border-gray-100">
                        <table class="w-full text-sm min-w-[800px]">
                            <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-5 py-3 text-left font-semibold">Invoice #</th>
                                    <th class="px-5 py-3 text-left font-semibold">Customer</th>
                                    <th class="px-5 py-3 text-left font-semibold">Date</th>
                                    <th class="px-5 py-3 text-left font-semibold">Due</th>
                                    <th class="px-5 py-3 text-right font-semibold">Days</th>
                                    <th class="px-5 py-3 text-left font-semibold">Bucket</th>
                                    <th class="px-5 py-3 text-right font-semibold">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows->sortByDesc('days_overdue') as $r)
                                    @php
                                        $bucketColor = match($r['bucket']) {
                                            'current' => 'bg-money-100 text-money-800',
                                            '30-60'   => 'bg-amber-100 text-amber-800',
                                            '60-90'   => 'bg-orange-100 text-orange-800',
                                            '90+'     => 'bg-red-100 text-red-800',
                                        };
                                        $bucketLabel = match($r['bucket']) {
                                            'current' => '≤ 30 days',
                                            '30-60'   => '31–60 days',
                                            '60-90'   => '61–90 days',
                                            '90+'     => '91+ days',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 font-mono text-xs">
                                            <a href="{{ route('invoices.show', $r['invoice_id']) }}" class="text-brand-700 hover:underline font-medium">{{ $r['invoice_number'] }}</a>
                                        </td>
                                        <td class="px-5 py-3">{{ $r['customer_name'] }}</td>
                                        <td class="px-5 py-3 text-xs text-gray-600">{{ $r['invoice_date']?->format('d M Y') }}</td>
                                        <td class="px-5 py-3 text-xs text-gray-600">{{ $r['due_date']?->format('d M Y') ?? '—' }}</td>
                                        <td class="px-5 py-3 text-right font-mono tabular-nums">{{ $r['days_overdue'] }}d</td>
                                        <td class="px-5 py-3"><span class="inline-block text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded {{ $bucketColor }}">{{ $bucketLabel }}</span></td>
                                        <td class="px-5 py-3 text-right font-mono font-semibold tabular-nums">₹{{ number_format($r['balance'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </div>
    </div>

    {{-- Print mode: show only the customer-level table; hide everything else (nav, tabs, mobile cards, action panels) --}}
    <style>
        @media print {
            @page { size: A4 landscape; margin: 12mm; }
            body { background: #fff !important; }
            * { color: #000 !important; background: #fff !important; }
            .shadow, .shadow-sm, [class*="ring-"] { box-shadow: none !important; }
            .printable { border: 1px solid #000 !important; }
        }
    </style>
</x-app-layout>
