<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Books period lock.
 *
 * After this date, no invoices, expenses, cash memos, or payments dated on or
 * before `books_locked_until` may be created, edited, or deleted. This
 * prevents retrospective tampering with a closed financial year — a
 * fundamental requirement under Section 44AA of the Income Tax Act and
 * Indian audit conventions for any entity carrying on business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->date('books_locked_until')->nullable()->after('declaration');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('books_locked_until');
        });
    }
};
