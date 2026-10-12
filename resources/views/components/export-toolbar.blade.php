@props([
    // A key from config('reports.registry'): Excel, CSV, PDF and Print then
    // come from /reports/{report}/{format} with the screen's filters.
    'report' => null,
    // Optional "Import" link shown as its own button before the menu.
    'import' => null,
    'class' => '',
    // Screens with their own export routes (GST returns, aging) pass the
    // links instead of a report key. print="window" prints this page.
    'xlsx' => null,
    'csv' => null,
    'pdf' => null,
    'print' => null,
    // Extra menu rows: [['label' => 'JSON for the GST portal', 'href' => …, 'hint' => …], …]
    'items' => [],
    'label' => 'Export',
])

{{--
    One "Export" menu on every list and report: Excel, CSV, PDF, Print. Each
    link carries the screen's current filters (search, status, dates…) so what
    you export is exactly what you are looking at.
--}}
@php
    $rows = [];
    $allowed = true;
    if ($report) {
        $allowed = \App\Support\Team::can('reports.export');
        $query = request()->except(['page']);
        $url = fn (string $format) => route('reports.export', array_merge(['report' => $report, 'format' => $format], $query));
        $xlsx ??= $url('xlsx');
        $csv ??= $url('csv');
        $pdf ??= $url('pdf');
        $print ??= $url('print');
    }
    $icons = [
        'xlsx' => 'M3 10h18M3 14h18M10 3v18M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z',
        'csv' => 'M9 17v-6h13M3 7h13v6m0 0H3',
        'pdf' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'print' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
        'file' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
    ];
    if ($xlsx) { $rows[] = ['label' => 'Excel (.xlsx)', 'href' => $xlsx, 'icon' => $icons['xlsx'], 'title' => 'Download as Excel']; }
    if ($csv) { $rows[] = ['label' => 'CSV', 'href' => $csv, 'icon' => $icons['csv'], 'title' => 'Download as CSV']; }
    if ($pdf) { $rows[] = ['label' => 'PDF', 'href' => $pdf, 'icon' => $icons['pdf'], 'title' => 'Download as PDF']; }
    foreach ($items as $item) {
        $rows[] = ($item + ['icon' => $icons['file'], 'title' => $item['label'] ?? '']);
    }
    if ($print === 'window') {
        $rows[] = ['label' => 'Print', 'href' => null, 'print' => true, 'icon' => $icons['print'], 'title' => 'Print this page'];
    } elseif ($print) {
        $rows[] = ['label' => 'Print', 'href' => $print, 'target' => '_blank', 'icon' => $icons['print'], 'title' => 'Open a printable view'];
    }
    $btn = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-md text-sm font-semibold bg-white text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 transition';
@endphp
@if ($allowed && ($rows || $import))
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 print:hidden ' . $class]) }} role="toolbar" aria-label="Export">
    @if ($import)
        <a href="{{ $import }}" class="{{ $btn }}" title="Import from an Excel or CSV file">
            <svg class="w-4 h-4 text-gray-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Import
        </a>
    @endif
    @if ($rows)
        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="if (open) { open = false; $refs.button.focus(); }">
            <button type="button" x-ref="button" class="{{ $btn }}" @click="open = ! open"
                    aria-haspopup="true" :aria-expanded="open.toString()" aria-expanded="false">
                <svg class="w-4 h-4 text-gray-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['file'] }}"/></svg>
                {{ $label }}
                <svg class="w-4 h-4 text-gray-500 transition-transform" :class="open && 'rotate-180'" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                 class="absolute right-0 z-40 mt-1.5 w-60 max-w-[calc(100vw-2rem)] rounded-lg bg-white shadow-lg ring-1 ring-gray-200 py-1">
                @foreach ($rows as $row)
                    @if (! empty($row['print']))
                        <button type="button" @click="open = false; window.print()"
                                class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-gray-800 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none" title="{{ $row['title'] }}">
                            <svg class="w-4 h-4 shrink-0 text-gray-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $row['icon'] }}"/></svg>
                            {{ $row['label'] }}
                        </button>
                    @else
                        <a href="{{ $row['href'] }}" @click="open = false"
                           @if (! empty($row['target'])) target="{{ $row['target'] }}" rel="noopener" @endif
                           class="flex items-start gap-2.5 px-3 py-2.5 text-sm text-gray-800 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none" title="{{ $row['title'] }}">
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $row['icon'] }}"/></svg>
                            <span class="min-w-0">
                                <span class="block">{{ $row['label'] }}</span>
                                @if (! empty($row['hint']))
                                    <span class="block text-xs text-gray-500">{{ $row['hint'] }}</span>
                                @endif
                            </span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
@endif
