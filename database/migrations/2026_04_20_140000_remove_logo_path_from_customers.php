<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'logo_path')) {
            // Best-effort cleanup of any uploaded customer-logo files before the
            // column vanishes. Missing files are ignored.
            $paths = DB::table('customers')
                ->whereNotNull('logo_path')
                ->pluck('logo_path')
                ->all();

            foreach ($paths as $path) {
                Storage::disk('public')->delete($path);
            }

            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('email');
        });
    }
};
