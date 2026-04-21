<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Audit trail for cancelled invoices. GST best practice: never
            // delete a finalised invoice — mark it cancelled with reason + timestamp.
            $table->timestamp('cancelled_at')->nullable()->after('finalized_at');
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
