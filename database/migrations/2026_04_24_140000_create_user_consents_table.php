<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            // Nullable because cookie consent may be given before sign-up.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('consent_type', 40);
            $table->string('policy_version', 32);
            $table->boolean('given');
            $table->string('context', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'consent_type']);
            $table->index(['consent_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
