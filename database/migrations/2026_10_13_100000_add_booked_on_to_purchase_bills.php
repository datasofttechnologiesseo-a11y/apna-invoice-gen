<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A supplier invoice that arrives after its month is locked keeps its own
 * date (the document date) but is booked, and its input tax credit claimed,
 * in the month it is recorded (s.16(4) allows a later return). booked_on is
 * that date; null means the bill is booked on its own date, as every
 * existing bill is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->date('booked_on')->nullable()->after('bill_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropColumn('booked_on');
        });
    }
};
