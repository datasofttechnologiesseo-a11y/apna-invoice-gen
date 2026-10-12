<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id',
        'name', 'sku', 'kind',
        'hsn_sac', 'unit',
        'rate', 'gst_rate', 'purchase_rate',
        'is_active', 'description',
        'track_stock', 'opening_stock', 'low_stock_level',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'track_stock' => 'boolean',
        'opening_stock' => 'decimal:3',
        'low_stock_level' => 'decimal:3',
        'purchase_rate' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Stock on hand (opening + movements). Uses a loaded stock_movements_sum_quantity when present. */
    public function stockOnHand(): float
    {
        $moved = array_key_exists('stock_movements_sum_quantity', $this->attributes)
            ? (float) $this->attributes['stock_movements_sum_quantity']
            : (float) $this->stockMovements()->sum('quantity');

        return round((float) $this->opening_stock + $moved, 3);
    }

    public function isLowOnStock(): bool
    {
        return $this->track_stock && $this->low_stock_level !== null
            && $this->stockOnHand() <= (float) $this->low_stock_level;
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function purchaseBillItems(): HasMany
    {
        return $this->hasMany(PurchaseBillItem::class);
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /**
     * Where this item has history, or null if it was never used. An item
     * with history is archived, never deleted: deleting would unlink it
     * from bills and erase its stock ledger, which cascades with the item.
     * Shared by the web and the app so both apply the same rule.
     */
    public function usedOn(): ?string
    {
        return match (true) {
            $this->invoiceItems()->exists() => 'an invoice',
            $this->purchaseBillItems()->exists() => 'a purchase invoice',
            $this->stockMovements()->exists() => 'stock entries',
            $this->quotationItems()->exists() => 'a quotation',
            default => null,
        };
    }

    public function kindLabel(): string
    {
        return config('uqc_units.kinds.' . $this->kind, ucfirst($this->kind));
    }
}
