<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Expenses</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $expenses->total() }} total · {{ $company->name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('finance.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to P&amp;L</a>
                <a href="{{ route('finance.expenses.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg text-sm whitespace-nowrap">+ Add expense</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{{ session('status') }}</div>
            @endif

            {{-- Filters --}}
            <form method="GET" class="bg-white p-4 rounded-xl border border-gray-200 flex flex-wrap items-end gap-3">
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Description or vendor" class="w-56 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
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
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <button class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Filter</button>
                @if (request()->anyFilled(['search', 'category', 'from', 'to']))
                    <a href="{{ route('finance.expenses') }}" class="text-sm text-gray-500 hover:text-gray-900">Clear</a>
                @endif
            </form>

            {{-- List --}}
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[800px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Date</th>
                                <th class="px-5 py-3 text-left font-semibold">Category</th>
                                <th class="px-5 py-3 text-left font-semibold">Vendor · Description</th>
                                <th class="px-5 py-3 text-right font-semibold">Amount</th>
                                <th class="px-5 py-3 text-right font-semibold">GST</th>
                                <th class="px-5 py-3 text-left font-semibold">Method</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
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
                                    <td class="px-5 py-3 text-right text-sm whitespace-nowrap">
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
                                    No expenses yet. <a href="{{ route('finance.expenses.create') }}" class="text-brand-700 underline">Add your first →</a>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $expenses->links() }}</div>
        </div>
    </div>
</x-app-layout>
