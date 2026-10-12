<x-app-layout title="All reports">
    <x-slot name="header">
        <div>
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">All reports</h1>
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
            ['title' => 'Profit & loss', 'desc' => 'Revenue, expenses and margin by period', 'href' => route('finance.index')],
            ['title' => 'Expenses', 'desc' => 'Category-wise spend with GST input', 'href' => route('finance.expenses')],
            ['title' => 'Cash memos', 'desc' => 'Self-prepared purchase vouchers', 'href' => route('finance.cash-memos.index')],
        ];
        // Only for the roles that may open it, as in the sidebar and settings.
        if (\App\Support\Team::can('backup.index')) {
            $finance[] = ['title' => 'Backups', 'desc' => 'Full data export (ZIP of CSV files for Excel)', 'href' => route('backup.index')];
        }
        $order = ['Registers', 'Outstanding', 'GST', 'Stock', 'Masters'];
        $card = 'bg-white rounded-xl ring-1 ring-gray-200 p-4';
        $fyLink = 'inline-flex items-center h-9 px-3 rounded-md ring-1 ring-inset ring-gray-300 text-sm text-gray-700 hover:bg-gray-50 font-medium';
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @foreach ($order as $group)
                @continue(! isset($groups[$group]))
                <section aria-labelledby="group-{{ \Illuminate\Support\Str::slug($group) }}">
                    <h2 id="group-{{ \Illuminate\Support\Str::slug($group) }}" class="text-sm font-semibold text-gray-700 mb-2">{{ $group }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach ($groups[$group] as $item)
                            @php
                                $r = $item['report'];
                                $fy = $presets['this_fy'];
                                $name = \App\Http\Controllers\ReportController::screenTitle($item['key'], $r);
                                $open = $item['key'] === 'receivables-aging'
                                    ? route('finance.aging')
                                    : route('reports.show', $r->usesDates() ? ['report' => $item['key'], 'from' => $presets['this_month']['from']->toDateString(), 'to' => $presets['this_month']['to']->toDateString()] : $item['key']);
                            @endphp
                            <div class="{{ $card }} flex flex-col gap-3">
                                <a href="{{ $open }}" class="group">
                                    <div class="font-semibold text-gray-900 group-hover:text-brand-700">{{ $name }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $r->usesDates() ? 'Opens on the current month' : 'As on today' }}</div>
                                </a>
                                @if (\App\Support\Team::can('reports.export'))
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($r->usesDates())
                                            <span class="text-xs text-gray-500 mr-1">{{ $fy['label'] }}:</span>
                                        @endif
                                        @foreach (['xlsx' => 'Excel', 'pdf' => 'PDF', 'print' => 'Print'] as $fmt => $label)
                                            <a href="{{ route('reports.export', array_merge(['report' => $item['key'], 'format' => $fmt], $r->usesDates() ? ['from' => $fy['from']->toDateString(), 'to' => $fy['to']->toDateString()] : [])) }}"
                                               @if ($fmt === 'print') target="_blank" rel="noopener" @endif
                                               class="{{ $fyLink }}">{{ $label }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                        @if ($group === 'GST')
                            @foreach ($gst as $g)
                                <a href="{{ $g['href'] }}" class="{{ $card }} hover:ring-brand-300 group">
                                    <div class="font-semibold text-gray-900 group-hover:text-brand-700">{{ $g['title'] }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $g['desc'] }}</div>
                                </a>
                            @endforeach
                        @endif
                    </div>
                </section>
            @endforeach

            <section aria-labelledby="group-accounts">
                <h2 id="group-accounts" class="text-sm font-semibold text-gray-700 mb-2">Accounts</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                    @foreach ($finance as $f)
                        <a href="{{ $f['href'] }}" class="{{ $card }} hover:ring-brand-300 group">
                            <div class="font-semibold text-gray-900 group-hover:text-brand-700">{{ $f['title'] }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $f['desc'] }}</div>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
