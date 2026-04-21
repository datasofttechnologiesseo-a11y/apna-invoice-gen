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
        'rate', 'gst_rate',
        'is_active', 'description',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function kindLabel(): string
    {
        return config('uqc_units.kinds.' . $this->kind, ucfirst($this->kind));
    }
}
