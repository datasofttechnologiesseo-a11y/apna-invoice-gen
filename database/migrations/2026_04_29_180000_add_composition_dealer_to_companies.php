<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composition scheme flag on companies.
 *
 * Section 10 of CGST Act: composition dealers cannot collect tax from recipients,
 * cannot issue Tax Invoices, must issue "Bill of Supply" per Section 31(3)(c) /
 * Rule 49, and the bill must carry the declaration "composition taxable person,
 * not eligible to collect tax on supplies" at the top.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('composition_dealer')->default(false)->after('gstin');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('composition_dealer');
        });
    }
};
