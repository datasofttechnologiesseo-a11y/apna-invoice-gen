<?php

namespace App\Support;

class NumberToWords
{
    private const ONES = [
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen',
    ];

    private const TENS = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    public static function indianRupees(float $amount, string $currency = 'INR'): string
    {
        $amount = round($amount, 2);
        $integer = (int) floor($amount);
        $paise = (int) round(($amount - $integer) * 100);

        $majorUnit = $currency === 'INR' ? 'Rupees' : strtoupper($currency);
        $minorUnit = $currency === 'INR' ? 'Paise' : 'Cents';

        $words = self::convertIndian($integer);
        $result = $majorUnit . ' ' . $words;

        if ($paise > 0) {
            $result .= ' and ' . self::convertIndian($paise) . ' ' . $minorUnit;
        }

        return $result . ' Only';
    }

    private static function convertIndian(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $parts = [];

        $crore = intdiv($n, 10000000);
        $n %= 10000000;
        if ($crore > 0) {
            $parts[] = self::twoDigits($crore >= 100 ? intdiv($crore, 100) : 0)
                . (($crore >= 100) ? ' Hundred ' : '')
                . self::twoDigits($crore % 100) . ' Crore';
        }

        $lakh = intdiv($n, 100000);
        $n %= 100000;
        if ($lakh > 0) {
            $parts[] = self::twoDigits($lakh) . ' Lakh';
        }

        $thousand = intdiv($n, 1000);
        $n %= 1000;
        if ($thousand > 0) {
            $parts[] = self::twoDigits($thousand) . ' Thousand';
        }

        $hundred = intdiv($n, 100);
        $n %= 100;
        if ($hundred > 0) {
            $parts[] = self::ONES[$hundred] . ' Hundred';
        }

        if ($n > 0) {
            $parts[] = self::twoDigits($n);
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    private static function twoDigits(int $n): string
    {
        if ($n === 0) return '';
        if ($n < 20) return self::ONES[$n];
        $tens = intdiv($n, 10);
        $ones = $n % 10;
        return self::TENS[$tens] . ($ones > 0 ? ' ' . self::ONES[$ones] : '');
    }
}
