<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMemoItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_memo_id', 'sort_order',
        'description', 'hsn_sac',
        'quantity', 'unit', 'rate', 'amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function cashMemo(): BelongsTo
    {
        return $this->belongsTo(CashMemo::class);
    }
}
