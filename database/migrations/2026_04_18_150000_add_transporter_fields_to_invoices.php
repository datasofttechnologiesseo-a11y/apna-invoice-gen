<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('transporter_name', 120)->nullable()->after('reverse_charge');
            $table->string('transporter_id', 40)->nullable()->after('transporter_name');
            $table->string('vehicle_number', 30)->nullable()->after('transporter_id');
            $table->string('transport_mode', 20)->nullable()->after('vehicle_number');
            $table->string('eway_bill_number', 30)->nullable()->after('transport_mode');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'transporter_name', 'transporter_id', 'vehicle_number',
                'transport_mode', 'eway_bill_number',
            ]);
        });
    }
};
