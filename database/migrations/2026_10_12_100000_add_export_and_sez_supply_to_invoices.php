<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exports and supplies to SEZ (zero-rated, s.16 IGST Act). Additive only:
 * every existing invoice is "regular" and stays exactly as it is.
 *
 *  - invoices.supply_type: regular | export_igst | export_lut | sez_igst | sez_lut
 *  - shipping-bill details for GSTR-1 Table 6A (optional at invoice time)
 *  - companies.lut_number: the Letter of Undertaking printed on LUT invoices
 *  - states 96 "Foreign Country" and 97 "Other Territory", the GST portal's
 *    place-of-supply codes for exports and offshore supplies
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('supply_type', 16)->default('regular')->after('reverse_charge');
            $table->string('export_port_code', 10)->nullable()->after('supply_type');
            $table->string('shipping_bill_number', 20)->nullable()->after('export_port_code');
            $table->date('shipping_bill_date')->nullable()->after('shipping_bill_number');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('lut_number', 40)->nullable();
        });

        // Only where the states are already seeded (a live install); a fresh
        // install gets them from StateSeeder with the rest.
        if (! DB::table('states')->exists()) {
            return;
        }
        foreach ([
            ['code' => 'FC', 'name' => 'Foreign Country', 'gst_code' => '96'],
            ['code' => 'OT', 'name' => 'Other Territory', 'gst_code' => '97'],
        ] as $state) {
            if (! DB::table('states')->where('gst_code', $state['gst_code'])->exists()) {
                DB::table('states')->insert($state + ['is_union_territory' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['supply_type', 'export_port_code', 'shipping_bill_number', 'shipping_bill_date']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('lut_number');
        });
        // The two states are left in place: customers may point at them.
    }
};
