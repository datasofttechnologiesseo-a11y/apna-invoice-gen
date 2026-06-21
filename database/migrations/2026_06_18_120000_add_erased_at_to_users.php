<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DPDP right to erasure. When a user requests deletion but statutory GST
 * records must be retained, we depersonalise the login account and stamp
 * erased_at. A scheduled purge can later hard-delete the shell once all
 * retained records age out of the retention window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('erased_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('erased_at');
        });
    }
};
