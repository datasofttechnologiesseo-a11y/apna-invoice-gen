@props([
    'amount' => 0,
    'mode' => 'short',
    'symbol' => true,
])
{{--
    Compact Indian rupee formatter.
    - mode="short" (default): ₹12.5 L / ₹1.2 Cr for tight cards/headers
    - mode="full": ₹12,50,000 with Indian lakh/crore comma grouping
    - symbol=false: drops the ₹ prefix
--}}

@php
    $n = (float) $amount;
    $symbolPrefix = $symbol ? '₹' : '';

    if ($mode === 'short') {
        if (abs($n) >= 10000000) {
            // ≥ 1 Cr - show as ₹X Cr or ₹X.X Cr (no decimal once we're at hundreds of crores)
            $value = $n / 10000000;
            $formatted = abs($value) >= 100
                ? number_format($value, 0)
                : number_format($value, 1);
            $rendered = $symbolPrefix . $formatted . ' Cr';
        } elseif (abs($n) >= 100000) {
            // 1L–99.9L → ₹X.X L
            $value = $n / 100000;
            $rendered = $symbolPrefix . number_format($value, 1) . ' L';
        } elseif (abs($n) >= 1000) {
            // < 1L → ₹X,XXX (Indian grouping, no decimals to stay compact)
            $rendered = $symbolPrefix . number_format($n, 0);
        } else {
            // < ₹1,000 → exact rupees, no decimals (compact)
            $rendered = $symbolPrefix . number_format($n, 0);
        }
    } else {
        // 'full' mode - whole rupees with Indian lakh/crore grouping.
        $whole = rupees($n);
        $rendered = str_starts_with($whole, '-') ? '-' . $symbolPrefix . substr($whole, 1) : $symbolPrefix . $whole;
    }
@endphp
{{ $rendered }}
