<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank reconciliation by statement upload. A statement is a file a business
 * uploads from its bank; each line can be matched to a receipt, a vendor
 * payment or an expense, or ignored (bank charges, own transfers). The same
 * line uploaded twice (overlapping statements) is stored once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_name', 80);
            $table->string('file_name', 160)->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('lines_count')->default(0);
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
            $table->date('txn_date');
            $table->string('description', 255)->nullable();
            $table->string('reference', 80)->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('balance', 16, 2)->nullable();
            $table->string('status', 10)->default('unmatched');   // unmatched | matched | ignored
            $table->nullableMorphs('matched');                     // a Payment, PurchasePayment or Expense
            $table->timestamp('matched_at')->nullable();
            $table->string('line_hash', 40);
            $table->timestamps();

            $table->unique(['company_id', 'line_hash']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'txn_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
    }
};
