<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('auto_backup_enabled')->default(true)->after('referred_by_user_id');
            $table->timestamp('last_backup_sent_at')->nullable()->after('auto_backup_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auto_backup_enabled', 'last_backup_sent_at']);
        });
    }
};
