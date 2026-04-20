<x-layouts.admin title="Overview" subtitle="Real-time metrics across the entire platform">

    <div class="space-y-6">

        {{-- Headline KPIs — clickable --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $tiles = [
                    ['label' => 'Total users',    'value' => number_format($stats['users']['total']),     'sub' => '+' . $stats['users']['new_week'] . ' this week',     'href' => route('admin.users'),     'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'indigo'],
                    ['label' => 'Companies',      'value' => number_format($stats['companies']['total']), 'sub' => $stats['companies']['onboarded'] . ' onboarded',         'href' => route('admin.companies'), 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'sky'],
                    ['label' => 'Customers',      'value' => number_format($stats['customers']['total']), 'sub' => '+' . $stats['customers']['new_week'] . ' this week', 'href' => route('admin.customers'), 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'color' => 'emerald'],
                    ['label' => 'Total invoices', 'value' => number_format($stats['invoices']['total']),  'sub' => '+' . $stats['invoices']['new_week'] . ' this week',  'href' => route('admin.invoices'),  'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'color' => 'amber'],
                ];
                $colors = [
                    'indigo'  => ['bg' => 'bg-indigo-50',  'text' => 'text-indigo-700',  'border' => 'border-indigo-200',  'hover' => 'hover:border-indigo-400'],
                    'sky'     => ['bg' => 'bg-sky-50',     'text' => 'text-sky-700',     'border' => 'border-sky-200',     'hover' => 'hover:border-sky-400'],
                    'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'hover' => 'hover:border-emerald-400'],
                    'amber'   => ['bg' => 'bg-amber-50',   'text' => 'text-amber-700',   'border' => 'border-amber-200',   'hover' => 'hover:border-amber-400'],
                ];
            @endphp
            @foreach ($tiles as $t)
                @php $c = $colors[$t['color']]; @endphp
                <a href="{{ $t['href'] }}" class="group relative p-5 bg-white rounded-xl border {{ $c['border'] }} {{ $c['hover'] }} hover:shadow-md transition-all block">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t['label'] }}</div>
                            <div class="mt-2 font-display text-3xl font-extrabold text-slate-900 tabular-nums">{{ $t['value'] }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $t['sub'] }}</div>
                        </div>
                        <div class="w-10 h-10 rounded-lg {{ $c['bg'] }} flex items-center justify-center">
                            <svg class="w-5 h-5 {{ $c['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $t['icon'] }}"/></svg>
                        </div>
                    </div>
                    <div class="mt-3 text-xs font-medium {{ $c['text'] }} opacity-0 group-hover:opacity-100 transition">View all →</div>
                </a>
            @endforeach
        </div>

        {{-- Revenue + Status mix --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 p-6 bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 rounded-xl shadow-lg text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-48 h-48 bg-emerald-400 rounded-full blur-3xl opacity-20 -translate-y-12 translate-x-12"></div>
                <div class="relative">
                    <div class="text-xs font-bold uppercase tracking-widest text-emerald-200">Revenue processed · All time</div>
                    <div class="mt-2 font-display text-4xl lg:text-5xl font-extrabold tabular-nums">₹{{ number_format($stats['revenue']['grand_total_all_time'], 2) }}</div>
                    <div class="mt-5 grid grid-cols-3 gap-4 text-sm">
                        <div class="border-l-2 border-emerald-400/50 pl-3">
                            <div class="text-emerald-200 text-[10px] uppercase tracking-wider font-bold">Collected</div>
                            <div class="font-bold tabular-nums text-lg mt-0.5">₹{{ number_format($stats['revenue']['collected_all_time']) }}</div>
                        </div>
                        <div class="border-l-2 border-amber-300/70 pl-3">
                            <div class="text-amber-200 text-[10px] uppercase tracking-wider font-bold">Outstanding</div>
                            <div class="font-bold tabular-nums text-lg mt-0.5">₹{{ number_format($stats['revenue']['outstanding']) }}</div>
                        </div>
                        <div class="border-l-2 border-emerald-400/50 pl-3">
                            <div class="text-emerald-200 text-[10px] uppercase tracking-wider font-bold">This month</div>
                            <div class="font-bold tabular-nums text-lg mt-0.5">₹{{ number_format($stats['revenue']['this_month_billed']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 bg-white rounded-xl border border-slate-200">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Invoice status mix</div>
                    <a href="{{ route('admin.invoices') }}" class="text-xs font-semibold text-indigo-600 hover:underline">View all →</a>
                </div>
                @php
                    $statuses = [
                        'Drafts'    => ['count' => $stats['invoices']['drafts'],                                'color' => 'bg-slate-400'],
                        'Finalized' => ['count' => $stats['invoices']['finalized'] - $stats['invoices']['paid'], 'color' => 'bg-indigo-500'],
                        'Paid'      => ['count' => $stats['invoices']['paid'],                                   'color' => 'bg-emerald-500'],
                        'Cancelled' => ['count' => $stats['invoices']['cancelled'],                              'color' => 'bg-rose-500'],
                    ];
                    $totalSt = max(1, array_sum(array_column($statuses, 'count')));
                @endphp
                <div class="mt-4 space-y-3">
                    @foreach ($statuses as $label => $s)
                        @php $pct = $totalSt > 0 ? round(($s['count'] / $totalSt) * 100) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-700 font-medium">{{ $label }}</span>
                                <span class="font-mono text-slate-900 font-semibold">{{ $s['count'] }} · {{ $pct }}%</span>
                            </div>
                            <div class="mt-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $s['color'] }} rounded-full" style="width: {{ $pct }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Trend --}}
        <div class="p-6 bg-white rounded-xl border border-slate-200">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Invoices created</div>
                    <div class="text-sm text-slate-700 mt-0.5">Last 30 days</div>
                </div>
                <div class="flex items-baseline gap-2">
                    <div class="text-2xl font-bold tabular-nums text-slate-900">{{ array_sum(array_column($trend, 'count')) }}</div>
                    <div class="text-xs text-slate-500">total</div>
                </div>
            </div>
            @php $max = max(1, max(array_column($trend, 'count'))); @endphp
            <div class="mt-4 flex items-end gap-1 h-32">
                @foreach ($trend as $d)
                    @php $h = $d['count'] > 0 ? max(6, round(($d['count'] / $max) * 100)) : 2; @endphp
                    <div class="flex-1 flex items-end" title="{{ $d['label'] }}: {{ $d['count'] }}">
                        <div class="w-full rounded-t {{ $d['count'] > 0 ? 'bg-gradient-to-t from-indigo-600 to-indigo-400 hover:from-indigo-700 hover:to-indigo-500' : 'bg-slate-100' }} transition-colors" style="height: {{ $h }}%;"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[10px] text-slate-400 font-medium">
                <span>{{ $trend[0]['label'] }}</span>
                <span>{{ $trend[count($trend) - 1]['label'] }}</span>
            </div>
        </div>

        {{-- Top users + Recent signups --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-display font-bold text-slate-900">Top users by invoices</h3>
                    <a href="{{ route('admin.users') }}" class="text-xs font-semibold text-indigo-600 hover:underline">View all users →</a>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-[10px] text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-2 text-left font-semibold">User</th>
                            <th class="px-5 py-2 text-right font-semibold">Invoices</th>
                            <th class="px-5 py-2 text-right font-semibold">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($topUsers as $u)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.users.show', $u) }}" class="text-indigo-700 hover:underline font-medium">{{ $u->name }}</a>
                                    <div class="text-xs text-slate-500 truncate">{{ $u->email }}</div>
                                </td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ $u->invoices_count }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">₹{{ number_format((float) $u->revenue) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-display font-bold text-slate-900">Recent signups</h3>
                    <a href="{{ route('admin.users') }}" class="text-xs font-semibold text-indigo-600 hover:underline">View all users →</a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($recentUsers as $u)
                        <li class="px-5 py-3 flex items-center gap-3 hover:bg-slate-50">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-sky-500 text-white font-bold flex items-center justify-center text-xs flex-shrink-0">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-slate-900 truncate flex items-center gap-1.5">
                                    <a href="{{ route('admin.users.show', $u) }}" class="hover:underline">{{ $u->name }}</a>
                                    @if ($u->isSuperAdmin())<span class="text-[9px] px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 font-bold">SUPER</span>@endif
                                </div>
                                <div class="text-xs text-slate-500 truncate">{{ $u->email }}</div>
                            </div>
                            <div class="text-xs text-slate-400 whitespace-nowrap">{{ $u->created_at->diffForHumans() }}</div>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-slate-400">No users yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- GST rate usage --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="font-display font-bold text-slate-900">GST rate usage across all line items</h3>
                <p class="text-xs text-slate-500 mt-0.5">What rates businesses are actually billing at</p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[10px] text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-2 text-left font-semibold">GST rate</th>
                        <th class="px-5 py-2 text-right font-semibold">Line items</th>
                        <th class="px-5 py-2 font-semibold">Share</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $totalLines = max(1, $gstRateUsage->sum('line_count')); @endphp
                    @forelse ($gstRateUsage as $row)
                        @php $pct = round(($row->line_count / $totalLines) * 100, 1); @endphp
                        <tr>
                            <td class="px-5 py-2 font-mono">{{ rtrim(rtrim(number_format((float) $row->gst_rate, 2), '0'), '.') }}%</td>
                            <td class="px-5 py-2 text-right font-mono tabular-nums">{{ number_format($row->line_count) }}</td>
                            <td class="px-5 py-2">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-gradient-to-r from-indigo-500 to-sky-500 rounded-full" style="width: {{ $pct }}%"></div></div>
                                    <span class="text-xs text-slate-600 w-12 text-right tabular-nums font-semibold">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">No invoice items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-layouts.admin>
