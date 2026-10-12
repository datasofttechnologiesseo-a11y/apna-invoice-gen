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
        'bill_number', 'bill_date', 'due_date',
        'is_interstate', 'reverse_charge', 'itc_eligible',
        'subtotal', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax',
        'round_off', 'grand_total', 'paid_amount', 'balance', 'status',
        'expense_category', 'reference_number', 'notes', 'expense_id',
    ];

    protected $casts = [
        'bill_date' => 'date',
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
}
