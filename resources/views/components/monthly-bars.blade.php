@props([
    'rows' => [],              // [['label','full','a','b','url'], ...] - url optional
    'labelA' => 'Series A',
    'labelB' => 'Series B',
    'colorA' => '#70d5e1',     // the reference series - light on purpose
    'colorB' => '#16a34a',     // the one the reader is meant to land on
    'empty' => 'Nothing to compare yet.',
])

@php
    // Paired bars, one pair per bucket. Plain divs rather than SVG: the bars are
    // rectangles, CSS already knows how to lay rectangles out in a row, and a
    // percentage height reflows at every breakpoint without a viewBox to fight.
    //
    // The series are named 'a' and 'b' rather than 'invoiced'/'collected'
    // because the same chart draws sales and purchases; what each one means is
    // in the legend the caller passes.
    $rows = collect($rows);
    $peak = max(1, (float) max($rows->max('a') ?? 0, $rows->max('b') ?? 0));
    $anyData = $rows->contains(fn ($r) => (float) $r['a'] > 0 || (float) $r['b'] > 0);

    // Floor a non-zero bar at 2% so a small period is still visibly a bar and
    // not an empty column - the difference between "little" and "none" is the
    // whole point of the chart.
    $h = fn ($v) => (float) $v <= 0 ? 0 : max(2, round((float) $v / $peak * 100, 2));
@endphp

<div>
    <div class="flex items-center gap-4 flex-wrap">
        <span class="inline-flex items-center gap-1.5 text-xs text-gray-600">
            <span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $colorA }}"></span> {{ $labelA }}
        </span>
        <span class="inline-flex items-center gap-1.5 text-xs text-gray-600">
            <span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $colorB }}"></span> {{ $labelB }}
        </span>
    </div>

    @if (! $anyData)
        <div class="py-10 text-center text-sm text-gray-500">{{ $empty }}</div>
    @else
        <div class="mt-4">
            {{-- Peak marker, so the bar heights are readable as amounts rather
                 than only as a shape. --}}
            <div class="flex items-center gap-2 text-[10px] text-gray-400 font-mono">
                <span class="tabular-nums"><x-inr-compact :amount="$peak" /></span>
                <span class="flex-1 border-t border-dashed border-gray-200"></span>
            </div>

            <div class="flex items-end gap-2 sm:gap-4 h-36">
                @foreach ($rows as $row)
                    @php $href = $row['url'] ?? null; @endphp
                    <{{ $href ? 'a' : 'div' }}
                        @if ($href) href="{{ $href }}" @endif
                        class="flex-1 h-full flex items-end justify-center gap-1 sm:gap-1.5 rounded-t-md transition group {{ $href ? 'hover:bg-gray-50' : '' }}"
                        title="{{ $row['full'] }} — {{ $labelA }} ₹{{ number_format((float) $row['a'], 0) }}, {{ $labelB }} ₹{{ number_format((float) $row['b'], 0) }}">
                        <span class="w-1/2 max-w-[26px] rounded-t-[3px] transition-opacity group-hover:opacity-80"
                              style="height: {{ $h($row['a']) }}%; background: {{ $colorA }}"></span>
                        <span class="w-1/2 max-w-[26px] rounded-t-[3px] transition-opacity group-hover:opacity-80"
                              style="height: {{ $h($row['b']) }}%; background: {{ $colorB }}"></span>
                    </{{ $href ? 'a' : 'div' }}>
                @endforeach
            </div>

            <div class="flex gap-2 sm:gap-4 mt-2">
                @foreach ($rows as $row)
                    <div class="flex-1 text-center text-[11px] font-medium {{ $loop->last ? 'text-gray-900' : 'text-gray-500' }}">
                        {{ $row['label'] }}
                    </div>
                @endforeach
            </div>
        </div>

        <table class="sr-only">
            <caption>{{ $labelA }} and {{ $labelB }} by period</caption>
            <thead><tr><th>Period</th><th>{{ $labelA }} (INR)</th><th>{{ $labelB }} (INR)</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr><td>{{ $row['full'] }}</td><td>{{ $row['a'] }}</td><td>{{ $row['b'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
