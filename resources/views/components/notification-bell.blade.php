@php
    use App\Models\Invoice;
    use App\Models\Payment;
    use Illuminate\Support\Carbon;

    // Compute everything from existing tables - no schema change, no
    // background workers. Caller is responsible for gating with @auth.
    $user = auth()->user();

    // 1. ACTION NEEDED - overdue, unpaid invoices.
    $overdue = Invoice::query()
        ->where('user_id', $user->id)
        ->whereNotIn('status', ['cancelled', 'draft'])
        ->whereNotNull('due_date')
        ->whereDate('due_date', '<', now())
        ->where('balance', '>', 0)
        ->with('customer:id,name')
        ->orderBy('due_date')
        ->limit(5)
        ->get(['id', 'invoice_number', 'customer_id', 'due_date', 'balance']);

    // 2. RECENT - payments received in the last 7 days.
    $recentPayments = Payment::query()
        ->where('user_id', $user->id)
        ->where('created_at', '>=', now()->subDays(7))
        ->with(['invoice:id,invoice_number'])
        ->latest('created_at')
        ->limit(5)
        ->get(['id', 'invoice_id', 'amount', 'method', 'received_at', 'created_at']);

    // 3. RECENT - invoices finalized in the last 24 h. Useful "what did I do
    // this morning" peek when a user opens the app on their phone.
    $recentFinalized = Invoice::query()
        ->where('user_id', $user->id)
        ->where('finalized_at', '>=', now()->subDay())
        ->with('customer:id,name')
        ->latest('finalized_at')
        ->limit(5)
        ->get(['id', 'invoice_number', 'customer_id', 'finalized_at', 'grand_total']);

    $overdueCount = $overdue->count();
    $totalCount = $overdueCount + $recentPayments->count() + $recentFinalized->count();
@endphp

<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
    <button type="button"
            @click="open = !open"
            class="relative inline-flex items-center justify-center w-9 h-9 rounded-lg ring-1 ring-saffron-300 bg-white hover:bg-saffron-50 hover:ring-saffron-400 text-brand-900 shadow-md focus:outline-none focus:ring-2 focus:ring-saffron-400 transition"
            :aria-expanded="open.toString()"
            aria-label="Notifications {{ $overdueCount > 0 ? '(' . $overdueCount . ' need attention)' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if ($overdueCount > 0)
            <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-white">
                {{ $overdueCount > 9 ? '9+' : $overdueCount }}
            </span>
        @elseif ($totalCount > 0)
            <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span>
        @endif
    </button>

    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-80 origin-top-right bg-white rounded-xl shadow-xl ring-1 ring-gray-200 z-50 overflow-hidden">

        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-display font-bold text-gray-900 text-sm">Notifications</h3>
            @if ($totalCount > 0)
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">{{ $totalCount }} {{ $totalCount === 1 ? 'item' : 'items' }}</span>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @if ($totalCount === 0)
                <div class="px-4 py-10 text-center">
                    <div class="w-12 h-12 mx-auto rounded-full bg-emerald-50 ring-1 ring-emerald-100 flex items-center justify-center text-emerald-600 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="text-sm font-semibold text-gray-700">All caught up</div>
                    <div class="text-xs text-gray-500 mt-1">Nothing needs your attention right now.</div>
                </div>
            @endif

            @if ($overdue->isNotEmpty())
                <div class="px-4 pt-3 pb-1">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-red-600 flex items-center gap-1.5">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                        Action needed
                    </div>
                </div>
                <ul class="divide-y divide-gray-50">
                    @foreach ($overdue as $inv)
                        <a href="{{ route('invoices.show', $inv) }}" class="block px-4 py-2.5 hover:bg-red-50/40 transition">
                            <div class="flex items-baseline justify-between gap-2">
                                <div class="font-mono text-xs font-semibold text-gray-900 truncate">{{ $inv->invoice_number ?? '#' . $inv->id }}</div>
                                <div class="font-mono text-xs font-bold text-red-700 tabular-nums whitespace-nowrap">₹{{ inr($inv->balance) }}</div>
                            </div>
                            <div class="flex items-baseline justify-between gap-2 mt-0.5">
                                <div class="text-xs text-gray-500 truncate">{{ $inv->customer?->name ?? 'No customer' }}</div>
                                <div class="text-[10px] text-red-600 font-semibold whitespace-nowrap">{{ $inv->due_date?->diffForHumans() }}</div>
                            </div>
                        </a>
                    @endforeach
                </ul>
            @endif

            @if ($recentPayments->isNotEmpty())
                <div class="px-4 pt-3 pb-1">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-emerald-700 flex items-center gap-1.5">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Payments received
                    </div>
                </div>
                <ul class="divide-y divide-gray-50">
                    @foreach ($recentPayments as $p)
                        <a href="{{ $p->invoice ? route('invoices.show', $p->invoice) : '#' }}" class="block px-4 py-2.5 hover:bg-emerald-50/40 transition">
                            <div class="flex items-baseline justify-between gap-2">
                                <div class="text-xs font-semibold text-gray-900 truncate">
                                    ₹{{ inr($p->amount) }}
                                    <span class="font-normal text-gray-500 text-[11px]">on {{ $p->invoice?->invoice_number ?? 'invoice' }}</span>
                                </div>
                                <div class="text-[10px] text-gray-500 whitespace-nowrap">{{ $p->created_at?->diffForHumans() }}</div>
                            </div>
                        </a>
                    @endforeach
                </ul>
            @endif

            @if ($recentFinalized->isNotEmpty())
                <div class="px-4 pt-3 pb-1">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-brand-700 flex items-center gap-1.5">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                        Issued today
                    </div>
                </div>
                <ul class="divide-y divide-gray-50">
                    @foreach ($recentFinalized as $inv)
                        <a href="{{ route('invoices.show', $inv) }}" class="block px-4 py-2.5 hover:bg-brand-50/40 transition">
                            <div class="flex items-baseline justify-between gap-2">
                                <div class="font-mono text-xs font-semibold text-gray-900 truncate">{{ $inv->invoice_number }}</div>
                                <div class="font-mono text-xs text-gray-700 tabular-nums whitespace-nowrap">₹{{ inr($inv->grand_total) }}</div>
                            </div>
                            <div class="flex items-baseline justify-between gap-2 mt-0.5">
                                <div class="text-xs text-gray-500 truncate">{{ $inv->customer?->name ?? 'No customer' }}</div>
                                <div class="text-[10px] text-gray-500 whitespace-nowrap">{{ $inv->finalized_at?->diffForHumans() }}</div>
                            </div>
                        </a>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50">
            <a href="{{ route('invoices.index', ['status' => 'outstanding']) }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">View all outstanding →</a>
        </div>
    </div>
</div>
