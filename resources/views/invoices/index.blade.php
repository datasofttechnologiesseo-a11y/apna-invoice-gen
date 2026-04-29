<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Invoices') }}</h2>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('invoices.gstr1', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]) }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md"
                   title="Download invoices in GSTR-1 friendly CSV format for your CA">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h13M3 7h13v6m0 0H3"/></svg>
                    GSTR-1 (this month)
                </a>
                <a href="{{ route('invoices.templates') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-md">+ New invoice</a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white shadow sm:rounded-lg">
                <form method="GET" class="p-4 border-b flex flex-wrap gap-3 items-center">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by invoice #, customer name or mobile" class="border-gray-300 rounded-md shadow-sm w-full sm:w-80">
                    <select name="status" class="border-gray-300 rounded-md shadow-sm" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach (['draft','final','partially_paid','paid','cancelled'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                    <button class="px-3 py-1.5 bg-brand-700 text-white rounded text-sm hover:bg-brand-800">Filter</button>
                    @if (request('search') || request('status'))
                        <a href="{{ route('invoices.index') }}" class="text-gray-500 text-sm">clear</a>
                    @endif
                </form>

                @if ($invoices->isEmpty())
                    <x-empty-state
                        icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        title="{{ request('search') || request('status') ? 'No invoices match that filter' : 'No invoices yet' }}"
                        description="{{ request('search') || request('status') ? 'Try a different search term or clear the filter.' : 'Create your first invoice — it takes about 30 seconds once your customer and product details are saved.' }}"
                        actionHref="{{ request('search') || request('status') ? route('invoices.index') : route('invoices.templates') }}"
                        actionLabel="{{ request('search') || request('status') ? 'Clear filters' : 'Create invoice' }}"
                        :secondaryHref="request('search') || request('status') ? null : route('help')"
                        :secondaryLabel="request('search') || request('status') ? null : 'Read the how-to guide'"
                    />
                @else
                    {{-- Mobile card view — one card per invoice, no horizontal scroll --}}
                    <ul class="md:hidden divide-y divide-gray-100">
                        @foreach ($invoices as $inv)
                            @php
                                $colors = [
                                    'draft' => 'bg-gray-100 text-gray-700',
                                    'final' => 'bg-blue-100 text-blue-800',
                                    'partially_paid' => 'bg-amber-100 text-amber-800',
                                    'paid' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <li class="p-4 flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-mono text-sm">
                                            @if ($inv->isDraft())
                                                <span class="text-gray-400 italic">Draft #{{ $inv->id }}</span>
                                            @else
                                                <span class="font-semibold text-gray-900">{{ $inv->invoice_number }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-sm text-gray-900 truncate">{{ $inv->customer?->name ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $inv->invoice_date?->format('d M Y') }}</div>
                                    </div>
                                    <span class="shrink-0 inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $colors[$inv->status] ?? 'bg-gray-100' }}">{{ ucfirst(str_replace('_',' ',$inv->status)) }}</span>
                                </div>
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="text-gray-500">Total <span class="font-mono font-semibold text-gray-900 ml-1">₹{{ number_format((float) $inv->grand_total, 2) }}</span></span>
                                    @if ((float) $inv->balance > 0)
                                        <span class="text-gray-500">Balance <span class="font-mono font-semibold text-amber-700 ml-1">₹{{ number_format((float) $inv->balance, 2) }}</span></span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 pt-1 text-sm">
                                    <a href="{{ route('invoices.show', $inv) }}" class="inline-flex items-center min-h-[36px] text-brand-700 font-semibold">View</a>
                                    <a href="{{ route('invoices.pdf', $inv) }}" class="inline-flex items-center min-h-[36px] text-gray-700">PDF</a>
                                    @if ($inv->isEditable())
                                        <x-confirm-form
                                            :action="route('invoices.destroy', $inv)"
                                            method="DELETE"
                                            title="Delete draft #{{ $inv->id }}?"
                                            message="This draft and all its line items are permanently deleted. This cannot be undone."
                                            confirm-label="Delete draft"
                                            confirm-class="bg-red-600 hover:bg-red-700"
                                            tone="danger">
                                            <button type="button" class="inline-flex items-center min-h-[36px] text-red-600">Delete</button>
                                        </x-confirm-form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Desktop: full table (unchanged) --}}
                    <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Number</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($invoices as $inv)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-sm">
                                        @if ($inv->isDraft())
                                            <span class="text-gray-400 italic">Draft #{{ $inv->id }}</span>
                                        @else
                                            <span class="font-semibold text-gray-900">{{ $inv->invoice_number }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $inv->invoice_date?->format('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span>{{ $inv->customer?->name }}</span>
                                            @if ($inv->customer?->gstin)
                                                <span class="inline-block text-[9px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-bold uppercase tracking-wider" title="Customer has GSTIN — B2B reportable in GSTR-1">B2B</span>
                                            @else
                                                <span class="inline-block text-[9px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 font-bold uppercase tracking-wider" title="Unregistered customer — B2C">B2C</span>
                                            @endif
                                        </div>
                                        @if ($inv->customer?->phone)
                                            <div class="text-xs text-gray-500 font-mono">{{ $inv->customer->phone }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">₹{{ number_format((float) $inv->grand_total, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono">₹{{ number_format((float) $inv->balance, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $colors = [
                                                'draft' => 'bg-gray-100 text-gray-700',
                                                'final' => 'bg-blue-100 text-blue-800',
                                                'partially_paid' => 'bg-amber-100 text-amber-800',
                                                'paid' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                            ];
                                        @endphp
                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $colors[$inv->status] ?? 'bg-gray-100' }}">{{ ucfirst(str_replace('_',' ',$inv->status)) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                        <a href="{{ route('invoices.show', $inv) }}" class="text-brand-600 hover:underline">View</a>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <a href="{{ route('invoices.pdf', $inv) }}" class="text-gray-600 hover:underline">PDF</a>
                                        @if ($inv->isEditable())
                                            <span class="text-gray-300 mx-1">·</span>
                                            <x-confirm-form
                                                :action="route('invoices.destroy', $inv)"
                                                method="DELETE"
                                                title="Delete draft #{{ $inv->id }}?"
                                                message="This draft and all its line items are permanently deleted. This cannot be undone."
                                                confirm-label="Delete draft"
                                                confirm-class="bg-red-600 hover:bg-red-700"
                                                tone="danger">
                                                <button type="button" class="text-red-600 hover:underline">Delete</button>
                                            </x-confirm-form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    <div class="p-4">{{ $invoices->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
