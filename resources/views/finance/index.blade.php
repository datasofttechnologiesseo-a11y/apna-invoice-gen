<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Finance · P&amp;L</h2>
                <p class="text-sm text-gray-500 mt-1 truncate">{{ $company->name }} · {{ $periodLabel }}</p>
            </div>
            <a href="{{ route('finance.expenses.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-sm text-sm whitespace-nowrap">
                + Add expense
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{{ session('status') }}</div>
            @endif

            {{-- Period + view controls --}}
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 bg-white rounded-xl border border-gray-200 p-4">
                <form method="GET" class="flex items-center gap-2 flex-wrap">
                    <label class="text-xs uppercase tracking-wider font-bold text-gray-500">Period</label>
                    <select name="period" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach (['this_month' => 'This month','last_month' => 'Last month','this_quarter' => 'This quarter','this_fy' => 'This FY','last_fy' => 'Last FY','ytd' => 'Year to date'] as $key => $label)
                            <option value="{{ $key }}" @selected($periodKey === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="view" value="{{ $view }}">
                </form>

                {{-- View toggle (accrual / cash / GST) --}}
                <div class="inline-flex rounded-lg bg-gray-100 p-1 text-sm">
                    @php $viewOptions = ['accrual' => 'P&L (Accrual)', 'cash' => 'Cash flow', 'gst' => 'GST summary']; @endphp
                    @foreach ($viewOptions as $key => $label)
                        <a href="{{ route('finance.index', ['period' => $periodKey, 'view' => $key]) }}"
                           class="px-4 py-1.5 rounded-md font-semibold transition {{ $view === $key ? 'bg-white shadow text-brand-700' : 'text-gray-600 hover:text-gray-900' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- KPI tiles — vary by view --}}
            @if ($view === 'accrual')
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] border-l-brand-600">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Revenue (taxable)</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums">₹{{ number_format($revenue['taxable'], 0) }}</div>
                        <div class="mt-1 text-xs text-gray-500">Excl. GST · from finalized invoices</div>
                    </div>
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] border-l-red-500">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Expenses (taxable)</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums">₹{{ number_format($expense['taxable'], 0) }}</div>
                        <div class="mt-1 text-xs text-gray-500">Excl. recoverable GST ITC</div>
                    </div>
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] {{ $netProfit >= 0 ? 'border-l-emerald-600' : 'border-l-red-600' }}">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Net profit</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold {{ $netProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }} tabular-nums">₹{{ number_format($netProfit, 0) }}</div>
                        <div class="mt-1 text-xs text-gray-500">Margin: <strong class="{{ $netProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($margin, 1) }}%</strong></div>
                    </div>
                </div>
            @elseif ($view === 'cash')
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] border-l-emerald-600">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Cash received</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums">₹{{ number_format($revenue['received'], 0) }}</div>
                        <div class="mt-1 text-xs text-gray-500">Paid amounts incl. GST</div>
                    </div>
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] border-l-red-500">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Cash spent</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums">₹{{ number_format($expense['cash_out'], 0) }}</div>
                        <div class="mt-1 text-xs text-gray-500">Amount + GST paid on expenses</div>
                    </div>
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] {{ $cashInHand >= 0 ? 'border-l-emerald-600' : 'border-l-red-600' }}">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Cash in hand</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold {{ $cashInHand >= 0 ? 'text-emerald-700' : 'text-red-700' }} tabular-nums">₹{{ number_format($cashInHand, 0) }}</div>
                        <div class="mt-1 text-xs text-gray-500">Received − Spent</div>
                    </div>
                </div>
                <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded">
                    <strong>Outstanding receivables:</strong> ₹{{ number_format($revenue['outstanding'], 2) }} yet to be collected from customers.
                </div>
            @else {{-- gst --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] border-l-indigo-500">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">GST collected</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums">₹{{ number_format($revenue['gst_collected'], 2) }}</div>
                        <div class="mt-1 text-xs text-gray-500">From customers on finalized invoices</div>
                    </div>
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] border-l-sky-500">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Input Tax Credit (ITC)</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums">₹{{ number_format($expense['gst_itc'], 2) }}</div>
                        <div class="mt-1 text-xs text-gray-500">From expense GST · claimable</div>
                    </div>
                    <div class="p-5 bg-white rounded-xl border border-gray-200 border-l-[4px] {{ $gstPayable >= 0 ? 'border-l-rose-600' : 'border-l-emerald-600' }}">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Net GST payable</div>
                        <div class="mt-2 font-display text-2xl sm:text-3xl font-extrabold {{ $gstPayable >= 0 ? 'text-rose-700' : 'text-emerald-700' }} tabular-nums">₹{{ number_format($gstPayable, 2) }}</div>
                        <div class="mt-1 text-xs text-gray-500">To government on GSTR-3B</div>
                    </div>
                </div>
                <div class="p-4 bg-indigo-50 border border-indigo-200 text-indigo-900 text-sm rounded">
                    GST is a pass-through tax. "Collected" is money you hold for the government; subtract ITC from valid vendor tax invoices to get what you actually owe.
                </div>
            @endif

            {{-- 12-month trend --}}
            <div class="p-5 bg-white rounded-xl border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Revenue vs Expenses</div>
                        <div class="text-sm text-gray-700 mt-0.5">Last 12 months · taxable values</div>
                    </div>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 bg-brand-600 rounded"></span> Revenue</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 bg-red-500 rounded"></span> Expenses</span>
                    </div>
                </div>
                @php
                    $maxVal = max(collect($trend)->max('revenue'), collect($trend)->max('expenses'), 1);
                @endphp
                <div class="mt-5 flex items-end gap-1 h-40">
                    @foreach ($trend as $m)
                        @php
                            $rh = $m['revenue'] > 0 ? max(4, round(($m['revenue'] / $maxVal) * 100)) : 1;
                            $eh = $m['expenses'] > 0 ? max(4, round(($m['expenses'] / $maxVal) * 100)) : 1;
                        @endphp
                        <div class="flex-1 flex flex-col items-stretch" title="{{ $m['label'] }}: Revenue ₹{{ number_format($m['revenue']) }} · Expenses ₹{{ number_format($m['expenses']) }}">
                            <div class="flex-1 flex items-end gap-0.5">
                                <div class="flex-1 bg-gradient-to-t from-brand-700 to-brand-500 rounded-t" style="height: {{ $rh }}%"></div>
                                <div class="flex-1 bg-gradient-to-t from-red-600 to-red-400 rounded-t" style="height: {{ $eh }}%"></div>
                            </div>
                            <div class="text-[9px] text-center text-gray-500 mt-1 truncate">{{ $m['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Category breakdown + top expenses --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- By category --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="font-display font-bold text-gray-900">Expenses by category</h3>
                        <span class="text-xs text-gray-500">{{ $byCategory->count() }} categor{{ $byCategory->count() === 1 ? 'y' : 'ies' }}</span>
                    </div>
                    @if ($byCategory->isEmpty())
                        <div class="p-10 text-center text-gray-400 text-sm">No expenses this period. <a href="{{ route('finance.expenses.create') }}" class="text-brand-700 underline">Add one →</a></div>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($byCategory as $cat)
                                <li class="px-5 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-3 h-3 rounded-sm flex-shrink-0" style="background: {{ $cat['color'] }}"></span>
                                            <span class="font-medium text-gray-900 truncate">{{ $cat['label'] }}</span>
                                            <span class="text-xs text-gray-500 flex-shrink-0">· {{ $cat['count'] }}</span>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <div class="font-mono font-semibold text-gray-900 tabular-nums">₹{{ number_format($cat['total'], 0) }}</div>
                                            <div class="text-xs text-gray-500">{{ number_format($cat['share'], 1) }}%</div>
                                        </div>
                                    </div>
                                    <div class="mt-1.5 h-1.5 bg-gray-100 rounded overflow-hidden">
                                        <div class="h-full rounded" style="width: {{ $cat['share'] }}%; background: {{ $cat['color'] }}"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Top expenses --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="font-display font-bold text-gray-900">Top expenses this period</h3>
                        <a href="{{ route('finance.expenses') }}" class="text-xs font-semibold text-brand-700 hover:underline">View all →</a>
                    </div>
                    @if ($topExpenses->isEmpty())
                        <div class="p-10 text-center text-gray-400 text-sm">No expenses yet.</div>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($topExpenses as $e)
                                <li class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-gray-50">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block text-[9px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider" style="background: {{ $e->categoryColor() }}20; color: {{ $e->categoryColor() }};">{{ $e->categoryLabel() }}</span>
                                            <span class="font-medium text-gray-900 truncate">{{ $e->description }}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5 truncate">
                                            {{ $e->entry_date->format('d M Y') }}
                                            @if ($e->vendor_name) · {{ $e->vendor_name }} @endif
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="font-mono font-semibold text-gray-900 tabular-nums">₹{{ number_format((float) $e->amount, 2) }}</div>
                                        @if ((float) $e->gst_amount > 0)
                                            <div class="text-[10px] text-gray-500">+ ₹{{ number_format((float) $e->gst_amount, 0) }} GST</div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
