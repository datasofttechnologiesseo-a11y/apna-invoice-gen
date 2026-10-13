<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier's tax invoice as received. Amounts follow the sales invoice
 * conventions (subtotal = taxable, heads, round-off, grand total), with two
 * purchase-side twists:
 *
 *  - reverse_charge: the vendor's bill carries no tax; the heads hold the
 *    tax YOU must pay (GSTR-3B 3.1(d)) and may claim; grand_total excludes it.
 *  - itc_eligible: false for blocked credit (s.17(5)); the GST is then a cost
 *    in the P&L and left out of ITC.
 */
class PurchaseBill extends Model
{
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'user_id', 'company_id', 'vendor_id',
        'bill_number', 'bill_date', 'booked_on', 'due_date',
        'is_interstate', 'reverse_charge', 'itc_eligible',
        'subtotal', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax',
        'round_off', 'grand_total', 'paid_amount', 'balance', 'status',
        'expense_category', 'reference_number', 'notes', 'expense_id',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'booked_on' => 'date',
        'due_date' => 'date',
        'is_interstate' => 'boolean',
        'reverse_charge' => 'boolean',
        'itc_eligible' => 'boolean',
        'subtotal' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'round_off' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function stockMovements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseBillItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return $this->balance > 0 && $this->due_date && $this->due_date->isPast();
    }

    /** "SGST" or "UTGST" for the state share - see Invoice::stateTaxLabel(). */
    public function stateTaxLabel(): string
    {
        $state = $this->company?->state;

        return $state?->is_union_territory ? 'UTGST' : 'SGST';
    }

    /**
     * Recompute paid/balance/status from the payments table - the single
     * source of truth, exactly as sales invoices do.
     */
    public function syncPaymentTotals(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $balance = round((float) $this->grand_total - $paid, 2);

        $this->forceFill([
            'paid_amount' => $paid,
            'balance' => $balance,
            'status' => $balance <= 0 ? self::STATUS_PAID : ($paid > 0 ? self::STATUS_PARTIAL : self::STATUS_UNPAID),
        ])->save();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_PARTIAL => 'Partly paid',
            default => $this->isOverdue() ? 'Overdue' : 'Unpaid',
        };
    }

    /**
     * The date this bill counts in the books, GSTR-3B and the P&L: the day it
     * was booked when its own date fell in a locked period, else its date.
     */
    public function bookedDate(): ?\Illuminate\Support\Carbon
    {
        return $this->booked_on ?? $this->bill_date;
    }

    /**
     * CGST s.16(4): credit on a supplier invoice can be taken in a return
     * filed by 30 November after the end of the invoice's financial year.
     * The last return due before then is October's (due 20 November), and
     * for a quarterly (QRMP) filer July to September; so the return period
     * must end on or before 31 October after that year. Sales side twin:
     * Invoice::creditNoteDeadline().
     */
    public static function itcPeriodDeadline(\Carbon\CarbonInterface $billDate): \Illuminate\Support\Carbon
    {
        $fyEndYear = $billDate->month >= 4 ? $billDate->year + 1 : $billDate->year;

        return \Illuminate\Support\Carbon::create($fyEndYear, 10, 31)->endOfDay();
    }

    /**
     * Whether the credit on this bill is past the s.16(4) limit for the
     * return it falls in: by default the month it is booked in, or the
     * given return period's last day.
     */
    public function isItcTimeBarred(?\Carbon\CarbonInterface $periodEnd = null): bool
    {
        if (! $this->bill_date) {
            return false;
        }
        $periodEnd ??= $this->bookedDate()->copy()->endOfMonth();

        return $periodEnd->copy()->startOfDay()->gt(self::itcPeriodDeadline($this->bill_date));
    }

    /**
     * Rule 37 (second proviso to s.16(2)): a supplier not paid within 180
     * days of the invoice date. This is the last day a payment still counts.
     */
    public function rule37PayBy(): ?\Illuminate\Support\Carbon
    {
        return $this->bill_date?->copy()->addDays(180);
    }

    /**
     * The day the credit on the part still unpaid after 180 days is reversed
     * (GSTR-3B Table 4(B)(2)): the 181st day, or the day the bill was booked
     * if it was booked later than that.
     */
    public function rule37ReversalOn(): ?\Illuminate\Support\Carbon
    {
        if (! $this->bill_date) {
            return null;
        }
        $day181 = $this->bill_date->copy()->addDays(181);

        return $this->booked_on && $this->booked_on->gt($day181) ? $this->booked_on->copy() : $day181;
    }

    /**
     * Whether Rule 37 can apply at all: credit taken on the supplier's
     * invoice (not reverse charge, not blocked, not time-barred) and
     * something owed to the supplier.
     */
    public function rule37Applies(): bool
    {
        return $this->itc_eligible && ! $this->reverse_charge && (float) $this->grand_total > 0
            && (float) $this->total_tax > 0 && ! $this->isItcTimeBarred();
    }
}
