<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashMemo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id',
        'memo_number', 'memo_date',
        'seller_name', 'seller_address', 'seller_gstin', 'seller_phone', 'seller_state',
        'subtotal', 'discount', 'taxable_value',
        'total_cgst', 'total_sgst', 'total_igst',
        'round_off', 'grand_total', 'amount_in_words',
        'payment_mode', 'reference_number', 'expense_category', 'notes',
        'expense_id',
    ];

    protected $casts = [
        'memo_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
        'round_off' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CashMemoItem::class)->orderBy('sort_order');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function totalGst(): float
    {
        return (float) $this->total_cgst + (float) $this->total_sgst + (float) $this->total_igst;
    }

    public function isInterstate(): bool
    {
        return (float) $this->total_igst > 0;
    }
}
