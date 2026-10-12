<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock tracking, opt-in per item. Additive only: every existing product has
 * track_stock = false and nothing about invoicing changes until a business
 * switches it on for an item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_stock')->default(false)->after('is_active');
            $table->decimal('opening_stock', 14, 3)->default(0)->after('track_stock');
            $table->decimal('low_stock_level', 14, 3)->nullable()->after('opening_stock');
            $table->decimal('purchase_rate', 14, 2)->nullable()->after('rate');
        });

        Schema::table('purchase_bill_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('purchase_bill_id')->constrained()->nullOnDelete();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('moved_on');
            // Signed: + into stock (purchase, return, correction up), - out (sale, damage).
            $table->decimal('quantity', 14, 3);
            // sale | sale_cancelled | purchase | adjustment
            $table->string('kind', 20);
            $table->nullableMorphs('source');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'moved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::table('purchase_bill_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['track_stock', 'opening_stock', 'low_stock_level', 'purchase_rate']);
        });
    }
};
