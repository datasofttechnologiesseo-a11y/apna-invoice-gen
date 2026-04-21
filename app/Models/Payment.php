<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id', 'invoice_id',
        'receipt_number', 'received_at',
        'amount', 'method', 'reference_number', 'notes',
    ];

    protected $casts = [
        'received_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function methodLabel(): string
    {
        return config('payment_methods.methods.' . $this->method . '.label', ucfirst($this->method));
    }
}
