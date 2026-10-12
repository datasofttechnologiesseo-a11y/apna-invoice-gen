<x-app-layout :title="'Import ' . $importer->title() . ' (preview)'">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Check before importing</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $fileName }} · {{ count($results) }} rows read · not saved yet</p>
            </div>
            <a href="{{ route('imports.show', $importer->key()) }}" class="text-sm text-gray-500 hover:text-gray-700">← Upload a different file</a>
        </div>
    </x-slot>

    @php
        $fields = $importer->fields();
        $shown = array_slice(array_keys($fields), 0, 6);
    @endphp

    <div class="py-8" x-data="{ filter: 'all' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">
            <div class="grid grid-cols-3 gap-3">
                <button type="button" @click="filter = filter === 'ready' ? 'all' : 'ready'" :class="filter === 'ready' ? 'ring-2 ring-money-500' : 'ring-1 ring-gray-200'" class="text-left bg-white rounded-xl p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-money-700">Ready to import</div>
                    <div class="font-display text-2xl font-extrabold text-gray-900">{{ $counts['ready'] }}</div>
                </button>
                <button type="button" @click="filter = filter === 'duplicate' ? 'all' : 'duplicate'" :class="filter === 'duplicate' ? 'ring-2 ring-accent-500' : 'ring-1 ring-gray-200'" class="text-left bg-white rounded-xl p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-accent-700">Already exist (skipped)</div>
                    <div class="font-display text-2xl font-extrabold text-gray-900">{{ $counts['duplicate'] }}</div>
                </button>
                <button type="button" @click="filter = filter === 'error' ? 'all' : 'error'" :class="filter === 'error' ? 'ring-2 ring-danger-500' : 'ring-1 ring-gray-200'" class="text-left bg-white rounded-xl p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-danger-700">With errors (skipped)</div>
                    <div class="font-display text-2xl font-extrabold text-gray-900">{{ $counts['error'] }}</div>
                </button>
            </div>

            @if ($unmatched)
                <div class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                    Columns not used: {{ implode(', ', $unmatched) }}
                </div>
            @endif

            <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto max-h-[60vh]">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-left text-xs uppercase tracking-wider sticky top-0">
                            <tr>
                                <th class="px-3 py-2">Row</th>
                                <th class="px-3 py-2">Status</th>
                                @foreach ($shown as $k)<th class="px-3 py-2">{{ $fields[$k]['label'] }}</th>@endforeach
                                <th class="px-3 py-2">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($results as $r)
                                <tr x-show="filter === 'all' || filter === '{{ $r['status'] }}'" class="{{ $r['status'] === 'error' ? 'bg-danger-50/40' : ($r['status'] === 'duplicate' ? 'bg-accent-50/40' : '') }}">
                                    <td class="px-3 py-2 text-gray-500 tabular-nums">{{ $r['line'] }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        @if ($r['status'] === 'ready')<span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-money-100 text-money-800">Ready</span>
                                        @elseif ($r['status'] === 'duplicate')<span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-accent-100 text-accent-800">Skip</span>
                                        @else<span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-danger-100 text-danger-800">Error</span>@endif
                                    </td>
                                    @foreach ($shown as $k)<td class="px-3 py-2 text-gray-800 max-w-[14rem] truncate" title="{{ $r['input'][$k] ?? '' }}">{{ $r['input'][$k] ?? '' }}</td>@endforeach
                                    <td class="px-3 py-2 text-xs {{ $r['status'] === 'error' ? 'text-danger-700' : 'text-gray-600' }}">
                                        @if ($r['errors']){{ implode(' ', $r['errors']) }}@else{{ $r['note'] }}@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="POST" action="{{ route('imports.confirm', $importer->key()) }}" class="flex flex-wrap items-center justify-between gap-3">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <p class="text-sm text-gray-600">
                    @if ($counts['ready'])
                        <strong>{{ $counts['ready'] }}</strong> {{ Str::plural('row', $counts['ready']) }} will be added. Rows marked Skip or Error are left out. Fix them in the sheet and upload again.
                    @else
                        No rows are ready to import. Fix the errors in the sheet and upload again.
                    @endif
                </p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('imports.show', $importer->key()) }}" class="text-sm text-gray-500 hover:underline">Cancel</a>
                    <x-primary-button :disabled="! $counts['ready']">Import {{ $counts['ready'] }} {{ Str::plural('row', $counts['ready']) }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
