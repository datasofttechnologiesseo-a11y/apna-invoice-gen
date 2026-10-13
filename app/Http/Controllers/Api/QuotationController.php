<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\QuotationListResource;
use App\Http\Resources\QuotationResource;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\InvoiceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * JSON API for quotations — parallels the web QuotationController, reusing the
 * same InvoiceCalculator and quote-numbering/convert logic.
 */
class QuotationController extends Controller
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function index(Request $request)
    {
        $company = $request->user()->ensureCompany();

        $quotations = $company->quotations()
            ->with('customer:id,name')
            ->when($request->status, function ($q, $s) {
                if ($s === 'expired') {
                    return $q->whereIn('status', ['draft', 'sent'])
                        ->whereNotNull('valid_until')
                        ->where('valid_until', '<', now()->toDateString());
                }
                return $q->where('status', $s);
            })
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->whereLiteralLike('quote_number', $s)
                    ->orWhereHas('customer', fn ($c) => $c->whereLiteralLike('name', $s));
            }))
            ->orderByDesc('quote_date')
            ->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return QuotationListResource::collection($quotations);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validateQuotation($request, $company->id);
        $data = $this->hydrateProductDescriptions($data, $company->id);

        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        // Composition dealers quote without GST, as they bill (s.10(4)).
        $result = $this->calculator->recalculate(new Invoice(), $data['items'], $isInterstate, (bool) $company->composition_dealer);

        $quote = DB::transaction(function () use ($user, $company, $customer, $data, $result, $isInterstate) {
            $quote = Quotation::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'subject' => $data['subject'] ?? null,
                'reference' => $data['reference'] ?? null,
                'delivery_period' => $data['delivery_period'] ?? null,
                'is_interstate' => $isInterstate,
                'currency' => $data['currency'] ?? 'INR',
                'exchange_rate' => 1,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'style' => $data['style'] ?? 'classic',
                ...$result['totals'],
            ]);

            foreach ($result['items'] as $row) {
                $quote->items()->create($row);
            }

            return $quote;
        });

        AuditLog::record('quotation.created',
            "Quotation draft created · ₹" . inr($quote->grand_total) . " · " . $customer->name,
            $quote
        );

        return (new QuotationResource($quote->load(['items', 'customer.state'])))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize($request, $quotation);
        $quotation->load(['customer.state', 'company.state', 'items.product:id,name']);

        return new QuotationResource($quotation);
    }

    /** Shareable signed PDF link (same public view as the web "Copy link"). */
    public function shareLink(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorize($request, $quotation);

        if ($quotation->isDraft()) {
            return response()->json(['message' => 'Send the quotation before sharing it.'], 422);
        }

        return response()->json([
            'url' => \App\Http\Controllers\QuotationShareController::makePublicUrl($quotation),
            'whatsapp_link' => $quotation->whatsAppShareLink(),
        ]);
    }

    public function update(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        $this->authorize($request, $quotation);
        if (! $quotation->isEditable()) {
            return response()->json(['message' => 'This quotation can no longer be edited. Only drafts can be changed.'], 422);
        }

        $company = $quotation->company;
        $data = $this->validateQuotation($request, $company->id);
        $data = $this->hydrateProductDescriptions($data, $company->id);

        $customer = $company->customers()->findOrFail($data['customer_id']);
        $isInterstate = $this->calculator->isInterstate($company->state_id, $customer->state_id);
        // Composition dealers quote without GST, as they bill (s.10(4)).
        $result = $this->calculator->recalculate(new Invoice(), $data['items'], $isInterstate, (bool) $company->composition_dealer);

        DB::transaction(function () use ($quotation, $customer, $data, $result, $isInterstate) {
            $quotation->update([
                'customer_id' => $customer->id,
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'subject' => $data['subject'] ?? null,
                'reference' => $data['reference'] ?? null,
                'delivery_period' => $data['delivery_period'] ?? null,
                'is_interstate' => $isInterstate,
                'currency' => $data['currency'] ?? 'INR',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'style' => $data['style'] ?? 'classic',
                ...$result['totals'],
            ]);

            $quotation->items()->delete();
            foreach ($result['items'] as $row) {
                $quotation->items()->create($row);
            }
        });

        return new QuotationResource($quotation->fresh(['items', 'customer.state']));
    }

    public function destroy(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorize($request, $quotation);
        if (! $quotation->isDraft()) {
            return response()->json(['message' => 'Only draft quotations can be deleted.'], 422);
        }

        $snapshot = $quotation->only(['quote_number', 'quote_date', 'grand_total', 'customer_id']);
        $quotation->delete();
        AuditLog::record('quotation.deleted', 'Draft quotation deleted', null, $snapshot);

        return response()->json(['message' => 'Draft quotation deleted.']);
    }

    public function send(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        $this->authorize($request, $quotation);
        if (! $quotation->isDraft()) {
            return response()->json(['message' => 'Only drafts can be sent.'], 422);
        }

        DB::transaction(function () use ($quotation) {
            $company = $quotation->company()->lockForUpdate()->first();
            // A number the user chose (typed on the website) is kept, as on
            // the web; only a draft without one takes the next in the series.
            $number = filled($quotation->quote_number)
                ? $quotation->quote_number
                : $company->bumpQuoteCounter($quotation->quote_date);
            $quotation->update(['quote_number' => $number, 'status' => 'sent', 'sent_at' => now()]);
        });

        AuditLog::record('quotation.sent', "Quotation {$quotation->fresh()->quote_number} marked as sent", $quotation);

        return new QuotationResource($quotation->fresh(['items', 'customer.state']));
    }

    public function accept(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        $this->authorize($request, $quotation);
        if ($quotation->status !== 'sent') {
            return response()->json(['message' => 'Only sent quotations can be accepted.'], 422);
        }

        $quotation->update(['status' => 'accepted', 'accepted_at' => now()]);
        AuditLog::record('quotation.accepted', "Quotation {$quotation->quote_number} marked as accepted", $quotation);

        return new QuotationResource($quotation->fresh(['items', 'customer.state']));
    }

    public function decline(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        $this->authorize($request, $quotation);
        if (! in_array($quotation->status, ['sent', 'accepted'], true)) {
            return response()->json(['message' => 'Only sent or accepted quotations can be declined.'], 422);
        }

        $reason = $request->validate(['decline_reason' => ['nullable', 'string', 'max:500']])['decline_reason'] ?? null;
        $quotation->update(['status' => 'declined', 'declined_at' => now(), 'decline_reason' => $reason]);
        AuditLog::record('quotation.declined',
            "Quotation {$quotation->quote_number} marked as declined" . ($reason ? " — {$reason}" : ''),
            $quotation
        );

        return new QuotationResource($quotation->fresh(['items', 'customer.state']));
    }

    /** Convert an accepted quote into a draft invoice and return the invoice. */
    public function convert(Request $request, Quotation $quotation): InvoiceResource|JsonResponse
    {
        $this->authorize($request, $quotation);
        if (! $quotation->canBeConverted()) {
            return response()->json(['message' => 'This quotation is already converted or declined, so it cannot be converted.'], 422);
        }

        $quotation->loadMissing('items', 'customer');

        $invoice = DB::transaction(function () use ($quotation) {
            // A second tab or a double click sends "convert" again. Re-read the
            // quotation under a lock and check again, or both requests pass the
            // check above and the quotation becomes two invoices.
            $locked = \App\Models\Quotation::whereKey($quotation->id)->lockForUpdate()->first();
            abort_unless($locked && $locked->canBeConverted(), 422, 'This quotation has just been converted to an invoice.');
            // Same as the web path: taxes are recomputed at conversion from the
            // customer's state as it stands today, not copied from the quote.
            $calculator = app(\App\Services\InvoiceCalculator::class);
            $isInterstate = $calculator->isInterstate($quotation->company?->state_id, $quotation->customer?->state_id);
            $calc = $calculator->recalculate(new Invoice(), $quotation->items->map(fn ($qi) => [
                'product_id' => $qi->product_id,
                'description' => $qi->description,
                'hsn_sac' => $qi->hsn_sac,
                'quantity' => $qi->quantity,
                'unit' => $qi->unit,
                'rate' => $qi->rate,
                'discount' => $qi->discount,
                'gst_rate' => $qi->gst_rate,
            ])->all(), $isInterstate, false);
            $t = $calc['totals'];

            $invoice = Invoice::create([
                'user_id' => $quotation->user_id,
                'company_id' => $quotation->company_id,
                'customer_id' => $quotation->customer_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'place_of_supply_state_id' => $quotation->customer?->state_id,
                'is_interstate' => $isInterstate,
                'reverse_charge' => false,
                'currency' => $quotation->currency,
                'exchange_rate' => $quotation->exchange_rate,
                'status' => 'draft',
                'subtotal' => $t['subtotal'],
                'total_cgst' => $t['total_cgst'],
                'total_sgst' => $t['total_sgst'],
                'total_igst' => $t['total_igst'],
                'total_tax' => $t['total_tax'],
                'round_off' => $t['round_off'],
                'grand_total' => $t['grand_total'],
                'paid_amount' => 0,
                'balance' => $t['grand_total'],
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'style' => $quotation->style,
            ]);

            // Lines come from the same calculation as the totals above.
            $invoice->items()->createMany($calc['items']);

            $quotation->update([
                'status' => 'converted',
                'converted_to_invoice_id' => $invoice->id,
                'converted_at' => now(),
            ]);

            return $invoice;
        });

        AuditLog::record('quotation.converted',
            "Quotation {$quotation->quote_number} converted to Invoice draft #{$invoice->id}",
            $quotation,
            ['invoice_id' => $invoice->id]
        );

        return new InvoiceResource($invoice->load(['items', 'customer.state', 'payments']));
    }

    // ---- helpers -----------------------------------------------------------

    private function authorize(Request $request, Quotation $quotation): void
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);
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

    private function validateQuotation(Request $request, int $companyId): array
    {
        return $request->validate([
            // (The customer is looked up within this business below: another
            // business's customer is a 404, as before.)
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            'subject' => ['nullable', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:100'],
            'delivery_period' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'style' => ['nullable', 'string', 'in:classic,bold,minimal,retail,warm'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'in:' . implode(',', config('gst.allowed_values'))],
            // An item from another business would print its name on this quotation.
            'items.*.product_id' => ['nullable', 'integer', "exists:products,id,company_id,{$companyId}"],
        ]);
    }
}
