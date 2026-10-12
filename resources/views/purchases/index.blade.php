<x-app-layout title="Purchase invoices">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Purchase invoices</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $bills->total() }} {{ Str::plural('invoice', $bills->total()) }} · {{ $company->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-export-toolbar report="purchase-register" />
                <a href="{{ route('vendors.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white ring-1 ring-gray-300 hover:ring-brand-400 text-gray-700 hover:text-brand-700 text-sm font-medium rounded-md transition">Vendors</a>
                <a href="{{ route('purchases.create') }}" class="inline-flex items-center gap-1 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New purchase invoice
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @include('partials.sales-tabs', ['active' => 'purchases', 'stats' => $salesStats ?? []])

            {{-- What you owe, at a glance - the purchase-side counterpart of "Outstanding". --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ([
                    ['label' => 'Payable to vendors', 'value' => $summary['payable'], 'sub' => $summary['open_count'] . ' open ' . Str::plural('invoice', $summary['open_count']), 'tone' => 'text-gray-900'],
                    ['label' => 'Overdue', 'value' => $summary['overdue'], 'sub' => 'past due date', 'tone' => $summary['overdue'] > 0 ? 'text-danger-700' : 'text-gray-900'],
                    ['label' => 'Due in 7 days', 'value' => $summary['due_7_days'], 'sub' => 'upcoming payments', 'tone' => 'text-accent-800'],
                    ['label' => 'Input credit this month', 'value' => $summary['itc_this_month'], 'sub' => 'GST on eligible invoices', 'tone' => 'text-money-700'],
                ] as $card)
                    <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm px-4 py-3">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">{{ $card['label'] }}</div>
                        <div class="mt-1 font-display text-xl font-extrabold tabular-nums {{ $card['tone'] }}">₹{{ rupees($card['value']) }}</div>
                        <div class="text-[11px] text-gray-500">{{ $card['sub'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                @if (! $bills->isEmpty() || request()->anyFilled(['search', 'status', 'from', 'to']))
                <form method="GET" class="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice number or vendor" class="w-56 border-gray-300 rounded-md shadow-sm text-sm" aria-label="Search invoice number or vendor">
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Status</label>
                        <select name="status" aria-label="Filter by status" class="border-gray-300 rounded-md shadow-sm text-sm" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach (['unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'paid' => 'Paid'] as $k => $label)
                                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">From</label>
                        <input type="date" name="from" value="{{ request('from') }}" aria-label="From date" class="border-gray-300 rounded-md shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">To</label>
                        <input type="date" name="to" value="{{ request('to') }}" aria-label="To date" class="border-gray-300 rounded-md shadow-sm text-sm">
                    </div>
                    <button class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Filter</button>
                    @if (request()->anyFilled(['search', 'status', 'from', 'to']))
                        <a href="{{ route('purchases.index') }}" class="text-sm text-gray-500 hover:text-gray-900">Clear</a>
                    @endif
                </form>
                @endif

                @if ($bills->isEmpty())
                    <x-empty-state
                        icon="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                        :title="request()->anyFilled(['search', 'status', 'from', 'to']) ? 'No purchase invoices match the filter' : 'No purchase invoices yet'"
                        :description="request()->anyFilled(['search', 'status', 'from', 'to']) ? 'Change the search or clear the filter.' : 'Supplier invoices post ITC to GSTR-3B and cost to your P&L. Unpaid amounts show here until paid.'"
                        :actionHref="request()->anyFilled(['search', 'status', 'from', 'to']) ? route('purchases.index') : route('purchases.create')"
                        :actionLabel="request()->anyFilled(['search', 'status', 'from', 'to']) ? 'Clear filters' : 'New purchase invoice'"
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-left text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Invoice no.</th>
                                    <th class="px-4 py-3">Vendor</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Due</th>
                                    <th class="px-4 py-3 text-right">Taxable</th>
                                    <th class="px-4 py-3 text-right">GST</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($bills as $bill)
                                    @php
                                        $tone = match (true) {
                                            $bill->isPaid() => 'bg-money-100 text-money-800',
                                            $bill->isOverdue() => 'bg-danger-100 text-danger-800',
                                            $bill->status === 'partial' => 'bg-accent-100 text-accent-800',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-mono"><a href="{{ route('purchases.show', $bill) }}" class="text-brand-700 hover:underline">{{ $bill->bill_number }}</a>
                                            @if ($bill->reverse_charge)<span class="ml-1 text-[10px] font-bold uppercase text-accent-700" title="Reverse charge: tax payable by you">RCM</span>@endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ $bill->vendor->name }}</div>
                                            @if ($bill->vendor->gstin)<div class="text-[11px] font-mono text-gray-500">{{ $bill->vendor->gstin }}</div>@endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $bill->bill_date->format('d M Y') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap {{ $bill->isOverdue() ? 'text-danger-700 font-semibold' : '' }}">{{ $bill->due_date?->format('d M Y') ?? '-' }}</td>
                                        <td class="px-4 py-3 text-right font-mono tabular-nums">{{ rupees($bill->subtotal) }}</td>
                                        <td class="px-4 py-3 text-right font-mono tabular-nums {{ $bill->itc_eligible ? '' : 'text-gray-500' }}" title="{{ $bill->itc_eligible ? 'ITC claimable' : 'Blocked credit under Section 17(5), not claimable' }}">{{ rupees($bill->total_tax) }}</td>
                                        <td class="px-4 py-3 text-right font-mono tabular-nums font-semibold">{{ rupees($bill->grand_total) }}</td>
                                        <td class="px-4 py-3 text-right font-mono tabular-nums">{{ rupees($bill->balance) }}</td>
                                        <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $tone }}">{{ $bill->statusLabel() }}</span></td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('purchases.show', $bill) }}" class="text-brand-700 hover:underline">View</a>
                                            <span class="text-gray-300">·</span>
                                            <a href="{{ route('purchases.edit', $bill) }}" class="text-gray-600 hover:underline">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-gray-100">{{ $bills->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <x-quick-action-fab :href="route('purchases.create')" label="New purchase invoice" />
</x-app-layout>
