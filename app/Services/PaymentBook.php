<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Receipts against an invoice. The web form and the mobile app both go
 * through here, so a receipt is accepted, numbered, refused and reversed by
 * the same rules whichever door it came in by.
 */
class PaymentBook
{
    /** How long an app's Idempotency-Key keeps answering with the first receipt. */
    private const IDEMPOTENCY_TTL_HOURS = 24;

    /** Validation rules for a new receipt against this invoice. */
    public function rules(Invoice $invoice): array
    {
        // Remaining also subtracts what credit notes took off: a credit note
        // reduces what is owed. Rounded to paise before it becomes the "max"
        // bound, or a float tail (999.9999998) refuses an exact 1000.00.
        $remaining = round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount - (float) $invoice->credited_amount), 2);
        $methods = array_keys(config('payment_methods.methods'));

        // Money cannot be received against an invoice before the invoice
        // exists. (An advance is typed on the draft and booked at issue,
        // dated the invoice date.)
        $receivedAt = ['required', 'date', 'before_or_equal:today'];
        if ($invoice->invoice_date) {
            $receivedAt[] = 'after_or_equal:' . $invoice->invoice_date->toDateString();
        }

        return [
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$remaining}"],
            'method' => ['required', 'string', 'in:' . implode(',', $methods)],
            'received_at' => $receivedAt,
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            // TDS deducted at source by the customer, kept for Form 26AS
            // matching. 30% is the highest TDS rate in India.
            'tds_amount' => ['nullable', 'numeric', 'min:0', 'lt:amount'],
            'tds_section' => ['nullable', 'string', 'max:16'],
            'tds_rate' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ];
    }

    public function messages(Invoice $invoice): array
    {
        return [
            'received_at.after_or_equal' => 'The payment date cannot be before the invoice date ('
                . $invoice->invoice_date?->format('d M Y') . ').',
            'received_at.before_or_equal' => 'The payment date cannot be in the future.',
        ];
    }

    /**
     * A receipt dated inside a closed period would change that period's books.
     * Money received today against an old invoice is fine: the lock is on the
     * receipt date, not the invoice date.
     */
    public function lockProblem(Invoice $invoice, string $receivedAt): ?string
    {
        $company = $invoice->company;

        return $company->isBooksLockedOn($receivedAt)
            ? "Books are locked up to {$company->books_locked_until->format('d M Y')}. Date the receipt after that."
            : null;
    }

    /**
     * Book the receipt, number it and settle the invoice. Everything that a
     * double submit, a second tab or an app retry could get wrong is checked
     * again under the company lock.
     *
     * With an Idempotency-Key (sent by the app), a retry of a request that
     * already landed gets the first receipt back instead of a second one;
     * check $payment->wasRecentlyCreated to tell the two apart.
     *
     * @throws ValidationException
     */
    public function record(Invoice $invoice, array $data, ?string $idempotencyKey = null): Payment
    {
        $cacheKey = filled($idempotencyKey)
            ? 'payments:idempotency:' . $invoice->id . ':' . hash('sha256', (string) $idempotencyKey)
            : null;

        $payment = DB::transaction(function () use ($invoice, $data, $cacheKey) {
            // Lock the company row: it serialises every receipt, credit note
            // and cancellation on this business, and holds the receipt counter.
            $company = $invoice->company()->lockForUpdate()->first();

            if ($cacheKey && ($firstId = Cache::get($cacheKey))) {
                $first = Payment::where('invoice_id', $invoice->id)->find($firstId);
                if ($first) {
                    return $first;
                }
            }

            // The checks made before the lock may be stale.
            $invoice->refresh();

            // One bank transfer is one receipt. A part payment sent twice
            // passes the balance cap (500 + 500 <= 1,180); its bank reference
            // (UTR, cheque number) is what identifies it.
            $reference = trim((string) ($data['reference_number'] ?? ''));
            if ($reference !== '') {
                $same = Payment::where('invoice_id', $invoice->id)->where('reference_number', $reference)->first();
                if ($same) {
                    throw ValidationException::withMessages([
                        'reference_number' => "The payment with reference {$reference} is already recorded on this invoice (receipt {$same->receipt_number}).",
                    ]);
                }
            }

            $owed = round((float) $invoice->grand_total - (float) $invoice->payments()->sum('amount') - (float) $invoice->credited_amount, 2);
            if ($invoice->isDraft() || $invoice->isCancelled() || (float) $data['amount'] > $owed + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => $owed > 0
                        ? 'A payment was just recorded. Only ₹' . inr(max(0, $owed)) . ' is still owed on this invoice.'
                        : 'A payment was just recorded. Nothing more is owed on this invoice.',
                ]);
            }

            $company->increment('receipt_counter');
            $receiptNumber = $company->receipt_prefix . '-' .
                str_pad((string) $company->receipt_counter, $company->receipt_number_padding, '0', STR_PAD_LEFT);

            $payment = Payment::create([
                'user_id' => $invoice->user_id,
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'receipt_number' => $receiptNumber,
                'received_at' => $data['received_at'],
                'amount' => $data['amount'],
                'tds_amount' => (float) ($data['tds_amount'] ?? 0),
                'tds_section' => ! empty($data['tds_amount']) ? ($data['tds_section'] ?? null) : null,
                'tds_rate' => ! empty($data['tds_amount']) ? ($data['tds_rate'] ?? null) : null,
                'method' => $data['method'],
                'reference_number' => $reference !== '' ? $reference : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recompute($invoice);

            if ($cacheKey) {
                Cache::put($cacheKey, $payment->id, now()->addHours(self::IDEMPOTENCY_TTL_HOURS));
            }

            return $payment;
        });

        if ($payment->wasRecentlyCreated) {
            AuditLog::record('payment.recorded',
                '₹' . inr($payment->amount) . ' · ' . strtoupper($payment->method) . " · Receipt {$payment->receipt_number} · Invoice {$invoice->invoice_number}",
                $payment
            );
        }

        return $payment;
    }

    /**
     * Why this receipt cannot be reversed, or null. Only the receipt's own
     * date matters, as when it was recorded: a receipt booked in an open
     * month against an invoice from a closed one changes the open month only.
     */
    public function reversalProblem(Payment $payment): ?string
    {
        $company = $payment->invoice->company;

        return $company->isBooksLockedOn($payment->received_at)
            ? "Books are locked up to {$company->books_locked_until->format('d M Y')}. This payment cannot be reversed."
            : null;
    }

    public function reverse(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $snapshot = $payment->only([
            'receipt_number', 'amount', 'tds_amount', 'tds_section',
            'method', 'reference_number', 'received_at',
        ]);

        DB::transaction(function () use ($payment, $invoice) {
            // Same company lock as recording a payment, and the invoice read
            // again under it: a concurrent payment must not be lost.
            $invoice->company()->lockForUpdate()->first();
            $invoice->refresh();
            $payment->delete();
            $this->recompute($invoice);
        });

        AuditLog::record('payment.reversed',
            "Receipt {$snapshot['receipt_number']} reversed · ₹" . inr($snapshot['amount']) . " · Invoice {$invoice->invoice_number}",
            $invoice,
            $snapshot
        );
    }

    /**
     * Paid, balance and status worked out again from the receipts and the
     * credited amount, never nudged. Paid plus credited covering the total
     * means the invoice is settled.
     */
    public function recompute(Invoice $invoice): void
    {
        $paid = (float) $invoice->payments()->sum('amount');
        $credited = (float) $invoice->credited_amount;
        $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
        $settled = ($paid + $credited) >= (float) $invoice->grand_total;
        $status = $settled ? 'paid' : ($paid > 0 ? 'partially_paid' : 'final');

        $invoice->update([
            'paid_amount' => $paid,
            'balance' => $balance,
            'status' => $status,
        ]);
    }
}
