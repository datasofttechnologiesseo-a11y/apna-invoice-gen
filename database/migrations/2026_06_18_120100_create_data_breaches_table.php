<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal-data breach register. DPDP Act §8(6) requires a data fiduciary to
 * notify the Data Protection Board and each affected data principal of a
 * personal-data breach. This table is the operator's record of each incident
 * and the notifications made, so the obligation is auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_breaches', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            // open → contained → reported → closed
            $table->string('status', 20)->default('open');
            // low / medium / high / critical
            $table->string('severity', 20)->default('medium');
            $table->timestamp('occurred_at')->nullable();
            // useCurrent() gives MySQL (strict mode) a valid default; the form
            // always supplies a real discovery time on top of it.
            $table->timestamp('discovered_at')->useCurrent();
            $table->unsignedInteger('affected_users')->default(0);
            $table->text('data_categories')->nullable();   // what kind of PII was exposed
            $table->text('remediation')->nullable();        // steps taken / planned
            // Statutory notification milestones.
            $table->timestamp('board_notified_at')->nullable();
            $table->timestamp('users_notified_at')->nullable();
            // Who logged it (nullable so the row survives that admin's deletion).
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'discovered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
    }
};
