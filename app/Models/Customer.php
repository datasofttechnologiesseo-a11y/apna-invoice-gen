<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'gstin', 'company_id',
        'address_line1', 'address_line2', 'city', 'state_id', 'postal_code', 'country',
        'phone', 'email',
    ];

    // GSTINs are case-insensitive identifiers whose canonical form is upper
    // case (the checksum alphabet is 0-9A-Z). Normalise on write and on read
    // so invoices/PDFs always show the statutory form even for rows saved
    // before this cast existed.
    protected function gstin(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v === null ? null : strtoupper($v),
            set: fn (?string $v) => $v === null ? null : strtoupper(trim($v)),
        );
    }

    /**
     * Free-text match on the four things a business actually looks a customer
     * up by: name, mobile, email, GSTIN.
     *
     * It lives on the model because both the customer list and the invoice
     * list search customers, and they had drifted - invoices matched name and
     * phone, the customer page matched name and email, so the same term found
     * different people depending on which screen you were standing on.
     *
     * Phone is normalised on both sides, because the number a shopkeeper
     * types ("+91 98765-43210") is almost never punctuated the way the one on
     * file is ("9876543210").
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $digits = preg_replace('/\D/', '', $term);

        return $query->where(function (Builder $w) use ($term, $digits) {
            $w->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('gstin', 'like', '%' . strtoupper($term) . '%')
              ->orWhere('phone', 'like', "%{$term}%");

            // Four digits is the shortest fragment worth matching; below that
            // every customer with a phone number comes back.
            if (strlen($digits) >= 4) {
                $w->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'+',''),'(',''),')','') LIKE ?",
                    ["%{$digits}%"]
                );
            }
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
