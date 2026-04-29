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
        'amount', 'tds_amount', 'tds_section', 'tds_rate',
        'method', 'reference_number', 'notes',
    ];

    protected $casts = [
        'received_at' => 'date',
        'amount' => 'decimal:2',
        'tds_amount' => 'decimal:2',
        'tds_rate' => 'decimal:2',
    ];

    /** Net cash actually received in your bank = gross applied minus TDS deducted at source. */
    public function netReceived(): float
    {
        return (float) $this->amount - (float) $this->tds_amount;
    }

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
