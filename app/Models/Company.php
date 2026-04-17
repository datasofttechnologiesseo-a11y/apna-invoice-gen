<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'user_id', 'name', 'gstin', 'pan',
        'address_line1', 'address_line2', 'city', 'state_id', 'postal_code', 'country',
        'phone', 'email', 'website',
        'logo_path', 'signature_path',
        'default_currency', 'default_terms',
        'invoice_prefix', 'invoice_counter', 'invoice_number_padding',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function nextInvoiceNumber(): string
    {
        $next = $this->invoice_counter + 1;
        return $this->invoice_prefix . '-' . str_pad((string) $next, $this->invoice_number_padding, '0', STR_PAD_LEFT);
    }
}
