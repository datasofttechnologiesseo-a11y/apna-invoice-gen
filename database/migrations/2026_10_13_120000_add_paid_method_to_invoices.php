<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the amount typed in "Paid amount" on an invoice was paid. At issue it
 * becomes a receipt, and the receipt must print the real method (UPI, NEFT,
 * cheque…) and its reference, not "Cash" for everything. Null on every
 * existing invoice, which keeps booking as cash, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('paid_method', 20)->nullable()->after('paid_amount');
            $table->string('paid_reference', 80)->nullable()->after('paid_method');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['paid_method', 'paid_reference']);
        });
    }
};
