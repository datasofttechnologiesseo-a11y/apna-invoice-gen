<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations (also known as proforma invoices) — a pre-sale price proposal sent
 * to a prospective customer BEFORE any GST-compliant tax invoice is issued.
 *
 * Key differences from `invoices`:
 *   - Not GST-compliant per se (the document explicitly states "this is not a tax invoice")
 *   - Not tied to GSTR-1 / GSTR-3B / FY-reset numbering rules
 *   - Not subject to books-locked-until period close
 *   - Has a `valid_until` expiry instead of `due_date`
 *   - Status flow: draft → sent → accepted → converted (when turned into invoice)
 *                                  ↘ declined / expired
 *   - Once accepted, can be converted to an Invoice (one-way; original quote stays read-only)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // Numbering — separate sequence from invoices. NULL until first send/finalize.
            $table->string('quote_number', 50)->nullable();
            $table->date('quote_date');
            $table->date('valid_until')->nullable();

            // Tax mode (computed from customer state, like invoices)
            $table->boolean('is_interstate')->default(false);

            // Money columns — same shape as invoices for consistent math + PDF rendering
            $table->string('currency', 3)->default('INR');
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total_cgst', 14, 2)->default(0);
            $table->decimal('total_sgst', 14, 2)->default(0);
            $table->decimal('total_igst', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('round_off', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);

            // Status: draft, sent, accepted, declined, expired, converted
            $table->string('status', 20)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason', 500)->nullable();

            // Conversion link — once the quote is accepted and converted to an
            // invoice, this stores the FK so we can show "Converted to INV-0042"
            // on the quote view and not let it be re-converted.
            $table->foreignId('converted_to_invoice_id')->nullable()
                ->constrained('invoices')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            // Free-text fields — same as invoice
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('style', 20)->default('classic');

            $table->timestamps();

            $table->unique(['company_id', 'quote_number']);
            $table->index(['company_id', 'quote_date']);
            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()
                ->constrained('products')->nullOnDelete();

            $table->string('description', 500);
            $table->string('hsn_sac', 10)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20)->nullable();
            $table->decimal('rate', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('gst_rate', 6, 2);
            $table->decimal('amount', 14, 2);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);

            $table->timestamps();

            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
