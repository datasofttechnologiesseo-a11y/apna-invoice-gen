<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight audit log.
 *
 * Records every meaningful mutation (create/update/delete/finalize/cancel) on
 * key financial records — Invoices, Payments, Cash Memos, Expenses, Credit
 * Notes — so the user (and their CA) can trace who did what, when, why.
 *
 * This is the bare minimum for audit defensibility under Section 44AA of the
 * Income Tax Act and any books-of-accounts inspection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 80); // e.g. App\Models\Invoice
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 40); // created, updated, deleted, finalized, cancelled, payment_recorded …
            $table->string('summary', 255)->nullable(); // short human-readable line
            $table->json('changes')->nullable(); // before / after diff (optional)
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
