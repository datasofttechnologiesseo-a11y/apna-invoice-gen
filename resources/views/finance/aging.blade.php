<x-app-layout title="Receivables aging">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Reports', 'href' => route('reports.index')],
            ['label' => 'Receivables aging'],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Receivables aging</h1>
                <p class="text-sm text-gray-500 mt-1">As on {{ $today->format('d M Y') }} · days past the due date · {{ $company->name }}</p>
            </div>
            <x-export-toolbar
                :xlsx="\App\Support\Team::can('reports.export') ? route('reports.export', ['report' => 'receivables-aging', 'format' => 'xlsx']) : null"
                :pdf="route('finance.aging.export.pdf')"
                :items="[['label' => 'CSV, invoice by invoice', 'href' => route('finance.aging.export.csv')]]"
                print="window" />
        </div>
    </x-slot>

    <div class="py-6 sm:py-8 print:py-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @include('reports.partials.aging', [
                'partyLabel' => 'Customer',
                'docLabel' => 'invoice',
                'emptyText' => 'Every issued invoice is fully paid.',
                'parties' => $byCustomer->map(fn ($c) => [
                    'name' => $c['name'],
                    'gstin' => $c['gstin'],
                    'count' => $c['invoice_count'],
                    'oldest' => $c['oldest_days'],
                    'b0_30' => $c['current'],
                    'b31_60' => $c['b30_60'],
                    'b61_90' => $c['b60_90'],
                    'b90' => $c['b90_plus'],
                    'total' => $c['total'],
                    'url' => $c['customer_id'] ? route('customers.ledger', $c['customer_id']) : null,
                ])->all(),
            ])

            @if ($summary['invoices'] > 0)
                @if ($summary['b90_plus'] > 0)
                    <div class="p-4 rounded-xl bg-danger-50 border border-danger-200 text-sm text-danger-900 print:hidden">
                        <strong>₹{{ rupees($summary['b90_plus']) }} is overdue by more than 90 days.</strong>
                        Send payment reminders, or settle the balance with a credit note.
                    </div>
                @endif

                {{-- ─── Invoice-level detail (collapsed by default, opens for power users) ─── --}}
                <details class="bg-white rounded-xl ring-1 ring-gray-200 print:hidden group">
                    <summary class="px-5 py-4 cursor-pointer flex items-center justify-between font-semibold text-gray-900 hover:bg-gray-50 rounded-xl">
                        <span>Invoice-wise breakdown ({{ $rows->count() }} {{ Str::plural('invoice', $rows->count()) }})</span>
                        <svg class="w-5 h-5 text-gray-500 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="overflow-x-auto border-t border-gray-100">
                        <table class="w-full text-sm min-w-[800px]">
                            <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
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
                                            'current' => 'bg-gray-100 text-gray-700',
                                            '30-60'   => 'bg-accent-50 text-accent-800',
                                            '60-90'   => 'bg-accent-50 text-accent-800',
                                            '90+'     => 'bg-danger-50 text-danger-700',
                                        };
                                        $bucketLabel = match($r['bucket']) {
                                            'current' => '0–30 days',
                                            '30-60'   => '31–60 days',
                                            '60-90'   => '61–90 days',
                                            '90+'     => 'Over 90 days',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 font-mono text-xs">
                                            <a href="{{ route('invoices.show', $r['invoice_id']) }}" class="text-brand-700 hover:underline font-medium">{{ $r['invoice_number'] }}</a>
                                        </td>
                                        <td class="px-5 py-3">{{ $r['customer_name'] }}</td>
                                        <td class="px-5 py-3 text-xs text-gray-600">{{ $r['invoice_date']?->format('d M Y') }}</td>
                                        <td class="px-5 py-3 text-xs text-gray-600">{{ $r['due_date']?->format('d M Y') ?? '–' }}</td>
                                        <td class="px-5 py-3 text-right tabular-nums">{{ $r['days_overdue'] }}</td>
                                        <td class="px-5 py-3"><span class="inline-block text-xs font-medium whitespace-nowrap px-2 py-0.5 rounded-full {{ $bucketColor }}">{{ $bucketLabel }}</span></td>
                                        <td class="px-5 py-3 text-right font-mono font-semibold tabular-nums">₹{{ rupees($r['balance']) }}</td>
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
