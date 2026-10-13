<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The review invite used to be shown once and then retired, whether or not
 * the user ever reviewed. It now keeps coming back (weekly) until the user
 * says they have left the review, so that moment needs its own timestamp.
 * `review_prompt_shown_at` stays and now means "last shown at".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('review_given_at')->nullable()->after('review_prompt_shown_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('review_given_at');
        });
    }
};
