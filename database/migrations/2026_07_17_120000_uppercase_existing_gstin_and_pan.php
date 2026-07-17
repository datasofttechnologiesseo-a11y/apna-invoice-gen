<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GSTIN/PAN were historically stored exactly as typed — ValidGstin uppercases
 * its own copy before checking, so mixed-case values (e.g. "07aahCd4796l1z0")
 * passed validation and were saved verbatim, then printed on invoices in a
 * non-statutory form. The models now normalise on read/write; this migration
 * cleans the rows that were saved before that cast existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // UPPER()/TRIM() are portable across MySQL, SQLite and Postgres.
        DB::table('companies')->update([
            'gstin' => DB::raw('UPPER(TRIM(gstin))'),
            'pan' => DB::raw('UPPER(TRIM(pan))'),
        ]);
        DB::table('customers')->update(['gstin' => DB::raw('UPPER(TRIM(gstin))')]);
        DB::table('invoices')->update(['ship_to_gstin' => DB::raw('UPPER(TRIM(ship_to_gstin))')]);
        DB::table('cash_memos')->update(['seller_gstin' => DB::raw('UPPER(TRIM(seller_gstin))')]);
    }

    public function down(): void
    {
        // Original casing was never meaningful; nothing to restore.
    }
};
