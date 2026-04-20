<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number', 40)->nullable()->change();
        });

        // Clear placeholder DRAFT-* numbers so drafts show real preview numbers instead
        DB::table('invoices')
            ->where('status', 'draft')
            ->where('invoice_number', 'like', 'DRAFT-%')
            ->update(['invoice_number' => null]);
    }

    public function down(): void
    {
        DB::table('invoices')
            ->whereNull('invoice_number')
            ->update(['invoice_number' => DB::raw("CONCAT('DRAFT-', id)")]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number', 40)->nullable(false)->change();
        });
    }
};
