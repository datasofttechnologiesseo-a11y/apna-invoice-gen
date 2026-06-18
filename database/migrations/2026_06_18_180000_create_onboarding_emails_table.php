<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which activation/onboarding lifecycle emails each user has been sent,
 * so the welcome + day-1/3/7 nudges each fire exactly once per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 32); // welcome | day1 | day3 | day7
            $table->timestamp('sent_at')->useCurrent();

            $table->unique(['user_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_emails');
    }
};
