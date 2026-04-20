<x-layouts.admin title="{{ $user->name }}" subtitle="{{ $user->email }} · Joined {{ $user->created_at->format('d M Y') }}">
    <x-slot:action>
        @if (! $user->isSuperAdmin() && $user->id !== auth()->id())
            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}"
                  onsubmit="return confirm('Log in as {{ $user->name }}? You can return to your own account anytime.')">
                @csrf
                <button class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded shadow-sm">Impersonate</button>
            </form>
        @endif
        <a href="{{ route('admin.users') }}" class="text-sm text-slate-500 hover:text-slate-900">← All users</a>
    </x-slot:action>

    <div class="space-y-6">

        <div class="flex items-center gap-2">
            @if ($user->isSuperAdmin())
                <span class="px-2.5 py-1 rounded bg-rose-100 text-rose-700 text-xs font-bold uppercase tracking-wider">Super admin</span>
            @endif
            @if ($user->email_verified_at)
                <span class="px-2.5 py-1 rounded bg-emerald-100 text-emerald-700 text-xs font-bold uppercase tracking-wider">Email verified</span>
            @else
                <span class="px-2.5 py-1 rounded bg-amber-100 text-amber-700 text-xs font-bold uppercase tracking-wider">Email unverified</span>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @php
                $tiles = [
                    ['label' => 'Companies', 'value' => $totals['companies'], 'color' => 'sky'],
                    ['label' => 'Customers', 'value' => $totals['customers'], 'color' => 'emerald'],
                    ['label' => 'Invoices',  'value' => $totals['invoices'], 'color' => 'amber'],
                    ['label' => 'Revenue',   'value' => '₹' . number_format($totals['revenue']), 'color' => 'indigo'],
                ];
                $bgColors = ['sky' => 'bg-sky-50 text-sky-700', 'emerald' => 'bg-emerald-50 text-emerald-700', 'amber' => 'bg-amber-50 text-amber-700', 'indigo' => 'bg-indigo-50 text-indigo-700'];
            @endphp
            @foreach ($tiles as $t)
                <div class="p-5 bg-white rounded-xl border border-slate-200">
                    <div class="text-xs font-bold uppercase tracking-wider {{ $bgColors[$t['color']] }} inline-block px-2 py-0.5 rounded">{{ $t['label'] }}</div>
                    <div class="mt-2 font-display text-2xl font-extrabold text-slate-900 tabular-nums">{{ $t['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200"><h3 class="font-display font-bold text-slate-900">Companies ({{ $user->companies->count() }})</h3></div>
            @if ($user->companies->isEmpty())
                <div class="px-5 py-10 text-center text-slate-400">No companies.</div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($user->companies as $co)
                        <li class="px-5 py-3 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-medium text-slate-900 flex items-center gap-2">
                                    {{ $co->name }}
                                    @if ($co->id === $user->active_company_id)<span class="text-[9px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 font-bold uppercase tracking-wider">Active</span>@endif
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    @if ($co->gstin)<span class="font-mono">GSTIN {{ $co->gstin }}</span> · @endif
                                    Prefix <span class="font-mono">{{ $co->invoice_prefix }}</span> ·
                                    Counter at {{ $co->invoice_counter }}
                                </div>
                            </div>
                            <div class="text-xs text-slate-500 whitespace-nowrap text-right">
                                <div>{{ $co->customers_count }} customer{{ $co->customers_count === 1 ? '' : 's' }}</div>
                                <div>{{ $co->invoices_count }} invoice{{ $co->invoices_count === 1 ? '' : 's' }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="font-display font-bold text-slate-900">Recent invoices</h3>
                <a href="{{ route('admin.invoices') }}" class="text-xs font-semibold text-indigo-600 hover:underline">View all →</a>
            </div>
            @if ($recentInvoices->isEmpty())
                <div class="px-5 py-10 text-center text-slate-400">No invoices yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-[10px] text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-2 text-left font-semibold">Number</th>
                            <th class="px-5 py-2 text-left font-semibold">Customer</th>
                            <th class="px-5 py-2 text-left font-semibold">Company</th>
                            <th class="px-5 py-2 text-left font-semibold">Status</th>
                            <th class="px-5 py-2 text-right font-semibold">Total</th>
                            <th class="px-5 py-2 text-left font-semibold">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentInvoices as $inv)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-2 font-mono text-xs">{{ $inv->invoice_number ?? 'Draft #' . $inv->id }}</td>
                                <td class="px-5 py-2">{{ $inv->customer?->name ?? '—' }}</td>
                                <td class="px-5 py-2 text-xs text-slate-600">{{ $inv->company?->name ?? '—' }}</td>
                                <td class="px-5 py-2"><span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ ucfirst(str_replace('_', ' ', $inv->status)) }}</span></td>
                                <td class="px-5 py-2 text-right font-mono tabular-nums">₹{{ number_format((float) $inv->grand_total, 2) }}</td>
                                <td class="px-5 py-2 text-xs text-slate-500">{{ $inv->invoice_date?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-layouts.admin>
