<x-app-layout title="Expenses">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Expenses</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $expenses->total() }} entries · {{ $periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('finance.cash-memos.index') }}" class="inline-flex items-center justify-center gap-1.5 h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Cash memos
                </a>
                <a href="{{ route('finance.expenses.create') }}" class="inline-flex items-center justify-center gap-1 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New expense
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $presets = [
            ['key' => 'today',        'label' => 'Today'],
            ['key' => 'yesterday',    'label' => 'Yesterday'],
            ['key' => 'this_month',   'label' => 'This month'],
            ['key' => 'this_quarter', 'label' => 'This quarter'],
            ['key' => 'this_half',    'label' => 'This half-year'],
            ['key' => 'this_fy',      'label' => 'This financial year'],
            ['key' => 'last_fy',      'label' => 'Last financial year'],
            ['key' => 'ytd',          'label' => 'Financial year to date'],
        ];
        $exportQuery = request()->only(['period', 'from', 'to', 'category', 'search']);
        $fieldLabel = 'block text-xs font-medium text-gray-600 mb-1';
        $btnSecondary = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md whitespace-nowrap';
        $methods = ['cash' => 'Cash', 'bank' => 'Bank', 'upi' => 'UPI', 'card' => 'Card', 'cheque' => 'Cheque', 'other' => 'Other'];
    @endphp

    <div class="py-8 print:py-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 print:max-w-none print:px-0">
            @if (session('status'))
                <div class="p-3 bg-money-50 border border-money-200 text-money-800 rounded-lg text-sm print:hidden" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 bg-danger-50 border border-danger-200 text-danger-800 rounded-lg text-sm flex items-start gap-2 print:hidden" role="alert">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-danger-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0-6V8m0 13a9 9 0 110-18 9 9 0 010 18z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @include('finance.partials.tabs')

            {{-- ─── Period chooser (dropdown) ─── --}}
            <div class="bg-white p-4 rounded-xl border border-gray-200 print:hidden">
                <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                    {{-- Preset dropdown --}}
                    <form method="GET" class="flex items-end gap-2 w-full sm:w-auto">
                        @if (request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                        @if (request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        <div class="w-full sm:w-auto">
                            <label for="period" class="{{ $fieldLabel }}">Period</label>
                            <select id="period" name="period" onchange="this.form.submit()"
                                    class="w-full sm:w-auto sm:min-w-[220px] h-10 border-gray-300 rounded-md shadow-sm text-sm font-semibold focus:border-brand-600 focus:ring-brand-600">
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
                            <label for="exp-from" class="{{ $fieldLabel }}">From</label>
                            <input id="exp-from" type="date" name="from" value="{{ request('from', $periodStart->toDateString()) }}" class="h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                        </div>
                        <div>
                            <label for="exp-to" class="{{ $fieldLabel }}">To</label>
                            <input id="exp-to" type="date" name="to" value="{{ request('to', $periodEnd->toDateString()) }}" class="h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                        </div>
                        <button class="{{ $btnSecondary }}">Apply</button>
                    </form>
                </div>

                {{-- Active period banner + export actions --}}
                <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm">
                        <span class="text-gray-500">Showing</span>
                        <strong class="text-gray-900">{{ $periodLabel }}</strong>
                        <span class="text-gray-500 ml-2">({{ $periodStart->format('d M Y') }} → {{ $periodEnd->format('d M Y') }})</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('finance.expenses.export.pdf', $exportQuery) }}" class="{{ $btnSecondary }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download PDF
                        </a>
                        <a href="{{ route('finance.expenses.export.csv', $exportQuery) }}" class="{{ $btnSecondary }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h13M3 7h13v6m0 0H3"/></svg>
                            CSV
                        </a>
                        <button type="button" onclick="window.print()" class="{{ $btnSecondary }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                </div>
            </div>

            {{-- ─── Summary cards ─── --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 print:hidden">
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="text-xs font-semibold text-gray-600">Entries</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ \App\Support\IndianNumber::format($summary['count'], 0) }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="text-xs font-semibold text-gray-600">Taxable value</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 font-mono tabular-nums">₹{{ rupees($summary['taxable']) }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    {{-- All GST on these entries. Not all of it is input credit:
                         blocked credit and cash-memo GST cannot be claimed. --}}
                    <div class="text-xs font-semibold text-gray-600">GST paid</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 font-mono tabular-nums">₹{{ rupees($summary['gst']) }}</div>
                    <div class="text-xs text-gray-500">Includes GST you cannot claim</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="text-xs font-semibold text-gray-600">Total including GST</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 font-mono tabular-nums">₹{{ rupees($summary['cash_out']) }}</div>
                </div>
            </div>

            {{-- ─── Category breakdown (collapsed by default on mobile) ─── --}}
            @if ($byCategory->isNotEmpty())
                <details class="bg-white border border-gray-200 rounded-xl print:hidden" open>
                    <summary class="px-4 py-3 min-h-[44px] cursor-pointer font-semibold text-sm text-gray-800">
                        By category <span class="text-gray-500 font-normal">({{ $byCategory->count() }})</span>
                    </summary>
                    <div class="overflow-x-auto border-t border-gray-100">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-600">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Category</th>
                                    <th class="px-4 py-2 text-right font-semibold">Entries</th>
                                    <th class="px-4 py-2 text-right font-semibold">Taxable</th>
                                    <th class="px-4 py-2 text-right font-semibold">GST</th>
                                    <th class="px-4 py-2 text-right font-semibold">Total</th>
                                    <th class="px-4 py-2 text-right font-semibold">Share</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($byCategory as $cat)
                                    <tr>
                                        <td class="px-4 py-2">
                                            <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium" style="background: {{ $cat['color'] }}1a; color: {{ $cat['color'] }};">{{ $cat['label'] }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $cat['count'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums">₹{{ rupees($cat['taxable']) }}</td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $cat['gst'] > 0 ? '₹' . rupees($cat['gst']) : '–' }}</td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums font-semibold">₹{{ rupees($cat['taxable'] + $cat['gst']) }}</td>
                                        <td class="px-4 py-2 text-right text-xs text-gray-500">{{ $summary['taxable'] > 0 ? number_format($cat['taxable'] / $summary['taxable'] * 100, 1) . '%' : '–' }}</td>
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
                <div class="w-full sm:w-auto">
                    <label for="exp-search" class="{{ $fieldLabel }}">Search</label>
                    <input id="exp-search" type="text" name="search" value="{{ request('search') }}" placeholder="Description, vendor, ref no." class="w-full sm:w-56 h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                </div>
                <div class="w-full sm:w-auto">
                    <label for="exp-category" class="{{ $fieldLabel }}">Category</label>
                    <select id="exp-category" name="category" onchange="this.form.submit()" class="w-full sm:w-auto h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                        <option value="">All categories</option>
                        @foreach (config('expense_categories') as $key => $cfg)
                            <option value="{{ $key }}" @selected(request('category') === $key)>{{ $cfg['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="{{ $btnSecondary }}">Apply</button>
                @if (request()->anyFilled(['search', 'category']))
                    <a href="{{ route('finance.expenses', ['period' => $periodKey]) }}" class="inline-flex items-center h-10 text-sm text-gray-600 hover:text-gray-900">Clear filters</a>
                @endif
            </form>

            {{-- ─── Wrapper for everything that should print (header + table only) ─── --}}
            <div class="expenses-print-area">

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
            <div class="xl:hidden space-y-2 md:space-y-0 md:grid md:grid-cols-2 md:gap-3 print:hidden">
                @forelse ($expenses as $e)
                    <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-200">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium mb-1.5" style="background: {{ $e->categoryColor() }}1a; color: {{ $e->categoryColor() }};">{{ $e->categoryLabel() }}</span>
                                <div class="font-medium text-gray-900">{{ $e->description }}</div>
                                @if ($e->vendor_name)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $e->vendor_name }}</div>
                                @endif
                                <div class="flex items-center gap-3 text-xs text-gray-500 mt-2">
                                    <span>{{ $e->entry_date->format('d M Y') }}</span>
                                    <span>{{ $methods[$e->payment_method] ?? ($e->payment_method ? ucfirst($e->payment_method) : '–') }}</span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="font-mono font-bold text-gray-900 tabular-nums">₹{{ rupees($e->amount) }}</div>
                                @if ((float) $e->gst_amount > 0)
                                    <div class="text-xs text-gray-500 font-mono">+ ₹{{ rupees($e->gst_amount) }} GST</div>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 pt-1 border-t border-gray-100 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <a href="{{ route('finance.expenses.pdf', ['expense' => $e, 'inline' => 1]) }}" target="_blank" rel="noopener" class="inline-flex items-center min-h-[40px] text-gray-700 hover:text-brand-700 hover:underline font-medium" title="View voucher in browser">View</a>
                            <a href="{{ route('finance.expenses.pdf', $e) }}" class="inline-flex items-center min-h-[40px] text-gray-700 hover:text-brand-700 hover:underline font-medium" title="Download voucher PDF">PDF</a>
                            @if ($e->purchase_bill_id)
                                <a href="{{ route('purchases.show', $e->purchase_bill_id) }}" class="inline-flex items-center min-h-[40px] text-brand-700 hover:underline font-medium">Open purchase invoice</a>
                            @elseif ($e->cash_memo_id)
                                <a href="{{ route('finance.cash-memos.show', $e->cash_memo_id) }}" class="inline-flex items-center min-h-[40px] text-brand-700 hover:underline font-medium">Open cash memo</a>
                            @else
                            <a href="{{ route('finance.expenses.edit', $e) }}" class="inline-flex items-center min-h-[40px] text-brand-700 hover:underline font-medium">Edit</a>
                            <span class="ml-auto">
                                <x-confirm-form
                                    :action="route('finance.expenses.destroy', $e)"
                                    method="DELETE"
                                    title="Delete this expense?"
                                    message="This cannot be undone."
                                    confirmLabel="Delete expense"
                                    confirmClass="bg-danger-600 hover:bg-danger-700"
                                    tone="danger">
                                    <button type="button" class="inline-flex items-center min-h-[40px] text-danger-600 hover:underline">Delete</button>
                                </x-confirm-form>
                            </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-xl p-8 text-center text-gray-500 md:col-span-2">
                        <x-empty-state
                            icon="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                            title="No expenses in this period"
                            description="Recorded expenses count towards your profit and your input tax credit."
                            :actionHref="route('finance.expenses.create')"
                            actionLabel="New expense"
                        />
                    </div>
                @endforelse
            </div>

            {{-- ─── Expenses list (desktop table) ─── --}}
            <div class="hidden xl:block bg-white shadow-sm ring-1 ring-gray-200 rounded-xl overflow-hidden print:shadow-none print:ring-0 print:block">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-600 print:bg-white">
                            <tr>
                                <th class="px-3 py-3 text-left font-semibold">Date</th>
                                <th class="px-3 py-3 text-left font-semibold">Category</th>
                                <th class="px-3 py-3 text-left font-semibold">Vendor · Description</th>
                                <th class="px-3 py-3 text-right font-semibold">Amount</th>
                                <th class="px-3 py-3 text-right font-semibold">GST</th>
                                <th class="px-3 py-3 text-left font-semibold">Method</th>
                                <th class="px-3 py-3 text-right font-semibold print:hidden">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($expenses as $e)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-3 whitespace-nowrap text-gray-700">{{ $e->entry_date->format('d M Y') }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap" style="background: {{ $e->categoryColor() }}1a; color: {{ $e->categoryColor() }};">{{ $e->categoryLabel() }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-medium text-gray-900">{{ $e->description }}</div>
                                        @if ($e->vendor_name)
                                            <div class="text-xs text-gray-500">{{ $e->vendor_name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono font-semibold tabular-nums whitespace-nowrap">₹{{ rupees($e->amount) }}</td>
                                    <td class="px-3 py-3 text-right font-mono text-gray-600 tabular-nums whitespace-nowrap">{{ (float) $e->gst_amount > 0 ? '₹' . rupees($e->gst_amount) : '–' }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-600">{{ $methods[$e->payment_method] ?? ($e->payment_method ? ucfirst($e->payment_method) : '–') }}</td>
                                    <td class="px-3 py-3 text-right text-sm whitespace-nowrap print:hidden">
                                        <a href="{{ route('finance.expenses.pdf', ['expense' => $e, 'inline' => 1]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-gray-700 hover:text-brand-700 hover:underline font-medium" title="View voucher in browser">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            View
                                        </a>
                                        <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                                        <a href="{{ route('finance.expenses.pdf', $e) }}" class="inline-flex items-center gap-1 text-gray-700 hover:text-brand-700 hover:underline font-medium" title="Download voucher PDF">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            PDF
                                        </a>
                                        <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                                        @if ($e->purchase_bill_id)
                                            <a href="{{ route('purchases.show', $e->purchase_bill_id) }}" class="text-brand-700 hover:underline font-medium" title="Edit or delete from the purchase invoice">Open purchase invoice</a>
                                        @elseif ($e->cash_memo_id)
                                            <a href="{{ route('finance.cash-memos.show', $e->cash_memo_id) }}" class="text-brand-700 hover:underline font-medium" title="Edit or delete from the cash memo">Open cash memo</a>
                                        @else
                                        <a href="{{ route('finance.expenses.edit', $e) }}" class="inline-flex items-center min-h-[36px] text-brand-700 hover:underline font-medium">Edit</a>
                                        <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                                        <x-confirm-form
                                            :action="route('finance.expenses.destroy', $e)"
                                            method="DELETE"
                                            title="Delete this expense?"
                                            message="This cannot be undone."
                                            confirmLabel="Delete expense"
                                            confirmClass="bg-danger-600 hover:bg-danger-700"
                                            tone="danger">
                                            <button type="button" class="text-danger-600 hover:underline">Delete</button>
                                        </x-confirm-form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">
                                    <x-empty-state
                                        icon="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                                        title="No expenses in this period"
                                        description="Recorded expenses count towards your profit and your input tax credit."
                                        :actionHref="route('finance.expenses.create')"
                                        actionLabel="New expense"
                                    />
                                </td></tr>
                            @endforelse
                        </tbody>
                        @if ($summary['count'] > 0)
                            <tfoot class="bg-gray-50 print:bg-white">
                                <tr class="font-bold border-t-2 border-gray-300">
                                    <td colspan="3" class="px-3 py-3 text-right text-sm text-gray-700">Total ({{ \App\Support\IndianNumber::format($summary['count'], 0) }} entries)</td>
                                    <td class="px-3 py-3 text-right font-mono tabular-nums whitespace-nowrap">₹{{ rupees($summary['taxable']) }}</td>
                                    <td class="px-3 py-3 text-right font-mono tabular-nums whitespace-nowrap">₹{{ rupees($summary['gst']) }}</td>
                                    <td class="px-3 py-3" colspan="2"><span class="text-xs font-normal text-gray-500">Including GST</span> <span class="font-mono tabular-nums text-gray-900 whitespace-nowrap">₹{{ rupees($summary['cash_out']) }}</span></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            </div>{{-- /expenses-print-area --}}

            <div class="print:hidden">{{ $expenses->links() }}</div>
        </div>
    </div>

    <style>
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            html, body { background: white !important; margin: 0 !important; padding: 0 !important; }

            /* Hide everything by default - navbar, topbar, finance tabs, summary cards, etc. */
            body * { visibility: hidden !important; }

            /* Then re-show only the expenses-print-area and its descendants */
            .expenses-print-area, .expenses-print-area * { visibility: visible !important; }

            /* Pull the print area to the top-left so it owns the page */
            .expenses-print-area {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
            }

            /* Tighter typography for the printed table */
            .expenses-print-area table { font-size: 9px !important; }
            .expenses-print-area th, .expenses-print-area td { padding: 4px 6px !important; }
        }
    </style>
</x-app-layout>
