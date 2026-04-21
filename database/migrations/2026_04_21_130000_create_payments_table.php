<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Assigned at creation (company-scoped sequential). Unique per company.
            $table->string('receipt_number', 40);

            $table->date('received_at');
            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->default('cash');
            $table->string('reference_number', 80)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'receipt_number']);
            $table->index(['company_id', 'received_at']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
