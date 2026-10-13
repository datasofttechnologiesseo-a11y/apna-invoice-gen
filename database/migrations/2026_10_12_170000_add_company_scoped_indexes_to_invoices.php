<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every invoice screen filters by the active company first, then by date,
 * status or finalisation time: the invoice list (ordered by invoice_date),
 * the dashboard counters, GSTR-1/3B and the sales register. Until now the
 * only company-leading index was the (company_id, invoice_number) unique key,
 * so each of those queries read every invoice the company has ever issued.
 *
 * Additive only: three secondary indexes, no column or data changes. Each is
 * skipped if an index of the same name already exists.
 */
return new class extends Migration
{
    private const INDEXES = [
        'invoices_company_id_invoice_date_index' => ['company_id', 'invoice_date'],
        'invoices_company_id_status_index' => ['company_id', 'status'],
        'invoices_company_id_finalized_at_index' => ['company_id', 'finalized_at'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $columns) {
            if (! Schema::hasIndex('invoices', $name)) {
                Schema::table('invoices', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            if (Schema::hasIndex('invoices', $name)) {
                Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }
};
