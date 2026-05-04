<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quote numbering — entirely separate from invoice numbering. Quotes don't
 * touch GST returns, so they can have their own counter that runs continuously
 * (no FY reset by default — but we still support {FY} in the format string for
 * users who want it). Default format: QT/{FY}/{N} which renders as
 * "QT/2026-27/0001" — visually similar to invoice numbers but unmistakably a
 * quotation, not a tax invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('quote_prefix', 10)->nullable()->after('invoice_counter_fy');
            $table->string('quote_number_format', 60)->nullable()->after('quote_prefix');
            $table->unsignedInteger('quote_counter')->default(0)->after('quote_number_format');
            $table->unsignedSmallInteger('quote_counter_fy')->nullable()->after('quote_counter');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['quote_prefix', 'quote_number_format', 'quote_counter', 'quote_counter_fy']);
        });
    }
};
