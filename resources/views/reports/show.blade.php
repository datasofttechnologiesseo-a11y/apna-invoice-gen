@php
    $screenTitle = \App\Http\Controllers\ReportController::screenTitle($key, $report);

    // Registers are read-only: say where their records come from.
    $source = [
        'credit-notes' => ['To add one, open an invoice and choose Issue credit note.', route('invoices.index'), 'Invoices'],
        'receipts' => ['To add one, open an invoice and choose Record payment.', route('invoices.index'), 'Invoices'],
        'vendor-payments' => ['To add one, open a purchase invoice and choose Record payment.', route('purchases.index'), 'Purchase invoices'],
    ][$key] ?? null;

    $aging = in_array($key, ['receivables-aging', 'payables-aging'], true);
    $numeric = fn (array $col) => in_array($col['type'] ?? 'text', ['money', 'qty', 'number', 'percent'], true);
    // On screen: ₹ on every amount, and a zero amount shows as a dash.
    $cell = function (array $col, $value) use ($fmt) {
        if (($col['type'] ?? 'text') === 'money' && empty($col['unit_price'])) {
            if ($value === null || $value === '' || (float) $value == 0.0) {
                return '–';
            }

            return (float) $value < 0 ? '-₹' . ltrim($fmt($col, abs((float) $value)), '-') : '₹' . $fmt($col, $value);
        }

        return $fmt($col, $value);
    };
    $filterKeys = ['from', 'to', 'search', 'page'];
@endphp
<x-app-layout :title="$screenTitle">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Reports', 'href' => route('reports.index')],
            ['label' => $screenTitle],
        ]" />
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="min-w-0">
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ $screenTitle }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ $report->subtitle() }}</p>
                @if ($source)
                    <p class="text-sm text-gray-600 mt-1">{{ $source[0] }} <a href="{{ $source[1] }}" class="text-brand-700 font-semibold hover:underline">{{ $source[2] }}</a></p>
                @endif
            </div>
            <x-export-toolbar :report="$key" />
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if ($aging)
                @include('reports.partials.aging', [
                    'partyLabel' => $key === 'payables-aging' ? 'Vendor' : 'Customer',
                    'docLabel' => $key === 'payables-aging' ? 'purchase invoice' : 'invoice',
                    'emptyText' => $key === 'payables-aging' ? 'Every purchase invoice is fully paid.' : 'Every issued invoice is fully paid.',
                    'parties' => collect($rows)->map(fn ($r) => [
                        'name' => $r['party'] ?? '–',
                        'gstin' => $r['gstin'] ?? null,
                        'count' => (int) ($r['count'] ?? 0),
                        'oldest' => (int) ($r['oldest'] ?? 0),
                        'b0_30' => (float) ($r['b0_30'] ?? 0),
                        'b31_60' => (float) ($r['b31_60'] ?? 0),
                        'b61_90' => (float) ($r['b61_90'] ?? 0),
                        'b90' => (float) ($r['b90'] ?? 0),
                        'total' => (float) ($r['total'] ?? 0),
                        'url' => null,
                    ])->all(),
                ])
            @else
            <form method="GET" class="bg-white rounded-xl ring-1 ring-gray-200 p-3 sm:p-4 flex flex-wrap items-end gap-3 text-sm">
                {{-- Keep the report's own filters (status, type…) when the period or search changes. --}}
                @foreach (request()->except($filterKeys) as $name => $value)
                    @if (is_scalar($value))
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                @if ($report->usesDates())
                    <div class="w-full flex flex-wrap gap-1.5" role="group" aria-label="Period">
                        @foreach ($presets as $pk => $p)
                            @php $active = request('from') === $p['from']->toDateString() && request('to') === $p['to']->toDateString(); @endphp
                            <a href="{{ route('reports.show', array_merge(request()->except(['from', 'to', 'page']), ['report' => $key, 'from' => $p['from']->toDateString(), 'to' => $p['to']->toDateString()])) }}"
                               @if ($active) aria-current="true" @endif
                               @class(['inline-flex items-center h-9 px-3 rounded-md text-sm font-medium ring-1 ring-inset transition', 'bg-brand-700 text-white ring-brand-700' => $active, 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' => ! $active])>{{ $p['label'] }}</a>
                        @endforeach
                    </div>
                    <div>
                        <label for="from" class="block text-sm font-medium text-gray-700">From</label>
                        <input id="from" type="date" name="from" value="{{ request('from') }}" class="mt-1 h-10 border-gray-300 rounded-md text-sm">
                    </div>
                    <div>
                        <label for="to" class="block text-sm font-medium text-gray-700">To</label>
                        <input id="to" type="date" name="to" value="{{ request('to') }}" class="mt-1 h-10 border-gray-300 rounded-md text-sm">
                    </div>
                @endif
                <div class="flex-1 min-w-[12rem]">
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input id="search" type="text" name="search" value="{{ request('search') }}" placeholder="Number, name…" class="mt-1 h-10 w-full border-gray-300 rounded-md text-sm">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-md bg-white ring-1 ring-inset ring-gray-300 text-sm font-semibold text-gray-800 hover:bg-gray-50">Apply</button>
                    @if (request()->anyFilled(['from', 'to', 'search']))
                        <a href="{{ route('reports.show', array_merge(request()->except($filterKeys), ['report' => $key])) }}" class="inline-flex items-center h-10 px-2 text-sm text-gray-600 hover:text-gray-900">Clear</a>
                    @endif
                </div>
            </form>

            @if ($truncated)
                <div class="px-4 py-2 rounded-lg bg-accent-50 border border-accent-200 text-sm text-accent-900">
                    Showing the first {{ number_format($limit) }} rows. Totals include all rows. Download Excel or PDF for the full list.
                </div>
            @endif

            <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                <div class="overflow-x-auto max-h-[70vh]">
                    <table class="min-w-full text-sm data-grid">
                        <thead class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase sticky top-0 z-10">
                            <tr>
                                @foreach ($columns as $col)
                                    <th class="px-3 py-2.5 whitespace-nowrap {{ $numeric($col) ? 'text-right' : 'text-left' }}">{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $row)
                                <tr class="hover:bg-brand-50/40">
                                    @foreach ($columns as $i => $col)
                                        @php $text = $cell($col, $row[$col['key']] ?? null); @endphp
                                        <td class="px-3 py-2 whitespace-nowrap {{ $numeric($col) ? 'text-right tabular-nums' : '' }} {{ $text === '–' ? 'text-gray-400' : '' }}">
                                            @if ($i === 0 && ! empty($row['_url']))
                                                <a href="{{ $row['_url'] }}" class="text-brand-700 font-medium hover:underline">{{ $text }}</a>
                                            @else
                                                {{ $text }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) }}" class="px-3 py-10 text-center text-gray-500">
                                        No records for this selection.
                                        @if ($source)
                                            <span class="block mt-1">{{ $source[0] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($totals && count($rows))
                            <tfoot class="bg-gray-50 font-semibold sticky bottom-0">
                                <tr>
                                    @foreach ($columns as $i => $col)
                                        <td class="px-3 py-2.5 whitespace-nowrap {{ array_key_exists($col['key'], $totals) ? 'text-right tabular-nums' : '' }}">{{ $i === 0 ? 'Total' : (array_key_exists($col['key'], $totals) ? $cell($col, $totals[$col['key']]) : '') }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
