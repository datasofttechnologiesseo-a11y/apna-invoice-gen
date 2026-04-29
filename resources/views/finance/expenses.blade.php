<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Expenses</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $expenses->total() }} entries · {{ $periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('finance.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to P&amp;L</a>
                <a href="{{ route('finance.cash-memos.index') }}" class="inline-flex items-center px-3 py-2 bg-white border border-brand-700 text-brand-700 hover:bg-brand-50 font-semibold rounded-lg text-sm whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Cash Memos
                </a>
                <a href="{{ route('finance.expenses.create') }}" class="inline-flex items-center px-3 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg text-sm whitespace-nowrap">+ Add expense</a>
            </div>
        </div>
    </x-slot>

    @php
        $presets = [
            ['key' => 'today',        'label' => 'Today'],
            ['key' => 'yesterday',    'label' => 'Yesterday'],
            ['key' => 'this_month',   'label' => 'Current Month'],
            ['key' => 'this_quarter', 'label' => 'Current Quarter'],
            ['key' => 'this_half',    'label' => 'Current Half-Year'],
            ['key' => 'this_fy',      'label' => 'Current Financial Year'],
            ['key' => 'last_fy',      'label' => 'Previous Financial Year'],
            ['key' => 'ytd',          'label' => 'Financial Year to Date'],
        ];
        $exportQuery = request()->only(['period', 'from', 'to', 'category', 'search']);
    @endphp

    <div class="py-8 print:py-0">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4 print:max-w-none print:px-0">
            @if (session('status'))
                <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm print:hidden">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm print:hidden">🔒 {{ session('error') }}</div>
            @endif

            @include('finance.partials.tabs')

            {{-- ─── Period chooser (dropdown) ─── --}}
            <div class="bg-white p-4 rounded-xl border border-gray-200 print:hidden">
                <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                    {{-- Preset dropdown --}}
                    <form method="GET" class="flex items-end gap-2">
                        @if (request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                        @if (request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        <div>
                            <label for="period" class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Period</label>
                            <select id="period" name="period" onchange="this.form.submit()"
                                    class="min-w-[220px] border-gray-300 rounded-md shadow-sm text-sm font-semibold focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($presets as $p)
                                    <option value="{{ $p['key'] }}" @selected($periodKey === $p['key'])>{{ $p['label'] }}</option>
                                @endforeach
                                <option value="custom" @selected($periodKey === 'custom')>Custom range…</option>
                            </select>
                        </div>
                    </form>

                    {{-- Custom date range (only shown when custom is selected, or always to allow override) --}}
                    <form method="GET" class="flex flex-wrap items-end gap-2">
                        <input type="hidden" name="period" value="custom">
                        @if (request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                        @if (request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        <div>
                            <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">From</label>
                            <input type="date" name="from" value="{{ request('from', $periodStart->toDateString()) }}" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">To</label>
                            <input type="date" name="to" value="{{ request('to', $periodEnd->toDateString()) }}" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <button class="px-3 py-2 bg-gray-800 text-white rounded text-sm">Apply custom</button>
                    </form>
                </div>

                {{-- Active period banner + export actions --}}
                <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm">
                        <span class="text-gray-500">Showing</span>
                        <strong class="text-gray-900">{{ $periodLabel }}</strong>
                        <span class="text-gray-400 ml-2">({{ $periodStart->format('d M Y') }} → {{ $periodEnd->format('d M Y') }})</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('finance.expenses.export.pdf', $exportQuery) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download PDF
                        </a>
                        <a href="{{ route('finance.expenses.export.csv', $exportQuery) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h13M3 7h13v6m0 0H3"/></svg>
                            Excel / CSV
                        </a>
                        <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold rounded">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                </div>
            </div>

            {{-- ─── Summary cards ─── --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Entries</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['count']) }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Taxable value</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 font-mono tabular-nums">₹{{ number_format($summary['taxable'], 2) }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">GST (Input Tax Credit)</div>
                    <div class="mt-1 text-2xl font-bold text-emerald-700 font-mono tabular-nums">₹{{ number_format($summary['gst'], 2) }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Total cash out</div>
                    <div class="mt-1 text-2xl font-bold text-red-700 font-mono tabular-nums">₹{{ number_format($summary['cash_out'], 2) }}</div>
                </div>
            </div>

            {{-- ─── Category breakdown (collapsed by default on mobile) ─── --}}
            @if ($byCategory->isNotEmpty())
                <details class="bg-white border border-gray-200 rounded-lg" open>
                    <summary class="px-4 py-3 cursor-pointer font-semibold text-sm text-gray-800 flex items-center justify-between">
                        <span>Category-wise breakdown <span class="text-gray-400 font-normal">({{ $byCategory->count() }})</span></span>
                        <span class="text-xs text-gray-400">click to collapse</span>
                    </summary>
                    <div class="overflow-x-auto border-t border-gray-100">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Category</th>
                                    <th class="px-4 py-2 text-right font-semibold">Entries</th>
                                    <th class="px-4 py-2 text-right font-semibold">Taxable (₹)</th>
                                    <th class="px-4 py-2 text-right font-semibold">GST (₹)</th>
                                    <th class="px-4 py-2 text-right font-semibold">Total (₹)</th>
                                    <th class="px-4 py-2 text-right font-semibold">Share</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($byCategory as $cat)
                                    <tr>
                                        <td class="px-4 py-2">
                                            <span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider" style="background: {{ $cat['color'] }}20; color: {{ $cat['color'] }};">{{ $cat['label'] }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $cat['count'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums">{{ number_format($cat['taxable'], 2) }}</td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums text-emerald-700">{{ $cat['gst'] > 0 ? number_format($cat['gst'], 2) : '—' }}</td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums font-semibold">{{ number_format($cat['taxable'] + $cat['gst'], 2) }}</td>
                                        <td class="px-4 py-2 text-right text-xs text-gray-500">{{ $summary['taxable'] > 0 ? number_format($cat['taxable'] / $summary['taxable'] * 100, 1) . '%' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif

            {{-- ─── Search + category filter (in addition to period) ─── --}}
            <form method="GET" class="bg-white p-4 rounded-xl border border-gray-200 flex flex-wrap items-end gap-3 print:hidden">
                <input type="hidden" name="period" value="{{ $periodKey }}">
                @if ($periodKey === 'custom')
                    <input type="hidden" name="from" value="{{ request('from') }}">
                    <input type="hidden" name="to" value="{{ request('to') }}">
                @endif
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Description, vendor, ref no." class="w-56 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Category</label>
                    <select name="category" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">All categories</option>
                        @foreach (config('expense_categories') as $key => $cfg)
                            <option value="{{ $key }}" @selected(request('category') === $key)>{{ $cfg['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Filter</button>
                @if (request()->anyFilled(['search', 'category']))
                    <a href="{{ route('finance.expenses', ['period' => $periodKey]) }}" class="text-sm text-gray-500 hover:text-gray-900">Clear filters</a>
                @endif
            </form>

            {{-- ─── Print-only header (visible only when printing) ─── --}}
            <div class="hidden print:block mb-3">
                <div class="text-center border-b-2 border-gray-900 pb-2 mb-3">
                    <div class="text-xl font-bold">{{ $company->name }}</div>
                    @if ($company->gstin)<div class="text-xs">GSTIN: {{ $company->gstin }}</div>@endif
                    <div class="text-base font-semibold mt-1">EXPENSE STATEMENT</div>
                    <div class="text-sm text-gray-700">Period: {{ $periodLabel }} · {{ $periodStart->format('d M Y') }} to {{ $periodEnd->format('d M Y') }}</div>
                </div>
            </div>

            {{-- ─── Expenses list (mobile cards) ─── --}}
            <div class="md:hidden space-y-2 print:hidden">
                @forelse ($expenses as $e)
                    <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-100">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider mb-1.5" style="background: {{ $e->categoryColor() }}20; color: {{ $e->categoryColor() }};">{{ $e->categoryLabel() }}</span>
                                <div class="font-medium text-gray-900">{{ $e->description }}</div>
                                @if ($e->vendor_name)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $e->vendor_name }}</div>
                                @endif
                                <div class="flex items-center gap-3 text-xs text-gray-500 mt-2">
                                    <span>{{ $e->entry_date->format('d M Y') }}</span>
                                    <span class="uppercase">{{ $e->payment_method ?: '—' }}</span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="font-mono font-bold text-gray-900 tabular-nums">₹{{ number_format((float) $e->amount, 2) }}</div>
                                @if ((float) $e->gst_amount > 0)
                                    <div class="text-[10px] text-emerald-700 font-mono">+ ₹{{ number_format((float) $e->gst_amount, 2) }} GST</div>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-gray-100 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <a href="{{ route('finance.expenses.pdf', ['expense' => $e, 'inline' => 1]) }}" target="_blank" rel="noopener" class="text-gray-700 hover:text-brand-700 hover:underline font-medium" title="View voucher in browser">View</a>
                            <a href="{{ route('finance.expenses.pdf', $e) }}" class="text-emerald-700 hover:underline font-medium" title="Download voucher PDF">PDF</a>
                            <a href="{{ route('finance.expenses.edit', $e) }}" class="text-brand-700 hover:underline font-medium">Edit</a>
                            <form method="POST" action="{{ route('finance.expenses.destroy', $e) }}" class="inline ml-auto" onsubmit="return confirm('Delete this expense?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-400">
                        No expenses in this period.
                        <a href="{{ route('finance.expenses.create') }}" class="block mt-2 text-brand-700 underline">Add your first →</a>
                    </div>
                @endforelse
            </div>

            {{-- ─── Expenses list (desktop table) ─── --}}
            <div class="hidden md:block bg-white shadow sm:rounded-lg overflow-hidden print:shadow-none print:block">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[800px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider print:bg-white">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Date</th>
                                <th class="px-5 py-3 text-left font-semibold">Category</th>
                                <th class="px-5 py-3 text-left font-semibold">Vendor · Description</th>
                                <th class="px-5 py-3 text-right font-semibold">Amount</th>
                                <th class="px-5 py-3 text-right font-semibold">GST</th>
                                <th class="px-5 py-3 text-left font-semibold">Method</th>
                                <th class="px-5 py-3 text-right font-semibold print:hidden">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($expenses as $e)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 whitespace-nowrap text-gray-700">{{ $e->entry_date->format('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-block text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider" style="background: {{ $e->categoryColor() }}20; color: {{ $e->categoryColor() }};">{{ $e->categoryLabel() }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900">{{ $e->description }}</div>
                                        @if ($e->vendor_name)
                                            <div class="text-xs text-gray-500">{{ $e->vendor_name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono font-semibold tabular-nums">₹{{ number_format((float) $e->amount, 2) }}</td>
                                    <td class="px-5 py-3 text-right font-mono text-gray-600 tabular-nums">{{ (float) $e->gst_amount > 0 ? '₹' . number_format((float) $e->gst_amount, 2) : '—' }}</td>
                                    <td class="px-5 py-3 text-xs text-gray-600 uppercase">{{ $e->payment_method ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right text-sm whitespace-nowrap print:hidden">
                                        <a href="{{ route('finance.expenses.pdf', ['expense' => $e, 'inline' => 1]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-gray-700 hover:text-brand-700 hover:underline font-medium" title="View voucher in browser">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            View
                                        </a>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <a href="{{ route('finance.expenses.pdf', $e) }}" class="inline-flex items-center gap-1 text-emerald-700 hover:underline font-medium" title="Download voucher PDF">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            PDF
                                        </a>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <a href="{{ route('finance.expenses.edit', $e) }}" class="text-brand-700 hover:underline font-medium">Edit</a>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <form method="POST" action="{{ route('finance.expenses.destroy', $e) }}" class="inline" onsubmit="return confirm('Delete this expense?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">
                                    No expenses in this period. <a href="{{ route('finance.expenses.create') }}" class="text-brand-700 underline">Add one →</a>
                                </td></tr>
                            @endforelse
                        </tbody>
                        @if ($summary['count'] > 0)
                            <tfoot class="bg-gray-50 print:bg-white">
                                <tr class="font-bold border-t-2 border-gray-300">
                                    <td colspan="3" class="px-5 py-3 text-right text-xs uppercase tracking-wider text-gray-700">Total ({{ number_format($summary['count']) }} entries)</td>
                                    <td class="px-5 py-3 text-right font-mono tabular-nums">₹{{ number_format($summary['taxable'], 2) }}</td>
                                    <td class="px-5 py-3 text-right font-mono tabular-nums text-emerald-700">₹{{ number_format($summary['gst'], 2) }}</td>
                                    <td class="px-5 py-3" colspan="2"><span class="text-xs text-gray-500 uppercase tracking-wider">Cash out:</span> <span class="font-mono tabular-nums text-red-700">₹{{ number_format($summary['cash_out'], 2) }}</span></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="print:hidden">{{ $expenses->links() }}</div>
        </div>
    </div>

    <style>
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: white !important; font-size: 10px; }
            details { open: true; }
            summary { display: none; }
            table { font-size: 9px !important; }
            th, td { padding: 4px 6px !important; }
        }
    </style>
</x-app-layout>
