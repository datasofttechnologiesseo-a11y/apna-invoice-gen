<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'entry_date', 'category', 'vendor_name', 'description',
        'amount', 'gst_amount',
        'payment_method', 'reference_number', 'notes',
        'cash_memo_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashMemo(): BelongsTo
    {
        return $this->belongsTo(CashMemo::class);
    }

    public function categoryLabel(): string
    {
        return config('expense_categories.' . $this->category . '.label', ucfirst($this->category));
    }

    public function categoryColor(): string
    {
        return config('expense_categories.' . $this->category . '.color', '#6b7280');
    }

    /** Cash actually paid out (pre-GST amount + GST component) */
    public function cashOutflow(): float
    {
        return (float) $this->amount + (float) $this->gst_amount;
    }
}
