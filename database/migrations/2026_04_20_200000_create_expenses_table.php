<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->date('entry_date');
            $table->string('category', 60);
            $table->string('vendor_name', 120)->nullable();
            $table->string('description');

            $table->decimal('amount', 14, 2);            // pre-GST amount (the actual expense)
            $table->decimal('gst_amount', 14, 2)->default(0); // ITC-claimable GST, if supplier issued tax invoice

            $table->string('payment_method', 20)->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
