<x-app-layout title="Items">
    @php
        $qtyText = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        // Stock state of a tracked item: Short (below zero), Out, Low or fine.
        $stockState = function ($p) {
            $onHand = $p->stockOnHand();
            $low = $p->low_stock_level !== null && $onHand > 0 && $onHand <= (float) $p->low_stock_level;

            return [$onHand, $onHand < 0 ? 'Short' : ($onHand == 0 ? 'Out' : ($low ? 'Low' : null))];
        };
        $chip = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';
    @endphp
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Items</h1>
            <div class="flex flex-wrap items-center gap-2">
                <x-export-toolbar report="products" :import="route('imports.show', 'products')" />
                <a href="{{ route('products.create') }}" class="inline-flex items-center justify-center gap-1 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm whitespace-nowrap transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New item
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Inventory at a glance (items that track stock). --}}
            @if ($inventory)
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <a href="{{ route('reports.show', ['report' => 'stock-summary']) }}" class="rounded-xl bg-white ring-1 ring-gray-200 shadow-sm px-4 py-3 hover:ring-brand-300">
                        <div class="text-xs font-semibold text-gray-500">Stock value</div>
                        <div class="mt-0.5 font-display text-xl font-extrabold text-gray-900 tabular-nums">₹{{ rupees($inventory['value']) }}</div>
                        <div class="text-xs text-gray-500">At purchase price</div>
                    </a>
                    <a href="{{ route('reports.show', ['report' => 'stock-summary']) }}" class="rounded-xl bg-white ring-1 ring-gray-200 shadow-sm px-4 py-3 hover:ring-brand-300">
                        <div class="text-xs font-semibold text-gray-500">Items in stock</div>
                        <div class="mt-0.5 font-display text-xl font-extrabold text-gray-900 tabular-nums">{{ $inventory['items'] }}</div>
                        <div class="text-xs text-brand-700 font-semibold">Stock summary</div>
                    </a>
                    <a href="{{ route('products.index', ['stock' => 'low']) }}" class="rounded-xl bg-white ring-1 shadow-sm px-4 py-3 {{ $inventory['low'] ? 'ring-accent-300 hover:ring-accent-400' : 'ring-gray-200 hover:ring-brand-300' }}">
                        <div class="text-xs font-semibold text-gray-500">Low stock</div>
                        <div class="mt-0.5 font-display text-xl font-extrabold tabular-nums {{ $inventory['low'] ? 'text-accent-800' : 'text-gray-900' }}">{{ $inventory['low'] }}</div>
                        <div class="text-xs text-gray-500">At or below the alert level</div>
                    </a>
                    <a href="{{ route('products.index', ['stock' => 'out']) }}" class="rounded-xl bg-white ring-1 shadow-sm px-4 py-3 {{ $inventory['out'] ? 'ring-danger-300 hover:ring-danger-400' : 'ring-gray-200 hover:ring-brand-300' }}">
                        <div class="text-xs font-semibold text-gray-500">Out of stock</div>
                        <div class="mt-0.5 font-display text-xl font-extrabold tabular-nums {{ $inventory['out'] ? 'text-danger-700' : 'text-gray-900' }}">{{ $inventory['out'] }}</div>
                        <div class="text-xs text-gray-500">Nothing left to sell</div>
                    </a>
                </div>
            @endif
            @if (in_array(request('stock'), ['low', 'out'], true))
                <div class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-lg bg-accent-50 ring-1 ring-accent-200 text-sm text-accent-900">
                    <span>Showing items that are <strong>{{ request('stock') === 'out' ? 'out of stock' : 'low on stock' }}</strong>.</span>
                    <a href="{{ route('products.index') }}" class="inline-flex items-center min-h-[32px] font-semibold underline">Show all items</a>
                </div>
            @endif

            <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-xl overflow-hidden">
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
                    <div class="inline-flex rounded-md ring-1 ring-gray-300 overflow-hidden text-sm" role="group" aria-label="Which items to show">
                        @foreach ($tabs as $key => $label)
                            <a href="{{ route('products.index', array_filter(['status' => $key, 'search' => request('search'), 'kind' => request('kind')])) }}"
                               @if ($status === $key) aria-current="page" @endif
                               class="inline-flex items-center min-h-[40px] px-3 border-r border-gray-300 last:border-r-0 {{ $status === $key ? 'bg-brand-700 text-white font-semibold' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                                {{ $label }}@if ($key === 'archived' && $archivedCount > 0) ({{ $archivedCount }})@endif
                            </a>
                        @endforeach
                    </div>

                    <form method="GET" class="flex flex-wrap gap-3 items-center">
                        {{-- Carries the chosen tab through a search, which would
                             otherwise drop the user back to In use. --}}
                        <input type="hidden" name="status" value="{{ $status }}">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, SKU or HSN/SAC" class="w-full sm:w-80 h-10 border-gray-300 rounded-md shadow-sm text-sm" aria-label="Search name, SKU or HSN/SAC">
                        {{-- max-w-full: the long "Service (SAC …)" option otherwise sets an
                             intrinsic width wider than small phone viewports. --}}
                        <select name="kind" aria-label="Filter by kind" class="h-10 border-gray-300 rounded-md shadow-sm text-sm max-w-full" onchange="this.form.submit()">
                            <option value="">All kinds</option>
                            @foreach (config('uqc_units.kinds') as $k => $label)
                                <option value="{{ $k }}" @selected(request('kind') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md whitespace-nowrap">Apply</button>
                        @if ($hasFilters)
                            <a href="{{ route('products.index') }}" class="inline-flex items-center h-10 text-gray-600 hover:text-gray-900 text-sm">Clear</a>
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
                        Deleting an item already used on an invoice archives it; those invoices do not change.
                        Archived items are not offered on new invoices.
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
                            description="Deleting an item already used on an invoice archives it instead."
                            :actionHref="route('products.index')"
                            actionLabel="Back to items in use"
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
                        :title="$hasFilters ? 'No items match the filter' : 'No items yet'"
                        :description="$hasFilters ? 'Change the search or clear the filter.' : 'Save HSN/SAC, unit, price and GST rate once and they fill in on invoices. Items can also be added from the invoice form.'"
                        :actionHref="$hasFilters ? route('products.index') : route('products.create')"
                        :actionLabel="$hasFilters ? 'Clear filters' : 'New item'"
                    />
                    @endif
                @else
                    {{-- Phones and tablets: one card per item, actions in reach. --}}
                    <ul class="lg:hidden divide-y divide-gray-100">
                        @foreach ($products as $p)
                            <li class="p-4 {{ $p->is_active ? '' : 'bg-gray-50' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('products.edit', $p) }}" class="font-semibold text-gray-900 hover:text-brand-700 hover:underline break-words">{{ $p->name }}</a>
                                        @unless ($p->is_active)
                                            <span class="ml-1 {{ $chip }} bg-gray-100 text-gray-700 ring-gray-200">Archived</span>
                                        @endunless
                                        <div class="text-xs text-gray-500">{{ collect([$p->sku, $p->hsn_sac ? 'HSN ' . $p->hsn_sac : null, $p->unit, ucfirst($p->kind)])->filter()->implode(' · ') }}</div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="font-mono font-semibold tabular-nums text-gray-900">₹{{ inr($p->rate) }}</div>
                                        <div class="text-xs text-gray-500">GST {{ rtrim(rtrim(number_format((float) $p->gst_rate, 2, '.', ''), '0'), '.') }}%</div>
                                    </div>
                                </div>
                                @if ($p->track_stock)
                                    @php [$onHand, $state] = $stockState($p); @endphp
                                    <div class="mt-1 text-sm text-gray-700">
                                        In stock <span class="font-mono font-semibold tabular-nums {{ $state ? 'text-danger-700' : 'text-gray-900' }}">{{ $qtyText($onHand) }}</span>
                                        @if ($state)<span class="ml-1 {{ $chip }} bg-danger-50 text-danger-700 ring-danger-200">{{ $state }}</span>@endif
                                    </div>
                                @endif
                                <div class="mt-1 flex flex-wrap items-center gap-x-5 text-sm">
                                    @if ($p->track_stock)
                                        <a href="{{ route('products.stock', $p) }}" class="inline-flex items-center min-h-[40px] font-semibold text-brand-700">Stock</a>
                                    @endif
                                    <a href="{{ route('products.edit', $p) }}" class="inline-flex items-center min-h-[40px] {{ $p->track_stock ? 'text-gray-700' : 'font-semibold text-brand-700' }}">Edit</a>
                                    @if ($p->is_active)
                                        @php
                                            $willArchive = $p->invoice_items_count + $p->purchase_bill_items_count + $p->quotation_items_count + $p->stock_movements_count > 0;
                                            $usedOn = collect([
                                                $p->invoice_items_count ? 'invoices' : null,
                                                $p->purchase_bill_items_count ? 'purchase invoices' : null,
                                                $p->quotation_items_count ? 'quotations' : null,
                                                $p->stock_movements_count && ! $p->invoice_items_count && ! $p->purchase_bill_items_count ? 'stock entries' : null,
                                            ])->filter()->join(', ', ' and ');
                                        @endphp
                                        <x-confirm-form
                                            :action="route('products.destroy', $p)"
                                            method="DELETE"
                                            title="{{ $willArchive ? 'Archive' : 'Delete' }} {{ $p->name }}?"
                                            message="{{ $willArchive ? 'This item is used on ' . $usedOn . ', so it will be archived, not deleted. That history does not change. It will not be offered on new invoices; restore it any time from the Archived tab.' : 'This item has never been used, so it will be deleted. This cannot be undone.' }}"
                                            confirm-label="{{ $willArchive ? 'Archive' : 'Delete' }} item"
                                            confirm-class="{{ $willArchive ? 'bg-brand-700 hover:bg-brand-800' : 'bg-danger-600 hover:bg-danger-700' }}"
                                            tone="{{ $willArchive ? 'warning' : 'danger' }}">
                                            <button type="button" class="inline-flex items-center min-h-[40px] {{ $willArchive ? 'text-accent-800' : 'text-danger-600' }}">{{ $willArchive ? 'Archive' : 'Delete' }}</button>
                                        </x-confirm-form>
                                    @else
                                        <form method="POST" action="{{ route('products.restore', $p) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center min-h-[40px] font-semibold text-brand-700">Restore</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3 hidden xl:table-cell">Kind</th>
                                <th class="px-4 py-3">HSN/SAC</th>
                                <th class="px-4 py-3 hidden xl:table-cell">Unit</th>
                                <th class="px-4 py-3 text-right">In stock</th>
                                <th class="px-4 py-3 text-right">Rate</th>
                                <th class="px-4 py-3 text-right">GST</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($products as $p)
                                <tr class="{{ $p->is_active ? '' : 'bg-gray-50 text-gray-500' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        {{ $p->name }}
                                        @unless ($p->is_active)
                                            <span class="ml-2 {{ $chip }} bg-gray-100 text-gray-700 ring-gray-200"
                                                  title="Kept for existing invoices; not offered on new ones.">Archived</span>
                                        @endunless
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 font-mono text-sm">{{ $p->sku ?? '–' }}</td>
                                    <td class="px-4 py-3 text-sm hidden xl:table-cell">{{ ucfirst($p->kind) }}</td>
                                    <td class="px-4 py-3 font-mono text-sm">{{ $p->hsn_sac }}</td>
                                    <td class="px-4 py-3 text-sm hidden xl:table-cell">{{ $p->unit }}</td>
                                    <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                        @if ($p->track_stock)
                                            @php [$onHand, $state] = $stockState($p); @endphp
                                            <a href="{{ route('products.stock', $p) }}" title="Stock history and adjustments"
                                               class="font-mono font-semibold tabular-nums hover:underline {{ $state ? 'text-danger-700' : 'text-gray-900' }}">{{ $qtyText($onHand) }}</a>
                                            @if ($state)
                                                <span class="ml-1 {{ $chip }} bg-danger-50 text-danger-700 ring-danger-200">{{ $state }}</span>
                                            @endif
                                        @elseif ($p->kind === 'service')
                                            <span class="text-gray-500" aria-label="Not applicable for a service">–</span>
                                        @else
                                            <a href="{{ route('products.edit', $p) }}#stock" class="text-gray-500 hover:text-brand-700 hover:underline" title="Turn on stock tracking for this item">Not tracked</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono tabular-nums whitespace-nowrap">₹{{ inr($p->rate) }}</td>
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
                                            @php
                                                $willArchive = $p->invoice_items_count + $p->purchase_bill_items_count + $p->quotation_items_count + $p->stock_movements_count > 0;
                                                $usedOn = collect([
                                                    $p->invoice_items_count ? 'invoices' : null,
                                                    $p->purchase_bill_items_count ? 'purchase invoices' : null,
                                                    $p->quotation_items_count ? 'quotations' : null,
                                                    $p->stock_movements_count && ! $p->invoice_items_count && ! $p->purchase_bill_items_count ? 'stock entries' : null,
                                                ])->filter()->join(', ', ' and ');
                                            @endphp
                                            <x-confirm-form
                                                :action="route('products.destroy', $p)"
                                                method="DELETE"
                                                title="{{ $willArchive ? 'Archive' : 'Delete' }} {{ $p->name }}?"
                                                message="{{ $willArchive ? 'This item is used on ' . $usedOn . ', so it will be archived, not deleted. That history does not change. It will not be offered on new invoices; restore it any time from the Archived tab.' : 'This item has never been used, so it will be deleted. This cannot be undone.' }}"
                                                confirm-label="{{ $willArchive ? 'Archive' : 'Delete' }} item"
                                                confirm-class="{{ $willArchive ? 'bg-brand-700 hover:bg-brand-800' : 'bg-danger-600 hover:bg-danger-700' }}"
                                                tone="{{ $willArchive ? 'warning' : 'danger' }}">
                                                <button type="button" class="{{ $willArchive ? 'text-accent-800' : 'text-danger-600' }} hover:underline text-sm">{{ $willArchive ? 'Archive' : 'Delete' }}</button>
                                            </x-confirm-form>
                                        @else
                                            {{-- The way back. Before this the only route out
                                                 of the archive was an Active tick box buried
                                                 in the edit form. --}}
                                            <form method="POST" action="{{ route('products.restore', $p) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-brand-700 hover:underline text-sm font-medium">Restore</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    <div class="p-4 border-t border-gray-100">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
