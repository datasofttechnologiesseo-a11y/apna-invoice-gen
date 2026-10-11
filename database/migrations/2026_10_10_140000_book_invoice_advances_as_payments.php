<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An advance typed into "Paid amount" on a draft used to be carried only in
 * invoices.paid_amount, with no row in payments. The first recorded payment
 * then recomputed paid from the payments table and the advance vanished
 * (₹10,000 bill, ₹4,000 advance, ₹2,000 payment -> "paid 2,000").
 *
 * Issued invoices now book the advance as a receipt at issue time. This gives
 * every already-issued invoice whose paid_amount exceeds its receipts the
 * missing receipt for the difference, dated on the invoice, so the books
 * add up the same way for old and new invoices. Drafts are left alone: they
 * get their receipt when issued. Nothing is changed on any invoice whose
 * receipts already account for its paid amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        $methods = array_keys(config('payment_methods.methods', []));
        $method = in_array('cash', $methods, true) ? 'cash' : ($methods[0] ?? 'cash');

        $rows = DB::table('invoices as i')
            ->leftJoin('payments as p', 'p.invoice_id', '=', 'i.id')
            ->whereNotNull('i.finalized_at')
            ->where('i.paid_amount', '>', 0)
            ->groupBy('i.id', 'i.user_id', 'i.company_id', 'i.invoice_date', 'i.paid_amount')
            ->havingRaw('i.paid_amount - COALESCE(SUM(p.amount), 0) > 0.009')
            ->selectRaw('i.id, i.user_id, i.company_id, i.invoice_date, i.paid_amount - COALESCE(SUM(p.amount), 0) as advance')
            ->orderBy('i.id')
            ->get();

        foreach ($rows as $row) {
            DB::transaction(function () use ($row, $method) {
                $company = DB::table('companies')->where('id', $row->company_id)->lockForUpdate()->first();
                if (! $company) {
                    return;
                }
                $counter = (int) $company->receipt_counter + 1;
                DB::table('companies')->where('id', $company->id)->update(['receipt_counter' => $counter]);

                DB::table('payments')->insert([
                    'user_id' => $row->user_id,
                    'company_id' => $row->company_id,
                    'invoice_id' => $row->id,
                    'receipt_number' => ($company->receipt_prefix ?? 'RCPT') . '-' .
                        str_pad((string) $counter, (int) ($company->receipt_number_padding ?? 4), '0', STR_PAD_LEFT),
                    'received_at' => $row->invoice_date,
                    'amount' => round((float) $row->advance, 2),
                    'tds_amount' => 0,
                    'method' => $method,
                    'notes' => 'Advance recorded on the invoice',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        DB::table('payments')->where('notes', 'Advance recorded on the invoice')->delete();
    }
};
