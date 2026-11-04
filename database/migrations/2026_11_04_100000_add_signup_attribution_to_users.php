<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a new account came from: the Google Ads click id and UTM tags of the
 * visit that led to sign-up, and the page it landed on (see
 * App\Http\Middleware\CaptureAttribution). Additive: null for every existing
 * account and for sign-ups that came without tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('signup_attribution')->nullable()->after('referred_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signup_attribution');
        });
    }
};
