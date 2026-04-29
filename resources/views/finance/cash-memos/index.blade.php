<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Cash Memos</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $memos->total() }} total · {{ $company->name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('finance.expenses') }}" class="text-sm text-gray-500 hover:text-gray-700">← All expenses</a>
                <a href="{{ route('finance.cash-memos.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg text-sm whitespace-nowrap">+ New cash memo</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{{ session('status') }}</div>
            @endif

            @include('finance.partials.tabs')

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                <div class="font-semibold mb-1">What is a Cash Memo?</div>
                <p>A self-prepared purchase voucher used when you buy something for cash and the seller doesn't issue a formal tax invoice — common with small / unregistered vendors. Each memo also auto-creates a matching Expense entry so it flows into your P&amp;L.</p>
            </div>

            <form method="GET" class="bg-white p-4 rounded-xl border border-gray-200 flex flex-wrap items-end gap-3">
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Memo number or seller" class="w-56 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
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
                @if (request()->anyFilled(['search', 'from', 'to']))
                    <a href="{{ route('finance.cash-memos.index') }}" class="text-sm text-gray-500 hover:text-gray-900">Clear</a>
                @endif
            </form>

            {{-- Mobile cards --}}
            <div class="md:hidden space-y-2">
                @forelse ($memos as $m)
                    <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-100">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('finance.cash-memos.show', $m) }}" class="font-mono font-semibold text-brand-700 hover:underline">{{ $m->memo_number }}</a>
                                <div class="font-medium text-gray-900 mt-1">{{ $m->seller_name }}</div>
                                @if ($m->seller_address)
                                    <div class="text-xs text-gray-500 mt-0.5 truncate">{{ $m->seller_address }}</div>
                                @endif
                                <div class="flex items-center gap-3 text-xs text-gray-500 mt-2">
                                    <span>{{ $m->memo_date->format('d M Y') }}</span>
                                    <span class="uppercase">{{ $m->payment_mode }}</span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="font-mono font-bold text-gray-900 tabular-nums">₹{{ number_format((float) $m->grand_total, 2) }}</div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-gray-100 flex items-center gap-3 text-xs">
                            <a href="{{ route('finance.cash-memos.show', $m) }}" class="text-brand-700 hover:underline font-medium">View / Print</a>
                            <a href="{{ route('finance.cash-memos.pdf', $m) }}" class="text-emerald-700 hover:underline font-medium">PDF</a>
                            <form method="POST" action="{{ route('finance.cash-memos.destroy', $m) }}" class="inline ml-auto" onsubmit="return confirm('Delete this cash memo? The linked expense entry will also be removed.')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-400">
                        No cash memos yet.
                        <a href="{{ route('finance.cash-memos.create') }}" class="block mt-2 text-brand-700 underline">Create your first →</a>
                    </div>
                @endforelse
            </div>

            {{-- Desktop table --}}
            <div class="hidden md:block bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[700px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Memo No.</th>
                                <th class="px-5 py-3 text-left font-semibold">Date</th>
                                <th class="px-5 py-3 text-left font-semibold">Purchased From</th>
                                <th class="px-5 py-3 text-right font-semibold">Total</th>
                                <th class="px-5 py-3 text-left font-semibold">Mode</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($memos as $m)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 whitespace-nowrap font-mono font-semibold text-gray-900">{{ $m->memo_number }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-gray-700">{{ $m->memo_date->format('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900">{{ $m->seller_name }}</div>
                                        @if ($m->seller_address)
                                            <div class="text-xs text-gray-500 truncate max-w-xs">{{ $m->seller_address }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono font-semibold tabular-nums">₹{{ number_format((float) $m->grand_total, 2) }}</td>
                                    <td class="px-5 py-3 text-xs text-gray-600 uppercase">{{ $m->payment_mode }}</td>
                                    <td class="px-5 py-3 text-right text-sm whitespace-nowrap">
                                        <a href="{{ route('finance.cash-memos.show', $m) }}" class="text-brand-700 hover:underline font-medium">View / Print</a>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <form method="POST" action="{{ route('finance.cash-memos.destroy', $m) }}" class="inline" onsubmit="return confirm('Delete this cash memo? The linked expense entry will also be removed.')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                    No cash memos yet. <a href="{{ route('finance.cash-memos.create') }}" class="text-brand-700 underline">Create your first →</a>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $memos->links() }}</div>
        </div>
    </div>
</x-app-layout>
