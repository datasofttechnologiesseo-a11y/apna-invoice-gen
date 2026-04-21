<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Receipt numbering sequence — separate from invoice sequence.
            // Payment receipts do not need statutory numbering under GST, but
            // keeping a company-scoped sequence is standard business practice.
            $table->string('receipt_prefix', 20)->default('RCPT')->after('invoice_number_padding');
            $table->unsignedInteger('receipt_counter')->default(0)->after('receipt_prefix');
            $table->unsignedTinyInteger('receipt_number_padding')->default(4)->after('receipt_counter');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['receipt_prefix', 'receipt_counter', 'receipt_number_padding']);
        });
    }
};
