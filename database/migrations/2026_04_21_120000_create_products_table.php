<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');                       // Product / service name
            $table->string('sku', 60)->nullable();        // Optional internal SKU
            $table->enum('kind', ['goods', 'service'])->default('goods');
            $table->string('hsn_sac', 10);                // 4/6/8 digits for goods, SAC (99xxxx) for services
            $table->string('unit', 10)->default('NOS');   // UQC code
            $table->decimal('rate', 14, 2)->default(0);   // Default unit price (pre-tax)
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'name']);
            // SKU must be unique within a company (allowed to be null)
            $table->unique(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
