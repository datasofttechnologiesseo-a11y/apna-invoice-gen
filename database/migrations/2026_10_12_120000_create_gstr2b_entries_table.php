<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSTR-2B lines uploaded by the business (the portal's JSON or Excel), kept
 * per return period so the match with purchase invoices can be re-run as the
 * books change. A new upload for the same period replaces the old lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gstr2b_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);                 // YYYY-MM
            $table->string('kind', 12);                  // invoice | credit_note | debit_note
            $table->string('supplier_gstin', 15);
            $table->string('supplier_name', 200)->nullable();
            $table->string('document_number', 40);
            $table->date('document_date')->nullable();
            $table->decimal('document_value', 14, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('igst', 14, 2)->default(0);
            $table->decimal('cgst', 14, 2)->default(0);
            $table->decimal('sgst', 14, 2)->default(0);
            $table->decimal('cess', 14, 2)->default(0);
            $table->boolean('reverse_charge')->default(false);
            $table->boolean('itc_available')->default(true);
            $table->string('place_of_supply', 2)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'period']);
            $table->index(['company_id', 'supplier_gstin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gstr2b_entries');
    }
};
