<x-app-layout title="Vendors">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Vendors</h1>
                <p class="text-sm text-gray-500 mt-1">Suppliers on your purchase invoices · {{ $company->name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-export-toolbar report="vendors" :import="route('imports.show', 'vendors')" />
                <a href="{{ route('vendors.create') }}" class="inline-flex items-center gap-1 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add vendor
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                @if (! $vendors->isEmpty() || request('search'))
                <form method="GET" class="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-center">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, GSTIN, phone or email" class="w-full sm:w-80 border-gray-300 rounded-md shadow-sm text-sm" aria-label="Search vendors">
                    <button class="px-3 py-1.5 bg-brand-700 text-white rounded text-sm hover:bg-brand-800">Search</button>
                    @if (request('search'))<a href="{{ route('vendors.index') }}" class="text-gray-500 text-sm">Clear</a>@endif
                </form>
                @endif

                @if ($vendors->isEmpty())
                    <x-empty-state
                        icon="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                        :title="request('search') ? 'No vendors match the search' : 'No vendors yet'"
                        :description="request('search') ? 'Search by another name or GSTIN.' : 'The vendor state sets CGST/SGST or IGST; the GSTIN is used for the GSTR-2B match.'"
                        :actionHref="request('search') ? route('vendors.index') : route('vendors.create')"
                        :actionLabel="request('search') ? 'Clear search' : 'Add vendor'"
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-left text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">GSTIN</th>
                                    <th class="px-4 py-3">State</th>
                                    <th class="px-4 py-3">Contact</th>
                                    <th class="px-4 py-3 text-right">Purchase invoices</th>
                                    <th class="px-4 py-3 text-right">Payable</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($vendors as $v)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $v->name }}</td>
                                        <td class="px-4 py-3 font-mono text-xs">{{ $v->gstin ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ $v->state?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-600">{{ collect([$v->phone, $v->email])->filter()->implode(' · ') ?: '-' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $v->purchase_bills_count }}</td>
                                        <td class="px-4 py-3 text-right font-mono tabular-nums {{ (float) $v->payable > 0 ? 'text-accent-800 font-semibold' : '' }}">₹{{ rupees($v->payable ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('purchases.index', ['search' => $v->name]) }}" class="text-brand-700 hover:underline">Purchases</a>
                                            <span class="text-gray-300">·</span>
                                            <a href="{{ route('vendors.edit', $v) }}" class="text-gray-600 hover:underline">Edit</a>
                                            <span class="text-gray-300">·</span>
                                            <x-confirm-form :action="route('vendors.destroy', $v)" method="DELETE" title="Delete this vendor?" message="Only vendors with no purchase invoices can be deleted." confirm-label="Delete" confirm-class="bg-danger-600 hover:bg-danger-700" tone="danger">
                                                <button type="button" class="text-danger-600 hover:underline">Delete</button>
                                            </x-confirm-form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-gray-100">{{ $vendors->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
