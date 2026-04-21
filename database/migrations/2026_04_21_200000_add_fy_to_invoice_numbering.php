<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial-year-aware invoice numbering.
 *
 * Indian GST convention: every business restarts its invoice series on
 * 1 April (FY begins), typical formats:
 *   INV/2025-26/0001
 *   ACME-25-26/0001
 *
 * We add:
 *   - invoice_number_format  template string; tokens {FY}, {FY_SHORT}, {YYYY}, {N}
 *   - invoice_counter_fy     integer year (e.g. 2025 for FY 2025-26) telling
 *                            us which FY the current counter belongs to; if
 *                            we finalize into a later FY we auto-reset.
 *
 * Back-compat: existing companies with no format fall back to the legacy
 * "{prefix}-{N padded}" behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('invoice_number_format', 60)->nullable()->after('invoice_number_padding');
            $table->unsignedSmallInteger('invoice_counter_fy')->nullable()->after('invoice_number_format');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['invoice_number_format', 'invoice_counter_fy']);
        });
    }
};
