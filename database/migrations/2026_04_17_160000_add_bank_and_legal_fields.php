<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('signature_path');
            $table->string('bank_account_number', 30)->nullable()->after('bank_name');
            $table->string('bank_ifsc', 15)->nullable()->after('bank_account_number');
            $table->string('bank_branch')->nullable()->after('bank_ifsc');
            $table->string('upi_id')->nullable()->after('bank_branch');
            $table->text('declaration')->nullable()->after('default_terms');
            $table->timestamp('onboarded_at')->nullable()->after('invoice_number_padding');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('reverse_charge')->default(false)->after('is_interstate');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_ifsc', 'bank_branch', 'upi_id', 'declaration', 'onboarded_at']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('reverse_charge');
        });
    }
};
