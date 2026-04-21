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
        abort_if($invoice->isDraft(), 422, 'Finalize the invoice before issuing a credit note.');
        abort_if($invoice->isCancelled(), 422, 'Cancelled invoices cannot be credited.');

        // Max creditable = grand_total - already credited (we don't subtract
        // paid_amount, because a credit note can apply even on a paid invoice
        // to trigger a refund).
        $creditable = max(0, (float) $invoice->grand_total - (float) $invoice->credited_amount);
        if ($creditable <= 0) {
            return redirect()->route('invoices.show', $invoice)
                ->with('status', 'This invoice is already fully credited.');
        }

        return view('credit-notes.edit', [
            'invoice' => $invoice,
            'creditable' => $creditable,
            'creditNote' => new CreditNote(['credit_note_date' => now()->toDateString()]),
        ]);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_if($invoice->isDraft(), 422, 'Finalize the invoice before issuing a credit note.');
        abort_if($invoice->isCancelled(), 422, 'Cancelled invoices cannot be credited.');

        $creditable = max(0, (float) $invoice->grand_total - (float) $invoice->credited_amount);
        $reasons = array_keys(config('credit_note_reasons'));

        $data = $request->validate([
            'credit_note_date' => ['required', 'date', 'after_or_equal:' . $invoice->invoice_date?->toDateString(), 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$creditable}"],
            'reason' => ['required', 'string', 'in:' . implode(',', $reasons)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $creditNote = DB::transaction(function () use ($invoice, $data) {
            // Lock company row + allocate next CRN number atomically.
            $company = $invoice->company()->lockForUpdate()->first();
            $number = $company->bumpCreditNoteCounter($data['credit_note_date']);

            // Pro-rata the amount across the invoice's tax components so the
            // credit note mirrors the tax structure of the original bill.
            // This is the minimum needed for GSTR-1 filing: taxable value +
            // CGST/SGST/IGST split.
            $ratio = $invoice->grand_total > 0
                ? min(1.0, (float) $data['amount'] / (float) $invoice->grand_total)
                : 0;

            $cn = CreditNote::create([
                'user_id' => $invoice->user_id,
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'credit_note_number' => $number,
                'credit_note_date' => $data['credit_note_date'],
                'amount' => $data['amount'],
                'taxable_value' => round((float) $invoice->subtotal * $ratio, 2),
                'total_cgst' => round((float) $invoice->total_cgst * $ratio, 2),
                'total_sgst' => round((float) $invoice->total_sgst * $ratio, 2),
                'total_igst' => round((float) $invoice->total_igst * $ratio, 2),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);

            // Recalculate the invoice's credited_amount + balance + status.
            $credited = (float) $invoice->creditNotes()->sum('amount');
            $paid = (float) $invoice->paid_amount;
            $balance = max(0, round((float) $invoice->grand_total - $paid - $credited, 2));
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

        return redirect()->route('invoices.show', $invoice)
            ->with('status', "Credit note {$creditNote->credit_note_number} issued for ₹" . number_format((float) $creditNote->amount, 2));
    }

    /** Delete/reverse a credit note. Recomputes invoice totals. */
    public function destroy(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorizeCreditNote($request, $creditNote);

        $invoice = $creditNote->invoice;

        DB::transaction(function () use ($creditNote, $invoice) {
            $creditNote->delete();

            $credited = (float) $invoice->creditNotes()->sum('amount');
            $paid = (float) $invoice->paid_amount;
            $balance = max(0, round((float) $invoice->grand_total - $paid - $credited, 2));
            $status = $balance <= 0
                ? 'paid'
                : ($paid > 0 ? 'partially_paid' : 'final');

            $invoice->update([
                'credited_amount' => $credited,
                'balance' => $balance,
                'status' => $status,
            ]);
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('status', 'Credit note reversed. Invoice balance restored.');
    }

    /** Download the credit note as a PDF (ink-saver by default, ?color=1 for full colour). */
    public function pdf(Request $request, CreditNote $creditNote): Response
    {
        $this->authorizeCreditNote($request, $creditNote);
        $creditNote->load(['invoice.customer.state', 'invoice.company.state', 'invoice.placeOfSupply']);

        $amountInWords = NumberToWords::indianRupees((float) $creditNote->amount, 'INR');
        $print = ! $request->boolean('color');

        $pdf = Pdf::loadView('credit-notes.pdf', [
                'creditNote' => $creditNote,
                'amountInWords' => $amountInWords,
                'print' => $print,
            ])
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        return $pdf->download('credit-note-' . $creditNote->credit_note_number . '.pdf');
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
