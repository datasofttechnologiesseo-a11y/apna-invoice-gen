<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referral reward: refer a friend, and when they issue their first invoice
 * you get lifetime free access. lifetime_free_at records when it was earned;
 * billing (when paid plans arrive) must never charge an account that has it.
 *
 * Referrals whose friend has already issued an invoice are rewarded now, so
 * people who referred before the offer existed are not left out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('lifetime_free_at')->nullable()->after('referred_by_user_id');
        });

        $now = now();
        $qualified = DB::table('referrals')
            ->where('reward_status', 'pending')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('invoices')
                ->whereColumn('invoices.user_id', 'referrals.referee_user_id')
                ->whereNotNull('invoices.finalized_at'))
            ->get(['id', 'referrer_user_id']);

        foreach ($qualified as $r) {
            DB::table('referrals')->where('id', $r->id)->update(['reward_status' => 'rewarded', 'rewarded_at' => $now, 'updated_at' => $now]);
            DB::table('users')->where('id', $r->referrer_user_id)->whereNull('lifetime_free_at')->update(['lifetime_free_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('lifetime_free_at');
        });
    }
};
