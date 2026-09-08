<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the user last opened the notification bell.
     *
     * The bell had no read state at all: its green dot was derived purely
     * from time windows (payments in the last 7 days, invoices issued in the
     * last 24 h), so opening it changed nothing and the dot sat there for a
     * week. One timestamp is enough - the dot compares against the newest
     * activity rather than tracking each item.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('notifications_seen_at')->nullable()->after('review_prompt_shown_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notifications_seen_at');
        });
    }
};
