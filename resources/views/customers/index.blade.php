<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Customers') }}</h2>
            <a href="{{ route('customers.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-md whitespace-nowrap">+ New customer</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />
            @if ($errors->any())
                <x-flash type="error" :message="implode(' · ', $errors->all())" :auto="false" />
            @endif

            <div class="bg-white shadow sm:rounded-lg">
                <form method="GET" class="p-4 border-b">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email" class="w-full md:w-80 border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                </form>

                @if ($customers->isEmpty())
                    <x-empty-state
                        icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        title="{{ request('search') ? 'No customers match that search' : 'No customers yet' }}"
                        description="{{ request('search') ? 'Try a different search term or clear the filter.' : 'Save customer details once — name, GSTIN, address, mobile — and reuse them on every invoice. We auto-detect intrastate vs interstate GST based on their state.' }}"
                        actionHref="{{ request('search') ? route('customers.index') : route('customers.create') }}"
                        actionLabel="{{ request('search') ? 'Clear search' : 'Add a customer' }}"
                    />
                @else
                    <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">GSTIN</th>
                                <th class="px-4 py-3">State</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($customers as $c)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $c->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 font-mono text-sm">{{ $c->gstin ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $c->state?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 text-sm">
                                        @if ($c->email)<div class="truncate max-w-[200px]" title="{{ $c->email }}">{{ $c->email }}</div>@endif
                                        @if ($c->phone)<div class="text-gray-500 font-mono text-xs">{{ $c->phone }}</div>@endif
                                        @if (! $c->email && ! $c->phone)<span class="text-gray-300">—</span>@endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                            <a href="{{ route('customers.ledger', $c) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-money-50 hover:bg-money-100 text-money-800 text-xs font-semibold ring-1 ring-money-200 transition"
                                               title="View ledger — Dr/Cr running balance for this customer">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2a4 4 0 014-4h6m0 0l-3-3m3 3l-3 3M3 7h6a4 4 0 014 4v6"/></svg>
                                                Ledger
                                            </a>
                                            <a href="{{ route('customers.edit', $c) }}" class="text-brand-600 hover:underline text-sm px-2 py-1.5">Edit</a>
                                            <x-confirm-form
                                                :action="route('customers.destroy', $c)"
                                                method="DELETE"
                                                title="Delete {{ $c->name }}?"
                                                message="Customers with issued invoices can't be deleted for GST audit reasons — we'll show a friendly error if that happens."
                                                confirm-label="Delete customer"
                                                confirm-class="bg-red-600 hover:bg-red-700"
                                                tone="danger">
                                                <button type="button" class="text-red-600 hover:underline text-sm px-2 py-1.5">Delete</button>
                                            </x-confirm-form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    <div class="p-4">{{ $customers->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
