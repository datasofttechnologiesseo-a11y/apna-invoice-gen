<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indian B2B quotations conventionally carry a few extra fields on top of the
 * line items: a Subject line ("Sub: Quotation for…"), the customer's enquiry
 * Reference, and a Delivery period. None of these affect GST math; they're
 * descriptive metadata that ends up on the PDF and helps the customer place
 * the quote against their own RFQ trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('subject', 200)->nullable()->after('valid_until');
            $table->string('reference', 100)->nullable()->after('subject');
            $table->string('delivery_period', 100)->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['subject', 'reference', 'delivery_period']);
        });
    }
};
