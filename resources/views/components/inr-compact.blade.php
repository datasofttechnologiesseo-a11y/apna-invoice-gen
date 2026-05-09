@props([
    'amount' => 0,
    'mode' => 'short',
    'symbol' => true,
])
{{--
    Compact Indian rupee formatter.
    - mode="short" (default): ₹12.5 L / ₹1.2 Cr for tight cards/headers
    - mode="full": ₹12,50,000.00 with Indian lakh/crore comma grouping
    - symbol=false: drops the ₹ prefix
--}}

@php
    $n = (float) $amount;
    $symbolPrefix = $symbol ? '₹' : '';

    if ($mode === 'short') {
        if (abs($n) >= 10000000) {
            // ≥ 1 Cr — show as ₹X Cr or ₹X.X Cr (no decimal once we're at hundreds of crores)
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
        // 'full' mode — Indian lakh/crore comma grouping with 2-decimal paise.
        // PHP's number_format uses Western thousand-grouping (,) so we build
        // the lakh-grouping manually: last 3 digits, then groups of 2.
        $negative = $n < 0;
        $abs = abs($n);
        $whole = (int) floor($abs);
        $paise = number_format($abs - $whole, 2, '.', '');
        $paise = substr($paise, 1); // ".XX"

        $s = (string) $whole;
        if (strlen($s) <= 3) {
            $grouped = $s;
        } else {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $grouped = $rest . ',' . $last3;
        }
        $rendered = ($negative ? '-' : '') . $symbolPrefix . $grouped . $paise;
    }
@endphp
{{ $rendered }}
