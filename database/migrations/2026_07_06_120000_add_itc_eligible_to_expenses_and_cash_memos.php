<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an `itc_eligible` flag to expenses and cash memos.
 *
 * Under CGST Act §17(5) ("blocked credits"), GST paid on some purchases —
 * motor vehicles, staff food/catering, club memberships, goods for personal
 * use, etc. — cannot be claimed as input tax credit. The GSTR-3B helper needs
 * to exclude those, so users mark such rows as not eligible.
 *
 * Defaults to TRUE so every existing row (and the common case) stays claimable
 * — no historical ITC figure changes until a user actively flags something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('itc_eligible')->default(true)->after('gst_amount');
        });

        Schema::table('cash_memos', function (Blueprint $table) {
            $table->boolean('itc_eligible')->default(true)->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('itc_eligible');
        });

        Schema::table('cash_memos', function (Blueprint $table) {
            $table->dropColumn('itc_eligible');
        });
    }
};
