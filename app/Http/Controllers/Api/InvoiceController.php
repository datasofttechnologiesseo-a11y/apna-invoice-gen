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
                    $w->where('invoice_number', 'like', "%{$term}%")
                      ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return InvoiceListResource::collection($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validateInvoice($request, $company);
        $data = $this->hydrateProductDescriptions($data, $company->id);

        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $reverseCharge = (bool) ($data['reverse_charge'] ?? false);
        $calc = $this->calculator->recalculate(new Invoice(), $data['items'], $isInterstate, $reverseCharge);

        $invoice = DB::transaction(function () use ($user, $company, $customer, $data, $calc, $isInterstate) {
            $invoice = $user->invoices()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number' => null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
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
                'balance' => $calc['totals']['grand_total'] - (float) ($data['paid_amount'] ?? 0),
                'paid_amount' => (float) ($data['paid_amount'] ?? 0),
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

        // Finalized invoices: only soft fields may change.
        if (! $invoice->isEditable()) {
            $soft = $request->validate([
                'due_date' => ['nullable', 'date'],
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
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        $reverseCharge = (bool) ($data['reverse_charge'] ?? false);
        $calc = $this->calculator->recalculate($invoice, $data['items'], $isInterstate, $reverseCharge);

        DB::transaction(function () use ($invoice, $customer, $data, $calc, $isInterstate) {
            $paid = (float) ($data['paid_amount'] ?? 0);
            $invoice->update([
                'customer_id' => $customer->id,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'place_of_supply_state_id' => $customer->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
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
                'balance' => $calc['totals']['grand_total'] - $paid,
            ]);

            $invoice->items()->delete();
            $invoice->items()->createMany($calc['items']);
        });

        return new InvoiceResource($invoice->fresh(['items', 'customer.state', 'payments']));
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (! ($invoice->isEditable() || $invoice->isCancelled())) {
            return response()->json([
                'message' => 'Issued invoices cannot be deleted. Cancel first, then delete.',
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
        if ($invoice->company->isBooksLockedOn($invoice->invoice_date)) {
            return response()->json([
                'message' => "Books are locked up to {$invoice->company->books_locked_until->format('d M Y')}. Cannot issue an invoice dated on or before that.",
            ], 422);
        }

        DB::transaction(function () use ($invoice) {
            $company = $invoice->company()->lockForUpdate()->first();
            $number = $company->bumpCounterForFinalize($invoice->invoice_date?->toDateString());

            $invoice->update([
                'invoice_number' => $number,
                'status' => (float) $invoice->paid_amount >= (float) $invoice->grand_total ? 'paid' : ((float) $invoice->paid_amount > 0 ? 'partially_paid' : 'final'),
                'finalized_at' => now(),
            ]);
        });

        AuditLog::record('invoice.finalized',
            "Invoice {$invoice->fresh()->invoice_number} finalized · ₹" . number_format($invoice->grand_total, 2),
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

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $data['cancellation_reason'],
        ]);

        AuditLog::record('invoice.cancelled',
            "Invoice {$invoice->invoice_number} cancelled · ₹" . number_format($invoice->grand_total, 2) . " · Reason: " . $data['cancellation_reason'],
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
    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->isDraft()) {
            return response()->json(['message' => 'Issue the invoice before downloading the PDF.'], 422);
        }

        $invoice->load(['items.product:id,name', 'customer.state', 'company.state', 'placeOfSupply', 'shipToState']);
        $amountInWords = NumberToWords::indianRupees((float) $invoice->grand_total, $invoice->currency);
        $style = $invoice->style ?: 'classic';
        $print = ! $request->boolean('color');
        $copies = $request->has('copies')
            ? max(1, min(3, (int) $request->input('copies')))
            : $invoice->defaultCopyCount();

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'amountInWords', 'style', 'print', 'copies'))
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
                'message' => $record->error ?: 'No reminder needed — invoice is already settled.',
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

        $remaining = max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount - (float) $invoice->credited_amount);
        $methods = array_keys(config('payment_methods.methods'));

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$remaining}"],
            'method' => ['required', 'string', 'in:' . implode(',', $methods)],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            'tds_amount' => ['nullable', 'numeric', 'min:0', 'lt:amount'],
            'tds_section' => ['nullable', 'string', 'max:16'],
            'tds_rate' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);

        $payment = DB::transaction(function () use ($invoice, $data) {
            $company = $invoice->company()->lockForUpdate()->first();
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
            "₹" . number_format($payment->amount, 2) . " · " . strtoupper($payment->method) . " · Receipt {$payment->receipt_number} · Invoice {$invoice->invoice_number}",
            $payment
        );

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function deletePayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        $invoice = $payment->invoice;

        $company = $invoice->company;
        if ($company->isBooksLockedOn($payment->received_at) || $company->isBooksLockedOn($invoice->invoice_date)) {
            return response()->json([
                'message' => "Books are locked up to {$company->books_locked_until->format('d M Y')}. This payment cannot be reversed.",
            ], 422);
        }

        $snapshot = $payment->only([
            'receipt_number', 'amount', 'tds_amount', 'tds_section',
            'method', 'reference_number', 'received_at',
        ]);

        DB::transaction(function () use ($payment, $invoice) {
            $payment->delete();

            $paid = (float) $invoice->payments()->sum('amount');
            $credited = (float) $invoice->credited_amount;
            $balance = round((float) $invoice->grand_total - $paid - $credited, 2);
            $settled = ($paid + $credited) >= (float) $invoice->grand_total;
            $status = $settled ? 'paid' : ($paid > 0 ? 'partially_paid' : 'final');

            $invoice->update(['paid_amount' => $paid, 'balance' => $balance, 'status' => $status]);
        });

        AuditLog::record('payment.reversed',
            "Receipt {$snapshot['receipt_number']} reversed · ₹" . number_format($snapshot['amount'], 2) . " · Invoice {$invoice->invoice_number}",
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.description' => ['nullable', 'string', 'max:150'],
            'items.*.hsn_sac' => [$hsnRequired ? 'required' : 'nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:' . implode(',', config('gst.allowed_values'))],
        ]);
    }
}
