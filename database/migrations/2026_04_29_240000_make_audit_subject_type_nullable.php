<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log fix: make subject_type nullable.
 *
 * The original migration declared subject_type as NOT NULL, which silently
 * rejected every delete-event log (where the subject model has already
 * been removed and we pass null). This broke the audit trail for the most
 * audit-critical events: deletions.
 *
 * Making it nullable lets `record('expense.deleted', ...)` etc. write the
 * row even though the subject reference is gone. The action + summary +
 * changes JSON still capture what happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('subject_type', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('subject_type', 80)->nullable(false)->change();
        });
    }
};
