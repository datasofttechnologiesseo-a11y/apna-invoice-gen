<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'gstin', 'pan',
        'address_line1', 'address_line2', 'city', 'state_id', 'postal_code', 'country',
        'phone', 'email', 'website',
        'logo_path', 'signature_path',
        'bank_name', 'bank_account_number', 'bank_ifsc', 'bank_branch', 'upi_id',
        'default_currency', 'default_terms', 'declaration',
        'invoice_prefix', 'invoice_counter', 'invoice_number_padding',
        'invoice_number_format', 'invoice_counter_fy',
        'receipt_prefix', 'receipt_counter', 'receipt_number_padding',
        'credit_note_prefix', 'credit_note_counter', 'credit_note_number_padding', 'credit_note_counter_fy',
        'onboarded_at',
    ];

    protected $casts = [
        'onboarded_at' => 'datetime',
    ];

    public function isBusinessComplete(): bool
    {
        return filled($this->name) && filled($this->state_id) && filled($this->address_line1);
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * Preview the NEXT invoice number without mutating anything.
     *
     * Takes into account:
     *   - The `invoice_number_format` template (tokens: {FY}, {FY_SHORT},
     *     {YYYY}, {N}). If null, falls back to the legacy `{prefix}-{N padded}`.
     *   - The current Indian financial year (Apr–Mar): if the stored
     *     `invoice_counter_fy` is from a past FY, the counter preview resets
     *     to 1 for the new FY automatically (the actual reset happens in
     *     `bumpCounterForFinalize()` when the invoice is finalized).
     */
    public function nextInvoiceNumber(?string $referenceDate = null): string
    {
        $ref = $referenceDate ? \Illuminate\Support\Carbon::parse($referenceDate) : now();
        [$fyStartYear, $fyEndYear] = self::financialYearFor($ref);

        // If the stored counter is from an older FY we're about to roll over.
        $counter = ($this->invoice_counter_fy && $this->invoice_counter_fy < $fyStartYear)
            ? 0
            : ($this->invoice_counter ?? 0);

        $next = $counter + 1;
        $padded = str_pad((string) $next, $this->invoice_number_padding ?: 4, '0', STR_PAD_LEFT);

        $format = $this->invoice_number_format;
        if (! $format) {
            return ($this->invoice_prefix ?: 'INV') . '-' . $padded;
        }

        return strtr($format, [
            '{FY}' => sprintf('%04d-%02d', $fyStartYear, $fyEndYear % 100),
            '{FY_SHORT}' => sprintf('%02d-%02d', $fyStartYear % 100, $fyEndYear % 100),
            '{YYYY}' => $ref->format('Y'),
            '{N}' => $padded,
        ]);
    }

    /**
     * Atomically advance the counter for a finalize. Caller should already
     * hold a DB lock on this row (InvoiceController::finalize does).
     * Handles FY rollover (resets counter when FY changes).
     *
     * Returns the invoice number to stamp on the row.
     */
    public function bumpCounterForFinalize(?string $referenceDate = null): string
    {
        $ref = $referenceDate ? \Illuminate\Support\Carbon::parse($referenceDate) : now();
        [$fyStartYear] = self::financialYearFor($ref);

        if ($this->invoice_counter_fy !== null && $this->invoice_counter_fy < $fyStartYear) {
            $this->invoice_counter = 0;
        }
        $this->invoice_counter = ($this->invoice_counter ?? 0) + 1;
        $this->invoice_counter_fy = $fyStartYear;
        $this->save();

        // Re-call nextInvoiceNumber with the post-bump counter rolled back
        // mentally: we want the *current* number, not the next one. Build it
        // directly.
        [$fyS, $fyE] = self::financialYearFor($ref);
        $padded = str_pad((string) $this->invoice_counter, $this->invoice_number_padding ?: 4, '0', STR_PAD_LEFT);
        $format = $this->invoice_number_format;
        if (! $format) {
            return ($this->invoice_prefix ?: 'INV') . '-' . $padded;
        }
        return strtr($format, [
            '{FY}' => sprintf('%04d-%02d', $fyS, $fyE % 100),
            '{FY_SHORT}' => sprintf('%02d-%02d', $fyS % 100, $fyE % 100),
            '{YYYY}' => $ref->format('Y'),
            '{N}' => $padded,
        ]);
    }

    /**
     * Return [startYear, endYear] for the Indian financial year containing
     * the given date. Indian FY runs 1 April → 31 March, so 15 June 2025
     * is in FY 2025-26 → [2025, 2026], while 15 Feb 2026 is still in
     * FY 2025-26 → [2025, 2026].
     *
     * @return array{0:int,1:int}
     */
    public static function financialYearFor(\Illuminate\Support\Carbon $date): array
    {
        $year = (int) $date->format('Y');
        $start = ($date->month >= 4) ? $year : $year - 1;
        return [$start, $start + 1];
    }

    public function nextReceiptNumber(): string
    {
        $next = ($this->receipt_counter ?? 0) + 1;
        return ($this->receipt_prefix ?? 'RCPT') . '-' .
            str_pad((string) $next, $this->receipt_number_padding ?? 4, '0', STR_PAD_LEFT);
    }

    /**
     * Atomically advance the credit-note counter with FY-aware reset semantics.
     * Caller must hold a DB lock on this row.
     */
    public function bumpCreditNoteCounter(?string $referenceDate = null): string
    {
        $ref = $referenceDate ? \Illuminate\Support\Carbon::parse($referenceDate) : now();
        [$fyStart] = self::financialYearFor($ref);

        if ($this->credit_note_counter_fy !== null && $this->credit_note_counter_fy < $fyStart) {
            $this->credit_note_counter = 0;
        }
        $this->credit_note_counter = ($this->credit_note_counter ?? 0) + 1;
        $this->credit_note_counter_fy = $fyStart;
        $this->save();

        return ($this->credit_note_prefix ?? 'CRN') . '-' .
            str_pad((string) $this->credit_note_counter, $this->credit_note_number_padding ?? 4, '0', STR_PAD_LEFT);
    }
}
