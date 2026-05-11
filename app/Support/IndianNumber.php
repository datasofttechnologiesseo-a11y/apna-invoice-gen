<?php

namespace App\Support;

/**
 * Indian (lakh / crore) number-grouping formatter.
 *
 * Built specifically for money on invoices, quotations, receipts, ledgers etc.
 * Returns the bare number string (no currency symbol) so callers can pair it
 * with the right symbol/style:
 *
 *   IndianNumber::format(1137520)     → "11,37,520.00"
 *   IndianNumber::format(964000)      → "9,64,000.00"
 *   IndianNumber::format(173520.5)    → "1,73,520.50"
 *   IndianNumber::format(0)           → "0.00"
 *   IndianNumber::format(-1234567.89) → "-12,34,567.89"
 *
 * The grouping rule: last 3 digits, then groups of 2.
 *   1234567   → 12,34,567
 *   12345678  → 1,23,45,678
 */
class IndianNumber
{
    public static function format(float|int|string $value, int $decimals = 2): string
    {
        $n = (float) $value;
        $negative = $n < 0;
        $abs = abs($n);

        // Split whole and fractional parts so the grouping only touches the integer side.
        $whole = (int) floor($abs);
        $fraction = $decimals > 0
            ? substr(number_format($abs - $whole, $decimals, '.', ''), 1) // ".XX"
            : '';

        $s = (string) $whole;
        if (strlen($s) <= 3) {
            $grouped = $s;
        } else {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            // Insert a comma every 2 digits from the right of the "rest" (the lakhs/crores part).
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $grouped = $rest . ',' . $last3;
        }

        return ($negative ? '-' : '') . $grouped . $fraction;
    }
}
