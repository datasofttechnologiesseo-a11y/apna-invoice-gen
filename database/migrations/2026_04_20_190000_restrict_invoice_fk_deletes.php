<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GST record retention: Indian GST law requires invoice records be preserved
 * for at least 72 months. Previously the user_id and company_id FKs on invoices
 * used cascadeOnDelete — so a Profile→Delete or company wipe would silently
 * destroy finalized invoice records. Switch both to restrictOnDelete so the DB
 * refuses the deletion unless invoices are handled first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['company_id']);

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['company_id']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }
};
