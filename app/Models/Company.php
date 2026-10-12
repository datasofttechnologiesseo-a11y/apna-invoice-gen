<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'gstin', 'composition_dealer', 'pan',
        'address_line1', 'address_line2', 'city', 'state_id', 'postal_code', 'country',
        'phone', 'email', 'website',
        'logo_path', 'signature_path',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_account_type',
        'bank_ifsc', 'bank_branch', 'upi_id',
        'default_currency', 'default_terms', 'declaration',
        'invoice_prefix', 'invoice_counter', 'invoice_number_padding',
        'invoice_number_format', 'invoice_counter_fy',
        'receipt_prefix', 'receipt_counter', 'receipt_number_padding',
        'credit_note_prefix', 'credit_note_counter', 'credit_note_number_padding', 'credit_note_counter_fy',
        'cash_memo_prefix', 'cash_memo_counter', 'cash_memo_number_padding', 'cash_memo_counter_fy',
        'quote_prefix', 'quote_counter', 'quote_number_format', 'quote_counter_fy',
        'books_locked_until',
        'lut_number',
        'onboarded_at',
    ];

    protected $casts = [
        'onboarded_at' => 'datetime',
        'composition_dealer' => 'boolean',
        'books_locked_until' => 'date',
    ];

    // GSTIN and PAN are statutory identifiers whose canonical form is upper
    // case. Normalise on write and on read so every document (invoice, PDF,
    // cash memo, GSTR-3B) shows them correctly even for pre-existing rows.
    protected function gstin(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v === null ? null : strtoupper($v),
            set: fn (?string $v) => $v === null ? null : strtoupper(trim($v)),
        );
    }

    protected function pan(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v === null ? null : strtoupper($v),
            set: fn (?string $v) => $v === null ? null : strtoupper(trim($v)),
        );
    }

    /**
     * Display label for the account type, or null when the owner never
     * chose one. Stored lower case so the value is stable; the mapping
     * lives here so no view has to know how to capitalise it.
     */
    public function bankAccountTypeLabel(): ?string
    {
        return match ($this->bank_account_type) {
            'savings' => 'Savings',
            'current' => 'Current',
            default => null,
        };
    }

    /**
     * True if the given date falls inside a period that has been locked
     * for editing (e.g. closed FY). Used by controllers to refuse mutations
     * to old invoices / expenses / cash memos / payments.
     */
    public function isBooksLockedOn(\Illuminate\Support\Carbon|string|null $date): bool
    {
        if (! $this->books_locked_until || ! $date) return false;
        $d = $date instanceof \Illuminate\Support\Carbon
            ? $date
            : \Illuminate\Support\Carbon::parse($date);
        return $d->lte($this->books_locked_until);
    }

    public function isBusinessComplete(): bool
    {
        // Name + state are the billing essentials (state drives CGST/SGST vs
        // IGST). Address is only a completeness requirement for GST-registered
        // businesses, where Rule 46 mandates it on the tax invoice.
        return filled($this->name)
            && filled($this->state_id)
            && (blank($this->gstin) || filled($this->address_line1));
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

    /** People invited to work in this business (see CompanyMember). */
    public function members(): HasMany
    {
        return $this->hasMany(CompanyMember::class);
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

    public function cashMemos(): HasMany
    {
        return $this->hasMany(CashMemo::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function purchaseBills(): HasMany
    {
        return $this->hasMany(PurchaseBill::class);
    }

    /**
     * Preview the NEXT quote number without mutating the counter.
     * Mirrors invoice numbering — supports {FY} / {N} placeholders, defaults
     * to QT-0001 if no format is configured yet. Quotes don't legally need
     * an FY-aware sequence, but supporting {FY} keeps numbering visually
     * consistent with invoices for owners who want it.
     */
    public function nextQuoteNumber(?string $referenceDate = null): string
    {
        $ref = $referenceDate ? \Illuminate\Support\Carbon::parse($referenceDate) : now();
        [$fyStartYear] = self::financialYearFor($ref);

        $counter = ($this->quote_counter_fy && $this->quote_counter_fy < $fyStartYear)
            ? 0
            : ($this->quote_counter ?? 0);

        // Skip numbers already taken: a quote saved with the suggested number
        // typed in, or entered by hand, does not move the counter.
        do {
            $number = $this->renderQuoteNumber(++$counter, $ref);
        } while ($this->quoteNumberTaken($number));

        return $number;
    }

    /**
     * Atomically advance the quote counter when the user sends/accepts a
     * quote. Caller should hold a DB lock on this row. Mirrors the invoice
     * pattern (FY rollover handled), and skips numbers already in use so
     * sending never collides with a quote numbered by hand.
     */
    public function bumpQuoteCounter(?string $referenceDate = null): string
    {
        $ref = $referenceDate ? \Illuminate\Support\Carbon::parse($referenceDate) : now();
        [$fyStartYear] = self::financialYearFor($ref);

        if ($this->quote_counter_fy !== null && $this->quote_counter_fy < $fyStartYear) {
            $this->quote_counter = 0;
        }
        do {
            $this->quote_counter = ($this->quote_counter ?? 0) + 1;
            $number = $this->renderQuoteNumber($this->quote_counter, $ref);
        } while ($this->quoteNumberTaken($number));
        $this->quote_counter_fy = $fyStartYear;
        $this->save();

        return $number;
    }

    private function renderQuoteNumber(int $counter, \Carbon\CarbonInterface $ref): string
    {
        $padded = str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
        $format = $this->quote_number_format;
        if (! $format) {
            return ($this->quote_prefix ?: 'QT') . '-' . $padded;
        }
        [$fyS, $fyE] = self::financialYearFor($ref);

        return strtr($format, [
            '{FY}' => sprintf('%04d-%02d', $fyS, $fyE % 100),
            '{FY_SHORT}' => sprintf('%02d-%02d', $fyS % 100, $fyE % 100),
            '{YYYY}' => $ref->format('Y'),
            '{N}' => $padded,
        ]);
    }

    private function quoteNumberTaken(string $number): bool
    {
        return $this->exists && Quotation::where('company_id', $this->id)->where('quote_number', $number)->exists();
    }

    /**
     * Preview the NEXT cash-memo number without mutating the counter.
     * Used to pre-fill the form so the user can accept or override it.
     */
    public function nextCashMemoNumber(?string $referenceDate = null): string
    {
        $ref = $referenceDate ? \Illuminate\Support\Carbon::parse($referenceDate) : now();
        [$fyStart, $fyEnd] = self::financialYearFor($ref);

        $counter = ($this->cash_memo_counter_fy && $this->cash_memo_counter_fy < $fyStart)
            ? 0
            : ($this->cash_memo_counter ?? 0);

        $next = $counter + 1;
        $padded = str_pad((string) $next, $this->cash_memo_number_padding ?? 4, '0', STR_PAD_LEFT);
        $fyTag = sprintf('%02d-%02d', $fyStart % 100, $fyEnd % 100);

        return ($this->cash_memo_prefix ?? 'CM') . '/' . $fyTag . '/' . $padded;
    }

    /**
     * Atomically advance the cash-memo counter with FY-aware reset semantics.
     * Caller must hold a DB lock on this row.
     */
    public function bumpCashMemoCounter(?string $referenceDate = null): string
    {
        $ref = $referenceDate ? \Illuminate\Support\Carbon::parse($referenceDate) : now();
        [$fyStart, $fyEnd] = self::financialYearFor($ref);

        if ($this->cash_memo_counter_fy !== null && $this->cash_memo_counter_fy < $fyStart) {
            $this->cash_memo_counter = 0;
        }
        $this->cash_memo_counter = ($this->cash_memo_counter ?? 0) + 1;
        $this->cash_memo_counter_fy = $fyStart;
        $this->save();

        $padded = str_pad((string) $this->cash_memo_counter, $this->cash_memo_number_padding ?? 4, '0', STR_PAD_LEFT);
        $fyTag = sprintf('%02d-%02d', $fyStart % 100, $fyEnd % 100);

        return ($this->cash_memo_prefix ?? 'CM') . '/' . $fyTag . '/' . $padded;
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
    /**
     * Learn the numbering series from an invoice number the user typed.
     *
     * Only businesses without a GSTIN can set their own number (a GST tax
     * invoice must follow a consecutive statutory series), and this is what
     * lets such a business carry on from an existing bill book: type 5464
     * once and the next auto-generated bill is 5465, not INV-0001.
     *
     * Rules:
     *   - the trailing group of digits becomes the counter, so "INV-0120"
     *     continues at 121 and a bare "5464" continues at 5465;
     *   - the counter only ever moves forward, so re-issuing an older number
     *     can never rewind the series;
     *   - a format is only derived when none has been chosen, so a deliberate
     *     setting is never overwritten.
     */
    public function learnSeriesFrom(string $number): void
    {
        if (! preg_match('/^(.*?)(\d+)$/', trim($number), $m)) {
            return;   // no trailing number to continue from
        }

        [, $prefix, $digits] = $m;
        $value = (int) $digits;

        $changes = [];

        if ($value > (int) ($this->invoice_counter ?? 0)) {
            $changes['invoice_counter'] = $value;
            $changes['invoice_counter_fy'] = self::financialYearFor(now())[0];
        }

        if (blank($this->invoice_number_format)) {
            $changes['invoice_number_format'] = $prefix . '{N}';
            $changes['invoice_number_padding'] = strlen($digits);
        }

        if ($changes) {
            $this->forceFill($changes)->save();
        }
    }

    public function bumpCounterForFinalize(?string $referenceDate = null): string
    {
        $ref = $referenceDate ? \Illuminate\Support\Carbon::parse($referenceDate) : now();
        [$fyStartYear] = self::financialYearFor($ref);

        // An invoice dated in an EARLIER financial year than the counter (a
        // late bill for last March, books not yet locked) continues that
        // year's own series. Using this year's counter gave it a number like
        // INV/2025-26/0005 after 0002, a gap Rule 46(b) and GSTR-1 Table 13
        // do not allow, and moved the counter's year back, so this year's
        // series restarted at 0001 and had to skip the numbers already used.
        if ($this->invoice_counter_fy !== null && $this->invoice_counter_fy > $fyStartYear
            && $this->numberingRestartsEachYear()) {
            return $this->nextNumberInEarlierYear($ref);
        }

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

    /** Does the invoice number carry the year, so each FY has its own series? */
    public function numberingRestartsEachYear(): bool
    {
        $format = (string) $this->invoice_number_format;

        return str_contains($format, '{FY}') || str_contains($format, '{FY_SHORT}') || str_contains($format, '{YYYY}');
    }

    /**
     * The next number in an earlier financial year's own series: one more
     * than the highest number already issued in that series, skipping any
     * that are taken. The running counter (this year's) is not touched.
     */
    private function nextNumberInEarlierYear(\Illuminate\Support\Carbon $ref): string
    {
        [$fyS, $fyE] = self::financialYearFor($ref);
        $tokens = [
            '{FY}' => sprintf('%04d-%02d', $fyS, $fyE % 100),
            '{FY_SHORT}' => sprintf('%02d-%02d', $fyS % 100, $fyE % 100),
            '{YYYY}' => $ref->format('Y'),
        ];
        $format = (string) $this->invoice_number_format;
        $pattern = '/^' . str_replace('\{N\}', '(\d+)', preg_quote(strtr($format, $tokens), '/')) . '$/';

        $highest = 0;
        $numbers = Invoice::where('company_id', $this->id)->whereNotNull('invoice_number')
            ->whereBetween('invoice_date', [sprintf('%04d-04-01', $fyS), sprintf('%04d-03-31', $fyE)])
            ->pluck('invoice_number');
        foreach ($numbers as $number) {
            if (preg_match($pattern, (string) $number, $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        $padding = $this->invoice_number_padding ?: 4;
        do {
            $number = strtr($format, $tokens + ['{N}' => str_pad((string) ++$highest, $padding, '0', STR_PAD_LEFT)]);
        } while (Invoice::where('company_id', $this->id)->where('invoice_number', $number)->exists());

        return $number;
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
