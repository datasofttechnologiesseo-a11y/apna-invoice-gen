<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The guided tour shown on a new user's first visit. People who already use
 * the app are marked as having seen it, so nobody is interrupted; anyone can
 * take it again from the account menu or Help.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('guide_completed_at')->nullable();
        });

        DB::table('users')->update(['guide_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('guide_completed_at');
        });
    }
};
