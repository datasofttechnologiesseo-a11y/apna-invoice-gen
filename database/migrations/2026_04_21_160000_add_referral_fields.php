<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Short human-readable code (e.g. "AI-K4X9"). Unique, generated
            // lazily when the user first visits their referral page.
            $table->string('referral_code', 16)->nullable()->unique()->after('remember_token');

            // If this user signed up via someone else's code, remember who.
            // nullOnDelete so deleting the referrer doesn't cascade into referees.
            $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 16); // snapshot of the code used
            $table->string('reward_status', 20)->default('pending'); // pending / rewarded / disqualified
            $table->timestamp('signed_up_at');
            $table->timestamp('rewarded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('referee_user_id'); // a user can only be referred once
            $table->index(['referrer_user_id', 'reward_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn(['referral_code', 'referred_by_user_id']);
        });
    }
};
