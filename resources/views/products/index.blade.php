<x-app-layout title="Items">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Items</h1>
            <div class="flex flex-wrap items-center gap-2">
                <x-export-toolbar report="products" :import="route('imports.show', 'products')" />
                <a href="{{ route('products.create') }}" class="inline-flex items-center gap-1 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm whitespace-nowrap transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add item
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Inventory at a glance (items that track stock). --}}
            @if ($inventory)
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <a href="{{ route('reports.show', ['report' => 'stock-summary']) }}" class="rounded-xl bg-white ring-1 ring-gray-200 shadow-sm px-4 py-3 hover:ring-brand-300">
                        <div class="text-xs font-semibold text-gray-500">Stock value</div>
                        <div class="mt-0.5 font-display text-xl font-extrabold text-gray-900 tabular-nums">₹{{ rupees($inventory['value']) }}</div>
                        <div class="text-xs text-gray-500">at purchase price</div>
                    </a>
                    <a href="{{ route('reports.show', ['report' => 'stock-summary']) }}" class="rounded-xl bg-white ring-1 ring-gray-200 shadow-sm px-4 py-3 hover:ring-brand-300">
                        <div class="text-xs font-semibold text-gray-500">Items in stock</div>
                        <div class="mt-0.5 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $inventory['items'] }}</div>
                        <div class="text-xs text-brand-700 font-semibold">Stock summary →</div>
                    </a>
                    <a href="{{ route('products.index', ['stock' => 'low']) }}" class="rounded-xl bg-white ring-1 shadow-sm px-4 py-3 {{ $inventory['low'] ? 'ring-accent-300 hover:ring-accent-400' : 'ring-gray-200 hover:ring-brand-300' }}">
                        <div class="text-xs font-semibold text-gray-500">Low stock</div>
                        <div class="mt-0.5 font-display text-xl font-extrabold tabular-nums {{ $inventory['low'] ? 'text-accent-800' : 'text-gray-900' }}">{{ $inventory['low'] }}</div>
                        <div class="text-xs text-gray-500">at or below reorder level</div>
                    </a>
                    <a href="{{ route('products.index', ['stock' => 'out']) }}" class="rounded-xl bg-white ring-1 shadow-sm px-4 py-3 {{ $inventory['out'] ? 'ring-danger-300 hover:ring-danger-400' : 'ring-gray-200 hover:ring-brand-300' }}">
                        <div class="text-xs font-semibold text-gray-500">Out of stock</div>
                        <div class="mt-0.5 font-display text-xl font-extrabold tabular-nums {{ $inventory['out'] ? 'text-danger-700' : 'text-gray-900' }}">{{ $inventory['out'] }}</div>
                        <div class="text-xs text-gray-500">nothing left to sell</div>
                    </a>
                </div>
            @endif
            @if (in_array(request('stock'), ['low', 'out'], true))
                <div class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-lg bg-accent-50 ring-1 ring-accent-200 text-sm text-accent-900">
                    <span>Showing items that are <strong>{{ request('stock') === 'out' ? 'out of stock' : 'low on stock' }}</strong>.</span>
                    <a href="{{ route('products.index') }}" class="font-semibold underline">Show all items</a>
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg">
                {{-- Nothing to filter until there is something in the list. Showing an
                     empty search bar to a new user is noise in front of the one thing
                     they actually came here to do. --}}
                @php
                    // "Is something being hidden from the user right now?" - drives
                    // the clear link and the empty state. Landing on In use is the
                    // ordinary view of a catalogue, not a filter someone applied,
                    // so it only counts once the tab has been moved off it.
                    $stockFilter = in_array(request('stock'), ['low', 'out'], true) ? request('stock') : null;
                    $hasFilters = filled(request('search')) || filled(request('kind')) || $status !== 'active' || $stockFilter !== null;
                    $tabs = ['active' => 'In use', 'archived' => 'Archived', 'all' => 'All'];
                @endphp

                @if (! $products->isEmpty() || $hasFilters || $archivedCount > 0)
                <div class="p-4 border-b space-y-3">
                    {{-- Three named states instead of the old "Show archived" tick
                         box, which read as "add the archived ones to this list" and
                         actually meant "replace this list with only those". --}}
                    <div class="inline-flex rounded-md ring-1 ring-gray-300 overflow-hidden text-sm" role="group" aria-label="Which products to show">
                        @foreach ($tabs as $key => $label)
                            <a href="{{ route('products.index', array_filter(['status' => $key, 'search' => request('search'), 'kind' => request('kind')])) }}"
                               @if ($status === $key) aria-current="page" @endif
                               class="px-3 py-1.5 border-r border-gray-300 last:border-r-0 {{ $status === $key ? 'bg-brand-700 text-white font-semibold' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
                                {{ $label }}@if ($key === 'archived' && $archivedCount > 0) ({{ $archivedCount }})@endif
                            </a>
                        @endforeach
                    </div>

                    <form method="GET" class="flex flex-wrap gap-3 items-center">
                        {{-- Carries the chosen tab through a search, which would
                             otherwise drop the user back to In use. --}}
                        <input type="hidden" name="status" value="{{ $status }}">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, SKU or HSN/SAC" class="w-full sm:w-80 border-gray-300 rounded-md shadow-sm" aria-label="Search name, SKU or HSN/SAC">
                        {{-- max-w-full: the long "Service (SAC …)" option otherwise sets an
                             intrinsic width wider than small phone viewports. --}}
                        <select name="kind" aria-label="Filter by kind" class="border-gray-300 rounded-md shadow-sm max-w-full" onchange="this.form.submit()">
                            <option value="">All kinds</option>
                            @foreach (config('uqc_units.kinds') as $k => $label)
                                <option value="{{ $k }}" @selected(request('kind') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="px-3 py-1.5 bg-brand-700 text-white rounded text-sm hover:bg-brand-800">Filter</button>
                        @if ($hasFilters)
                            <a href="{{ route('products.index') }}" class="text-gray-500 text-sm">Clear</a>
                        @endif
                    </form>
                </div>
                @endif

                {{-- Say what archived means where someone is actually looking at
                     archived rows, rather than only inside a confirm dialog they
                     clicked through some weeks ago. --}}
                @if ($status === 'archived')
                    <div class="px-4 py-3 bg-accent-50 border-b border-accent-200 text-sm text-accent-900">
                        <strong>Archived is not deleted.</strong>
                        Deleting a product already used on an invoice archives it; those invoices do not change.
                        Archived products are not offered on new invoices.
                        <span class="block mt-1">Use <strong>Restore</strong> on a row to bring it back into use.</span>
                    </div>
                @endif

                @if ($products->isEmpty())
                    @if ($status === 'archived' && ! filled(request('search')) && ! filled(request('kind')))
                        {{-- An empty archive and a filter hiding everything look the
                             same on screen; only one of them is good news. --}}
                        <x-empty-state
                            icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            title="Nothing is archived"
                            description="Deleting a product already used on an invoice archives it instead."
                            :actionHref="route('products.index')"
                            actionLabel="Back to products in use"
                        />
                    @elseif ($stockFilter)
                    <x-empty-state
                        icon="M5 13l4 4L19 7"
                        :title="$stockFilter === 'low' ? 'Nothing is low on stock' : 'Nothing is out of stock'"
                        :description="$stockFilter === 'low' ? 'An item shows here when its stock falls to the low stock level set on the item.' : 'An item shows here when its stock runs out.'"
                        :actionHref="route('products.index')"
                        actionLabel="Show all items"
                    />
                    @else
                    <x-empty-state
                        icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                        :title="$hasFilters ? 'No products match the filter' : 'No products yet'"
                        :description="$hasFilters ? 'Change the search or clear the filter.' : 'Save HSN/SAC, unit, price and GST rate once and they fill in on invoices. Products can also be added from the invoice form.'"
                        :actionHref="$hasFilters ? route('products.index') : route('products.create')"
                        :actionLabel="$hasFilters ? 'Clear filters' : 'Add product'"
                    />
                    @endif
                @else
                    <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3">Kind</th>
                                <th class="px-4 py-3">HSN/SAC</th>
                                <th class="px-4 py-3">Unit</th>
                                <th class="px-4 py-3 text-right">In stock</th>
                                <th class="px-4 py-3 text-right">Rate (₹)</th>
                                <th class="px-4 py-3 text-right">GST</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($products as $p)
                                <tr class="{{ $p->is_active ? '' : 'bg-gray-50 text-gray-500' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        {{ $p->name }}
                                        @unless ($p->is_active)
                                            <span class="ml-2 text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 uppercase tracking-wider"
                                                  title="Kept for existing invoices; not offered on new ones.">Archived</span>
                                        @endunless
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 font-mono text-sm">{{ $p->sku ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm">{{ ucfirst($p->kind) }}</td>
                                    <td class="px-4 py-3 font-mono text-sm">{{ $p->hsn_sac }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $p->unit }}</td>
                                    <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                        @if ($p->track_stock)
                                            @php $onHand = $p->stockOnHand(); $low = $p->low_stock_level !== null && $onHand <= (float) $p->low_stock_level; @endphp
                                            <a href="{{ route('products.stock', $p) }}" title="Stock history and adjustments"
                                               class="font-mono font-semibold hover:underline {{ $onHand < 0 || $low ? 'text-danger-700' : 'text-gray-900' }}">{{ rtrim(rtrim(number_format($onHand, 3, '.', ''), '0'), '.') }}</a>
                                            @if ($onHand < 0)
                                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-danger-100 text-danger-800 font-bold uppercase">Short</span>
                                            @elseif ($low)
                                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-danger-100 text-danger-800 font-bold uppercase">Low</span>
                                            @endif
                                        @elseif ($p->kind === 'service')
                                            <span class="text-gray-400" aria-label="Not applicable for a service">-</span>
                                        @else
                                            <a href="{{ route('products.edit', $p) }}#stock" class="text-gray-500 hover:text-brand-700 hover:underline" title="Turn on stock tracking for this item">Not tracked</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">₹{{ inr($p->rate) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ rtrim(rtrim(number_format((float) $p->gst_rate, 2, '.', ''), '0'), '.') }}%</td>
                                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                        @if ($p->track_stock)
                                            <a href="{{ route('products.stock', $p) }}" class="text-brand-700 hover:underline text-sm" title="Add or remove stock, and see its history">Stock</a>
                                        @endif
                                        <a href="{{ route('products.edit', $p) }}" class="text-brand-700 hover:underline text-sm">Edit</a>
                                        @if ($p->is_active)
                                            {{-- invoice_items_count is eager-loaded by the
                                                 controller; exists() here would run one
                                                 query per row. --}}
                                            @php $willArchive = $p->invoice_items_count > 0; @endphp
                                            <x-confirm-form
                                                :action="route('products.destroy', $p)"
                                                method="DELETE"
                                                title="{{ $willArchive ? 'Archive' : 'Delete' }} {{ $p->name }}?"
                                                message="{{ $willArchive ? 'This product is on ' . $p->invoice_items_count . ' invoice line' . ($p->invoice_items_count === 1 ? '' : 's') . ', so it will be archived, not deleted. Those invoices do not change. It will not be offered on new invoices; restore it any time from the Archived tab.' : 'This product has never been on an invoice, so it will be deleted. This cannot be undone.' }}"
                                                confirm-label="{{ $willArchive ? 'Archive' : 'Delete' }} product"
                                                confirm-class="{{ $willArchive ? 'bg-accent-600 hover:bg-accent-700' : 'bg-danger-600 hover:bg-danger-700' }}"
                                                tone="{{ $willArchive ? 'warning' : 'danger' }}">
                                                <button type="button" class="{{ $willArchive ? 'text-accent-600' : 'text-danger-600' }} hover:underline text-sm">{{ $willArchive ? 'Archive' : 'Delete' }}</button>
                                            </x-confirm-form>
                                        @else
                                            {{-- The way back. Before this the only route out
                                                 of the archive was an Active tick box buried
                                                 in the edit form. --}}
                                            <form method="POST" action="{{ route('products.restore', $p) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-money-700 hover:underline text-sm font-medium">Restore</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    <div class="p-4">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
