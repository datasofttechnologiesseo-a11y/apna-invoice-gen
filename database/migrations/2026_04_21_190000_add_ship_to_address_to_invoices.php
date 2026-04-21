<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-invoice "Ship to" address.
 *
 * For goods, Rule 46 requires both Bill-to (customer's registered address)
 * and Ship-to (delivery address) when they differ. We store Ship-to on the
 * invoice rather than the customer because the same customer can have the
 * same bill-to address but many delivery sites (warehouses, branches, etc).
 *
 * All columns nullable — when every field is null, the invoice is treated
 * as Bill-to = Ship-to (the default / most common case).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('ship_to_name', 255)->nullable()->after('customer_id');
            $table->string('ship_to_address_line1', 255)->nullable()->after('ship_to_name');
            $table->string('ship_to_address_line2', 255)->nullable()->after('ship_to_address_line1');
            $table->string('ship_to_city', 100)->nullable()->after('ship_to_address_line2');
            $table->foreignId('ship_to_state_id')->nullable()->after('ship_to_city')->constrained('states');
            $table->string('ship_to_postal_code', 10)->nullable()->after('ship_to_state_id');
            $table->string('ship_to_gstin', 15)->nullable()->after('ship_to_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['ship_to_state_id']);
            $table->dropColumn([
                'ship_to_name', 'ship_to_address_line1', 'ship_to_address_line2',
                'ship_to_city', 'ship_to_state_id', 'ship_to_postal_code', 'ship_to_gstin',
            ]);
        });
    }
};
