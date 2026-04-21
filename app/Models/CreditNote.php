<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_id', 'invoice_id',
        'credit_note_number', 'credit_note_date',
        'amount', 'taxable_value',
        'total_cgst', 'total_sgst', 'total_igst',
        'reason', 'notes',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'amount' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
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

    public function reasonLabel(): string
    {
        return config('credit_note_reasons.' . $this->reason . '.label', ucfirst(str_replace('_', ' ', $this->reason)));
    }
}
