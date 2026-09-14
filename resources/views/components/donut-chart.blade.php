@props([
    'segments' => [],          // [['label' =>, 'amount' =>, 'color' => '#rrggbb', 'href' => null, 'note' => null], ...]
    'title' => '',
    'subtitle' => null,
    'centerLabel' => 'Total',
    'empty' => 'Nothing to show here yet.',
    'href' => null,            // optional "view more" link on the header
    'hrefLabel' => 'View all →',
])

@php
    // Inline-SVG donut. Same rules as the sparkline next to it: no chart
    // library, no JS, prints and PDFs cleanly, readable with images off via
    // the sr-only table at the bottom.
    //
    // The ring is one <circle> per segment with pathLength="100", so every
    // dash length is literally a percentage and none of the arc maths depends
    // on the radius. Segments are placed by pushing the dash forward with a
    // negative stroke-dashoffset.
    $rows = collect($segments)
        ->map(fn ($s) => $s + ['amount' => 0, 'color' => '#94a3b8', 'href' => null, 'note' => null])
        ->filter(fn ($s) => (float) $s['amount'] > 0)
        ->values();

    $total = (float) $rows->sum('amount');

    $arcs = [];
    $cursor = 0.0;
    foreach ($rows as $row) {
        $pct = $total > 0 ? ((float) $row['amount'] / $total) * 100 : 0;
        // A hairline gap reads as separation without lying about the size.
        // Segments under ~2% are drawn solid: taking 0.8 off a 1% slice would
        // shrink it by nearly half.
        $gap = $pct > 2 ? 0.8 : 0;
        $arcs[] = [
            'row' => $row,
            'pct' => $pct,
            'dash' => round(max($pct - $gap, 0.35), 3) . ' ' . round(100 - max($pct - $gap, 0.35), 3),
            'offset' => round(-$cursor, 3),
        ];
        $cursor += $pct;
    }
@endphp

<div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-xs uppercase font-bold tracking-wider text-gray-500">{{ $title }}</div>
            @if ($subtitle)
                <div class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</div>
            @endif
        </div>
        @if ($href)
            <a href="{{ $href }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800 whitespace-nowrap">{{ $hrefLabel }}</a>
        @endif
    </div>

    @if ($total <= 0)
        <div class="mt-6 py-8 text-center text-sm text-gray-500">{{ $empty }}</div>
    @else
        <div class="mt-5 flex flex-col sm:flex-row items-center gap-6">
            <div class="relative shrink-0 w-36 h-36">
                <svg viewBox="0 0 42 42" class="w-full h-full -rotate-90" role="img"
                     aria-label="{{ $title }}: {{ $rows->map(fn ($r) => $r['label'] . ' ' . round($r['amount']))->join(', ') }}">
                    {{-- Track, so a single-segment ring still reads as a ring. --}}
                    <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#f1f5f9" stroke-width="5"/>
                    @foreach ($arcs as $arc)
                        <circle cx="21" cy="21" r="15.9155" fill="none"
                                stroke="{{ $arc['row']['color'] }}" stroke-width="5"
                                pathLength="100"
                                stroke-dasharray="{{ $arc['dash'] }}"
                                stroke-dashoffset="{{ $arc['offset'] }}"
                                stroke-linecap="butt">
                            <title>{{ $arc['row']['label'] }} — {{ round($arc['pct']) }}%</title>
                        </circle>
                    @endforeach
                </svg>

                {{-- Centre figure in HTML rather than <text>: it inherits the
                     page font and can use the shared compact-rupee component. --}}
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <div class="font-display text-xl font-extrabold text-gray-900 tabular-nums leading-none">
                        <x-inr-compact :amount="$total" />
                    </div>
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mt-1">{{ $centerLabel }}</div>
                </div>
            </div>

            <div class="flex-1 w-full space-y-2">
                @foreach ($arcs as $arc)
                    @php $row = $arc['row']; @endphp
                    <{{ $row['href'] ? 'a' : 'div' }}
                        @if ($row['href']) href="{{ $row['href'] }}" @endif
                        class="flex items-baseline gap-2.5 rounded-md -mx-1.5 px-1.5 py-1 {{ $row['href'] ? 'hover:bg-gray-50 transition' : '' }}">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0 translate-y-0.5" style="background: {{ $row['color'] }}"></span>
                        <span class="text-sm text-gray-700 flex-1 min-w-0">
                            <span class="truncate">{{ $row['label'] }}</span>
                            @if ($row['note'])
                                <span class="block text-xs text-gray-500">{{ $row['note'] }}</span>
                            @endif
                        </span>
                        <span class="text-sm font-semibold text-gray-900 tabular-nums whitespace-nowrap">
                            <x-inr-compact :amount="$row['amount']" />
                        </span>
                        <span class="text-xs text-gray-500 tabular-nums w-9 text-right shrink-0">{{ round($arc['pct']) }}%</span>
                    </{{ $row['href'] ? 'a' : 'div' }}>
                @endforeach
            </div>
        </div>

        <table class="sr-only">
            <caption>{{ $title }}</caption>
            <thead><tr><th>Bucket</th><th>Amount (INR)</th><th>Share</th></tr></thead>
            <tbody>
                @foreach ($arcs as $arc)
                    <tr><td>{{ $arc['row']['label'] }}</td><td>{{ $arc['row']['amount'] }}</td><td>{{ round($arc['pct']) }}%</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
