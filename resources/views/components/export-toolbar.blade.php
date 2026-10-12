@props(['report', 'import' => null, 'class' => ''])

{{--
    The same four buttons on every list: Excel, CSV, PDF, Print - each one
    carries the screen's current filters (search, status, dates…) so what
    you export is exactly what you are looking at. Optional Import link for
    lists that can be filled from a spreadsheet.
--}}
@php
    $query = request()->except(['page']);
    $url = fn (string $format) => route('reports.export', array_merge(['report' => $report, 'format' => $format], $query));
    $btn = 'inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-md ring-1 ring-gray-300 bg-white text-gray-700 hover:ring-brand-400 hover:text-brand-800 transition';
@endphp
@if (\App\Support\Team::can('reports.export'))
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5 print:hidden ' . $class]) }} role="toolbar" aria-label="Export">
    @if ($import)
        <a href="{{ $import }}" class="{{ $btn }}" title="Import from an Excel or CSV file">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Import
        </a>
        <span class="w-px h-5 bg-gray-200 mx-0.5" aria-hidden="true"></span>
    @endif
    <a href="{{ $url('xlsx') }}" class="{{ $btn }}" title="Download as Excel (.xlsx)">
        <svg class="w-3.5 h-3.5 text-money-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M10 3v18M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
        Excel
    </a>
    <a href="{{ $url('csv') }}" class="{{ $btn }}" title="Download as CSV">CSV</a>
    <a href="{{ $url('pdf') }}" class="{{ $btn }}" title="Download as PDF">
        <svg class="w-3.5 h-3.5 text-danger-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        PDF
    </a>
    <a href="{{ $url('print') }}" target="_blank" rel="noopener" class="{{ $btn }}" title="Open printable view">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print
    </a>
</div>
@endif
