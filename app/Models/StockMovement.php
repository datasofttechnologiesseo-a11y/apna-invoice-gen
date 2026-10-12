<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One change to an item's stock: signed quantity (+ in, - out), the day it
 * happened and the document behind it (an invoice, a purchase invoice, or a
 * manual adjustment with a reason).
 */
class StockMovement extends Model
{
    public const KINDS = [
        'sale' => 'Sold',
        'sale_cancelled' => 'Sale cancelled',
        'purchase' => 'Purchased',
        'adjustment' => 'Adjustment',
    ];

    protected $fillable = [
        'company_id', 'product_id', 'user_id', 'moved_on', 'quantity', 'kind', 'source_type', 'source_id', 'note',
    ];

    protected $casts = [
        'moved_on' => 'date',
        'quantity' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }
}
