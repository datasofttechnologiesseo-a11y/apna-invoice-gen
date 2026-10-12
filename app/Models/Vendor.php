<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier you buy from. The state decides whether its bills carry
 * CGST + SGST or IGST on the input credit; the GSTIN is what GSTR-2B matches on.
 */
class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id', 'name', 'gstin',
        'address_line1', 'address_line2', 'city', 'state_id', 'postal_code', 'country',
        'phone', 'email', 'notes',
    ];

    // Same normalisation as Customer: the statutory form is upper case.
    protected function gstin(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v === null ? null : strtoupper($v),
            set: fn (?string $v) => $v === null || trim($v) === '' ? null : strtoupper(trim($v)),
        );
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $w) use ($term) {
            $w->where('name', 'like', "%{$term}%")
              ->orWhere('gstin', 'like', '%' . strtoupper($term) . '%')
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function purchaseBills(): HasMany
    {
        return $this->hasMany(PurchaseBill::class);
    }

    /** What you still owe this vendor across all its bills. */
    public function payable(): float
    {
        return (float) $this->purchaseBills()->where('balance', '>', 0)->sum('balance');
    }
}
