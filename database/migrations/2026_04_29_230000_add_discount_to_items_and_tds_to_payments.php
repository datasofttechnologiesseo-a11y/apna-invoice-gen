<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two unrelated but related-by-need additions:
 *
 * 1. Per-line-item discount on invoices — per Section 15(3) of CGST Act,
 *    pre-tax discount reduces the *taxable value* of the supply. Without a
 *    dedicated field, users had to bake discounts into rate or use negative
 *    line items, neither of which produces a clean Tax Invoice.
 *
 * 2. TDS fields on payments — Indian B2B service providers commonly receive
 *    payments where the customer (especially corporate) deducts TDS at
 *    source under Section 51 (govt-related) or Section 194-x of the Income
 *    Tax Act before paying. We record the gross applied to the invoice plus
 *    the TDS portion separately so the supplier can reconcile against
 *    Form 26AS / Form 16A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Pre-tax discount in absolute rupees per Section 15(3).
            // Discount is subtracted from (qty × rate) BEFORE GST is computed.
            $table->decimal('discount', 14, 2)->default(0)->after('rate');
        });

        Schema::table('payments', function (Blueprint $table) {
            // TDS deducted by the *customer* at source from this payment.
            // amount stays as the gross figure applied to the invoice;
            // the customer's bank transfer = amount - tds_amount.
            $table->decimal('tds_amount', 14, 2)->default(0)->after('amount');
            // Section / sub-section: 194C, 194J, 194I, 194Q, 51 (GST TDS), etc.
            $table->string('tds_section', 12)->nullable()->after('tds_amount');
            // Rate at which TDS was deducted (1, 2, 10, etc.). Derived but
            // stored for audit clarity / Form 16A reconciliation.
            $table->decimal('tds_rate', 5, 2)->nullable()->after('tds_section');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['tds_amount', 'tds_section', 'tds_rate']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
