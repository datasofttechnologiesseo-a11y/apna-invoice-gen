<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceShareController;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Services\InvoiceCalculator;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * JSON API for invoices — a thin parallel to the web InvoiceController that
 * reuses the same InvoiceCalculator, numbering and audit logic so the mobile
 * app produces byte-identical GST documents.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function index(Request $request)
    {
        $company = $request->user()->ensureCompany();

        $invoices = $company->invoices()
            ->with('customer:id,name')
            ->when($request->status, function ($q, $s) {
                if ($s === 'outstanding') {
                    return $q->whereIn('status', ['final', 'partially_paid'])
                             ->where('balance', '>', 0);
                }
                return $q->where('status', $s);
            })
            ->when($request->search, function ($q, $s) {
                $term = trim($s);
                $q->where(function ($w) use ($term) {
                    $w->whereLiteralLike('invoice_number', $term)
                      ->orWhereHas('customer', fn ($c) => $c->whereLiteralLike('name', $term));
                });
            })
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return InvoiceListResource::collection($invoices);
    }

    /**
     * How the "Paid amount" was paid, for the receipt booked at issue. Nothing
     * paid, nothing stored.
     */
    private function paidVia(array $data): array
    {
        $paid = (float) ($data['paid_amount'] ?? 0) > 0;

        return [
            'paid_method' => $paid ? ($data['paid_method'] ?? null) : null,
            'paid_reference' => $paid ? (trim((string) ($data['paid_reference'] ?? '')) ?: null) : null,
        ];
    }

    /**
     * Rows (1-based) with no HSN/SAC code, when the code is required: Rule
     * 46(g) once either party has a GSTIN, the same rule the invoice form
     * applies on save. A draft made from a quotation, whose form does not
     * ask for it, could otherwise be issued without one.
     *
     * @return list<int>
     */
    private function rowsMissingHsn(Invoice $invoice): array
    {
        if (! $invoice->company?->gstin && ! $invoice->billedCustomer()?->gstin) {
            return [];
        }

        return $invoice->items()->orderBy('sort_order')->orderBy('id')->get()->values()
            ->filter(fn ($item) => trim((string) $item->hsn_sac) === '')
            ->keys()->map(fn ($k) => $k + 1)->all();
    }

    /** Same rule as the web form: an advance cannot be more than the bill. */
    private function guardPaidAmount(array $data, array $calc): void
    {
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);
        $grand = (float) $calc['totals']['grand_total'];
        if ($paid > $grand + 0.001) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'paid_amount' => 'Paid amount (₹' . inr($paid) . ') cannot be more than the grand total (₹' . inr($grand) . ').',
            ]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validateInvoice($request, $company);
        // Same as the website: a closed year takes no new documents.
        if ($company->isBooksLockedOn($data['invoice_date'])) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. Date the invoice after that.",
                'errors' => ['invoice_date' => ['Inside the locked period.']],
            ], 422);
        }
        $data = $this->hydrateProductDescriptions($data, $company->id);

        $customer = $company->customers()->findOrFail($data['customer_id']);
        $supplyType = $data['supply_type'] ?? 'regular';
        \App\Support\ZeroRatedSupply::guard($supplyType, $data, $customer);
        [$isInterstate, $noTax] = $this->calculator->taxBasis($company->state_id, $customer->state_id, $supplyType, (bool) ($data['reverse_charge'] ?? false));
        // A composition dealer issues a bill of supply and cannot collect tax
        // on it (s.10(4), s.31(3)(c)): the rate stays on the line, the tax is nil.
        $noTax = $noTax || (bool) $company->composition_dealer;
        $calc = $this->calculator->recalculate(new Invoice(), $data['items'], $isInterstate, $noTax);
        $this->guardPaidAmount($data, $calc);

        $invoice = DB::transaction(function () use ($user, $company, $customer, $data, $calc, $isInterstate, $supplyType) {
            $invoice = $user->invoices()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number' => null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
                ...\App\Support\ZeroRatedSupply::attributes($supplyType, $data),
                'transporter_name' => $data['transporter_name'] ?? null,
                'transporter_id' => $data['transporter_id'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'eway_bill_number' => $data['eway_bill_number'] ?? null,
                'ship_to_name' => $data['ship_to_name'] ?? null,
                'ship_to_address_line1' => $data['ship_to_address_line1'] ?? null,
                'ship_to_address_line2' => $data['ship_to_address_line2'] ?? null,
                'ship_to_city' => $data['ship_to_city'] ?? null,
                'ship_to_state_id' => $data['ship_to_state_id'] ?? null,
                'ship_to_postal_code' => $data['ship_to_postal_code'] ?? null,
                'ship_to_gstin' => $data['ship_to_gstin'] ?? null,
                'currency' => 'INR',
                'exchange_rate' => 1,
                'status' => 'draft',
                'style' => $data['style'] ?? 'classic',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                ...$calc['totals'],
                // Clamp to >= 0 so an advance larger than the total can't store a
                // negative balance (matches the web InvoiceController invariant).
                'balance' => max(0, round($calc['totals']['grand_total'] - (float) ($data['paid_amount'] ?? 0), 2)),
                'paid_amount' => (float) ($data['paid_amount'] ?? 0),
                ...$this->paidVia($data),
            ]);

            $invoice->items()->createMany($calc['items']);

            return $invoice;
        });

        return (new InvoiceResource($invoice->load(['items', 'customer.state', 'payments'])))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['items.product:id,name', 'customer.state', 'company.state', 'payments']);

        return new InvoiceResource($invoice);
    }

    public function update(Request $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (! $invoice->isSoftEditable()) {
            return response()->json(['message' => 'This invoice cannot be edited.'], 422);
        }
        if (! $invoice->isEditable() && $invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return response()->json([
                'message' => "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. This invoice can no longer be changed.",
            ], 422);
        }

        // Finalized invoices: only soft fields may change.
        if (! $invoice->isEditable()) {
            $soft = $request->validate([
                // An issued invoice's due date can move, but never before the invoice date.
                'due_date' => ['nullable', 'date', 'after_or_equal:' . $invoice->invoice_date->toDateString()],
                'notes' => ['nullable', 'string'],
                'terms' => ['nullable', 'string'],
                'transporter_name' => ['nullable', 'string', 'max:120'],
                'transporter_id' => ['nullable', 'string', 'max:40'],
                'vehicle_number' => ['nullable', 'string', 'max:30'],
                'transport_mode' => ['nullable', 'string', 'in:Road,Rail,Air,Ship'],
                'eway_bill_number' => ['nullable', 'string', 'max:30'],
            ]);

            $invoice->update($soft);

            return new InvoiceResource($invoice->fresh(['items', 'customer.state', 'payments']));
        }

        $company = $invoice->company;
        $data = $this->validateInvoice($request, $company);
        $data = $this->hydrateProductDescriptions($data, $company->id);
        $customer = $company->customers()->findOrFail($data['customer_id']);
        // Left out of an update, the draft keeps its supply type.
        $supplyType = $data['supply_type'] ?? ($invoice->supply_type ?: 'regular');
        \App\Support\ZeroRatedSupply::guard($supplyType, $data, $customer);
        [$isInterstate, $noTax] = $this->calculator->taxBasis($company->state_id, $customer->state_id, $supplyType, (bool) ($data['reverse_charge'] ?? false));
        // A composition dealer issues a bill of supply and cannot collect tax
        // on it (s.10(4), s.31(3)(c)): the rate stays on the line, the tax is nil.
        $noTax = $noTax || (bool) $company->composition_dealer;
        $calc = $this->calculator->recalculate($invoice, $data['items'], $isInterstate, $noTax);
        $this->guardPaidAmount($data, $calc);

        DB::transaction(function () use ($invoice, $customer, $data, $calc, $isInterstate, $supplyType) {
            $paid = (float) ($data['paid_amount'] ?? 0);
            $invoice->update([
                'customer_id' => $customer->id,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
                ...\App\Support\ZeroRatedSupply::attributes($supplyType, $data),
                'transporter_name' => $data['transporter_name'] ?? null,
                'transporter_id' => $data['transporter_id'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'eway_bill_number' => $data['eway_bill_number'] ?? null,
                'ship_to_name' => $data['ship_to_name'] ?? null,
                'ship_to_address_line1' => $data['ship_to_address_line1'] ?? null,
                'ship_to_address_line2' => $data['ship_to_address_line2'] ?? null,
                'ship_to_city' => $data['ship_to_city'] ?? null,
                'ship_to_state_id' => $data['ship_to_state_id'] ?? null,
                'ship_to_postal_code' => $data['ship_to_postal_code'] ?? null,
                'ship_to_gstin' => $data['ship_to_gstin'] ?? null,
                'currency' => 'INR',
                'exchange_rate' => 1,
                'style' => $data['style'] ?? $invoice->style ?? 'classic',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                ...$calc['totals'],
                'paid_amount' => $paid,
                ...$this->paidVia($data),
                'balance' => max(0, round($calc['totals']['grand_total'] - $paid, 2)),
            ]);

            $invoice->items()->delete();
            $invoice->items()->createMany($calc['items']);
        });

        return new InvoiceResource($invoice->fresh(['items', 'customer.state', 'payments']));
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        // Same rule as the web: only a draft (or a cancelled one that never got
        // a number) can be deleted. An issued number stays on record, cancelled
        // or not, so the series has no holes (Rule 46(b), GSTR-1 Table 13).
        if (! $invoice->canBeDeleted()) {
            return response()->json([
                'message' => 'An issued invoice cannot be deleted; its number must stay on record. Cancel it instead.',
            ], 422);
        }

        if ($invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return response()->json([
                'message' => "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. This invoice cannot be deleted.",
            ], 422);
        }

        $wasCancelled = $invoice->isCancelled();
        $logMessage = $wasCancelled
            ? "Cancelled invoice {$invoice->invoice_number} deleted (number stays consumed; counter not recycled)"
            : "Draft #{$invoice->id} deleted";

        AuditLog::record('invoice.deleted', $logMessage, $invoice,
            $invoice->only(['invoice_number', 'invoice_date', 'grand_total', 'status', 'cancelled_at', 'cancellation_reason'])
        );

        // A quotation converted into this invoice is open again, as on the web.
        \App\Models\Quotation::where('converted_to_invoice_id', $invoice->id)
            ->update(['status' => 'accepted', 'converted_to_invoice_id' => null, 'converted_at' => null]);
        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted.']);
    }

    public function finalize(Request $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (! $invoice->isDraft()) {
            return response()->json(['message' => 'Only draft invoices can be issued.'], 422);
        }
        if ($invoice->items()->count() === 0) {
            return response()->json(['message' => 'Cannot issue an invoice with no line items.'], 422);
        }
        if ((float) $invoice->grand_total <= 0) {
            return response()->json(['message' => 'Cannot issue a zero-amount invoice.'], 422);
        }
        if ($rows = $this->rowsMissingHsn($invoice)) {
            return response()->json(['message' => 'Enter the HSN/SAC code on row ' . implode(', ', $rows) . ' before issuing. It is required when either party has a GSTIN.'], 422);
        }
        // Same guard as the web path: without the business's own state the
        // calculator cannot tell CGST/SGST from IGST, so a taxed invoice could
        // go out with the wrong heads.
        if (! $invoice->company->state_id && ((float) $invoice->total_tax > 0 || $invoice->company->gstin)) {
            return response()->json([
                'message' => 'Business state is not set, so CGST/SGST or IGST cannot be worked out. Set it in Company settings, then save this draft again before issuing it.',
            ], 422);
        }
        if ($invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return response()->json([
                'message' => "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. Cannot issue an invoice dated on or before that.",
            ], 422);
        }

        // Same rule as the website: an invoice dated after today cannot be issued yet.
        if ($invoice->invoice_date && $invoice->invoice_date->isAfter(now()->endOfDay())) {
            return response()->json(['message' => 'This invoice is dated ' . $invoice->invoice_date->format('d M Y') . '. Issue it on that day, or change its date to today.'], 422);
        }

        if ($invoice->taxBasisIsStale()) {
            return response()->json([
                'message' => "The customer's state or your business state has changed since this draft was saved. Save the draft again to update the GST split, then issue it.",
            ], 422);
        }

        $issued = DB::transaction(function () use ($invoice) {
            $company = $invoice->company()->lockForUpdate()->first();
            // A retried or double-sent request waits here; the invoice may be
            // issued by then and must not be numbered a second time.
            $invoice->refresh();
            if (! $invoice->isDraft()) {
                return false;
            }

            // Same rule as the web path: a number the business already put on
            // the draft is kept, and the series is carried forward from it.
            // This branch was missing here, so an invoice issued through the
            // API silently lost a custom number that the web app would have
            // honoured - the same document, numbered differently depending on
            // which door it went out of.
            if (filled($invoice->invoice_number)) {
                $number = $invoice->invoice_number;
                $company->learnSeriesFrom($number, $invoice->invoice_date);
            } else {
                // Skip past any number a custom-numbered invoice already claimed,
                // or the unique (company_id, invoice_number) index rejects the
                // update.
                do {
                    $number = $company->bumpCounterForFinalize($invoice->invoice_date?->toDateString());
                } while (Invoice::where('company_id', $company->id)
                    ->where('id', '!=', $invoice->id)
                    ->where('invoice_number', $number)
                    ->exists());
            }

            $invoice->update([
                'invoice_number' => $number,
                'place_of_supply_state_id' => $invoice->placeOfSupplyForIssue(),
                'status' => (float) $invoice->paid_amount >= (float) $invoice->grand_total ? 'paid' : ((float) $invoice->paid_amount > 0 ? 'partially_paid' : 'final'),
                'finalized_at' => now(),
            ]);

            // Same as the web path: freeze the recipient as printed, and turn
            // the draft's advance into a receipt so later recomputes keep it.
            $invoice->snapshotCustomer();
            $invoice->bookAdvanceAsPayment();
            // Items that track stock leave the shelf when the invoice is issued.
            app(\App\Services\StockLedger::class)->recordInvoice($invoice);

            return true;
        });

        if (! $issued) {
            return new InvoiceResource($invoice->fresh(['items', 'customer.state', 'payments']));
        }

        AuditLog::record('invoice.finalized',
            "Invoice {$invoice->fresh()->invoice_number} finalized · ₹" . inr($invoice->grand_total),
            $invoice
        );

        return new InvoiceResource($invoice->fresh(['items', 'customer.state', 'payments']));
    }

    public function cancel(Request $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (! $invoice->canBeCancelled()) {
            return response()->json(['message' => 'This invoice cannot be cancelled.'], 422);
        }

        $company = $invoice->company;
        if ($company->isBooksLockedOn($invoice->invoice_date)) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. Issue a credit note instead.",
            ], 422);
        }

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $cancelled = DB::transaction(function () use ($invoice, $data) {
            $invoice->company()->lockForUpdate()->first();
            $invoice->refresh();
            if (! $invoice->canBeCancelled()) {
                return false;
            }
            $invoice->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $data['cancellation_reason'],
            ]);
            app(\App\Services\StockLedger::class)->reverseInvoice($invoice);

            return true;
        });
        if (! $cancelled) {
            return response()->json(['message' => 'This invoice can no longer be cancelled. A payment or credit note was just recorded against it.'], 422);
        }

        AuditLog::record('invoice.cancelled',
            "Invoice {$invoice->invoice_number} cancelled · ₹" . inr($invoice->grand_total) . " · Reason: " . $data['cancellation_reason'],
            $invoice,
            ['reason' => $data['cancellation_reason'], 'grand_total' => $invoice->grand_total]
        );

        return new InvoiceResource($invoice->fresh(['items', 'customer.state', 'payments']));
    }

    /**
     * Full tax-invoice PDF download — renders the GST copy set (3 for goods,
     * 2 for services, per CGST Rule 48), identical to the web download. This is
     * what the mobile "Download PDF" button fetches (the public share link is a
     * single recipient copy instead).
     */
    public function pdf(Request $request, Invoice $invoice): Response|JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->isDraft()) {
            return response()->json(['message' => 'Issue the invoice before downloading the PDF.'], 422);
        }

        $invoice->load(['items.product:id,name', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);
        $style = $invoice->style ?: 'classic';
        $print = ! $request->boolean('color');

        // Caller can pick exactly which copies to print via ?copyTypes=recipient,
        // transporter,supplier. Falls back to the count-based default (3 for
        // goods, 2 for services) when no selection is sent.
        $copyLabels = $request->filled('copyTypes')
            ? $invoice->copyLabelsFor(array_filter(array_map('trim', explode(',', (string) $request->input('copyTypes')))))
            : $invoice->copyLabels(
                $request->has('copies')
                    ? max(1, min(3, (int) $request->input('copies')))
                    : $invoice->defaultCopyCount()
            );

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'amountInWords', 'style', 'print', 'copyLabels'))
            ->setPaper('A4')
            ->setOption(['isRemoteEnabled' => true]);

        return $pdf->download('invoice-' . $invoice->filenameSafeNumber() . '.pdf');
    }

    /** A shareable signed PDF link (30-day TTL), same as the web "Copy link". */
    public function shareLink(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->isDraft()) {
            return response()->json(['message' => 'Issue the invoice before sharing it.'], 422);
        }

        return response()->json([
            'url' => InvoiceShareController::makePublicUrl($invoice),
            'whatsapp_link' => $invoice->whatsAppShareLink('share'),
        ]);
    }

    /**
     * Send a manual payment reminder through email / whatsapp / sms. Mirrors the
     * web InvoiceController::sendReminder — re-checks eligibility inside the
     * ReminderService so a payment that lands mid-request never triggers a
     * "balance due" message after the invoice is settled.
     */
    public function sendReminder(Request $request, Invoice $invoice, \App\Services\Reminders\ReminderService $reminders): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (! $reminders->isEligible($invoice)) {
            return response()->json(['message' => 'This invoice is not eligible for reminders.'], 422);
        }

        $data = $request->validate([
            'channel' => ['required', 'in:email,whatsapp,sms'],
        ]);

        $record = $reminders->send($invoice, $data['channel'], 'manual');

        if ($record->status === 'sent') {
            return response()->json([
                'status' => 'sent',
                'message' => "Reminder sent via {$data['channel']} to {$record->recipient}.",
            ]);
        }
        if ($record->status === 'skipped') {
            return response()->json([
                'status' => 'skipped',
                'message' => $record->error ?: 'No reminder sent. This invoice is already settled.',
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'message' => $record->error ?: 'Failed to send reminder.',
        ], 422);
    }

    // ---- Payments ----------------------------------------------------------

    public function recordPayment(Request $request, Invoice $invoice): PaymentResource|JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->isDraft()) {
            return response()->json(['message' => 'Issue the invoice before recording payments.'], 422);
        }
        if ($invoice->status === 'cancelled') {
            return response()->json(['message' => 'Cancelled invoices cannot accept payments.'], 422);
        }

        // Rounded to paise: an unrounded float tail made the last ₹0.10 unpayable.
        $remaining = round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount - (float) $invoice->credited_amount), 2);
        $methods = array_keys(config('payment_methods.methods'));

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$remaining}"],
            'method' => ['required', 'string', 'in:' . implode(',', $methods)],
            // Money cannot be received for a bill before the bill exists.
            'received_at' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $invoice->invoice_date->toDateString()],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            'tds_amount' => ['nullable', 'numeric', 'min:0', 'lt:amount'],
            'tds_section' => ['nullable', 'string', 'max:16'],
            'tds_rate' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);

        if ($invoice->company->isBooksLockedOn($data['received_at'])) {
            return response()->json([
                'message' => "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. Date the receipt after that.",
                'errors' => ['received_at' => ['Inside the locked period.']],
            ], 422);
        }

        $payment = DB::transaction(function () use ($invoice, $data) {
            $company = $invoice->company()->lockForUpdate()->first();

            // Checked again under the lock: two sends of the same payment must not both go through.
            $invoice->refresh();
            $owed = round((float) $invoice->grand_total - (float) $invoice->payments()->sum('amount') - (float) $invoice->credited_amount, 2);
            if ($invoice->isDraft() || $invoice->status === 'cancelled' || (float) $data['amount'] > $owed + 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Only ₹' . inr(max(0, $owed)) . ' is still owed on this invoice.',
                ]);
            }
            // An app retry of a part payment passes the amount check; its bank
            // reference does not (same rule as the website).
            if ($duplicate = $invoice->duplicatePaymentReference($data['reference_number'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reference_number' => $duplicate]);
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
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $paid = (float) $invoice->payments()->sum('amount');
            $credited = (float) $invoice->credited_amount;
            $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
            $settled = ($paid + $credited) >= (float) $invoice->grand_total;
            $status = $settled ? 'paid' : ($paid > 0 ? 'partially_paid' : 'final');

            $invoice->update(['paid_amount' => $paid, 'balance' => $balance, 'status' => $status]);

            return $payment;
        });

        AuditLog::record('payment.recorded',
            "₹" . inr($payment->amount) . " · " . strtoupper($payment->method) . " · Receipt {$payment->receipt_number} · Invoice {$invoice->invoice_number}",
            $payment
        );

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function deletePayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        $invoice = $payment->invoice;

        $company = $invoice->company;
        if ($company->isBooksLockedOn($payment->received_at)) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. This payment cannot be reversed.",
            ], 422);
        }

        $snapshot = $payment->only([
            'receipt_number', 'amount', 'tds_amount', 'tds_section',
            'method', 'reference_number', 'received_at',
        ]);

        DB::transaction(function () use ($payment, $invoice) {
            // Same company lock as recording a payment or a credit note, then
            // the invoice read again under it, so a payment or note saved
            // meanwhile is not overwritten by figures loaded before the lock.
            $invoice->company()->lockForUpdate()->first();
            $invoice->refresh();

            $payment->delete();

            $paid = (float) $invoice->payments()->sum('amount');
            $credited = (float) $invoice->credited_amount;
            $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
            $settled = ($paid + $credited) >= (float) $invoice->grand_total;
            $status = $settled ? 'paid' : ($paid > 0 ? 'partially_paid' : 'final');

            $invoice->update(['paid_amount' => $paid, 'balance' => $balance, 'status' => $status]);
        });

        AuditLog::record('payment.reversed',
            "Receipt {$snapshot['receipt_number']} reversed · ₹" . inr($snapshot['amount']) . " · Invoice {$invoice->invoice_number}",
            $invoice, $snapshot
        );

        return response()->json(['message' => 'Payment reversed.']);
    }

    // ---- helpers -----------------------------------------------------------

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }

    private function hydrateProductDescriptions(array $data, int $companyId): array
    {
        if (empty($data['items']) || ! is_array($data['items'])) {
            return $data;
        }

        $productIds = collect($data['items'])->pluck('product_id')->filter()->unique()->values();
        if ($productIds->isEmpty()) {
            return $data;
        }

        $productNames = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        foreach ($data['items'] as $i => $item) {
            $pid = $item['product_id'] ?? null;
            if (! $pid || ! isset($productNames[$pid])) {
                continue;
            }
            $desc = trim((string) ($item['description'] ?? ''));
            $looksLikeJunk = $desc === '' || preg_match('/^0+(\.0+)?$/', $desc) === 1;
            if ($looksLikeJunk) {
                $data['items'][$i]['description'] = $productNames[$pid];
            }
        }

        return $data;
    }

    private function validateInvoice(Request $request, \App\Models\Company $company): array
    {
        $companyId = $company->id;
        $customerId = (int) $request->input('customer_id');
        $customer = $customerId ? $company->customers()->find($customerId) : null;
        $hsnRequired = ! empty($company->gstin) || ! empty($customer?->gstin);

        return $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reverse_charge' => ['nullable', 'boolean'],
            ...\App\Support\ZeroRatedSupply::rules(),
            'style' => ['nullable', 'string', 'in:' . implode(',', array_keys(config('invoice_styles')))],
            'transporter_name' => ['nullable', 'string', 'max:120'],
            'transporter_id' => ['nullable', 'string', 'max:40'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'transport_mode' => ['nullable', 'string', 'in:Road,Rail,Air,Ship'],
            'eway_bill_number' => ['nullable', 'string', 'max:30'],
            'ship_to_name' => ['nullable', 'string', 'max:255'],
            'ship_to_address_line1' => ['nullable', 'string', 'max:255'],
            'ship_to_address_line2' => ['nullable', 'string', 'max:255'],
            'ship_to_city' => ['nullable', 'string', 'max:100'],
            'ship_to_state_id' => ['nullable', 'exists:states,id'],
            'ship_to_postal_code' => ['nullable', 'string', 'max:10'],
            'ship_to_gstin' => ['nullable', 'string', new \App\Rules\ValidGstin(
                $request->input('ship_to_state_id') ? (int) $request->input('ship_to_state_id') : null
            )],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_method' => ['nullable', Rule::in(array_keys(config('payment_methods.methods', [])))],
            'paid_reference' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.description' => ['nullable', 'string', 'max:150'],
            'items.*.hsn_sac' => [$hsnRequired ? 'required' : 'nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:' . implode(',', config('gst.allowed_values'))],
        ]);
    }
}
