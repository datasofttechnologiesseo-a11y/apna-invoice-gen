<x-app-layout title="Purchase invoices">
    @php
        $hasFilters = request()->anyFilled(['search', 'status', 'from', 'to']) || $vendor;
        // Badge colour follows the label: an unpaid bill past its due date is Overdue.
        $badge = fn ($bill) => $bill->status === 'unpaid' && $bill->isOverdue() ? 'overdue' : $bill->status;
        $btnSecondary = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md whitespace-nowrap transition';
        $fieldLabel = 'block text-xs font-medium text-gray-600 mb-1';
    @endphp
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Purchase invoices</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $bills->total() }} {{ Str::plural('invoice', $bills->total()) }} · {{ $company->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-export-toolbar report="purchase-register" />
                <a href="{{ route('vendors.index') }}" class="{{ $btnSecondary }}">Vendors</a>
                <a href="{{ route('purchases.create') }}" class="inline-flex items-center justify-center gap-1 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm whitespace-nowrap transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New purchase invoice
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @include('partials.sales-tabs', ['active' => 'purchases', 'stats' => $salesStats ?? []])

            {{-- What you owe, at a glance - the purchase-side counterpart of "Outstanding". --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ([
                    ['label' => 'Payable to vendors', 'value' => $summary['payable'], 'sub' => $summary['open_count'] . ' open ' . Str::plural('invoice', $summary['open_count']), 'tone' => 'text-gray-900'],
                    ['label' => 'Overdue', 'value' => $summary['overdue'], 'sub' => 'Past the due date', 'tone' => $summary['overdue'] > 0 ? 'text-danger-700' : 'text-gray-900'],
                    ['label' => 'Due in 7 days', 'value' => $summary['due_7_days'], 'sub' => 'Upcoming payments', 'tone' => $summary['due_7_days'] > 0 ? 'text-accent-800' : 'text-gray-900'],
                    ['label' => 'Input credit this month', 'value' => $summary['itc_this_month'], 'sub' => 'GST on eligible invoices', 'tone' => 'text-gray-900'],
                ] as $card)
                    <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm px-4 py-3">
                        <div class="text-xs font-semibold text-gray-600">{{ $card['label'] }}</div>
                        <div class="mt-1 font-display text-xl font-extrabold tabular-nums {{ $card['tone'] }}">₹{{ rupees($card['value']) }}</div>
                        <div class="text-xs text-gray-500">{{ $card['sub'] }}</div>
                    </div>
                @endforeach
            </div>

            @if ($vendor)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 rounded-lg bg-brand-50 ring-1 ring-brand-200 text-sm text-brand-900">
                    <span>Showing purchase invoices from <strong>{{ $vendor->name }}</strong>.</span>
                    <span class="flex items-center gap-4">
                        <a href="{{ route('vendors.edit', $vendor) }}" class="inline-flex items-center min-h-[32px] font-semibold underline">Vendor details</a>
                        <a href="{{ route('purchases.index', request()->except(['vendor', 'page'])) }}" class="inline-flex items-center min-h-[32px] font-semibold underline">Show all vendors</a>
                    </span>
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                @if (! $bills->isEmpty() || $hasFilters)
                <form method="GET" class="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-end">
                    @if ($vendor)<input type="hidden" name="vendor" value="{{ $vendor->id }}">@endif
                    <div class="w-full sm:w-auto">
                        <label for="purchase-search" class="{{ $fieldLabel }}">Search</label>
                        <input id="purchase-search" type="text" name="search" value="{{ request('search') }}" placeholder="Invoice number or vendor" class="w-full sm:w-56 h-10 border-gray-300 rounded-md shadow-sm text-sm">
                    </div>
                    <div>
                        <label for="purchase-status" class="{{ $fieldLabel }}">Status</label>
                        <select id="purchase-status" name="status" onchange="this.form.submit()" class="h-10 border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">All</option>
                            @foreach (['unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'paid' => 'Paid'] as $k => $label)
                                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="purchase-from" class="{{ $fieldLabel }}">From</label>
                        <input id="purchase-from" type="date" name="from" value="{{ request('from') }}" class="h-10 border-gray-300 rounded-md shadow-sm text-sm">
                    </div>
                    <div>
                        <label for="purchase-to" class="{{ $fieldLabel }}">To</label>
                        <input id="purchase-to" type="date" name="to" value="{{ request('to') }}" class="h-10 border-gray-300 rounded-md shadow-sm text-sm">
                    </div>
                    <button class="{{ $btnSecondary }}">Apply</button>
                    @if ($hasFilters)
                        <a href="{{ route('purchases.index') }}" class="inline-flex items-center h-10 text-sm text-gray-600 hover:text-gray-900">Clear</a>
                    @endif
                </form>
                @endif

                @if ($bills->isEmpty())
                    <x-empty-state
                        icon="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                        :title="$hasFilters ? 'No purchase invoices match the filter' : 'No purchase invoices yet'"
                        :description="$hasFilters ? 'Change the search or clear the filter.' : 'Supplier invoices post ITC to GSTR-3B and cost to your P&L. Unpaid amounts show here until paid.'"
                        :actionHref="$hasFilters ? route('purchases.index') : route('purchases.create')"
                        :actionLabel="$hasFilters ? 'Clear filters' : 'New purchase invoice'"
                    />
                @else
                    {{-- Phones and tablets: one card per invoice, actions in reach. --}}
                    <ul class="lg:hidden divide-y divide-gray-100">
                        @foreach ($bills as $bill)
                            <li class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('purchases.show', $bill) }}" class="font-mono text-sm font-semibold text-brand-700 hover:underline">{{ $bill->bill_number }}</a>
                                        @if ($bill->reverse_charge)<span class="ml-1 text-xs font-semibold text-accent-800" title="Reverse charge: tax payable by you">RCM</span>@endif
                                        <div class="mt-0.5 text-sm text-gray-900 truncate">{{ $bill->vendor->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $bill->bill_date->format('d M Y') }}@if ($bill->due_date) · <span class="{{ $bill->isOverdue() ? 'text-danger-700 font-semibold' : '' }}">Due {{ $bill->due_date->format('d M Y') }}</span>@endif</div>
                                    </div>
                                    <x-status-badge class="shrink-0" :status="$badge($bill)" :label="$bill->statusLabel()" />
                                </div>
                                <div class="mt-2 flex items-baseline justify-between gap-3 text-sm">
                                    <span class="text-gray-500">Total <span class="ml-1 font-mono font-semibold tabular-nums text-gray-900">₹{{ rupees($bill->grand_total) }}</span></span>
                                    @if ((float) $bill->balance > 0)
                                        <span class="text-gray-500">Balance <span class="ml-1 font-mono font-semibold tabular-nums text-gray-900">₹{{ rupees($bill->balance) }}</span></span>
                                    @endif
                                </div>
                                <div class="mt-1 flex items-center gap-5 text-sm">
                                    <a href="{{ route('purchases.show', $bill) }}" class="inline-flex items-center min-h-[40px] font-semibold text-brand-700">View</a>
                                    <a href="{{ route('purchases.edit', $bill) }}" class="inline-flex items-center min-h-[40px] text-gray-700">Edit</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-left text-xs font-semibold">
                                <tr>
                                    <th class="px-3 py-3">Invoice no.</th>
                                    <th class="px-3 py-3">Vendor</th>
                                    <th class="px-3 py-3">Date</th>
                                    <th class="px-3 py-3">Due</th>
                                    <th class="px-3 py-3 text-right hidden xl:table-cell">Taxable</th>
                                    <th class="px-3 py-3 text-right hidden xl:table-cell">GST</th>
                                    <th class="px-3 py-3 text-right">Total</th>
                                    <th class="px-3 py-3 text-right">Balance</th>
                                    <th class="px-3 py-3">Status</th>
                                    <th class="px-3 py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($bills as $bill)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 font-mono whitespace-nowrap"><a href="{{ route('purchases.show', $bill) }}" class="text-brand-700 hover:underline">{{ $bill->bill_number }}</a>
                                            @if ($bill->reverse_charge)<span class="ml-1 text-xs font-sans font-semibold text-accent-800" title="Reverse charge: tax payable by you">RCM</span>@endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="font-medium text-gray-900">{{ $bill->vendor->name }}</div>
                                            @if ($bill->vendor->gstin)<div class="text-xs font-mono text-gray-500">{{ $bill->vendor->gstin }}</div>@endif
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">{{ $bill->bill_date->format('d M Y') }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap {{ $bill->isOverdue() ? 'text-danger-700 font-semibold' : '' }}">{{ $bill->due_date?->format('d M Y') ?? '–' }}</td>
                                        <td class="px-3 py-3 text-right font-mono tabular-nums whitespace-nowrap hidden xl:table-cell">₹{{ rupees($bill->subtotal) }}</td>
                                        <td class="px-3 py-3 text-right font-mono tabular-nums whitespace-nowrap hidden xl:table-cell {{ $bill->itc_eligible ? '' : 'text-gray-500' }}" title="{{ $bill->itc_eligible ? 'ITC claimable' : 'Blocked credit under Section 17(5), not claimable' }}">₹{{ rupees($bill->total_tax) }}</td>
                                        <td class="px-3 py-3 text-right font-mono tabular-nums whitespace-nowrap font-semibold">₹{{ rupees($bill->grand_total) }}</td>
                                        <td class="px-3 py-3 text-right font-mono tabular-nums whitespace-nowrap">₹{{ rupees($bill->balance) }}</td>
                                        <td class="px-3 py-3"><x-status-badge :status="$badge($bill)" :label="$bill->statusLabel()" /></td>
                                        <td class="px-3 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('purchases.show', $bill) }}" class="text-brand-700 hover:underline">View</a>
                                            <span class="text-gray-300" aria-hidden="true">·</span>
                                            <a href="{{ route('purchases.edit', $bill) }}" class="text-gray-700 hover:underline">Edit</a>
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
