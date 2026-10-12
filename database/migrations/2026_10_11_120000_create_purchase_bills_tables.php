<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase bills: the tax invoices your suppliers give YOU. Until now the
 * purchase side was a one-line expense (amount + GST) or a self-made cash
 * memo. A supplier bill needs the vendor's GSTIN and state (CGST/SGST vs
 * IGST on input credit), its own number and date (what GSTR-2B will show),
 * line items with HSN and rate, whether the credit is blocked (s.17(5)) or
 * the tax is payable by you under reverse charge (s.9(3)/(4)), and what you
 * still owe the vendor.
 *
 * Every bill still mirrors into the expense ledger (expenses.purchase_bill_id)
 * so the P&L, the expense exports and existing reports keep working without
 * change - the same pattern cash memos use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('gstin', 15)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('state_id')->nullable()->constrained();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 80)->default('India');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        Schema::create('purchase_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();

            // The vendor's own invoice number and date - what GSTR-2B shows.
            $table->string('bill_number', 40);
            $table->date('bill_date');
            $table->date('due_date')->nullable();

            $table->boolean('is_interstate')->default(false);
            // s.9(3)/(4): tax payable by the recipient; the vendor's bill carries
            // no tax, the heads below are what YOU pay and (if eligible) claim.
            $table->boolean('reverse_charge')->default(false);
            // false = blocked credit under s.17(5); the GST is then a cost.
            $table->boolean('itc_eligible')->default(true);

            $table->decimal('subtotal', 14, 2)->default(0);      // taxable value after line discounts
            $table->decimal('total_cgst', 14, 2)->default(0);
            $table->decimal('total_sgst', 14, 2)->default(0);
            $table->decimal('total_igst', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('round_off', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);   // what the vendor is owed
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status', 16)->default('unpaid');     // unpaid | partial | paid

            $table->string('expense_category', 60)->nullable();
            $table->string('reference_number', 80)->nullable();
            $table->text('notes')->nullable();

            // Mirrored expense row (P&L); kept in sync on every save.
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            $table->timestamps();

            $table->unique(['company_id', 'vendor_id', 'bill_number']);
            $table->index(['company_id', 'bill_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description', 255);
            $table->string('hsn_sac', 10)->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);        // taxable value of the line
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_bill_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->default('bank');
            $table->string('reference_number', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'paid_on']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('purchase_bill_id')->nullable()->after('cash_memo_id')
                ->constrained('purchase_bills')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['purchase_bill_id']);
            $table->dropColumn('purchase_bill_id');
        });
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_bill_items');
        Schema::dropIfExists('purchase_bills');
        Schema::dropIfExists('vendors');
    }
};
