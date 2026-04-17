<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-display font-extrabold text-2xl text-gray-900 leading-tight">{{ __('Dashboard') }}</h2>
                <p class="text-sm text-gray-500 mt-1">Welcome back, {{ auth()->user()->name }}. Here's the view of your business today.</p>
            </div>
            <a href="{{ route('invoices.create') }}" class="inline-flex items-center px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-brand transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New invoice
            </a>
        </div>
    </x-slot>

    @php
        $sym = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'][$currency] ?? ($currency . ' ');
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))
                <div class="p-4 bg-money-50 border-l-4 border-money-500 text-money-800 rounded flex items-start gap-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.58l-1.3-1.3a1 1 0 10-1.4 1.42l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            @unless ($setupComplete)
                <div class="bg-white rounded-2xl shadow-card ring-1 ring-accent-200 overflow-hidden">
                    <div class="p-6 bg-gradient-to-r from-brand-900 via-brand-800 to-accent-900 text-white flex items-center justify-between">
                        <div>
                            <div class="text-xs uppercase font-bold tracking-widest text-accent-300">Getting started</div>
                            <h3 class="mt-1 font-display text-xl font-extrabold">You're {{ $setupProgress }}% set up.</h3>
                            <p class="mt-1 text-brand-100 text-sm">Complete the remaining steps to issue your first invoice.</p>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-20 h-20 rounded-full ring-4 ring-white/20 flex items-center justify-center font-display font-extrabold text-2xl bg-white/10 backdrop-blur">
                                {{ $setupProgress }}%
                            </div>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @php
                            $checklist = [
                                ['done' => $setup['business'], 'title' => 'Complete your business profile', 'sub' => 'Business name, GSTIN, address, state, logo', 'href' => route('company.edit'), 'cta' => $setup['business'] ? 'Edit' : 'Complete →'],
                                ['done' => $setup['customer'], 'title' => 'Add your first customer', 'sub' => 'Save details once, reuse on every invoice', 'href' => route('customers.create'), 'cta' => $setup['customer'] ? 'Manage' : 'Add customer →'],
                                ['done' => $setup['first_invoice'], 'title' => 'Issue your first invoice', 'sub' => 'Create a draft, finalize, download PDF', 'href' => route('invoices.create'), 'cta' => $setup['first_invoice'] ? 'Create another' : 'Create invoice →'],
                            ];
                        @endphp
                        @foreach ($checklist as $item)
                            <div class="flex items-center gap-4 p-5 {{ $item['done'] ? 'bg-money-50/40' : '' }}">
                                <div @class([
                                    'w-10 h-10 rounded-full flex items-center justify-center shrink-0 ring-4',
                                    'bg-money-500 text-white ring-money-100' => $item['done'],
                                    'bg-white text-gray-400 ring-gray-100 border border-gray-200' => ! $item['done'],
                                ])>
                                    @if ($item['done'])
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <div @class(['font-semibold', 'text-money-800 line-through' => $item['done'], 'text-gray-900' => ! $item['done']])>{{ $item['title'] }}</div>
                                    <div class="text-sm text-gray-500">{{ $item['sub'] }}</div>
                                </div>
                                <a href="{{ $item['href'] }}" @class([
                                    'text-sm font-semibold px-4 py-2 rounded-lg transition whitespace-nowrap',
                                    'text-gray-600 hover:text-gray-900 hover:bg-gray-100' => $item['done'],
                                    'text-white bg-brand-700 hover:bg-brand-800 shadow-sm' => ! $item['done'],
                                ])>{{ $item['cta'] }}</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endunless

            <!-- KPIs -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="relative p-6 bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-brand-50 rounded-full -mr-8 -mt-8"></div>
                    <div class="relative">
                        <div class="text-xs uppercase font-bold tracking-wider text-gray-500">Total invoices</div>
                        <div class="text-3xl font-display font-extrabold mt-2">{{ $stats['total'] }}</div>
                        <div class="mt-3 text-xs text-gray-400">all statuses</div>
                    </div>
                </div>
                <div class="relative p-6 bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-gray-50 rounded-full -mr-8 -mt-8"></div>
                    <div class="relative">
                        <div class="text-xs uppercase font-bold tracking-wider text-gray-500">Drafts</div>
                        <div class="text-3xl font-display font-extrabold mt-2">{{ $stats['drafts'] }}</div>
                        <div class="mt-3 text-xs text-gray-400">ready to finalize</div>
                    </div>
                </div>
                <div class="relative p-6 bg-gradient-to-br from-accent-50 to-saffron-50 rounded-2xl shadow-card ring-1 ring-accent-100 overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-accent-200/40 rounded-full -mr-8 -mt-8"></div>
                    <div class="relative">
                        <div class="text-xs uppercase font-bold tracking-wider text-accent-800">Outstanding <span class="text-[10px] text-accent-600">({{ $currency }})</span></div>
                        <div class="text-3xl font-display font-extrabold mt-2 text-accent-900">{{ $sym }}{{ number_format((float) $stats['outstanding'], 2) }}</div>
                        <div class="mt-3 text-xs text-accent-700">awaiting payment</div>
                    </div>
                </div>
                <div class="relative p-6 bg-gradient-to-br from-money-50 to-money-100 rounded-2xl shadow-card ring-1 ring-money-200 overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-money-300/30 rounded-full -mr-8 -mt-8"></div>
                    <div class="relative">
                        <div class="text-xs uppercase font-bold tracking-wider text-money-800">Paid this month <span class="text-[10px] text-money-600">({{ $currency }})</span></div>
                        <div class="text-3xl font-display font-extrabold mt-2 text-money-900">{{ $sym }}{{ number_format((float) $stats['paid_this_month'], 2) }}</div>
                        <div class="mt-3 text-xs text-money-700">{{ now()->format('F Y') }}</div>
                    </div>
                </div>
            </div>

            <!-- Quick actions + recent grid -->
            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Quick actions -->
                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-6">
                        <h3 class="font-display font-bold text-gray-900">Quick actions</h3>
                        <div class="mt-4 space-y-2">
                            <a href="{{ route('invoices.create') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-brand-50 transition group">
                                <div class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center group-hover:bg-brand-700 group-hover:text-white transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">New invoice</div>
                                    <div class="text-xs text-gray-500">Bill a customer now</div>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            <a href="{{ route('customers.create') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-saffron-50 transition group">
                                <div class="w-10 h-10 rounded-lg bg-saffron-100 text-saffron-700 flex items-center justify-center group-hover:bg-saffron-600 group-hover:text-white transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">Add customer</div>
                                    <div class="text-xs text-gray-500">Save for future invoices</div>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-saffron-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            <a href="{{ route('company.edit') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition group">
                                <div class="w-10 h-10 rounded-lg bg-gray-100 text-gray-700 flex items-center justify-center group-hover:bg-gray-800 group-hover:text-white transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">Company settings</div>
                                    <div class="text-xs text-gray-500">Logo, GSTIN, terms</div>
                                </div>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            <a href="{{ route('invoices.index') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-money-50 transition group">
                                <div class="w-10 h-10 rounded-lg bg-money-100 text-money-700 flex items-center justify-center group-hover:bg-money-600 group-hover:text-white transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">All invoices</div>
                                    <div class="text-xs text-gray-500">Filter by status & date</div>
                                </div>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Tips card -->
                    <div class="bg-gradient-to-br from-brand-900 to-brand-700 rounded-2xl p-6 text-white shadow-brand">
                        <div class="flex items-center gap-2 text-accent-300 text-xs font-bold uppercase tracking-wider">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2.39 4.84L18 8l-4 3.9.94 5.48L10 14.77 5.06 17.38 6 11.9 2 8l5.61-1.16z"/></svg>
                            Pro tip
                        </div>
                        <p class="mt-3 text-brand-100 text-sm leading-relaxed">
                            Add your <strong class="text-white">logo and signature image</strong> in Company settings — they'll appear on every PDF as a formal letterhead.
                        </p>
                        <a href="{{ route('company.edit') }}" class="mt-4 inline-flex items-center text-sm font-semibold text-accent-300 hover:text-accent-200">Set up letterhead →</a>
                    </div>
                </div>

                <!-- Recent invoices -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 class="font-display font-bold text-gray-900">Recent invoices</h3>
                                <p class="text-xs text-gray-500">Your latest activity</p>
                            </div>
                            <a href="{{ route('invoices.index') }}" class="text-sm text-brand-700 hover:text-brand-800 font-medium">View all →</a>
                        </div>
                        @if ($recent->isEmpty())
                            <div class="p-12 text-center">
                                <div class="w-16 h-16 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center mx-auto">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <h4 class="mt-4 font-semibold text-gray-900">No invoices yet</h4>
                                <p class="mt-1 text-sm text-gray-500">Create your first invoice to see it here.</p>
                                <a href="{{ route('invoices.create') }}" class="mt-5 inline-flex items-center px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg transition">+ Create invoice</a>
                            </div>
                        @else
                            <div class="divide-y divide-gray-100">
                                @foreach ($recent as $inv)
                                    @php
                                        $badge = [
                                            'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => 'Draft'],
                                            'final' => ['bg' => 'bg-brand-100', 'text' => 'text-brand-800', 'label' => 'Issued'],
                                            'partially_paid' => ['bg' => 'bg-accent-100', 'text' => 'text-accent-800', 'label' => 'Partially paid'],
                                            'paid' => ['bg' => 'bg-money-100', 'text' => 'text-money-800', 'label' => 'Paid'],
                                            'cancelled' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'label' => 'Cancelled'],
                                        ][$inv->status] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => ucfirst($inv->status)];
                                    @endphp
                                    <a href="{{ route('invoices.show', $inv) }}" class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 transition">
                                        <div class="w-10 h-10 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center font-bold text-sm flex-shrink-0">
                                            {{ strtoupper(substr($inv->customer?->name ?? 'NA', 0, 1)) }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-900 truncate">{{ $inv->customer?->name }}</div>
                                            <div class="text-xs text-gray-500 font-mono">
                                                {{ str_starts_with($inv->invoice_number, 'DRAFT-') ? '— Draft' : $inv->invoice_number }}
                                                · {{ $inv->invoice_date?->format('d M Y') }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-mono font-semibold text-gray-900">{{ $inv->currency }} {{ number_format((float) $inv->grand_total, 2) }}</div>
                                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $badge['bg'] }} {{ $badge['text'] }}">{{ $badge['label'] }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
