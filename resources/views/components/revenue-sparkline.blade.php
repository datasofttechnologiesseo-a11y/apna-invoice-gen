@props(['series'])

@php
    // Pure inline-SVG sparkline - zero JS deps, accessible, prints fine.
    // Expects $series as an array of ['date' => 'YYYY-MM-DD', 'label' => 'd M', 'amount' => float].
    $series = collect($series);
    $count = $series->count();
    $amounts = $series->pluck('amount')->all();
    $max = max(1, max($amounts));
    $total = array_sum($amounts);
    $today = $amounts[$count - 1] ?? 0;
    $peak = $series->sortByDesc('amount')->first();
    $peakAmount = (float) ($peak['amount'] ?? 0);

    // Build polyline points scaled to a 600 × 80 viewBox.
    $vbW = 600;
    $vbH = 80;
    $padTop = 6;
    $padBottom = 4;
    $usableH = $vbH - $padTop - $padBottom;
    $stepX = $count > 1 ? $vbW / ($count - 1) : 0;

    $points = [];
    foreach ($amounts as $i => $a) {
        $x = round($i * $stepX, 2);
        $y = round($padTop + ($usableH - ($a / $max) * $usableH), 2);
        $points[] = "{$x},{$y}";
    }
    $polyline = implode(' ', $points);

    // Closed area under the line: start at bottom-left, polyline, end at bottom-right.
    $area = '0,' . ($vbH - $padBottom) . ' ' . $polyline . ' ' . $vbW . ',' . ($vbH - $padBottom);

    // preserveAspectRatio="none" stretches the viewBox horizontally, which is
    // fine for the line (vector-effect keeps the stroke even) but turns a
    // circle into an ellipse. The end dot is drawn in HTML over the chart
    // instead, so it stays round at every width.
    $lastY = $count > 0 ? (float) explode(',', end($points))[1] : 0;
    $lastTopPct = round($lastY / $vbH * 100, 2);

    // Format INR shortform for big numbers (₹12.5L, ₹1.2Cr) so the labels
    // stay readable inside the card.
    $fmtInr = function ($n) {
        $n = (float) $n;
        if ($n >= 10000000) return '₹' . number_format($n / 10000000, $n >= 100000000 ? 0 : 1) . ' Cr';
        if ($n >= 100000) return '₹' . number_format($n / 100000, 1) . ' L';
        return '₹' . number_format($n, 0);
    };
@endphp

<div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="text-xs uppercase font-bold tracking-wider text-gray-500">Payments received · last 30 days</div>
            <div class="mt-1 flex items-baseline gap-3 flex-wrap">
                <div class="font-display text-2xl sm:text-3xl font-extrabold text-gray-900 tabular-nums">{{ $fmtInr($total) }}</div>
                @if ($total > 0)
                    <div class="text-xs text-gray-500">
                        <span class="font-semibold text-gray-700">{{ $fmtInr($today) }}</span> today
                        @if ($peakAmount > 0 && $peak)
                            · peak <span class="font-semibold text-money-700">{{ $fmtInr($peakAmount) }}</span> on {{ $peak['label'] }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <a href="{{ route('finance.index') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800 whitespace-nowrap">View finance →</a>
    </div>

    @if ($total <= 0)
        <div class="mt-6 py-8 text-center text-sm text-gray-500">
            No payments received in the last 30 days. Once receipts come in, you'll see the trend here.
        </div>
    @else
        <div class="mt-5">
            <div class="relative">
            <svg viewBox="0 0 {{ $vbW }} {{ $vbH }}" preserveAspectRatio="none" class="w-full h-20 block" role="img" aria-label="Payments received per day, last 30 days">
                <defs>
                    <linearGradient id="spark-fill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="rgb(16, 185, 129)" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="rgb(16, 185, 129)" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                {{-- Area fill --}}
                <polyline points="{{ $area }}" fill="url(#spark-fill)" stroke="none"/>
                {{-- Line --}}
                <polyline points="{{ $polyline }}" fill="none" stroke="rgb(5, 150, 105)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
            </svg>

                {{-- One hover target per day, so a reader can put a number to a
                     spike instead of guessing it off the shape. Native title
                     tooltips: no JS, and they work on the printed-out cases
                     where a custom tooltip would not. --}}
                <div class="absolute inset-0 flex" aria-hidden="true">
                    @foreach ($series as $row)
                        <div class="flex-1 hover:bg-money-500/10 transition-colors" title="{{ $row['label'] }} — {{ $fmtInr($row['amount']) }}"></div>
                    @endforeach
                </div>

                {{-- End-of-series dot, in HTML so it stays circular. --}}
                @if ($count > 0)
                    <span class="absolute w-2.5 h-2.5 rounded-full bg-money-600 ring-2 ring-white -translate-x-1/2 -translate-y-1/2 pointer-events-none"
                          style="left: 100%; top: {{ $lastTopPct }}%"></span>
                @endif
            </div>

            {{-- Axis labels: first / mid / last day --}}
            <div class="mt-2 flex justify-between text-[10px] text-gray-500 font-mono">
                <span>{{ $series->first()['label'] ?? '' }}</span>
                <span>{{ $series->get((int) floor($count / 2))['label'] ?? '' }}</span>
                <span>{{ $series->last()['label'] ?? '' }}</span>
            </div>

            {{-- Hidden screen-reader / SEO data table --}}
            <table class="sr-only">
                <caption>Payments received per day for the last 30 days</caption>
                <thead><tr><th>Date</th><th>Amount (INR)</th></tr></thead>
                <tbody>
                    @foreach ($series as $row)
                        <tr><td>{{ $row['date'] }}</td><td>{{ $row['amount'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
