<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id', 'customer_id',
        'invoice_number', 'invoice_date', 'due_date',
        'place_of_supply_state_id', 'is_interstate', 'reverse_charge',
        'transporter_name', 'transporter_id', 'vehicle_number', 'transport_mode', 'eway_bill_number',
        'ship_to_name', 'ship_to_address_line1', 'ship_to_address_line2',
        'ship_to_city', 'ship_to_state_id', 'ship_to_postal_code', 'ship_to_gstin',
        'currency', 'exchange_rate', 'status', 'style',
        'subtotal', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax',
        'round_off', 'grand_total', 'paid_amount', 'credited_amount', 'balance',
        'notes', 'terms', 'finalized_at',
        'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
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
        'exchange_rate' => 'decimal:6',
    ];

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
        return $this->hasMany(InvoiceItem::class);
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

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeCancelled(): bool
    {
        // Only issued invoices can be cancelled. Drafts should be deleted,
        // cancelled invoices can't be re-cancelled.
        return ! $this->isDraft() && ! $this->isCancelled();
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
}
