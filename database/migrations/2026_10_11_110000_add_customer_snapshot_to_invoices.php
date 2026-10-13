<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An issued invoice must keep printing the recipient exactly as it was
 * issued (Rule 46(d)/(e)), and GSTR-1 must classify it as it was filed.
 * Documents used to read the live customer row, so editing a customer's
 * GSTIN or state silently rewrote every past invoice and could flip an
 * April B2C invoice into B2B in June.
 *
 * The snapshot is taken at issue time from now on; already-issued invoices
 * get the customer as it stands today, which is the best record available
 * and exactly what they were printing until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('customer_snapshot')->nullable()->after('customer_id');
        });

        $rows = DB::table('invoices as i')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->leftJoin('states as s', 's.id', '=', 'c.state_id')
            ->whereNotNull('i.finalized_at')
            ->select('i.id', 'c.name', 'c.gstin', 'c.address_line1', 'c.address_line2', 'c.city',
                'c.postal_code', 'c.country', 'c.phone', 'c.email',
                's.id as state_id', 's.name as state_name', 's.gst_code', 's.is_union_territory')
            ->orderBy('i.id')
            ->get();

        foreach ($rows as $r) {
            DB::table('invoices')->where('id', $r->id)->update([
                'customer_snapshot' => json_encode([
                    'name' => $r->name,
                    'gstin' => $r->gstin,
                    'address_line1' => $r->address_line1,
                    'address_line2' => $r->address_line2,
                    'city' => $r->city,
                    'postal_code' => $r->postal_code,
                    'country' => $r->country,
                    'phone' => $r->phone,
                    'email' => $r->email,
                    'state' => $r->state_id ? [
                        'id' => $r->state_id,
                        'name' => $r->state_name,
                        'gst_code' => $r->gst_code,
                        'is_union_territory' => (bool) $r->is_union_territory,
                    ] : null,
                ]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('customer_snapshot');
        });
    }
};
