<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lines a credit note credits, when the owner picked them (a sales return
 * of 1 almirah out of 7). Additive only: existing credit notes have no lines
 * and keep being read exactly as before (amount spread pro rata over the
 * invoice). With lines, GSTR-1 Table 12 reduces the right HSN and quantity,
 * and a sales return can put the goods back into stock.
 *
 * Every figure is a snapshot taken when the note is issued, so it never
 * changes if the item or the invoice line is edited later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hsn_sac', 10)->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('unit', 20)->nullable();
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('cgst', 14, 2)->default(0);
            $table->decimal('sgst', 14, 2)->default(0);
            $table->decimal('igst', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
