<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->foreignId('state_id')->nullable()->constrained();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 80)->default('India');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->char('default_currency', 3)->default('INR');
            $table->text('default_terms')->nullable();
            $table->string('invoice_prefix', 10)->default('INV');
            $table->unsignedInteger('invoice_counter')->default(0);
            $table->unsignedSmallInteger('invoice_number_padding')->default(4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
