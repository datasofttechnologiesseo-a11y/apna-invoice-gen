<x-app-layout :title="'Import ' . Str::lower($importer->title()) . ' (preview)'">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => $importer->title(), 'href' => $importer->listRoute()],
            ['label' => 'Import', 'href' => route('imports.show', $importer->key())],
            ['label' => 'Preview'],
        ]" />
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Check before importing</h1>
        <p class="text-sm text-gray-500 mt-1 break-words">{{ $fileName }} · {{ count($results) }} {{ Str::plural('row', count($results)) }} read · not saved yet</p>
    </x-slot>

    @php
        $fields = $importer->fields();
        // Every column read from the file, so what will be saved can be checked
        // (purchase price, opening stock, low stock alert included).
        $shown = $mapped ?? array_slice(array_keys($fields), 0, 6);
        $numeric = ['rate', 'gst_rate', 'purchase_rate', 'opening_stock', 'low_stock_level'];
    @endphp

    <div class="py-6 sm:py-8" x-data="{ filter: 'all' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <button type="button" @click="filter = filter === 'ready' ? 'all' : 'ready'" :aria-pressed="filter === 'ready' ? 'true' : 'false'" :class="filter === 'ready' ? 'ring-2 ring-brand-600' : 'ring-1 ring-gray-200'" class="text-left bg-white rounded-xl p-4">
                    <div class="text-xs font-semibold text-money-700">Ready to import</div>
                    <div class="font-display text-2xl font-extrabold text-gray-900 tabular-nums">{{ $counts['ready'] }}</div>
                </button>
                <button type="button" @click="filter = filter === 'duplicate' ? 'all' : 'duplicate'" :aria-pressed="filter === 'duplicate' ? 'true' : 'false'" :class="filter === 'duplicate' ? 'ring-2 ring-brand-600' : 'ring-1 ring-gray-200'" class="text-left bg-white rounded-xl p-4">
                    <div class="text-xs font-semibold text-accent-800">Already exist (skipped)</div>
                    <div class="font-display text-2xl font-extrabold text-gray-900 tabular-nums">{{ $counts['duplicate'] }}</div>
                </button>
                <button type="button" @click="filter = filter === 'error' ? 'all' : 'error'" :aria-pressed="filter === 'error' ? 'true' : 'false'" :class="filter === 'error' ? 'ring-2 ring-brand-600' : 'ring-1 ring-gray-200'" class="text-left bg-white rounded-xl p-4">
                    <div class="text-xs font-semibold text-danger-700">With errors (skipped)</div>
                    <div class="font-display text-2xl font-extrabold text-gray-900 tabular-nums">{{ $counts['error'] }}</div>
                </button>
            </div>

            @if ($unmatched)
                <div class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
                    Columns not used: {{ implode(', ', $unmatched) }}
                </div>
            @endif

            <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto max-h-[60vh]">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-left text-xs font-semibold sticky top-0 z-[1]">
                            <tr>
                                <th class="px-3 py-2">Row</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 min-w-[18rem]">Notes</th>
                                @foreach ($shown as $k)<th class="px-3 py-2 whitespace-nowrap {{ in_array($k, $numeric, true) ? 'text-right' : '' }}">{{ $fields[$k]['label'] }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($results as $r)
                                <tr x-show="filter === 'all' || filter === '{{ $r['status'] }}'" class="align-top {{ $r['status'] === 'error' ? 'bg-danger-50/40' : ($r['status'] === 'duplicate' ? 'bg-accent-50/40' : '') }}">
                                    <td class="px-3 py-2 text-gray-500 tabular-nums">{{ $r['line'] }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        @if ($r['status'] === 'ready')<x-status-badge status="paid" label="Ready" />
                                        @elseif ($r['status'] === 'duplicate')<x-status-badge status="partial" label="Skip" />
                                        @else<x-status-badge status="overdue" label="Error" />@endif
                                    </td>
                                    {{-- Notes first and wide, so an error reads on one or two lines. --}}
                                    <td class="px-3 py-2 min-w-[18rem] max-w-[28rem] {{ $r['status'] === 'error' ? 'text-danger-700' : 'text-gray-600' }}">
                                        @if ($r['errors']){{ implode(' ', $r['errors']) }}@else{{ $r['note'] ?: '–' }}@endif
                                    </td>
                                    @foreach ($shown as $k)
                                        <td class="px-3 py-2 text-gray-800 max-w-[14rem] truncate {{ in_array($k, $numeric, true) ? 'text-right tabular-nums' : '' }}" title="{{ $r['input'][$k] ?? '' }}">{{ ($r['input'][$k] ?? '') !== '' ? $r['input'][$k] : '–' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="POST" action="{{ route('imports.confirm', $importer->key()) }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <p class="text-sm text-gray-600">
                    @if ($counts['ready'])
                        <strong>{{ $counts['ready'] }}</strong> {{ Str::plural('row', $counts['ready']) }} will be added. Rows marked Skip or Error are left out. Fix them in the sheet and upload again.
                    @else
                        No rows are ready to import. Fix the errors in the sheet and upload again.
                    @endif
                </p>
                <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3">
                    <a href="{{ route('imports.show', $importer->key()) }}" class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md">Upload a different file</a>
                    <x-primary-button class="h-10" :disabled="! $counts['ready']">Import {{ $counts['ready'] }} {{ Str::plural('row', $counts['ready']) }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
