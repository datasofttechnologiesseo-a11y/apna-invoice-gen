<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile-verified registration (Zoho-style). We store the E.164 phone and the
 * timestamp at which the OTP was verified, so we can prove the number was
 * confirmed at sign-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'phone_verified_at']);
        });
    }
};
