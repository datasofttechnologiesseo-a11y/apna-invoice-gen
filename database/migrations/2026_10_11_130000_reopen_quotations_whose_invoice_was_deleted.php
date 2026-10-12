<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quotations marked "converted" whose draft invoice was later deleted were
 * left pointing at nothing (the foreign key nulls on delete) and could never
 * be converted again. Put them back to "accepted" so they can be. Only rows
 * in that broken state are touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('quotations')
            ->where('status', 'converted')
            ->whereNull('converted_to_invoice_id')
            ->update(['status' => 'accepted', 'converted_at' => null]);
    }

    public function down(): void
    {
        // Nothing to restore: the invoices these pointed at no longer exist.
    }
};
