<?php

namespace App\Models;

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
