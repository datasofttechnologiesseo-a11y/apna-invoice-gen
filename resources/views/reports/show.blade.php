<x-app-layout :title="$report->title()">
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="min-w-0">
                <div class="text-xs text-gray-500"><a href="{{ route('reports.index') }}" class="hover:text-brand-700">Reports</a> / {{ $report->group() }}</div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ $report->title() }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ $report->subtitle() }}</p>
            </div>
            <x-export-toolbar :report="$key" />
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="bg-white rounded-xl ring-1 ring-gray-200 p-3 flex flex-wrap items-end gap-3 text-sm">
                @if ($report->usesDates())
                    <div class="flex flex-wrap gap-1" role="group" aria-label="Period">
                        @foreach ($presets as $pk => $p)
                            @php $active = request('from') === $p['from']->toDateString() && request('to') === $p['to']->toDateString(); @endphp
                            <a href="{{ route('reports.show', array_merge(request()->except(['from', 'to', 'page']), ['report' => $key, 'from' => $p['from']->toDateString(), 'to' => $p['to']->toDateString()])) }}"
                               @class(['px-2.5 py-1.5 rounded-md text-xs font-semibold ring-1 transition', 'bg-brand-700 text-white ring-brand-700' => $active, 'bg-white text-gray-700 ring-gray-300 hover:ring-brand-400' => ! $active])>{{ $p['label'] }}</a>
                        @endforeach
                    </div>
                    <div>
                        <label for="from" class="block text-[10px] uppercase tracking-wider font-bold text-gray-500">From</label>
                        <input id="from" type="date" name="from" value="{{ request('from') }}" class="mt-0.5 border-gray-300 rounded-md text-sm">
                    </div>
                    <div>
                        <label for="to" class="block text-[10px] uppercase tracking-wider font-bold text-gray-500">To</label>
                        <input id="to" type="date" name="to" value="{{ request('to') }}" class="mt-0.5 border-gray-300 rounded-md text-sm">
                    </div>
                @endif
                <div class="flex-1 min-w-[12rem]">
                    <label for="search" class="block text-[10px] uppercase tracking-wider font-bold text-gray-500">Search</label>
                    <input id="search" type="text" name="search" value="{{ request('search') }}" placeholder="Number, name…" class="mt-0.5 w-full border-gray-300 rounded-md text-sm">
                </div>
                <button class="px-3 py-2 bg-gray-800 text-white rounded-md text-xs font-semibold">Apply</button>
                @if (request()->anyFilled(['from', 'to', 'search']))
                    <a href="{{ route('reports.show', $key) }}" class="text-xs text-gray-500 hover:text-gray-900">Clear</a>
                @endif
            </form>

            @if ($truncated)
                <div class="px-4 py-2 rounded-lg bg-accent-50 border border-accent-200 text-xs text-accent-900">
                    Showing the first {{ number_format($limit) }} rows. Totals include all rows. Download Excel or PDF for the full list.
                </div>
            @endif

            <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto max-h-[70vh]">
                    <table class="min-w-full text-sm data-grid">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider sticky top-0 z-10">
                            <tr>
                                @foreach ($columns as $col)
                                    <th class="px-3 py-2 whitespace-nowrap {{ in_array($col['type'] ?? 'text', ['money', 'qty', 'number', 'percent'], true) ? 'text-right' : 'text-left' }}">{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $row)
                                <tr class="hover:bg-brand-50/40">
                                    @foreach ($columns as $i => $col)
                                        @php $num = in_array($col['type'] ?? 'text', ['money', 'qty', 'number', 'percent'], true); @endphp
                                        <td class="px-3 py-1.5 whitespace-nowrap {{ $num ? 'text-right font-mono tabular-nums' : '' }}">
                                            @if ($i === 0 && ! empty($row['_url']))
                                                <a href="{{ $row['_url'] }}" class="text-brand-700 font-medium hover:underline">{{ $fmt($col, $row[$col['key']] ?? null) }}</a>
                                            @else
                                                {{ $fmt($col, $row[$col['key']] ?? null) }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($columns) }}" class="px-3 py-10 text-center text-gray-500">No records for this selection.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($totals && count($rows))
                            <tfoot class="bg-gray-50 font-semibold sticky bottom-0">
                                <tr>
                                    @foreach ($columns as $i => $col)
                                        <td class="px-3 py-2 whitespace-nowrap {{ array_key_exists($col['key'], $totals) ? 'text-right font-mono tabular-nums' : '' }}">{{ $i === 0 ? 'Total' : (array_key_exists($col['key'], $totals) ? $fmt($col, $totals[$col['key']]) : '') }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
