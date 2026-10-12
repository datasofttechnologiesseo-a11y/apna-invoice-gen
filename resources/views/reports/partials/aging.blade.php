{{--
    One layout for both aging reports (receivables and payables): the same
    buckets, labels, zero display and ₹ on every amount, so a CA reading one
    can read the other.

    $parties:    list of ['name', 'gstin', 'count', 'oldest', 'b0_30', 'b31_60', 'b61_90', 'b90', 'total', 'url' => ?string]
    $partyLabel: 'Customer' or 'Vendor'
    $docLabel:   'invoice' or 'purchase invoice'
--}}
@php
    $buckets = ['b0_30' => '0–30 days', 'b31_60' => '31–60 days', 'b61_90' => '61–90 days', 'b90' => 'Over 90 days'];
    $parties = collect($parties);
    $summary = ['total' => round((float) $parties->sum('total'), 2), 'docs' => (int) $parties->sum('count')];
    foreach (array_keys($buckets) as $b) {
        $summary[$b] = round((float) $parties->sum($b), 2);
    }
    $money = fn ($v) => (float) $v != 0.0 ? '₹' . rupees($v) : '–';
    $pct = fn ($v) => $summary['total'] > 0 ? round((float) $v / $summary['total'] * 100) : 0;
    $th = 'px-4 py-3 text-xs font-semibold text-gray-600 uppercase';
@endphp

@if ($parties->isEmpty())
    <div class="bg-white rounded-xl ring-1 ring-gray-200 px-6 py-12 text-center">
        <h2 class="font-display text-lg font-bold text-gray-900">Nothing outstanding</h2>
        <p class="mt-1 text-sm text-gray-500">{{ $emptyText ?? 'Every ' . $docLabel . ' is fully paid.' }}</p>
    </div>
@else
    {{-- Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="col-span-2 lg:col-span-1 rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <div class="text-sm font-medium text-gray-600">Total outstanding</div>
            <div class="mt-1 font-display text-xl sm:text-2xl font-extrabold text-gray-900 tabular-nums">₹{{ rupees($summary['total']) }}</div>
            <div class="mt-1 text-xs text-gray-500">
                {{ $summary['docs'] }} {{ \Illuminate\Support\Str::plural($docLabel, $summary['docs']) }} · {{ $parties->count() }} {{ \Illuminate\Support\Str::plural(strtolower($partyLabel), $parties->count()) }}
            </div>
        </div>
        @foreach ($buckets as $key => $label)
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
                <div class="text-sm font-medium text-gray-600">{{ $label }}</div>
                <div class="mt-1 font-display text-lg sm:text-xl font-extrabold tabular-nums {{ $key === 'b90' && $summary[$key] > 0 ? 'text-danger-700' : 'text-gray-900' }}">{{ $money($summary[$key]) }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ $pct($summary[$key]) }}% of total</div>
            </div>
        @endforeach
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden printable">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="{{ $th }} text-left">{{ $partyLabel }}</th>
                        <th class="{{ $th }} text-right">{{ ucfirst(\Illuminate\Support\Str::plural($docLabel)) }}</th>
                        <th class="{{ $th }} text-right">Oldest</th>
                        @foreach ($buckets as $label)
                            <th class="{{ $th }} text-right whitespace-nowrap">{{ $label }}</th>
                        @endforeach
                        <th class="{{ $th }} text-right">Total</th>
                        @if ($parties->contains(fn ($p) => ! empty($p['url'])))
                            <th class="{{ $th }} print:hidden"><span class="sr-only">Ledger</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($parties as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $p['name'] }}</div>
                                @if (! empty($p['gstin']))
                                    <div class="text-xs text-gray-500 font-mono">{{ $p['gstin'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $p['count'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap {{ $p['oldest'] > 90 ? 'text-danger-700 font-semibold' : 'text-gray-600' }}">{{ $p['oldest'] }} {{ \Illuminate\Support\Str::plural('day', $p['oldest']) }}</td>
                            @foreach (array_keys($buckets) as $key)
                                <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap {{ (float) $p[$key] == 0.0 ? 'text-gray-400' : ($key === 'b90' ? 'text-danger-700 font-semibold' : 'text-gray-900') }}">{{ $money($p[$key]) }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-semibold text-gray-900">₹{{ rupees($p['total']) }}</td>
                            @if ($parties->contains(fn ($p) => ! empty($p['url'])))
                                <td class="px-4 py-3 text-right whitespace-nowrap print:hidden">
                                    @if (! empty($p['url']))
                                        <a href="{{ $p['url'] }}" class="text-brand-700 hover:underline text-sm font-semibold">Ledger</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t border-gray-300">
                    <tr class="font-semibold text-gray-900">
                        <td class="px-4 py-3" colspan="3">Total</td>
                        @foreach (array_keys($buckets) as $key)
                            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ $money($summary[$key]) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">₹{{ rupees($summary['total']) }}</td>
                        @if ($parties->contains(fn ($p) => ! empty($p['url'])))
                            <td class="print:hidden"></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Phones: one card per party --}}
    <ul class="md:hidden space-y-2 print:hidden">
        @foreach ($parties as $p)
            <li class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 break-words">{{ $p['name'] }}</div>
                        @if (! empty($p['gstin']))
                            <div class="text-xs text-gray-500 font-mono">{{ $p['gstin'] }}</div>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-semibold text-gray-900 tabular-nums">₹{{ rupees($p['total']) }}</div>
                        <div class="text-xs text-gray-500">{{ $p['count'] }} {{ \Illuminate\Support\Str::plural($docLabel, $p['count']) }} · oldest {{ $p['oldest'] }} {{ \Illuminate\Support\Str::plural('day', $p['oldest']) }}</div>
                    </div>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                    @foreach ($buckets as $key => $label)
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">{{ $label }}</dt>
                            <dd class="tabular-nums {{ (float) $p[$key] == 0.0 ? 'text-gray-400' : ($key === 'b90' ? 'text-danger-700 font-semibold' : 'text-gray-900 font-medium') }}">{{ $money($p[$key]) }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if (! empty($p['url']))
                    <a href="{{ $p['url'] }}" class="mt-2 inline-flex items-center min-h-[40px] text-sm text-brand-700 font-semibold">View ledger</a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
