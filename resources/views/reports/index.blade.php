<x-app-layout title="Reports">
    <x-slot name="header">
        <div>
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Reports</h1>
            <p class="text-sm text-gray-500 mt-0.5">Registers and statements on screen, or as Excel, PDF and print.</p>
        </div>
    </x-slot>

    @php
        $gst = [
            ['title' => 'GSTR-3B summary', 'desc' => 'Tax payable, ITC and net cash in the return layout', 'href' => route('finance.gstr3b')],
            ['title' => 'GSTR-1', 'desc' => 'Table-wise B2B, B2CL, B2CS, credit notes, nil, HSN and documents, with offline-tool Excel', 'href' => route('finance.gstr1')],
            ['title' => 'GSTR-1 invoice-wise (CSV)', 'desc' => 'Last month, invoice by invoice, with credit notes and HSN', 'href' => route('invoices.gstr1', ['from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString()])],
        ];
        $finance = [
            ['title' => 'Profit & Loss', 'desc' => 'Revenue, expenses and margin by period', 'href' => route('finance.index')],
            ['title' => 'Expenses', 'desc' => 'Category-wise spend with GST input', 'href' => route('finance.expenses')],
            ['title' => 'Cash memos', 'desc' => 'Self-prepared purchase vouchers', 'href' => route('finance.cash-memos.index')],
            ['title' => 'Backups', 'desc' => 'Full data export (ZIP of every table)', 'href' => route('backup.index')],
        ];
        $order = ['Registers', 'Outstanding', 'GST', 'Stock', 'Masters'];
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @foreach ($order as $group)
                @continue(! isset($groups[$group]))
                <section>
                    <h2 class="text-[11px] uppercase tracking-wider font-bold text-gray-500 mb-2">{{ $group }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach ($groups[$group] as $item)
                            @php $r = $item['report']; $fy = $presets['this_fy']; @endphp
                            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4 flex flex-col gap-3">
                                <a href="{{ route('reports.show', $r->usesDates() ? ['report' => $item['key'], 'from' => $presets['this_month']['from']->toDateString(), 'to' => $presets['this_month']['to']->toDateString()] : $item['key']) }}" class="group">
                                    <div class="font-semibold text-gray-900 group-hover:text-brand-700">{{ $r->title() }} →</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $r->usesDates() ? 'Opens on the current month' : 'As on today' }}</div>
                                </a>
                                <div class="flex flex-wrap gap-1.5 text-xs">
                                    @foreach (['xlsx' => 'Excel', 'pdf' => 'PDF', 'print' => 'Print'] as $fmt => $label)
                                        <a href="{{ route('reports.export', array_merge(['report' => $item['key'], 'format' => $fmt], $r->usesDates() ? ['from' => $fy['from']->toDateString(), 'to' => $fy['to']->toDateString()] : [])) }}"
                                           @if ($fmt === 'print') target="_blank" rel="noopener" @endif
                                           class="px-2 py-1 rounded ring-1 ring-gray-300 text-gray-700 hover:ring-brand-400 hover:text-brand-800 font-semibold">{{ $label }}{{ $r->usesDates() ? ' · FY' : '' }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        @if ($group === 'GST')
                            @foreach ($gst as $g)
                                <a href="{{ $g['href'] }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-4 hover:ring-brand-300 group">
                                    <div class="font-semibold text-gray-900 group-hover:text-brand-700">{{ $g['title'] }} →</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $g['desc'] }}</div>
                                </a>
                            @endforeach
                        @endif
                    </div>
                </section>
            @endforeach

            <section>
                <h2 class="text-[11px] uppercase tracking-wider font-bold text-gray-500 mb-2">Accounts</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                    @foreach ($finance as $f)
                        <a href="{{ $f['href'] }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-4 hover:ring-brand-300 group">
                            <div class="font-semibold text-gray-900 group-hover:text-brand-700">{{ $f['title'] }} →</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $f['desc'] }}</div>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
