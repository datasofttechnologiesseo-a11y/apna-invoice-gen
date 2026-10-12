<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Credit notes against an invoice (CGST s.34). The web form and the mobile
 * app both go through here, so a note is built, numbered, refused and
 * reversed the same way whichever door it came in by: an amount spread pro
 * rata over the invoice, or the invoice lines that came back, with the
 * goods of a sales return going back into stock.
 */
class CreditNoteBook
{
    /** Whether the request names invoice lines (item id => quantity returned). */
    public function hasLines(mixed $lines): bool
    {
        return CreditNote::linesAvailable()
            && collect((array) $lines)->contains(fn ($q) => is_numeric($q) && (float) $q > 0);
    }

    /** What is still left to credit on this invoice. */
    public function creditable(Invoice $invoice): float
    {
        return round(max(0, (float) $invoice->grand_total - (float) $invoice->credited_amount), 2);
    }

    public function rules(Invoice $invoice, bool $hasLines): array
    {
        $creditable = $this->creditable($invoice);
        $reasons = array_keys(config('credit_note_reasons'));

        return [
            // A note cannot reduce an invoice before it existed (s.34), nor be
            // dated after the day it is issued.
            'credit_note_date' => ['required', 'date', 'after_or_equal:' . $invoice->invoice_date?->toDateString(), 'before_or_equal:today'],
            // With lines picked the amount is worked out from them (and may
            // differ from the typed box by the invoice round-off).
            'amount' => $hasLines ? ['nullable', 'numeric'] : ['required', 'numeric', 'min:0.01', "max:{$creditable}"],
            'reason' => ['required', 'string', 'in:' . implode(',', $reasons)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['nullable', 'array'],
            'lines.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function lockProblem(Invoice $invoice, string $date): ?string
    {
        $company = $invoice->company;

        return $company->isBooksLockedOn($date)
            ? "Books are locked up to {$company->books_locked_until->format('d M Y')}. Credit note date must be after that."
            : null;
    }

    /**
     * Number and book the note, update the invoice, and put back the goods
     * of a sales return. The cap is checked again under the company lock: a
     * double-submitted or retried note must not credit more than the invoice.
     *
     * @throws ValidationException
     */
    public function issue(Invoice $invoice, array $data, bool $hasLines): CreditNote
    {
        $creditNote = DB::transaction(function () use ($invoice, $data, $hasLines) {
            $company = $invoice->company()->lockForUpdate()->first();

            $invoice->refresh();
            $stillCreditable = round(max(0, (float) $invoice->grand_total - (float) $invoice->creditNotes()->reorder()->sum('amount')), 2);
            if ($invoice->isCancelled() || (! $hasLines && (float) $data['amount'] > $stillCreditable + 0.001)) {
                throw ValidationException::withMessages([
                    'amount' => 'A credit note was just issued. Only ₹' . inr($stillCreditable) . ' of this invoice can still be credited.',
                ]);
            }

            // Lines picked (a return of 1 of 7): the note is the sum of its
            // lines. Otherwise the amount is spread pro rata across the
            // invoice's tax heads, never reversing more tax than was charged.
            // Built before a number is taken, so a refused line burns none.
            $built = app(CreditNoteLines::class)->build($invoice, (array) ($data['lines'] ?? []));
            if ($built) {
                $amount = $built['amount'];
                $split = array_intersect_key($built, array_flip(['taxable_value', 'total_cgst', 'total_sgst', 'total_igst']));
            } else {
                $amount = (float) $data['amount'];
                $split = app(CreditNoteSplit::class)->for($invoice, $amount);
            }

            // The counter resets each financial year but the number carries no
            // FY part, so CRN-0001 comes round again every April. Skip past any
            // number already issued instead of tripping the unique index.
            do {
                $number = $company->bumpCreditNoteCounter($data['credit_note_date']);
            } while (CreditNote::where('company_id', $company->id)
                ->where('credit_note_number', $number)
                ->exists());

            $cn = CreditNote::create([
                'user_id' => $invoice->user_id,
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'credit_note_number' => $number,
                'credit_note_date' => $data['credit_note_date'],
                'amount' => $amount,
                ...$split,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);
            if ($built) {
                $cn->items()->createMany($built['lines']);
                // A sales return puts the goods back in stock.
                app(StockLedger::class)->recordCreditNote($cn);
            }

            $this->recompute($invoice);

            return $cn;
        });

        AuditLog::record('credit_note.created',
            "Credit note {$creditNote->credit_note_number} issued · ₹" . inr((float) $creditNote->amount) . ' · ' . $creditNote->reasonLabel() . " · against Invoice {$invoice->invoice_number}",
            $creditNote
        );

        return $creditNote;
    }

    public function reversalProblem(CreditNote $creditNote): ?string
    {
        $company = $creditNote->invoice->company;

        return $company->isBooksLockedOn($creditNote->credit_note_date)
            ? "Books are locked up to {$company->books_locked_until->format('d M Y')}. This credit note cannot be reversed."
            : null;
    }

    /** Reverse the note: its lines go with it and a sales return's goods leave stock again. */
    public function reverse(CreditNote $creditNote): void
    {
        $invoice = $creditNote->invoice;
        $snapshot = $creditNote->only(['credit_note_number', 'credit_note_date', 'amount', 'reason']);

        DB::transaction(function () use ($creditNote, $invoice) {
            app(StockLedger::class)->reverseCreditNote($creditNote);
            if (CreditNote::linesAvailable()) {
                $creditNote->items()->delete();
            }
            $creditNote->delete();
            $this->recompute($invoice);
        });

        AuditLog::record('credit_note.deleted',
            "Credit note {$snapshot['credit_note_number']} reversed · ₹" . inr((float) $snapshot['amount']) . " · against Invoice {$invoice->invoice_number}",
            $invoice,
            $snapshot
        );
    }

    /**
     * Credited, balance and status worked out again from the notes. No clamp:
     * a credit note on an invoice the customer has already paid leaves a
     * negative balance, which is the refund owed to them.
     */
    public function recompute(Invoice $invoice): void
    {
        $credited = (float) $invoice->creditNotes()->reorder()->sum('amount');
        $paid = (float) $invoice->paid_amount;
        $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
        $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'final');

        $invoice->update([
            'credited_amount' => $credited,
            'balance' => $balance,
            'status' => $status,
        ]);
    }
}
