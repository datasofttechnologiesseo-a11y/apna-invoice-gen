<?php

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;

/**
 * Back-fill: any invoice whose paid_amount > 0 but has zero rows in the
 * payments table pre-dates the payments module. Without a matching payment
 * row, reversing any future payment would recompute paid_amount = 0 and
 * silently wipe the legacy balance.
 *
 * We synthesise one "LEGACY" Payment row per such invoice so the invariant
 * (paid_amount == SUM(payments.amount)) holds going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        $invoices = Invoice::query()
            ->where('paid_amount', '>', 0)
            ->whereDoesntHave('payments')
            ->get();

        foreach ($invoices as $invoice) {
            Payment::create([
                'user_id'          => $invoice->user_id,
                'company_id'       => $invoice->company_id,
                'invoice_id'       => $invoice->id,
                // Prefixed sentinel — distinct from the live RCPT sequence so
                // it's obvious these are imported from pre-module history.
                'receipt_number'   => 'LEGACY-' . $invoice->id,
                'received_at'      => $invoice->finalized_at?->toDateString() ?? $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
                'amount'           => $invoice->paid_amount,
                'method'           => 'other',
                'reference_number' => null,
                'notes'            => 'Imported from pre-payments-module balance.',
            ]);
        }
    }

    public function down(): void
    {
        // Reversible: drop only the sentinel rows we inserted.
        Payment::where('receipt_number', 'like', 'LEGACY-%')
            ->where('notes', 'Imported from pre-payments-module balance.')
            ->delete();
    }
};
