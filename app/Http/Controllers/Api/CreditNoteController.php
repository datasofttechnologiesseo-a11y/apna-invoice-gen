<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditNoteResource;
use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * JSON API for credit notes (CGST Section 34). Always tied to a parent invoice.
 * Mirrors the web CreditNoteController — same numbering, pro-rata tax split,
 * Section 34(2) window guard, books-lock guard and audit logging.
 */
class CreditNoteController extends Controller
{
    /**
     * List a single invoice's credit notes plus the metadata the mobile create
     * form needs (how much is still creditable, whether the GST window is shut,
     * and the picklist of valid reasons).
     */
    public function index(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $notes = $invoice->creditNotes()->orderByDesc('credit_note_date')->orderByDesc('id')->get();
        $creditable = max(0, (float) $invoice->grand_total - (float) $invoice->credited_amount);

        return response()->json([
            'data' => CreditNoteResource::collection($notes),
            'meta' => [
                'creditable' => round($creditable, 2),
                'window_closed' => $invoice->isCreditNoteWindowClosed(),
                'deadline' => optional($invoice->creditNoteDeadline())->toDateString(),
                'can_create' => ! $invoice->isDraft()
                    && ! $invoice->isCancelled()
                    && ! $invoice->isCreditNoteWindowClosed()
                    && $creditable > 0,
                'reasons' => collect(config('credit_note_reasons'))
                    ->map(fn ($r, $key) => ['value' => $key, 'label' => $r['label'], 'hint' => $r['hint'] ?? null])
                    ->values(),
            ],
        ]);
    }

    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->isDraft()) {
            return response()->json(['message' => 'Issue the invoice before creating a credit note.'], 422);
        }
        if ($invoice->isCancelled()) {
            return response()->json(['message' => 'Cancelled invoices cannot be credited.'], 422);
        }
        if ($invoice->isCreditNoteWindowClosed()) {
            return response()->json([
                'message' => 'The Section 34(2) window for this invoice closed on '
                    . $invoice->creditNoteDeadline()->format('d M Y')
                    . '. Credit notes issued after this date cannot reduce GST liability.',
            ], 422);
        }

        $creditable = max(0, (float) $invoice->grand_total - (float) $invoice->credited_amount);
        $reasons = array_keys(config('credit_note_reasons'));

        $data = $request->validate([
            'credit_note_date' => ['required', 'date', 'after_or_equal:' . $invoice->invoice_date?->toDateString(), 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$creditable}"],
            'reason' => ['required', 'string', 'in:' . implode(',', $reasons)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $company = $invoice->company;
        if ($company->isBooksLockedOn($data['credit_note_date'])) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. Credit note date must be after that.",
            ], 422);
        }

        $creditNote = DB::transaction(function () use ($invoice, $data) {
            $company = $invoice->company()->lockForUpdate()->first();
            $number = $company->bumpCreditNoteCounter($data['credit_note_date']);

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

            $this->recomputeInvoice($invoice);

            return $cn;
        });

        AuditLog::record('credit_note.created',
            "Credit note {$creditNote->credit_note_number} issued · ₹" . number_format((float) $creditNote->amount, 2) . " · " . ($creditNote->reason ?? '') . " · against Invoice {$invoice->invoice_number}",
            $creditNote
        );

        return (new CreditNoteResource($creditNote))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, CreditNote $creditNote): JsonResponse
    {
        $this->authorizeCreditNote($request, $creditNote);

        $invoice = $creditNote->invoice;
        $company = $invoice->company;

        if ($company->isBooksLockedOn($creditNote->credit_note_date)) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. This credit note cannot be reversed.",
            ], 422);
        }

        $snapshot = $creditNote->only(['credit_note_number', 'credit_note_date', 'amount', 'reason']);

        DB::transaction(function () use ($creditNote, $invoice) {
            $creditNote->delete();
            $this->recomputeInvoice($invoice);
        });

        AuditLog::record('credit_note.deleted',
            "Credit note {$snapshot['credit_note_number']} reversed · ₹" . number_format((float) $snapshot['amount'], 2) . " · against Invoice {$invoice->invoice_number}",
            $invoice,
            $snapshot
        );

        return response()->json(['message' => 'Credit note reversed. Invoice balance restored.']);
    }

    /** Stream the credit note PDF (ink-saver by default, ?color=1 for full colour). */
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

        $safeNumber = preg_replace('~[\\\\/\\:\\*\\?"<>\\|\\s]+~', '-', $creditNote->credit_note_number);

        return $pdf->download('credit-note-' . $safeNumber . '.pdf');
    }

    /** Recompute the parent invoice's credited_amount + balance + status. */
    private function recomputeInvoice(Invoice $invoice): void
    {
        $credited = (float) $invoice->creditNotes()->sum('amount');
        $paid = (float) $invoice->paid_amount;
        $balance = max(0, round((float) $invoice->grand_total - $paid - $credited, 2));
        $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'final');

        $invoice->update([
            'credited_amount' => $credited,
            'balance' => $balance,
            'status' => $status,
        ]);
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
