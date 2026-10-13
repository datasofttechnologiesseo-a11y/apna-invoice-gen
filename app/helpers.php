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
     *   inr(1137520)     → "11,37,520"
     *   inr(964000.00)   → "9,64,000"
     *   inr(0)           → "0"
     *   inr(-12345.5)    → "-12,345.50"
     *   inr(4461.89)     → "4,461.89"
     *
     * A whole amount prints without ".00"; real paise are kept, so the tax on
     * an invoice still equals rate × value. The value itself is never rounded.
     *
     * Returns the bare number string — caller adds the ₹ symbol where needed.
     * Use this for ALL money on customer-facing surfaces (PDFs, emails, web).
     */
    function inr(float|int|string|null $value, int $decimals = 2): string
    {
        $s = \App\Support\IndianNumber::format($value ?? 0, $decimals);

        return $decimals > 0 ? preg_replace('/\.0+$/', '', $s) : $s;
    }
}

if (! function_exists('rupees')) {
    /**
     * A money amount in whole rupees, for screens: lists, dashboard, reports,
     * GST summaries. rupees(118000.4) → "1,18,000", rupees(1180.5) → "1,181".
     *
     * Documents (invoice/quotation/credit note/receipt PDFs and their on-screen
     * copies), GST return files and exports keep paise: use inr() there.
     * Unit rates are prices, not amounts: they keep paise too.
     */
    function rupees(float|int|string|null $value): string
    {
        return \App\Support\IndianNumber::format($value ?? 0, 0);
    }
}

if (! function_exists('plain_amount')) {
    /**
     * An amount for an <input> box: "58500" not "58500.00", "49576.27" as is.
     * No grouping commas (the box must parse it). Anything not numeric, such
     * as what the user typed into a form that failed validation, is returned
     * unchanged.
     */
    function plain_amount(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return (string) $value;
        }
        $v = round((float) $value, 2);

        return $v == floor($v) ? (string) (int) $v : number_format($v, 2, '.', '');
    }
}

if (! function_exists('csv_put')) {
    /**
     * fputcsv() for exports: user text that Excel would run as a formula is
     * written inert (see App\Support\SafeExport). Use for every CSV we produce.
     */
    function csv_put($handle, array $fields): int|false
    {
        return fputcsv($handle, \App\Support\SafeExport::csvRow($fields));
    }
}
