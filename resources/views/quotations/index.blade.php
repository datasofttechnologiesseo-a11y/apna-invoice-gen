<x-app-layout title="Quotations">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ __('Quotations') }}</h1>
            <div class="flex flex-wrap items-center gap-3">
                <x-export-toolbar report="quotations" />
                <a href="{{ route('quotations.create') }}" class="inline-flex items-center gap-1 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New quotation
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Slim, dismissible explainer - only shown on first visit, then
                 gets out of the way. --}}
            <div x-data="{ show: !localStorage.getItem('hideQuotationsTip') }" x-show="show" x-cloak
                 class="px-4 py-2.5 bg-brand-50 border border-brand-200 text-brand-900 rounded-lg text-xs flex items-center gap-2.5">
                <svg class="w-4 h-4 flex-shrink-0 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="flex-1">
                    Quotations are not reported in GSTR-1 or GSTR-3B and are not affected by locked books. Use <strong>Convert to invoice</strong> on an accepted quote to raise the tax invoice.
                </span>
                <button type="button" @click="localStorage.setItem('hideQuotationsTip','1'); show=false"
                        class="shrink-0 inline-flex items-center justify-center w-10 h-10 -my-2 rounded text-lg text-brand-700 hover:text-brand-900 hover:bg-brand-100"
                        aria-label="Dismiss tip">×</button>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                <form method="GET" class="p-4 border-b flex flex-wrap gap-3 items-center">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by quote number or customer" class="h-10 border-gray-300 rounded-md shadow-sm w-full sm:w-80" aria-label="Search by quote number or customer">
                    <select name="status" aria-label="Filter by status" class="h-10 border-gray-300 rounded-md shadow-sm" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach (['draft','sent','accepted','declined','converted','expired'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <button class="inline-flex items-center h-10 px-4 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Apply</button>
                    @if (request('search') || request('status'))
                        <a href="{{ route('quotations.index') }}" class="inline-flex items-center min-h-[40px] text-sm text-gray-600 hover:text-gray-900 hover:underline">Clear</a>
                    @endif
                </form>

                @if ($quotations->isEmpty())
                    <x-empty-state
                        icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        :title="request('search') || request('status') ? 'No quotations match that filter' : 'No quotations yet'"
                        :description="request('search') || request('status') ? 'Change the search or clear the filters.' : 'Send a quotation by email or WhatsApp. Once accepted, convert it to a tax invoice without retyping.'"
                        :actionHref="request('search') || request('status') ? route('quotations.index') : route('quotations.create')"
                        :actionLabel="request('search') || request('status') ? 'Clear filters' : 'New quotation'"
                    />
                @else
                    {{-- Mobile cards --}}
                    <ul class="md:hidden divide-y divide-gray-100">
                        @foreach ($quotations as $q)
                            @php
                                $eff = $q->effectiveStatus();
                                $daysLeft = $q->daysUntilExpiry();
                                $showCountdown = $daysLeft !== null && in_array($q->status, ['draft', 'sent']);
                            @endphp
                            <li class="p-4 flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-mono text-sm">
                                            @if ($q->quote_number)
                                                <span class="font-semibold text-gray-900">{{ $q->quote_number }}</span>
                                            @else
                                                <span class="text-gray-500 italic">Draft #{{ $q->id }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-sm text-gray-900 truncate">{{ $q->customer?->name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $q->quote_date?->format('d M Y') }}
                                            @if ($q->valid_until)
                                                · valid until {{ $q->valid_until->format('d M Y') }}
                                                @if ($showCountdown && $daysLeft < 7)
                                                    <span class="ml-1 inline-block text-xs font-semibold {{ $daysLeft < 0 ? 'text-danger-700' : 'text-accent-800' }}">
                                                        @if ($daysLeft < 0) Expired @else ({{ $daysLeft }}d left) @endif
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    <x-status-badge class="shrink-0" :status="$eff" :label="$q->statusLabel()" />
                                </div>
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="text-gray-500">Total <span class="font-mono font-semibold text-gray-900 ml-1">₹{{ rupees($q->grand_total) }}</span></span>
                                </div>
                                <div class="flex items-center gap-3 pt-1 text-sm">
                                    <a href="{{ route('quotations.show', $q) }}" class="inline-flex items-center min-h-[40px] text-brand-700 font-semibold">View</a>
                                    <a href="{{ route('quotations.pdf', $q) }}" class="inline-flex items-center min-h-[40px] text-gray-700">PDF</a>
                                    @if ($q->isDraft())
                                        <x-confirm-form
                                            :action="route('quotations.destroy', $q)"
                                            method="DELETE"
                                            title="Delete draft quotation #{{ $q->id }}?"
                                            message="The draft and its line items will be deleted. This cannot be undone."
                                            confirm-label="Delete draft"
                                            confirm-class="bg-danger-600 hover:bg-danger-700"
                                            tone="danger">
                                            <button type="button" class="inline-flex items-center min-h-[40px] text-danger-600">Delete</button>
                                        </x-confirm-form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Desktop table --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">
                                <tr>
                                    <th class="px-4 py-3">Number</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Valid until</th>
                                    <th class="px-4 py-3">Customer</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($quotations as $q)
                                    @php
                                        $eff = $q->effectiveStatus();
                                        $daysLeft = $q->daysUntilExpiry();
                                        $showCountdown = $daysLeft !== null && in_array($q->status, ['draft', 'sent']);
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm">
                                            @if ($q->quote_number)
                                                <span class="font-semibold text-gray-900">{{ $q->quote_number }}</span>
                                            @else
                                                <span class="text-gray-500 italic">Draft #{{ $q->id }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $q->quote_date?->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            @if ($q->valid_until)
                                                <div>{{ $q->valid_until->format('d M Y') }}</div>
                                                @if ($showCountdown)
                                                    @if ($daysLeft < 0)
                                                        <div class="text-xs font-semibold text-danger-700">Expired</div>
                                                    @elseif ($daysLeft < 7)
                                                        <div class="text-xs font-semibold text-accent-800">Expires in {{ $daysLeft }}d</div>
                                                    @endif
                                                @endif
                                            @else
                                                –
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $q->customer?->name }}</td>
                                        <td class="px-4 py-3 text-right font-mono tabular-nums">₹{{ rupees($q->grand_total) }}</td>
                                        <td class="px-4 py-3">
                                            <x-status-badge :status="$eff" :label="$q->statusLabel()" />
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                            <a href="{{ route('quotations.show', $q) }}" class="text-brand-700 hover:underline">View</a>
                                            <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                                            <a href="{{ route('quotations.pdf', $q) }}" class="text-gray-600 hover:underline">PDF</a>
                                            @if ($q->isDraft())
                                                <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                                                <x-confirm-form
                                                    :action="route('quotations.destroy', $q)"
                                                    method="DELETE"
                                                    title="Delete draft quotation #{{ $q->id }}?"
                                                    message="The draft and its line items will be deleted. This cannot be undone."
                                                    confirm-label="Delete draft"
                                                    confirm-class="bg-danger-600 hover:bg-danger-700"
                                                    tone="danger">
                                                    <button type="button" class="text-danger-600 hover:underline">Delete</button>
                                                </x-confirm-form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $quotations->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
