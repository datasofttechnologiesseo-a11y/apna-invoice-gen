<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'gstin', 'pan',
        'address_line1', 'address_line2', 'city', 'state_id', 'postal_code', 'country',
        'phone', 'email', 'website',
        'logo_path', 'signature_path',
        'bank_name', 'bank_account_number', 'bank_ifsc', 'bank_branch', 'upi_id',
        'default_currency', 'default_terms', 'declaration',
        'invoice_prefix', 'invoice_counter', 'invoice_number_padding',
        'receipt_prefix', 'receipt_counter', 'receipt_number_padding',
        'onboarded_at',
    ];

    protected $casts = [
        'onboarded_at' => 'datetime',
    ];

    public function isBusinessComplete(): bool
    {
        return filled($this->name) && filled($this->state_id) && filled($this->address_line1);
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

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

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function nextInvoiceNumber(): string
    {
        $next = $this->invoice_counter + 1;
        return $this->invoice_prefix . '-' . str_pad((string) $next, $this->invoice_number_padding, '0', STR_PAD_LEFT);
    }

    public function nextReceiptNumber(): string
    {
        $next = ($this->receipt_counter ?? 0) + 1;
        return ($this->receipt_prefix ?? 'RCPT') . '-' .
            str_pad((string) $next, $this->receipt_number_padding ?? 4, '0', STR_PAD_LEFT);
    }
}
