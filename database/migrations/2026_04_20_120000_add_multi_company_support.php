<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_company_id')->nullable()->after('password')
                ->constrained('companies')->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')
                ->constrained('companies')->cascadeOnDelete();
            $table->index('company_id', 'customers_company_id_index');
        });

        // Backfill: each existing customer is mapped to its user's first company
        DB::table('users')->orderBy('id')->chunk(200, function ($users) {
            foreach ($users as $u) {
                $firstCompany = DB::table('companies')->where('user_id', $u->id)->orderBy('id')->first();
                if (! $firstCompany) {
                    continue;
                }

                DB::table('customers')
                    ->where('user_id', $u->id)
                    ->whereNull('company_id')
                    ->update(['company_id' => $firstCompany->id]);

                if ($u->active_company_id === null) {
                    DB::table('users')->where('id', $u->id)->update(['active_company_id' => $firstCompany->id]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex('customers_company_id_index');
            $table->dropColumn('company_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_company_id']);
            $table->dropColumn('active_company_id');
        });
    }
};
