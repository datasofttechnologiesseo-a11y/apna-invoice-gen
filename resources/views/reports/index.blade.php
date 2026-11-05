<x-app-layout title="All reports">
    <x-slot name="header">
        <div>
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">All reports</h1>
            <p class="text-sm text-gray-500 mt-0.5">Registers and statements on screen, or as Excel, CSV, PDF and print.</p>
        </div>
    </x-slot>

    @php
        $fy = $presets['this_fy'];
        $canExport = \App\Support\Team::can('reports.export');
        $lastMonth = \App\Support\ReturnPeriod::for(now()->subMonthNoOverflow()->startOfMonth());

        // One line on what each report answers.
        $purpose = [
            'sales-register' => 'Every invoice issued, with GST, amount received and balance',
            'purchase-register' => 'Purchase invoices from vendors, with ITC and what is still to pay',
            'quotations' => 'Quotations sent, with value and status',
            'credit-notes' => 'Credit notes issued against invoices',
            'receipts' => 'Money received from customers, with TDS deducted',
            'vendor-payments' => 'Money paid to vendors',
            'customers' => 'Customers with GSTIN, contact details and amount outstanding',
            'products' => 'Items with HSN/SAC, price and GST rate',
            'vendors' => 'Vendors with GSTIN, contact details and amount payable',
            'receivables-aging' => 'What customers owe you, by age',
            'payables-aging' => 'What you owe vendors, by age',
            'hsn-summary' => 'Sales by HSN/SAC code for GSTR-1 Table 12',
            'stock-summary' => 'Opening, in, out and closing stock, with value',
        ];

        // Every row on the page: [id, group, name, purpose, href, downloads].
        // A download is [label, href, hint?, newTab?].
        $sections = [];
        foreach (['Registers', 'Outstanding', 'GST', 'Stock', 'Masters'] as $group) {
            foreach ($groups[$group] ?? [] as $item) {
                $r = $item['report'];
                $k = $item['key'];
                $open = $k === 'receivables-aging'
                    ? route('finance.aging')
                    : route('reports.show', $r->usesDates() ? ['report' => $k, 'from' => $presets['this_month']['from']->toDateString(), 'to' => $presets['this_month']['to']->toDateString()] : $k);
                $range = $r->usesDates() ? ['from' => $fy['from']->toDateString(), 'to' => $fy['to']->toDateString()] : [];
                $dl = fn (string $fmt) => route('reports.export', array_merge(['report' => $k, 'format' => $fmt], $range));
                $sections[$group][] = [
                    'id' => $k,
                    'name' => \App\Http\Controllers\ReportController::screenTitle($k, $r),
                    'purpose' => $purpose[$k] ?? $r->title(),
                    'href' => $open,
                    'note' => $r->usesDates() ? 'Downloads cover ' . $fy['label'] : 'As on today',
                    'downloads' => $canExport ? ['xlsx' => $dl('xlsx'), 'csv' => $dl('csv'), 'pdf' => $dl('pdf'), 'print' => $dl('print')] : [],
                    'items' => [],
                ];
            }
            if ($group === 'GST') {
                $sections['GST'][] = [
                    'id' => 'gstr3b', 'name' => 'GSTR-3B summary', 'purpose' => 'Tax payable, ITC and cash to pay, in the return layout',
                    'href' => route('finance.gstr3b'), 'note' => 'Downloads cover ' . $lastMonth->label(),
                    'downloads' => array_filter([
                        'csv' => \App\Support\Team::can('finance.gstr3b.export.csv') ? route('finance.gstr3b.export.csv', $lastMonth->query()) : null,
                        'pdf' => \App\Support\Team::can('finance.gstr3b.export.pdf') ? route('finance.gstr3b.export.pdf', $lastMonth->query()) : null,
                    ]),
                    'items' => [],
                ];
                $sections['GST'][] = [
                    'id' => 'gstr1', 'name' => 'GSTR-1', 'purpose' => 'Outward supplies table by table, with Excel for the offline tool and JSON for the portal',
                    'href' => route('finance.gstr1'), 'note' => 'Downloads cover ' . $lastMonth->label(),
                    'downloads' => [],
                    'items' => array_values(array_filter([
                        \App\Support\Team::can('finance.gstr1.export.xlsx') ? ['label' => 'Excel for the offline tool', 'hint' => 'One sheet per table', 'href' => route('finance.gstr1.export.xlsx', $lastMonth->query())] : null,
                        \App\Support\Team::can('finance.gstr1.export.json') ? ['label' => 'JSON for the portal', 'hint' => 'Upload under Prepare offline', 'href' => route('finance.gstr1.export.json', $lastMonth->query())] : null,
                    ])),
                ];
                $sections['GST'][] = [
                    'id' => 'gstr1-csv', 'name' => 'GSTR-1 invoice-wise (CSV)', 'purpose' => 'Last month, invoice by invoice, with credit notes and HSN',
                    'href' => route('invoices.gstr1', ['from' => $lastMonth->start->toDateString(), 'to' => $lastMonth->end->toDateString()]),
                    'note' => null, 'downloads' => [], 'items' => [],
                ];
            }
        }
        $sections['Accounts'] = [
            ['id' => 'profit-loss', 'name' => 'Profit & loss', 'purpose' => 'Revenue, expenses and margin by period', 'href' => route('finance.index'), 'note' => null, 'downloads' => [], 'items' => []],
            ['id' => 'expenses', 'name' => 'Expenses', 'purpose' => 'Category-wise spend with GST input', 'href' => route('finance.expenses'), 'note' => null, 'downloads' => [], 'items' => []],
            ['id' => 'cash-memos', 'name' => 'Cash memos', 'purpose' => 'Self-prepared purchase vouchers', 'href' => route('finance.cash-memos.index'), 'note' => null, 'downloads' => [], 'items' => []],
        ];
        // Only for the roles that may open it, as in the sidebar and settings.
        if (\App\Support\Team::can('backup.index')) {
            $sections['Accounts'][] = ['id' => 'backups', 'name' => 'Backups', 'purpose' => 'Full data export (ZIP of CSV files for Excel)', 'href' => route('backup.index'), 'note' => null, 'downloads' => [], 'items' => []];
        }

        // What the filter box searches, by row id.
        $index = [];
        foreach ($sections as $group => $rows) {
            foreach ($rows as $row) {
                $index[$row['id']] = ['group' => $group, 'text' => mb_strtolower($row['name'] . ' ' . $row['purpose'] . ' ' . $group)];
            }
        }
        $all = collect($sections)->flatten(1);
    @endphp

    <script>
        window.reportsHub = function (index) {
            var KEY = 'apna.reports.favourites';
            var saved = [];
            try { saved = JSON.parse(window.localStorage.getItem(KEY) || '[]'); } catch (e) { saved = []; }
            if (!Array.isArray(saved)) { saved = []; }
            return {
                q: '',
                favs: saved.filter(function (id) { return Object.prototype.hasOwnProperty.call(index, id); }),
                matches: function (id) {
                    var q = this.q.trim().toLowerCase();
                    if (!q) { return true; }
                    var text = index[id] ? index[id].text : '';
                    return q.split(/\s+/).every(function (w) { return text.indexOf(w) !== -1; });
                },
                isFav: function (id) { return this.favs.indexOf(id) !== -1; },
                toggleFav: function (id) {
                    this.favs = this.isFav(id) ? this.favs.filter(function (f) { return f !== id; }) : this.favs.concat([id]);
                    try { window.localStorage.setItem(KEY, JSON.stringify(this.favs)); } catch (e) {}
                },
                groupShown: function (group) {
                    var self = this;
                    return Object.keys(index).some(function (id) { return index[id].group === group && self.matches(id); });
                },
                favShown: function () {
                    var self = this;
                    return this.favs.some(function (id) { return self.matches(id); });
                },
                anyShown: function () {
                    var self = this;
                    return Object.keys(index).some(function (id) { return self.matches(id); });
                }
            };
        };
    </script>

    <div class="py-6" x-data="reportsHub({{ \Illuminate\Support\Js::from($index) }})">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="relative w-full sm:max-w-sm">
                    <label for="report-filter" class="sr-only">Find a report</label>
                    <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input id="report-filter" type="search" x-model="q" placeholder="Find a report" autocomplete="off"
                           @keydown.escape="q = ''"
                           class="h-10 w-full pl-9 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                </div>
                <p class="text-xs text-gray-500">Tap ☆ to pin a report to the top.</p>
            </div>

            {{-- Pinned: a copy of each favourite row, shown only once pinned. --}}
            <section x-show="favShown()" x-cloak aria-labelledby="group-favourites">
                <h2 id="group-favourites" class="text-sm font-semibold text-gray-700 mb-2">Favourites</h2>
                <ul class="bg-white rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100">
                    @foreach ($all as $row)
                        <template x-if="isFav('{{ $row['id'] }}') && matches('{{ $row['id'] }}')">
                            @include('reports.partials.hub-row', ['row' => $row, 'suffix' => 'fav'])
                        </template>
                    @endforeach
                </ul>
            </section>

            @foreach ($sections as $group => $rows)
                @php $slug = \Illuminate\Support\Str::slug($group); @endphp
                <section aria-labelledby="group-{{ $slug }}" x-show="groupShown('{{ $group }}')">
                    <h2 id="group-{{ $slug }}" class="text-sm font-semibold text-gray-700 mb-2">{{ $group }}</h2>
                    <ul class="bg-white rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            @include('reports.partials.hub-row', ['row' => $row, 'suffix' => null])
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <p x-show="!anyShown()" x-cloak class="px-4 py-10 text-center text-sm text-gray-500 bg-white rounded-xl ring-1 ring-gray-200">
                No report matches "<span x-text="q.trim()"></span>".
            </p>
        </div>
    </div>
</x-app-layout>
