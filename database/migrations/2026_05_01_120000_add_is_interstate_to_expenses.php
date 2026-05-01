<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `is_interstate` to expenses so we can compute GSTR-3B ITC accurately.
 *
 * Default = false (intra-state). The existing GSTR-3B math splits gst_amount
 * 50/50 between CGST and SGST, which is the CORRECT behaviour for intra-state
 * supplies — so leaving every existing expense at false is a no-op for current
 * users. Inter-state expenses (e.g. SaaS subscriptions billed from another
 * state) should be ticked when entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('is_interstate')
                ->default(false)
                ->after('gst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('is_interstate');
        });
    }
};
