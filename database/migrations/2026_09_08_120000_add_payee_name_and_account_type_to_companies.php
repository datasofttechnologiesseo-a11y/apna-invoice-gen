<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two fields the bank block on an Indian invoice is expected to carry.
     *
     * `bank_account_name` is the payee — the name the bank holds on the
     * account, which is what a customer must put on a cheque or NEFT. It is
     * separate from `name` because it is often not the same string: a
     * proprietor billing as a trade name may be collecting into an account
     * held in their own name.
     *
     * `bank_account_type` is savings/current. It is not needed to move money,
     * but accounts teams check it against the beneficiary they have on file
     * and it is on every printed bank block they are used to.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('bank_account_name', 120)->nullable()->after('bank_name');
            $table->string('bank_account_type', 20)->nullable()->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['bank_account_name', 'bank_account_type']);
        });
    }
};
