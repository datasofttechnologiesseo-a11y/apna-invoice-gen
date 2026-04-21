<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Nullable so existing manually-entered items keep working. nullOnDelete
            // so deleting a product preserves historical invoice items (GST audit
            // trail: issued invoices are immutable).
            $table->foreignId('product_id')->nullable()->after('invoice_id')
                ->constrained('products')->nullOnDelete();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
