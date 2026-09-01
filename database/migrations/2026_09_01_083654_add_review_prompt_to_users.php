<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the Google-review invite, which is shown once - right after the
 * user issues their very first invoice.
 *
 * A single timestamp is all that is needed: it records that the invite has
 * been shown and, because it is only ever shown once, doubles as the flag
 * that stops it ever appearing again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('review_prompt_shown_at')->nullable()->after('last_backup_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('review_prompt_shown_at');
        });
    }
};
