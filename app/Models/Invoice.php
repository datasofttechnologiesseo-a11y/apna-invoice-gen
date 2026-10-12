<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    /**
     * Zero-rated supplies (s.16 IGST Act) alongside the regular kind. Exports
     * and supplies to an SEZ are always inter-state (s.7(5)); "with IGST" pays
     * the tax and claims it back as a refund, "under LUT" charges none.
     */
    public const SUPPLY_TYPES = [
        'regular' => 'Regular (within India)',
        'export_igst' => 'Export (with payment of IGST)',
        'export_lut' => 'Export (under LUT/bond, without IGST)',
        'sez_igst' => 'Supply to SEZ (with payment of IGST)',
        'sez_lut' => 'Supply to SEZ (under LUT/bond, without IGST)',
    ];

    protected $fillable = [
        'user_id', 'company_id', 'customer_id',
        'invoice_number', 'invoice_date', 'due_date',
        'place_of_supply_state_id', 'is_interstate', 'reverse_charge',
        'supply_type', 'export_port_code', 'shipping_bill_number', 'shipping_bill_date',
        'transporter_name', 'transporter_id', 'vehicle_number', 'transport_mode', 'eway_bill_number',
        'ship_to_name', 'ship_to_address_line1', 'ship_to_address_line2',
        'ship_to_city', 'ship_to_state_id', 'ship_to_postal_code', 'ship_to_gstin',
        'currency', 'exchange_rate', 'status', 'style',
        'subtotal', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax',
        'round_off', 'grand_total', 'paid_amount', 'credited_amount', 'balance',
        'customer_snapshot',
        'notes', 'terms', 'finalized_at',
        'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'shipping_bill_date' => 'date',
        'is_interstate' => 'boolean',
        'reverse_charge' => 'boolean',
        'subtotal' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'round_off' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'credited_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'customer_snapshot' => 'array',
        'exchange_rate' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        // The referral offer: an invoice issued for the first time by someone
        // who was referred earns their referrer lifetime free access. Runs
        // after the issue is committed, from the web, the app or a team member.
        static::saved(function (Invoice $invoice) {
            $justIssued = $invoice->finalized_at !== null
                && ($invoice->wasRecentlyCreated || $invoice->wasChanged('finalized_at'))
                && $invoice->getOriginal('finalized_at') === null;
            if ($justIssued && $invoice->user_id) {
                $userId = (int) $invoice->user_id;
                \Illuminate\Support\Facades\DB::afterCommit(
                    fn () => app(\App\Services\ReferralRewards::class)->invoiceIssuedBy($userId)
                );
            }
        });
    }

    // GSTINs are upper case by definition (checksum alphabet is 0-9A-Z).
    // Normalise on write and on read so old rows saved in mixed case still
    // print correctly on the invoice.
    protected function shipToGstin(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v === null ? null : strtoupper($v),
            set: fn (?string $v) => $v === null ? null : strtoupper(trim($v)),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function placeOfSupply(): BelongsTo
    {
        return $this->belongsTo(State::class, 'place_of_supply_state_id');
    }

    public function shipToState(): BelongsTo
    {
        return $this->belongsTo(State::class, 'ship_to_state_id');
    }

    /** True if any ship-to field is set (so we render a separate "Ship to" block). */
    public function hasSeparateShipTo(): bool
    {
        return filled($this->ship_to_name)
            || filled($this->ship_to_address_line1)
            || filled($this->ship_to_address_line2)
            || filled($this->ship_to_city)
            || filled($this->ship_to_state_id)
            || filled($this->ship_to_postal_code)
            || filled($this->ship_to_gstin);
    }

    public function items(): HasMany
    {
        // Deterministic line order — sort_order is stamped from the row's array
        // position in InvoiceCalculator. id is the fallback when two rows tie
        // (legacy rows from before the sort_order fill all share sort_order=0).
        return $this->hasMany(InvoiceItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at')->orderBy('id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(InvoiceReminder::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('credit_note_date')->orderBy('id');
    }

    /** Amount left unpaid after payments AND credit notes have been applied. */
    public function effectiveBalance(): float
    {
        return max(0, (float) $this->grand_total - (float) $this->paid_amount - (float) $this->credited_amount);
    }

    /**
     * Invoices eligible for payment reminders — issued, not cancelled, with
     * an outstanding balance. Single source of truth for the schedule command
     * and the ReminderService alike.
     */
    public function scopeEligibleForReminders($query)
    {
        return $query->whereNotNull('finalized_at')
            ->where('status', '!=', 'cancelled')
            ->whereNull('cancelled_at')
            ->where('balance', '>', 0);
    }

    /**
     * Free-text match on what someone actually has in front of them when they
     * go looking for a bill: the invoice number, the customer, or the amount
     * off a bank statement.
     *
     * It lives on the model because two callers need the same answer - the
     * invoice list filter and the search box's typeahead. If they drifted, the
     * dropdown would offer a bill that pressing Enter then failed to find,
     * which is worse than having no dropdown at all.
     *
     * Customer matching delegates to Customer::scopeSearch so the name, mobile,
     * email and GSTIN rules stay in one place too.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        // A bank statement gives you an amount, not a bill number, so "11,800"
        // and "₹11800" have to find the invoice too.
        $amount = str_replace([',', ' ', '₹'], '', $term);

        return $query->where(function (Builder $w) use ($term, $amount) {
            $w->where('invoice_number', 'like', "%{$term}%")
              ->orWhereHas('customer', fn (Builder $c) => $c->search($term));

            if (is_numeric($amount)) {
                $w->orWhere('grand_total', (float) $amount);
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Freeze the recipient as printed on the issued invoice (Rule 46(d)/(e))
     * so later edits to the customer record - a new GSTIN, a moved office -
     * never rewrite a document that has already gone out, nor flip its
     * B2B/B2C classification in GSTR-1.
     */
    public function snapshotCustomer(): void
    {
        $c = $this->customer;
        if (! $c) {
            return;
        }
        $s = $c->state;

        $this->forceFill(['customer_snapshot' => [
            'name' => $c->name,
            'gstin' => $c->gstin,
            'address_line1' => $c->address_line1,
            'address_line2' => $c->address_line2,
            'city' => $c->city,
            'postal_code' => $c->postal_code,
            'country' => $c->country,
            'phone' => $c->phone,
            'email' => $c->email,
            'state' => $s ? [
                'id' => $s->id,
                'name' => $s->name,
                'gst_code' => $s->gst_code,
                'is_union_territory' => (bool) $s->is_union_territory,
            ] : null,
        ]])->save();
    }

    /**
     * The customer as it should appear on this document: the snapshot taken
     * at issue when there is one (issued invoices), otherwise the live row
     * (drafts). Returned as an unsaved Customer with its state attached, so
     * views keep reading $cust->name, $cust->state->gst_code unchanged.
     */
    public function billedCustomer(): ?Customer
    {
        $snap = $this->customer_snapshot;
        if (! $snap) {
            return $this->customer;
        }

        // forceFill: the snapshot keys are trusted and must not be filtered by
        // the model's fillable list.
        $cust = (new Customer())->forceFill(collect($snap)->except('state')->all());
        $cust->exists = false;
        $cust->setRelation('state', $snap['state'] ? (new State($snap['state']))->forceFill(['id' => $snap['state']['id']]) : null);
        if ($this->customer_id) {
            $cust->forceFill(['id' => $this->customer_id]);
        }

        return $cust;
    }

    /**
     * "SGST" or "UTGST" for the state share of an intra-state supply. UTGST
     * applies when the place of supply is a Union Territory without a
     * legislature (Chandigarh, DNH & DD, Lakshadweep, A&N, Ladakh); Delhi,
     * Puducherry and J&K have legislatures and levy SGST. The amounts are
     * identical - only the printed head differs.
     */
    public function stockMovements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function supplyType(): string
    {
        return array_key_exists((string) $this->supply_type, self::SUPPLY_TYPES) ? $this->supply_type : 'regular';
    }

    public function isExport(): bool
    {
        return str_starts_with($this->supplyType(), 'export_');
    }

    public function isSez(): bool
    {
        return str_starts_with($this->supplyType(), 'sez_');
    }

    public function isZeroRated(): bool
    {
        return $this->supplyType() !== 'regular';
    }

    /** Under a Letter of Undertaking / bond: zero-rated without paying IGST. */
    public function isUnderLut(): bool
    {
        return str_ends_with($this->supplyType(), '_lut');
    }

    /**
     * The line Rule 46 requires on an export / SEZ invoice, in the words of
     * the proviso: with or without payment of integrated tax.
     */
    public function zeroRatedEndorsement(): ?string
    {
        if (! $this->isZeroRated()) {
            return null;
        }
        $what = $this->isExport() ? 'SUPPLY MEANT FOR EXPORT' : 'SUPPLY TO SEZ UNIT OR SEZ DEVELOPER FOR AUTHORISED OPERATIONS';

        return $this->isUnderLut()
            ? $what . ' UNDER BOND OR LETTER OF UNDERTAKING WITHOUT PAYMENT OF INTEGRATED TAX'
            : $what . ' ON PAYMENT OF INTEGRATED TAX';
    }

    /**
     * True when a draft's saved tax split no longer matches the parties: the
     * customer moved state, or the business did, after the draft was saved.
     * Issuing it as-is would print the wrong heads (CGST+SGST vs IGST).
     */
    public function taxBasisIsStale(): bool
    {
        $customer = $this->customer;
        $company = $this->company;
        if (! $customer?->state_id || ! $company?->state_id || (float) $this->total_tax <= 0) {
            return false; // no tax heads to get wrong
        }
        $interstate = $this->isZeroRated()
            || app(\App\Services\InvoiceCalculator::class)->isInterstate($company->state_id, $customer->state_id);
        if ((bool) $this->is_interstate === $interstate) {
            return false;
        }
        // Only a change made after the draft was saved counts; anything older
        // is how the draft was deliberately saved.
        $savedAt = $this->updated_at;

        return $savedAt !== null
            && (($customer->updated_at && $customer->updated_at->gt($savedAt))
                || ($company->updated_at && $company->updated_at->gt($savedAt)));
    }

    /**
     * Place of supply to print at issue: the customer's state as it is now,
     * when that agrees with the saved tax split (a customer who moved between
     * two other states still gets IGST, but the new state must be printed);
     * otherwise what the draft stored.
     */
    public function placeOfSupplyForIssue(): ?int
    {
        $state = $this->customer?->state_id;
        $companyState = $this->company?->state_id;
        if (! $state || ! $companyState) {
            return $this->place_of_supply_state_id;
        }
        $interstate = $this->isZeroRated() || app(\App\Services\InvoiceCalculator::class)->isInterstate($companyState, $state);

        return (bool) $this->is_interstate === $interstate ? $state : $this->place_of_supply_state_id;
    }

    public function stateTaxLabel(): string
    {
        $state = $this->placeOfSupply ?? $this->customer?->state ?? $this->company?->state;

        return $state?->is_union_territory ? 'UTGST' : 'SGST';
    }

    public function canBeCancelled(): bool
    {
        // Only issued invoices can be cancelled. Drafts should be deleted,
        // cancelled invoices can't be re-cancelled. Once money has moved
        // against it - a receipt or a credit note - cancelling would leave
        // those documents pointing at nothing and drop the receipts out of
        // the books; the correction is a credit note (s.34).
        return ! $this->isDraft()
            && ! $this->isCancelled()
            && (float) $this->paid_amount <= 0
            && (float) $this->credited_amount <= 0;
    }

    /** Full edit (line items, customer, dates, amounts). Drafts only. */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Soft-edit allowed (notes, terms, due date, transporter details).
     * Finalized invoices can fix non-amount fields; cancelled cannot.
     */
    public function isSoftEditable(): bool
    {
        return in_array($this->status, ['draft', 'final', 'partially_paid', 'paid'], true);
    }

    /**
     * Single source of truth for "what name do we show for this invoice?".
     *
     * Logic is driven by status — NOT by inspecting the invoice_number string —
     * so the answer stays correct even if the operator chose an unusual prefix
     * (e.g. starting with "DRAFT-" by accident in Company settings).
     *
     *   • status === 'draft'           → "Draft #{id}"     (no number assigned yet)
     *   • status === 'cancelled' + no number → "Draft #{id}" (cancelled before finalize)
     *   • Anything else with a number  → the assigned invoice_number
     *   • Defensive: if status is non-draft but the number is empty for any
     *     reason, fall back to "Draft #{id}" so the UI never renders a blank.
     */
    public function displayNumber(): string
    {
        if ($this->isDraft() || ! $this->invoice_number) {
            return 'Draft #' . $this->id;
        }
        return $this->invoice_number;
    }

    /**
     * File-system-safe name for PDF downloads. Invoice numbers under the
     * FY-format option (e.g. "ACME/2026-27/0001") contain '/' which Symfony's
     * Content-Disposition header rejects. Replace any forbidden chars with '-'.
     */
    public function filenameSafeNumber(): string
    {
        $raw = $this->isDraft() ? 'draft-' . $this->id : ($this->invoice_number ?: 'draft-' . $this->id);
        // Forbidden in Content-Disposition + most file systems
        return preg_replace('~[\\\\/\\:\\*\\?"<>\\|\\s]+~', '-', $raw);
    }

    /**
     * Last legally-permitted date to issue a credit note against this invoice.
     *
     * CGST Section 34(2): a credit note must be declared in the return for
     * the month of issue, but NO LATER THAN the 30th day of November
     * following the end of the financial year in which the original supply
     * was made. After this date the supplier loses the right to reduce their
     * GST liability via a credit note (commercial credit notes without GST
     * impact are still possible, but those are out of scope for our tool).
     *
     * Indian FY runs Apr 1 → Mar 31. So:
     *   • Supply on 15-Aug-2024 (FY 2024-25) → CN deadline 30-Nov-2025
     *   • Supply on 02-Apr-2024 (FY 2024-25) → CN deadline 30-Nov-2025
     *   • Supply on 31-Mar-2025 (FY 2024-25) → CN deadline 30-Nov-2025
     */
    public function creditNoteDeadline(): ?\Carbon\Carbon
    {
        if (! $this->invoice_date) {
            return null;
        }
        $invoiceMonth = (int) $this->invoice_date->format('n');
        $invoiceYear = (int) $this->invoice_date->format('Y');
        // FY end year: if month >= April, FY ends in (year + 1); else (year)
        $fyEndYear = $invoiceMonth >= 4 ? $invoiceYear + 1 : $invoiceYear;
        return \Carbon\Carbon::create($fyEndYear, 11, 30)->endOfDay();
    }

    /**
     * Whether the Section 34(2) window for this invoice has closed.
     * Driven by today's date vs creditNoteDeadline().
     */
    public function isCreditNoteWindowClosed(?\Carbon\Carbon $on = null): bool
    {
        $deadline = $this->creditNoteDeadline();
        if (! $deadline) {
            return false;
        }
        return ($on ?? now())->gt($deadline);
    }

    /**
     * Whole days left before the Section 34(2) credit-note window closes.
     * Negative once the deadline has passed, null if no invoice date. Used to
     * warn the operator *before* the window shuts, not only when they try to
     * issue a credit note after it has.
     */
    public function creditNoteDaysLeft(?\Carbon\Carbon $on = null): ?int
    {
        $deadline = $this->creditNoteDeadline();
        if (! $deadline) {
            return null;
        }
        return (int) ($on ?? now())->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false);
    }

    /**
     * Decide what title goes on the invoice (heading + PDF + print + email).
     *
     * Three-way logic, driven entirely by the company's GST profile + tax
     * actually charged. No operator action required.
     *
     *   • Company has no GSTIN
     *       → "Invoice"  (unregistered seller, outside CGST Rules 46/49)
     *   • Company is a composition dealer
     *       → "Bill of Supply"  (CGST Rule 49 + Section 31(3)(c) — composition
     *         dealers cannot collect tax on supplies, must NOT issue Tax Invoice)
     *   • Otherwise (registered, regular scheme)
     *       → "Tax Invoice"  (CGST Rule 46 — registered person, taxable supply)
     *
     * The composition branch is paired with a mandatory footer note on the
     * PDF — see invoices/pdf.blade.php — which the regulation also requires.
     */
    public function documentTitle(): string
    {
        $company = $this->company;
        if (! $company || ! $company->gstin) {
            return 'Invoice';
        }
        if ($company->composition_dealer ?? false) {
            return 'Bill of Supply';
        }
        return 'Tax Invoice';
    }

    /**
     * CGST Rule 48(1): when goods are supplied, three copies of the invoice
     * are required (Original/Recipient, Duplicate/Transporter, Triplicate/
     * Supplier). For services, only two copies (Original/Recipient,
     * Duplicate/Supplier).
     *
     * Goods carry HSN codes; services use SAC codes which start with '99'.
     * If any line item is goods (or HSN is missing — safer default), we
     * treat the whole invoice as a goods supply.
     */
    public function defaultCopyCount(): int
    {
        $items = $this->items ?? collect();
        if ($items->isEmpty()) {
            return 3;
        }
        $allServices = $items->every(fn ($i) => is_string($i->hsn_sac) && str_starts_with(trim($i->hsn_sac), '99'));
        return $allServices ? 2 : 3;
    }

    /**
     * Labels for printed copies, ordered as required by Rule 48(1).
     * Returns 1, 2 or 3 entries depending on $count.
     */
    public function copyLabels(int $count): array
    {
        $count = max(1, min(3, $count));
        return match ($count) {
            1 => ['Original for Recipient'],
            2 => ['Original for Recipient', 'Duplicate for Supplier'],
            3 => ['Original for Recipient', 'Duplicate for Transporter', 'Triplicate for Supplier'],
        };
    }

    /**
     * Labels for a caller-selected subset of copies. Accepts any of the keys
     * 'recipient' (Customer), 'transporter' (Delivery) and 'supplier'
     * (Supplier); the returned labels are always kept in Rule 48(1) order
     * regardless of the order the keys are passed in. Unknown keys are ignored;
     * an empty/invalid selection falls back to a single recipient copy.
     */
    public function copyLabelsFor(array $types): array
    {
        // Rule 48(1), goods: Original/Recipient, Duplicate/Transporter,
        // Triplicate/Supplier. Rule 48(2), services: Original/Recipient,
        // Duplicate/Supplier - there is no transporter copy.
        $services = $this->defaultCopyCount() === 2;
        $map = [
            'recipient' => 'Original for Recipient',
            'transporter' => 'Duplicate for Transporter',
            'supplier' => $services ? 'Duplicate for Supplier' : 'Triplicate for Supplier',
        ];

        $labels = [];
        foreach ($map as $key => $label) {
            if ($services && $key === 'transporter') {
                continue;
            }
            if (in_array($key, $types, true)) {
                $labels[] = $label;
            }
        }

        return $labels ?: ['Original for Recipient'];
    }

    /**
     * A draft can carry an advance - the "Paid amount" box on the form - with
     * no Payment row behind it. Give it one when the invoice is issued, so
     * every later recompute from payments()->sum('amount') still counts the
     * advance instead of wiping it on the first recorded payment. Returns
     * the receipt created, or null when there was nothing to book.
     *
     * Caller must hold the transaction; the company row is locked here for
     * the receipt counter, as recordPayment does.
     */
    public function bookAdvanceAsPayment(): ?Payment
    {
        $received = (float) $this->payments()->sum('amount');
        $advance = round((float) $this->paid_amount - $received, 2);
        // Never a receipt for more than the bill: an advance typed larger than
        // the total would book money the customer was never asked for.
        $advance = min($advance, round((float) $this->grand_total - $received, 2));
        if ($advance <= 0) {
            return null;
        }
        if ((float) $this->paid_amount > (float) $this->grand_total) {
            $this->forceFill([
                'paid_amount' => $this->grand_total,
                'balance' => round(0 - (float) $this->credited_amount, 2),
                'status' => 'paid',
            ])->save();
        }

        $company = $this->company()->lockForUpdate()->first();
        $company->increment('receipt_counter');
        $receiptNumber = $company->receipt_prefix . '-' .
            str_pad((string) $company->receipt_counter, $company->receipt_number_padding, '0', STR_PAD_LEFT);

        $methods = array_keys(config('payment_methods.methods', []));

        return Payment::create([
            'user_id' => $this->user_id,
            'company_id' => $this->company_id,
            'invoice_id' => $this->id,
            'receipt_number' => $receiptNumber,
            'received_at' => ($this->invoice_date ?? now())->toDateString(),
            'amount' => $advance,
            'tds_amount' => 0,
            'method' => in_array('cash', $methods, true) ? 'cash' : ($methods[0] ?? 'cash'),
            'notes' => 'Advance recorded on the invoice',
        ]);
    }

    /**
     * Generate the share/reminder message text — WhatsApp, SMS, email body all
     * use this so a single source of truth controls what customers see.
     *
     * Crucially: balance is read fresh from $this at call time. The Vyapar
     * pattern of "got a Balance Due message AFTER I paid cash" cannot happen
     * here because:
     *   1. ReminderService::isEligible() rejects invoices with balance ≤ 0
     *   2. This method emits a "Thank you for payment" message when paid
     *   3. Reminders are synchronous (not queued) so there is no race with payments
     *
     * @param string $context  'share' (manual click), 'reminder' (payment chase), 'thanks' (post-payment)
     */
    public function shareMessageText(string $context = 'share'): string
    {
        $cust = $this->customer;
        $company = $this->company;
        $name = trim($cust?->name ?? '') ?: 'there';
        $business = trim($company?->name ?? '') ?: 'Us';
        $docTitle = $this->documentTitle();
        $number = $this->invoice_number ?: ('#' . $this->id);
        // Indian grouping (₹1,17,000.00) - the message goes to an Indian customer.
        $grand = inr((float) $this->grand_total);
        $balance = (float) $this->balance;
        $paid = (float) $this->paid_amount;
        $upi = trim((string) ($company?->upi_id ?? ''));

        // Build a public link only if the invoice is finalized — drafts have no
        // shareable identity yet, and cancelled invoices are blocked at the
        // public route (HTTP 410). Generating it for those wastes signed tokens.
        $url = $this->finalized_at && ! $this->isCancelled()
            ? \App\Http\Controllers\InvoiceShareController::makePublicUrl($this)
            : null;

        $lines = ["Hi {$name},"];
        $lines[] = '';

        if ($this->isCancelled()) {
            $lines[] = "{$docTitle} {$number} has been cancelled. Please ignore it.";
            $lines[] = '';
            $lines[] = 'Regards,';
            $lines[] = $business;
            return implode("\n", $lines);
        }

        // PAID IN FULL — explicit thank-you message. Prevents the Vyapar
        // trust-killer where users get "Balance Due" reminders after paying.
        if ($balance <= 0 && $paid > 0) {
            $lines[] = "Thank you for the payment of ₹{$grand} for {$docTitle} {$number}.";
            if ($url) {
                $lines[] = '';
                $lines[] = "Receipt: {$url}";
            }
            $lines[] = '';
            $lines[] = 'Regards,';
            $lines[] = $business;
            return implode("\n", $lines);
        }

        // PARTIALLY PAID
        if ($paid > 0 && $balance > 0) {
            $paidStr = inr($paid);
            $balStr = inr($balance);
            $lines[] = "Sharing {$docTitle} {$number} for ₹{$grand}.";
            $lines[] = "Already received: ₹{$paidStr}.";
            $dueClause = $this->due_date
                ? " (due by " . $this->due_date->format('d M Y') . ")"
                : '';
            $lines[] = "Balance: ₹{$balStr}{$dueClause}.";
        } else {
            // Fully unpaid — vary the language based on overdue/due-today/future.
            $today = \Illuminate\Support\Carbon::now()->startOfDay();
            $due = $this->due_date?->copy()->startOfDay();
            $intro = match (true) {
                $due === null               => "Sharing {$docTitle} {$number} for ₹{$grand}.",
                $today->greaterThan($due)   => "{$docTitle} {$number} for ₹{$grand} is "
                                                . (int) $today->diffInDays($due, true)
                                                . " day(s) overdue.",
                $today->equalTo($due)       => "{$docTitle} {$number} for ₹{$grand} is due today.",
                default                     => "Sharing {$docTitle} {$number} for ₹{$grand}. Due by " . $due->format('d M Y') . ".",
            };
            $lines[] = $intro;
        }

        if ($url) {
            $lines[] = '';
            $lines[] = "View & download: {$url}";
        }
        if ($upi !== '') {
            $lines[] = "Pay via UPI: {$upi}";
        }
        $lines[] = '';
        $lines[] = 'Regards,';
        $lines[] = $business;

        return implode("\n", $lines);
    }

    /**
     * Convenience helper for callers that want a `wa.me/<digits>?text=...` URL.
     * Returns null when there's no usable phone (UI hides the button in that case).
     */
    public function whatsAppShareLink(string $context = 'share'): ?string
    {
        $phone = $this->customer?->phone;
        if (! $phone) return null;
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 10) return null;
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($this->shareMessageText($context));
    }
}
