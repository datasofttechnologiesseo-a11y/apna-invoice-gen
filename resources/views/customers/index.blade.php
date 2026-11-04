<x-app-layout title="Customers">
    @php
        $filtered = request('search') || $owedOnly;
        $canInvoice = \App\Support\Team::can('invoices.create');
        // A short WhatsApp reminder, or null when there is no usable mobile.
        $remindUrl = function ($c, float $owes) use ($company) {
            $digits = \App\Models\Invoice::whatsAppNumber($c->phone);
            if ($digits === null || $owes <= 0) {
                return null;
            }

            return 'https://wa.me/' . $digits . '?text=' . rawurlencode(
                "Namaste {$c->name}, this is a reminder from {$company->name} that ₹" . rupees($owes)
                . ' is due on your account. Please arrange the payment at the earliest. Thank you.');
        };
        $overdueDays = fn ($c) => $c->oldest_overdue_due
            ? (int) \Illuminate\Support\Carbon::parse($c->oldest_overdue_due)->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($today))
            : 0;
        $rowLink = 'inline-flex items-center min-h-[40px] px-2 hover:underline';
    @endphp
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Customers</h1>
                <p class="text-sm text-gray-500 mt-1">
                    @if ($collections['total'] > 0)
                        <span class="font-semibold text-gray-900 tabular-nums">₹{{ rupees($collections['total']) }}</span>
                        to receive from {{ $collections['customers'] }} {{ \Illuminate\Support\Str::plural('customer', $collections['customers']) }}
                    @else
                        Nothing to receive. Every issued invoice is paid.
                    @endif
                </p>
            </div>
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
                @if (! $customers->isEmpty() || $filtered)
                <form method="GET" class="p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3">
                    <x-search-suggest
                        scope="customers"
                        placeholder="Search by name, mobile, email or GSTIN"
                        label="Search by name, mobile, email or GSTIN"
                        all-label="See all customers matching" />
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <label class="inline-flex items-center gap-2 min-h-[40px] text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="owing" value="1" @checked($owedOnly) onchange="this.form.submit()"
                                   class="w-4 h-4 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                            Only who owe
                        </label>
                        <div class="flex items-center gap-2">
                            <label for="customer-sort" class="text-sm text-gray-600">Sort</label>
                            <select id="customer-sort" name="sort" onchange="this.form.submit()" class="h-10 border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="" @selected($sort === 'name')>Name, A to Z</option>
                                <option value="outstanding" @selected($sort === 'outstanding')>Highest outstanding</option>
                            </select>
                        </div>
                        <noscript><button class="inline-flex items-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 text-sm font-semibold rounded-md">Apply</button></noscript>
                        @if ($owedOnly || $sort !== 'name')
                            <a href="{{ route('customers.index', array_filter(['search' => request('search')])) }}" class="inline-flex items-center min-h-[40px] text-sm text-gray-600 hover:text-gray-900">Reset</a>
                        @endif
                    </div>
                </form>
                @endif

                @if ($customers->isEmpty())
                    <x-empty-state
                        icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        :title="request('search') ? 'No customers match this search' : ($owedOnly ? 'Nobody owes you money' : 'No customers yet')"
                        :description="request('search') ? 'Try another name, mobile number or GSTIN.' : ($owedOnly ? 'Every issued invoice is paid.' : 'Add your first customer. Their details fill in on every invoice.')"
                        :actionHref="$filtered ? route('customers.index') : route('customers.create')"
                        :actionLabel="request('search') ? 'Clear search' : ($owedOnly ? 'Show all customers' : 'New customer')"
                    />
                @else
                    {{-- Phones: one card per customer, actions in reach, no sideways scroll. --}}
                    <ul class="lg:hidden divide-y divide-gray-100">
                        @foreach ($customers as $c)
                            @php $owes = (float) ($c->outstanding ?? 0); $late = $overdueDays($c); $remind = $remindUrl($c, $owes); @endphp
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
                                        @if ($late > 0)
                                            <div class="text-xs font-medium text-danger-700 whitespace-nowrap">Overdue {{ $late }} {{ \Illuminate\Support\Str::plural('day', $late) }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-2 -ml-2 flex flex-wrap items-center gap-x-1 text-sm">
                                    <a href="{{ route('customers.ledger', $c) }}" class="{{ $rowLink }} text-brand-700 font-semibold">Ledger</a>
                                    @if ($canInvoice)
                                        <a href="{{ route('invoices.create', ['customer_id' => $c->id]) }}" class="{{ $rowLink }} text-gray-700">Invoice</a>
                                    @endif
                                    @if ($remind)
                                        <a href="{{ $remind }}" target="_blank" rel="noopener" class="{{ $rowLink }} text-money-700 font-semibold" aria-label="Remind {{ $c->name }} on WhatsApp">Remind</a>
                                    @endif
                                    <a href="{{ route('customers.edit', $c) }}" class="{{ $rowLink }} text-gray-700">Edit</a>
{{-- More actions. The menu is placed on the page body so the table's
                                         scroll box cannot clip it. Delete still asks first. --}}
                                    <div x-data="{ more: false, top: 0, right: 0 }" class="inline-block" @keydown.escape.window="more = false">
                                        <button type="button" x-ref="moreBtn"
                                                @click="const r = $refs.moreBtn.getBoundingClientRect(); top = r.bottom + 4; right = Math.max(8, window.innerWidth - r.right); more = !more"
                                                :aria-expanded="more.toString()" aria-haspopup="menu"
                                                aria-label="More actions for {{ $c->name }}" title="More actions"
                                                class="inline-flex items-center justify-center w-10 h-10 rounded-md text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition">
                                            <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        </button>
                                        <template x-teleport="body">
                                            <div x-show="more" x-cloak role="menu" aria-label="More actions for {{ $c->name }}"
                                                 @click.outside="more = false" @scroll.window="more = false" @resize.window="more = false"
                                                 :style="`top: ${top}px; right: ${right}px`"
                                                 class="fixed z-40 w-48 bg-white rounded-md shadow-lg ring-1 ring-gray-200 py-1 text-left">
                                                <x-confirm-form
                                                    :action="route('customers.destroy', $c)"
                                                    method="DELETE"
                                                    title="Delete {{ $c->name }}?"
                                                    message="This cannot be undone. A customer with invoices cannot be deleted."
                                                    confirm-label="Delete customer"
                                                    confirm-class="bg-danger-600 hover:bg-danger-700"
                                                    tone="danger">
                                                    <button type="button" role="menuitem" @click="more = false"
                                                            class="block w-full text-left px-4 min-h-[40px] text-sm text-danger-700 hover:bg-danger-50 focus:bg-danger-50 focus:outline-none">Delete customer</button>
                                                </x-confirm-form>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="hidden lg:block overflow-x-auto">
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
                                @php $owes = (float) ($c->outstanding ?? 0); $late = $overdueDays($c); $remind = $remindUrl($c, $owes); @endphp
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
                                    <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">
                                        <div class="{{ $owes > 0 ? 'font-semibold text-gray-900' : 'text-gray-400' }}">{{ $owes > 0 ? '₹' . rupees($owes) : '–' }}</div>
                                        @if ($late > 0)
                                            <div class="text-xs font-medium text-danger-700">Overdue {{ $late }} {{ \Illuminate\Support\Str::plural('day', $late) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <div class="inline-flex items-center whitespace-nowrap">
                                            <a href="{{ route('customers.ledger', $c) }}" class="text-brand-700 hover:underline px-2 py-1.5" title="Customer ledger with running Dr/Cr balance">Ledger</a>
                                            @if ($canInvoice)
                                                <a href="{{ route('invoices.create', ['customer_id' => $c->id]) }}" class="text-gray-700 hover:underline px-2 py-1.5" title="New invoice for {{ $c->name }}">Invoice</a>
                                            @endif
                                            @if ($remind)
                                                <a href="{{ $remind }}" target="_blank" rel="noopener" class="text-money-700 font-medium hover:underline px-2 py-1.5" aria-label="Remind {{ $c->name }} on WhatsApp" title="Send a payment reminder on WhatsApp">Remind</a>
                                            @endif
                                            <a href="{{ route('customers.edit', $c) }}" class="text-gray-700 hover:underline px-2 py-1.5">Edit</a>
{{-- More actions. The menu is placed on the page body so the table's
                                                 scroll box cannot clip it. Delete still asks first. --}}
                                            <div x-data="{ more: false, top: 0, right: 0 }" class="inline-block" @keydown.escape.window="more = false">
                                                <button type="button" x-ref="moreBtn"
                                                        @click="const r = $refs.moreBtn.getBoundingClientRect(); top = r.bottom + 4; right = Math.max(8, window.innerWidth - r.right); more = !more"
                                                        :aria-expanded="more.toString()" aria-haspopup="menu"
                                                        aria-label="More actions for {{ $c->name }}" title="More actions"
                                                        class="inline-flex items-center justify-center w-10 h-10 rounded-md text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition">
                                                    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                </button>
                                                <template x-teleport="body">
                                                    <div x-show="more" x-cloak role="menu" aria-label="More actions for {{ $c->name }}"
                                                         @click.outside="more = false" @scroll.window="more = false" @resize.window="more = false"
                                                         :style="`top: ${top}px; right: ${right}px`"
                                                         class="fixed z-40 w-48 bg-white rounded-md shadow-lg ring-1 ring-gray-200 py-1 text-left">
                                                        <x-confirm-form
                                                            :action="route('customers.destroy', $c)"
                                                            method="DELETE"
                                                            title="Delete {{ $c->name }}?"
                                                            message="This cannot be undone. A customer with invoices cannot be deleted."
                                                            confirm-label="Delete customer"
                                                            confirm-class="bg-danger-600 hover:bg-danger-700"
                                                            tone="danger">
                                                            <button type="button" role="menuitem" @click="more = false"
                                                                    class="block w-full text-left px-4 min-h-[40px] text-sm text-danger-700 hover:bg-danger-50 focus:bg-danger-50 focus:outline-none">Delete customer</button>
                                                        </x-confirm-form>
                                                    </div>
                                                </template>
                                            </div>
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
