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

    public function displayNumber(): string
    {
        if ($this->invoice_number && ! str_starts_with($this->invoice_number, 'DRAFT-')) {
            return $this->invoice_number;
        }
        return 'Draft #' . $this->id;
    }
}
