<x-app-layout title="Customers">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Customers</h1>
            <div class="flex flex-wrap items-center gap-2">
                <x-export-toolbar report="customers" :import="route('imports.show', 'customers')" />
                <a href="{{ route('customers.create') }}" class="inline-flex items-center gap-1.5 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm whitespace-nowrap transition">
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New customer
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />
            @if ($errors->any())
                <x-flash type="error" :message="implode(' · ', $errors->all())" :auto="false" />
            @endif

            <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                {{-- Nothing to filter until there is something in the list. Showing an
                     empty search bar to a new user is noise in front of the one thing
                     they actually came here to do. --}}
                @if (! $customers->isEmpty() || request('search'))
                <form method="GET" class="p-4 border-b border-gray-100">
                    <x-search-suggest
                        scope="customers"
                        placeholder="Search by name, mobile, email or GSTIN"
                        label="Search by name, mobile, email or GSTIN"
                        all-label="See all customers matching" />
                </form>
                @endif

                @if ($customers->isEmpty())
                    <x-empty-state
                        icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        :title="request('search') ? 'No customers match this search' : 'No customers yet'"
                        :description="request('search') ? 'Try another name, mobile number or GSTIN.' : 'Saved details fill in on every invoice. Their state sets CGST/SGST or IGST.'"
                        :actionHref="request('search') ? route('customers.index') : route('customers.create')"
                        :actionLabel="request('search') ? 'Clear search' : 'New customer'"
                    />
                @else
                    {{-- Phones: one card per customer, actions in reach, no sideways scroll. --}}
                    <ul class="md:hidden divide-y divide-gray-100">
                        @foreach ($customers as $c)
                            @php $owes = (float) ($c->outstanding ?? 0); @endphp
                            <li class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('customers.ledger', $c) }}" class="font-semibold text-brand-700 hover:underline break-words">{{ $c->name }}</a>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            {{ collect([$c->state?->name, $c->gstin])->filter()->implode(' · ') ?: 'No GSTIN' }}
                                        </div>
                                        @if ($c->phone || $c->email)
                                            <div class="text-xs text-gray-500 truncate">{{ collect([$c->phone, $c->email])->filter()->implode(' · ') }}</div>
                                        @endif
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs text-gray-500">Outstanding</div>
                                        <div class="font-semibold tabular-nums {{ $owes > 0 ? 'text-gray-900' : 'text-gray-400' }}">{{ $owes > 0 ? '₹' . rupees($owes) : '–' }}</div>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center gap-4 text-sm">
                                    <a href="{{ route('customers.ledger', $c) }}" class="inline-flex items-center min-h-[40px] text-brand-700 font-semibold">Ledger</a>
                                    <a href="{{ route('customers.edit', $c) }}" class="inline-flex items-center min-h-[40px] text-gray-700">Edit</a>
                                    <x-confirm-form
                                        :action="route('customers.destroy', $c)"
                                        method="DELETE"
                                        title="Delete {{ $c->name }}?"
                                        message="This cannot be undone. A customer with invoices cannot be deleted."
                                        confirm-label="Delete customer"
                                        confirm-class="bg-danger-600 hover:bg-danger-700"
                                        tone="danger">
                                        <button type="button" class="inline-flex items-center min-h-[40px] text-danger-600">Delete</button>
                                    </x-confirm-form>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">GSTIN</th>
                                <th class="px-4 py-3">State</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3 text-right">Outstanding</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @foreach ($customers as $c)
                                @php $owes = (float) ($c->outstanding ?? 0); @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('customers.ledger', $c) }}" class="font-medium text-brand-700 hover:underline" title="Open the ledger">{{ $c->name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 font-mono">{{ $c->gstin ?? '–' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $c->state?->name ?? '–' }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        @if ($c->email)<div class="truncate max-w-[200px]" title="{{ $c->email }}">{{ $c->email }}</div>@endif
                                        @if ($c->phone)<div class="text-gray-500 font-mono text-xs">{{ $c->phone }}</div>@endif
                                        @if (! $c->email && ! $c->phone)<span class="text-gray-400">–</span>@endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap {{ $owes > 0 ? 'font-semibold text-gray-900' : 'text-gray-400' }}">
                                        {{ $owes > 0 ? '₹' . rupees($owes) : '–' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1 whitespace-nowrap">
                                            <a href="{{ route('customers.ledger', $c) }}" class="text-brand-700 hover:underline px-2 py-1.5" title="Customer ledger with running Dr/Cr balance">Ledger</a>
                                            <a href="{{ route('customers.edit', $c) }}" class="text-gray-700 hover:underline px-2 py-1.5">Edit</a>
                                            <x-confirm-form
                                                :action="route('customers.destroy', $c)"
                                                method="DELETE"
                                                title="Delete {{ $c->name }}?"
                                                message="This cannot be undone. A customer with invoices cannot be deleted."
                                                confirm-label="Delete customer"
                                                confirm-class="bg-danger-600 hover:bg-danger-700"
                                                tone="danger">
                                                <button type="button" class="text-danger-600 hover:underline px-2 py-1.5">Delete</button>
                                            </x-confirm-form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    @if ($customers->hasPages())
                        <div class="p-4 border-t border-gray-100">{{ $customers->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <x-quick-action-fab :href="route('customers.create')" label="New customer" />
</x-app-layout>
