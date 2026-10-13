<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invoices converted from quotations were created without a line `total`
 * (the column defaulted to 0). Invoice views now print each line's Amount
 * from `total` (GST included), so those lines would read 0.00. Recompute it
 * from the stored taxable value and tax heads - the same sum the calculator
 * writes for every other line.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoice_items')
            ->where('total', 0)
            ->where('amount', '!=', 0)
            ->update([
                'total' => DB::raw('ROUND(amount + cgst_amount + sgst_amount + igst_amount, 2)'),
            ]);
    }

    public function down(): void
    {
        // Not reversible in a meaningful way: the zero totals were a bug.
    }
};
