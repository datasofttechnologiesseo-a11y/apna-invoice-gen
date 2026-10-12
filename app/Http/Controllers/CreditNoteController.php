<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreditNoteController extends Controller
{
    /** Show the create form for a new credit note against a given invoice. */
    public function create(Request $request, Invoice $invoice): View|RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_if($invoice->isDraft(), 422, 'Issue the invoice before creating a credit note.');
        abort_if($invoice->isCancelled(), 422, 'Cancelled invoices cannot be credited.');

        // CGST Section 34(2): credit notes for a given supply must be issued
        // by the 30th of November following the end of the FY in which the
        // supply was made. Past that date, the supplier can't claim back the
        // GST via a CN. Block politely and tell the operator the deadline.
        if ($invoice->isCreditNoteWindowClosed()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'The Section 34(2) deadline for this invoice was '
                    . $invoice->creditNoteDeadline()->format('d M Y')
                    . ', so a credit note can no longer reduce GST. Issue a commercial refund or note outside GST instead.');
        }

        // Max creditable = grand_total - already credited (we don't subtract
        // paid_amount, because a credit note can apply even on a paid invoice
        // to trigger a refund).
        $creditable = round(max(0, (float) $invoice->grand_total - (float) $invoice->credited_amount), 2);
        if ($creditable <= 0) {
            return redirect()->route('invoices.show', $invoice)
                ->with('status', 'This invoice is already fully credited.');
        }

        // Lines the owner can pick for a return: what each still has left to
        // credit after earlier notes, with the same per-unit figures the
        // server uses (CreditNoteLines), so the preview matches the note.
        $invoice->load('items');
        $prior = app(\App\Services\CreditNoteLines::class)->creditedSoFar($invoice);
        $lines = $invoice->items->map(fn ($item) => [
            'id' => $item->id,
            'description' => $item->description,
            'hsn_sac' => $item->hsn_sac,
            'unit' => $item->unit,
            'quantity' => (float) $item->quantity,
            'left' => round(max(0, (float) $item->quantity - ($prior[$item->id]['qty'] ?? 0)), 3),
            'gst_rate' => (float) $item->gst_rate,
            'amount' => (float) $item->amount,
            'cgst' => (float) $item->cgst_amount,
            'sgst' => (float) $item->sgst_amount,
            'igst' => (float) $item->igst_amount,
            'done' => $prior[$item->id] ?? ['qty' => 0, 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0],
        ])->values();

        return view('credit-notes.edit', [
            'invoice' => $invoice,
            'creditable' => $creditable,
            'lines' => CreditNote::linesAvailable() ? $lines : collect(),
            'creditNote' => new CreditNote(['credit_note_date' => now()->toDateString()]),
        ]);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_if($invoice->isDraft(), 422, 'Issue the invoice before creating a credit note.');
        abort_if($invoice->isCancelled(), 422, 'Cancelled invoices cannot be credited.');

        // CGST Section 34(2) — see CreditNoteController::create() for context.
        if ($invoice->isCreditNoteWindowClosed()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'The Section 34(2) deadline for this invoice was '
                    . $invoice->creditNoteDeadline()->format('d M Y') . ', so a credit note can no longer reduce GST.');
        }

        $creditable = round(max(0, (float) $invoice->grand_total - (float) $invoice->credited_amount), 2);
        $reasons = array_keys(config('credit_note_reasons'));

        $hasLines = CreditNote::linesAvailable()
            && collect((array) $request->input('lines', []))->contains(fn ($q) => is_numeric($q) && (float) $q > 0);
        $data = $request->validate([
            'credit_note_date' => ['required', 'date', 'after_or_equal:' . $invoice->invoice_date?->toDateString(), 'before_or_equal:today'],
            // With lines picked the amount is worked out from them (and may
            // differ from the typed box by the invoice round-off).
            'amount' => $hasLines ? ['nullable', 'numeric'] : ['required', 'numeric', 'min:0.01', "max:{$creditable}"],
            'reason' => ['required', 'string', 'in:' . implode(',', $reasons)],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Optional: invoice item id => quantity returned. With lines, the
            // note's amount and tax come from them (CreditNoteLines).
            'lines' => ['nullable', 'array'],
            'lines.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Books-lock: cannot issue a credit note dated inside a closed FY.
        $company = $invoice->company;
        if ($company->isBooksLockedOn($data['credit_note_date'])) {
            return redirect()->back()->withInput()
                ->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. Credit note date must be after that.");
        }

        $creditNote = DB::transaction(function () use ($invoice, $data, $hasLines) {
            // Lock company row + allocate next CRN number atomically.
            $company = $invoice->company()->lockForUpdate()->first();

            // The cap was read before the lock: a double-submitted note must be
            // checked again, or two notes credit more than the invoice is worth.
            $invoice->refresh();
            $stillCreditable = round(max(0, (float) $invoice->grand_total - (float) $invoice->creditNotes()->reorder()->sum('amount')), 2);
            if ($invoice->isCancelled() || (! $hasLines && (float) $data['amount'] > $stillCreditable + 0.001)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'A credit note was just issued. Only ₹' . inr($stillCreditable) . ' of this invoice can still be credited.',
                ]);
            }
            // The counter resets each financial year but the number carries no
            // FY part, so CRN-0001 comes round again every April. Skip past any
            // number already issued, as invoices do, instead of tripping the
            // unique index with a 500.
            do {
                $number = $company->bumpCreditNoteCounter($data['credit_note_date']);
            } while (\App\Models\CreditNote::where('company_id', $company->id)
                ->where('credit_note_number', $number)
                ->exists());

            // Lines picked (a return of 1 of 7): the note is the sum of its
            // lines. Otherwise the amount is spread pro rata across the
            // invoice's tax heads, as it always was, never reversing more tax
            // than the invoice charged.
            $built = app(\App\Services\CreditNoteLines::class)->build($invoice, (array) ($data['lines'] ?? []));
            if ($built) {
                $amount = $built['amount'];
                $split = array_intersect_key($built, array_flip(['taxable_value', 'total_cgst', 'total_sgst', 'total_igst']));
            } else {
                $amount = (float) $data['amount'];
                $split = app(\App\Services\CreditNoteSplit::class)->for($invoice, $amount);
            }

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
                app(\App\Services\StockLedger::class)->recordCreditNote($cn);
            }

            // Recalculate the invoice's credited_amount + balance + status.
            $credited = (float) $invoice->creditNotes()->reorder()->sum('amount');
            $paid = (float) $invoice->paid_amount;
            // No clamp: a credit note on an invoice the customer has already
            // paid leaves a NEGATIVE balance, which is the refund owed to them.
            // Clamping to 0 made that money disappear from the books.
            $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
            $status = $balance <= 0
                ? 'paid'
                : ($paid > 0 ? 'partially_paid' : 'final');

            $invoice->update([
                'credited_amount' => $credited,
                'balance' => $balance,
                'status' => $status,
            ]);

            return $cn;
        });

        \App\Models\AuditLog::record('credit_note.created',
            "Credit note {$creditNote->credit_note_number} issued · ₹" . inr((float) $creditNote->amount) . " · " . $creditNote->reasonLabel() . " · against Invoice {$invoice->invoice_number}",
            $creditNote
        );

        return redirect()->route('invoices.show', $invoice)
            ->with('status', "Credit note {$creditNote->credit_note_number} issued for ₹" . inr((float) $creditNote->amount));
    }

    /** Delete/reverse a credit note. Recomputes invoice totals. */
    public function destroy(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorizeCreditNote($request, $creditNote);

        $invoice = $creditNote->invoice;
        $company = $invoice->company;

        // Books-lock: a credit note dated inside a closed FY cannot be reversed.
        if ($company->isBooksLockedOn($creditNote->credit_note_date)) {
            return redirect()->back()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This credit note cannot be reversed.");
        }

        $snapshot = $creditNote->only(['credit_note_number', 'credit_note_date', 'amount', 'reason']);

        DB::transaction(function () use ($creditNote, $invoice) {
            // Stock a sales return put back comes out again with the note.
            app(\App\Services\StockLedger::class)->reverseCreditNote($creditNote);
            if (CreditNote::linesAvailable()) {
                $creditNote->items()->delete();
            }
            $creditNote->delete();

            $credited = (float) $invoice->creditNotes()->reorder()->sum('amount');
            $paid = (float) $invoice->paid_amount;
            // No clamp: a credit note on an invoice the customer has already
            // paid leaves a NEGATIVE balance, which is the refund owed to them.
            // Clamping to 0 made that money disappear from the books.
            $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
            $status = $balance <= 0
                ? 'paid'
                : ($paid > 0 ? 'partially_paid' : 'final');

            $invoice->update([
                'credited_amount' => $credited,
                'balance' => $balance,
                'status' => $status,
            ]);
        });

        \App\Models\AuditLog::record('credit_note.deleted',
            "Credit note {$snapshot['credit_note_number']} reversed · ₹" . inr((float) $snapshot['amount']) . " · against Invoice {$invoice->invoice_number}",
            $invoice,
            $snapshot
        );

        return redirect()->route('invoices.show', $invoice)
            ->with('status', 'Credit note reversed. Invoice balance restored.');
    }

    /** Download the credit note as a PDF (ink-saver by default, ?color=1 for full colour). */
    public function pdf(Request $request, CreditNote $creditNote): Response
    {
        $this->authorizeCreditNote($request, $creditNote);
        $creditNote->load(array_merge(CreditNote::linesAvailable() ? ['items'] : [], ['invoice.items', 'invoice.customer.state', 'invoice.company.state', 'invoice.placeOfSupply']));

        $amountInWords = NumberToWords::indianRupees((float) $creditNote->amount, 'INR');
        $print = ! $request->boolean('color');

        $pdf = Pdf::loadView('credit-notes.pdf', [
                'creditNote' => $creditNote,
                'amountInWords' => $amountInWords,
                'print' => $print,
            ])
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        $safeNumber = preg_replace('~[\\\\/\\:\\*\\?"<>\\|\\s]+~', '-', $creditNote->credit_note_number);
        return $pdf->download('credit-note-' . $safeNumber . '.pdf');
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }

    private function authorizeCreditNote(Request $request, CreditNote $creditNote): void
    {
        abort_unless($creditNote->user_id === $request->user()->id, 403);
    }
}
