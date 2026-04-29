<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash Memos.
 *
 * A cash memo is a self-prepared purchase voucher used by Indian SMEs to
 * record cash purchases — particularly from unregistered or sub-threshold
 * vendors who don't issue a formal tax invoice. It serves as the buyer's
 * accounting evidence and pairs with an Expense row in the P&L.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('memo_number', 40);
            $table->date('memo_date');

            // Seller (the person we bought from)
            $table->string('seller_name', 160);
            $table->string('seller_address', 500)->nullable();
            $table->string('seller_gstin', 20)->nullable();
            $table->string('seller_phone', 30)->nullable();
            $table->string('seller_state', 80)->nullable();

            // Money
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('total_cgst', 14, 2)->default(0);
            $table->decimal('total_sgst', 14, 2)->default(0);
            $table->decimal('total_igst', 14, 2)->default(0);
            $table->decimal('round_off', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('amount_in_words', 500)->nullable();

            // Payment + meta
            $table->string('payment_mode', 20)->default('cash'); // cash, upi, card, bank, cheque, other
            $table->string('reference_number', 60)->nullable();
            $table->string('expense_category', 60)->nullable(); // copied to linked Expense
            $table->text('notes')->nullable();

            // Linked Expense (auto-created so it flows into P&L)
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            $table->timestamps();

            $table->unique(['company_id', 'memo_number']);
            $table->index(['company_id', 'memo_date']);
        });

        Schema::create('cash_memo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_memo_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description', 255);
            $table->string('hsn_sac', 10)->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();

            $table->index('cash_memo_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('cash_memo_prefix', 20)->default('CM')->after('credit_note_counter_fy');
            $table->unsignedInteger('cash_memo_counter')->default(0)->after('cash_memo_prefix');
            $table->unsignedTinyInteger('cash_memo_number_padding')->default(4)->after('cash_memo_counter');
            $table->unsignedSmallInteger('cash_memo_counter_fy')->nullable()->after('cash_memo_number_padding');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('cash_memo_id')->nullable()->after('user_id')
                ->constrained('cash_memos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['cash_memo_id']);
            $table->dropColumn('cash_memo_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['cash_memo_prefix', 'cash_memo_counter',
                'cash_memo_number_padding', 'cash_memo_counter_fy']);
        });

        Schema::dropIfExists('cash_memo_items');
        Schema::dropIfExists('cash_memos');
    }
};
