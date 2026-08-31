<x-app-layout title="Products and services">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ __('Products & services') }}</h1>
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
                @if (! $products->isEmpty() || request('search') || request('kind') || request('only_inactive'))
                <form method="GET" class="p-4 border-b flex flex-wrap gap-3 items-center">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, SKU or HSN/SAC" class="w-full sm:w-80 border-gray-300 rounded-md shadow-sm">
                    {{-- max-w-full: the long "Service (SAC …)" option otherwise sets an
                         intrinsic width wider than small phone viewports. --}}
                    <select name="kind" class="border-gray-300 rounded-md shadow-sm max-w-full" onchange="this.form.submit()">
                        <option value="">All kinds</option>
                        @foreach (config('uqc_units.kinds') as $k => $label)
                            <option value="{{ $k }}" @selected(request('kind') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="text-sm flex items-center gap-1.5 text-gray-600">
                        <input type="checkbox" name="only_inactive" value="1" @checked(request('only_inactive')) onchange="this.form.submit()" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        Show archived
                    </label>
                    <button class="px-3 py-1.5 bg-brand-700 text-white rounded text-sm hover:bg-brand-800">Filter</button>
                    @if (request('search') || request('kind') || request('only_inactive'))
                        <a href="{{ route('products.index') }}" class="text-gray-500 text-sm">clear</a>
                    @endif
                </form>
                @endif

                @if ($products->isEmpty())
                    <x-empty-state
                        icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                        title="{{ request('search') || request('kind') ? 'No products match that filter' : 'Save the things you sell' }}"
                        description="{{ request('search') || request('kind') ? 'Try a different search term or clear the filter.' : 'Save the name, HSN or SAC code, unit, price and GST rate once. After that, typing the first few letters on an invoice fills in the rest. You can also add a product while making an invoice, without coming back here.' }}"
                        actionHref="{{ request('search') || request('kind') ? route('products.index') : route('products.create') }}"
                        actionLabel="{{ request('search') || request('kind') ? 'Clear filters' : 'Add your first product' }}"
                    />
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
                                            <span class="ml-2 text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 uppercase tracking-wider">Archived</span>
                                        @endunless
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 font-mono text-sm">{{ $p->sku ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm">{{ ucfirst($p->kind) }}</td>
                                    <td class="px-4 py-3 font-mono text-sm">{{ $p->hsn_sac }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $p->unit }}</td>
                                    <td class="px-4 py-3 text-right font-mono">₹{{ inr($p->rate) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ rtrim(rtrim(number_format((float) $p->gst_rate, 2, '.', ''), '0'), '.') }}%</td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <a href="{{ route('products.edit', $p) }}" class="text-brand-600 hover:underline text-sm">Edit</a>
                                        @php $willArchive = $p->invoiceItems()->exists(); @endphp
                                        <x-confirm-form
                                            :action="route('products.destroy', $p)"
                                            method="DELETE"
                                            title="{{ $willArchive ? 'Archive' : 'Delete' }} {{ $p->name }}?"
                                            message="{{ $willArchive ? 'This product has invoice history so it will be archived (hidden from the autocomplete) — the records stay intact for GST audit.' : 'This product has never been invoiced so it will be permanently deleted.' }}"
                                            confirm-label="{{ $willArchive ? 'Archive' : 'Delete' }} product"
                                            confirm-class="{{ $willArchive ? 'bg-amber-600 hover:bg-amber-700' : 'bg-red-600 hover:bg-red-700' }}"
                                            tone="{{ $willArchive ? 'warning' : 'danger' }}">
                                            <button type="button" class="{{ $willArchive ? 'text-amber-600' : 'text-red-600' }} hover:underline text-sm">{{ $willArchive ? 'Archive' : 'Delete' }}</button>
                                        </x-confirm-form>
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
