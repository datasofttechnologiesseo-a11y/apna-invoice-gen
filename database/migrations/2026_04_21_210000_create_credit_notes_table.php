<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit notes (Section 34 of CGST Act).
 *
 * Credit notes are the statutory way to adjust a finalized invoice:
 *   - Returned / rejected goods
 *   - Price overcharge correction
 *   - Partial refund
 *
 * A credit note references its parent invoice, has its own sequential number
 * (e.g. CRN-0001, scoped per company + FY), and reduces the effective
 * receivable on the parent invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('credit_note_number', 40);
            $table->date('credit_note_date');
            $table->decimal('amount', 14, 2); // gross value being credited (incl. tax)
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('total_cgst', 14, 2)->default(0);
            $table->decimal('total_sgst', 14, 2)->default(0);
            $table->decimal('total_igst', 14, 2)->default(0);
            $table->string('reason', 60); // sales_return, rate_correction, discount, refund, other
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'credit_note_number']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'credit_note_date']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('credit_note_prefix', 20)->default('CRN')->after('receipt_number_padding');
            $table->unsignedInteger('credit_note_counter')->default(0)->after('credit_note_prefix');
            $table->unsignedTinyInteger('credit_note_number_padding')->default(4)->after('credit_note_counter');
            $table->unsignedSmallInteger('credit_note_counter_fy')->nullable()->after('credit_note_number_padding');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Denormalised cumulative credited amount — kept in sync with
            // SUM(credit_notes.amount) when credit notes are created/deleted.
            $table->decimal('credited_amount', 14, 2)->default(0)->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('credited_amount');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['credit_note_prefix', 'credit_note_counter',
                'credit_note_number_padding', 'credit_note_counter_fy']);
        });

        Schema::dropIfExists('credit_notes');
    }
};
