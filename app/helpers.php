<?php

/**
 * Global helper functions auto-loaded via composer.json's "autoload.files".
 *
 * Keep this file tiny — only true cross-cutting helpers belong here. Anything
 * with state or testable logic should live in its own class under App\Support.
 */

if (! function_exists('inr')) {
    /**
     * Format a money amount in the Indian (lakh / crore) grouping convention.
     *
     *   inr(1137520)     → "11,37,520.00"
     *   inr(964000)      → "9,64,000.00"
     *   inr(0)           → "0.00"
     *   inr(-12345.5)    → "-12,345.50"
     *
     * Returns the bare number string — caller adds the ₹ symbol where needed.
     * Use this for ALL money on customer-facing surfaces (PDFs, emails, web).
     */
    function inr(float|int|string|null $value, int $decimals = 2): string
    {
        return \App\Support\IndianNumber::format($value ?? 0, $decimals);
    }
}
