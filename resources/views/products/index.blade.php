<x-app-layout title="Products / Services">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ __('Products') }}</h1>
            <a href="{{ route('products.create') }}" class="inline-flex items-center gap-1 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm whitespace-nowrap transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add new product
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white shadow sm:rounded-lg">
                {{-- Nothing to filter until there is something in the list. Showing an
                     empty search bar to a new user is noise in front of the one thing
                     they actually came here to do. --}}
                @php
                    // "Is something being hidden from the user right now?" - drives
                    // the clear link and the empty state. Landing on In use is the
                    // ordinary view of a catalogue, not a filter someone applied,
                    // so it only counts once the tab has been moved off it.
                    $hasFilters = filled(request('search')) || filled(request('kind')) || $status !== 'active';
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
                        <select name="kind" class="border-gray-300 rounded-md shadow-sm max-w-full" onchange="this.form.submit()">
                            <option value="">All kinds</option>
                            @foreach (config('uqc_units.kinds') as $k => $label)
                                <option value="{{ $k }}" @selected(request('kind') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="px-3 py-1.5 bg-brand-700 text-white rounded text-sm hover:bg-brand-800">Filter</button>
                        @if ($hasFilters)
                            <a href="{{ route('products.index') }}" class="text-gray-500 text-sm">clear</a>
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
                        A product lands here when you remove one that has already been on an invoice - those bills are untouched, and your GST records stay complete.
                        The only thing archiving changes is that the product stops being offered when you make a new invoice.
                        <span class="block mt-1">Press <strong>Restore</strong> on any row to put it back in use.</span>
                    </div>
                @endif

                @if ($products->isEmpty())
                    @if ($status === 'archived' && ! filled(request('search')) && ! filled(request('kind')))
                        {{-- An empty archive and a filter hiding everything look the
                             same on screen; only one of them is good news. --}}
                        <x-empty-state
                            icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            title="Nothing is archived"
                            description="Products you retire land here. Deleting one that has already been on an invoice archives it instead: the invoice keeps its line, and the product simply stops being offered on new ones. Nothing has been archived yet."
                            :actionHref="route('products.index')"
                            actionLabel="Back to products in use"
                        />
                    @else
                    <x-empty-state
                        icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                        :title="$hasFilters ? 'No products match that filter' : 'Save the things you sell'"
                        :description="$hasFilters ? 'Try a different search term or clear the filter.' : 'Save the name, HSN or SAC code, unit, price and GST rate once. After that, typing the first few letters on an invoice fills in the rest. You can also add a product while making an invoice, without coming back here.'"
                        :actionHref="$hasFilters ? route('products.index') : route('products.create')"
                        :actionLabel="$hasFilters ? 'Clear filters' : 'Add your first product'"
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
                                                  title="Kept for the invoices that already use it, but not offered when you make a new one. Press Restore to bring it back.">Archived</span>
                                        @endunless
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 font-mono text-sm">{{ $p->sku ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm">{{ ucfirst($p->kind) }}</td>
                                    <td class="px-4 py-3 font-mono text-sm">{{ $p->hsn_sac }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $p->unit }}</td>
                                    <td class="px-4 py-3 text-right font-mono">₹{{ inr($p->rate) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ rtrim(rtrim(number_format((float) $p->gst_rate, 2, '.', ''), '0'), '.') }}%</td>
                                    <td class="px-4 py-3 text-right space-x-2">
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
                                                message="{{ $willArchive ? 'This product is on ' . $p->invoice_items_count . ' invoice line' . ($p->invoice_items_count === 1 ? '' : 's') . ', so it gets archived rather than deleted. Those invoices do not change. It stops being offered when you make a new invoice, and you can restore it from the Archived tab whenever you want it back.' : 'This product has never been on an invoice, so there is nothing to keep - it is deleted for good.' }}"
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
