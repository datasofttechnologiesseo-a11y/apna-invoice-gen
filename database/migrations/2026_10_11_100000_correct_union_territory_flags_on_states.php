<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * is_union_territory decides whether the state share of an intra-state
 * supply prints as SGST or UTGST. UTGST applies only in Union Territories
 * WITHOUT a legislature (UTGST Act s.2(8)): Chandigarh (04), Dadra & Nagar
 * Haveli and Daman & Diu (26; the retired 25 kept for old rows), Lakshadweep
 * (31), Andaman & Nicobar (35) and Ladakh (38). Delhi (07), Puducherry (34)
 * and Jammu & Kashmir (01) have legislatures and levy SGST - the seeder had
 * flagged all three as UTs.
 *
 * Data only; no invoice amounts change (the split is the same, only the
 * label differs on documents printed from now on).
 */
return new class extends Migration
{
    private const UTGST_CODES = ['04', '25', '26', '31', '35', '38'];

    public function up(): void
    {
        DB::table('states')->update(['is_union_territory' => false]);
        DB::table('states')->whereIn('gst_code', self::UTGST_CODES)->update(['is_union_territory' => true]);
    }

    public function down(): void
    {
        // Restore the seeder's previous (wrong) flags for the three legislature UTs.
        DB::table('states')->whereIn('gst_code', ['01', '07', '34'])->update(['is_union_territory' => true]);
    }
};
